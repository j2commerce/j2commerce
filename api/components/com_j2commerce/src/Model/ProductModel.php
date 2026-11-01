<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\Model;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\ConfigHelper;
use J2Commerce\Component\J2commerce\Administrator\Model\ProductModel as AdminProductModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Database\ParameterType;

/** @since  6.0.16 */
class ProductModel extends AdminProductModel
{
    private const JSON_COLUMNS = ['params', 'plugins'];

    private const IMAGE_FIELDS = ['main_image', 'main_image_alt', 'thumb_image', 'thumb_image_alt'];

    private const ALT_MAX_LENGTH = 255;

    // #__j2commerce_variants.price is decimal(15,5).
    private const PRICE_MAX = 9999999999.99999;

    private const PRICE_SCALE = 5;

    /** PATCH extras (master price, images, params/plugins merged key by key) are validated before any write; POST is refused. */
    public function save($data): bool
    {
        $app = Factory::getApplication();

        if (!$app->isClient('api')) {
            return parent::save($data);
        }

        $pk = (int) ($data['j2commerce_product_id'] ?? 0);

        if ($pk === 0) {
            $this->setError('Product creation is not available through this API version.');

            return false;
        }

        // The form filter strips markup from the JSON columns, so they are rebuilt from the raw payload ApiController parks here.
        $posted = (array) $app->getInput()->get('jform', [], 'array');
        $json   = $this->mergeJsonColumns($pk, $posted);
        $extras = $json === null ? null : $this->apiExtras($posted);

        if ($extras === null) {
            return false;
        }

        $db = $this->getDatabase();
        $db->transactionStart();

        try {
            if (!parent::save(array_replace($data, $json))) {
                $db->transactionRollback();

                return false;
            }

            $this->saveImages($pk, $extras['images'] ?? []);

            if (isset($extras['price'])) {
                $this->saveMasterPrice($pk, $extras['price']);
            }

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            Log::add('API product update: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
            $this->setError('The product could not be saved.');

            return false;
        }

        $this->cleanCache('com_j2commerce');

        return true;
    }

    /** @return array<string, string>|null  The JSON columns to store, or null with the error set. */
    private function mergeJsonColumns(int $pk, array $posted): ?array
    {
        $stored = $this->getTable();
        $loaded = $stored->load($pk);
        $result = [];

        foreach (self::JSON_COLUMNS as $column) {
            $raw = $loaded ? (string) $stored->$column : '';
            $new = $posted[$column] ?? null;

            // On PATCH, ApiController refills unsent columns from the stored row; keep those untouched.
            if ($new === $raw) {
                $result[$column] = $raw === '' ? '{}' : $raw;

                continue;
            }

            $old = json_decode($raw, true);

            if (\is_string($new)) {
                $new = trim($new) === '' ? [] : json_decode($new, true);
            }

            if ($new !== null && (!\is_array($new) || ($new !== [] && array_is_list($new)))) {
                $this->setError(\sprintf('%s must be an object.', $column));

                return null;
            }

            $merged          = array_replace(\is_array($old) ? $old : [], $new ?? []);
            $result[$column] = $merged === [] ? '{}' : json_encode($merged);
        }

        return $result;
    }

    /** @return array<string, mixed>|null  The validated extras, or null with the error set. */
    private function apiExtras(array $posted): ?array
    {
        $extras = [];

        foreach (self::IMAGE_FIELDS as $field) {
            if (!\array_key_exists($field, $posted)) {
                continue;
            }

            if ($posted[$field] !== null && !\is_scalar($posted[$field])) {
                $this->setError(\sprintf('%s must be a string.', $field));

                return null;
            }

            $value = trim((string) $posted[$field]);

            if (str_ends_with($field, '_alt')) {
                $extras['images'][$field] = mb_substr(strip_tags($value), 0, self::ALT_MAX_LENGTH);

                continue;
            }

            if ($value !== '' && !$this->isLocalImage($value)) {
                $this->setError(\sprintf('%s must be a file in one of the store image folders.', $field));

                return null;
            }

            $extras['images'][$field] = $value;
        }

        if (\array_key_exists('price', $posted)) {
            $price = is_numeric($posted['price']) ? (float) $posted['price'] : -1.0;

            if (!is_finite($price) || $price < 0 || $price > self::PRICE_MAX) {
                $this->setError('price must be a number from 0 to 9999999999.99999.');

                return null;
            }

            $extras['price'] = $price;
        }

        return $extras;
    }

    private function isLocalImage(string $value): bool
    {
        $path = explode('#', $value, 2)[0];

        if (str_contains($path, '..') || str_contains($path, ':') || preg_match('/["\'<>\\\\]/', $value)) {
            return false;
        }

        foreach (ConfigHelper::getImageDirectoryPaths() as $root) {
            $root = trim($root, '/');

            if ($root !== '' && str_starts_with($path, $root . '/')) {
                return true;
            }
        }

        return false;
    }

    private function masterVariant(int $pk): ?object
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_variant_id', 'price']))
            ->from($db->quoteName('#__j2commerce_variants'))
            ->where($db->quoteName('product_id') . ' = :pk')
            ->where($db->quoteName('is_master') . ' = 1')
            ->bind(':pk', $pk, ParameterType::INTEGER);

        return $db->setQuery($query, 0, 1)->loadObject();
    }

    private function saveImages(int $pk, array $images): void
    {
        if ($images === []) {
            return;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_productimage_id'))
            ->from($db->quoteName('#__j2commerce_productimages'))
            ->where($db->quoteName('product_id') . ' = :pk')
            ->bind(':pk', $pk, ParameterType::INTEGER);
        $imageId = (int) $db->setQuery($query, 0, 1)->loadResult();
        $row     = (object) $images;

        if ($imageId !== 0) {
            $row->j2commerce_productimage_id = $imageId;
            $db->updateObject('#__j2commerce_productimages', $row, 'j2commerce_productimage_id');

            return;
        }

        $row->product_id = $pk;
        $row->main_image_alt  ??= '';
        $row->thumb_image_alt ??= '';
        $db->insertObject('#__j2commerce_productimages', $row);
    }

    private function saveMasterPrice(int $pk, float $price): void
    {
        $master = $this->masterVariant($pk);

        if ($master === null) {
            return;
        }

        $db        = $this->getDatabase();
        $variantId = (int) $master->j2commerce_variant_id;
        $value     = number_format($price, self::PRICE_SCALE, '.', '');
        $now       = Factory::getDate()->toSql();
        $userId    = (int) $this->getCurrentUser()->id;
        $query     = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_variants'))
            ->set($db->quoteName('price') . ' = :price')
            ->set($db->quoteName('modified_on') . ' = :now')
            ->set($db->quoteName('modified_by') . ' = :user')
            ->where($db->quoteName('j2commerce_variant_id') . ' = :id')
            ->where($db->quoteName('product_id') . ' = :pk')
            ->bind(':price', $value)
            ->bind(':now', $now)
            ->bind(':user', $userId, ParameterType::INTEGER)
            ->bind(':id', $variantId, ParameterType::INTEGER)
            ->bind(':pk', $pk, ParameterType::INTEGER);
        $db->setQuery($query)->execute();
    }

    public function getItem($pk = null): object|bool
    {
        $item = parent::getItem($pk);

        if (!$item || empty($item->j2commerce_product_id)) {
            return $item;
        }

        // The admin model returns these as JSON strings for its hidden fields.
        $item->params  = json_decode((string) ($item->params ?? ''), false) ?: new \stdClass();
        $item->plugins = json_decode((string) ($item->plugins ?? ''), false) ?: new \stdClass();

        $item->price = $this->masterVariant((int) $item->j2commerce_product_id)?->price;

        $article = $item->source ?? null;

        if ($article !== null && isset($article->id)) {
            $user    = $this->getCurrentUser();
            $visible = (int) ($article->state ?? 0) === 1
                && \in_array((int) ($article->access ?? 0), $user->getAuthorisedViewLevels(), true);

            if ($visible) {
                $safe               = new \stdClass();
                $safe->id           = $article->id ?? null;
                $safe->title        = $article->title ?? null;
                $safe->alias        = $article->alias ?? null;
                $safe->introtext    = $article->introtext ?? null;
                $safe->fulltext     = $article->fulltext ?? null;
                $safe->state        = $article->state ?? null;
                $safe->catid        = $article->catid ?? null;
                $safe->language     = $article->language ?? null;
                $safe->created      = $article->created ?? null;
                $safe->modified     = $article->modified ?? null;
                $safe->publish_up   = $article->publish_up ?? null;
                $safe->publish_down = $article->publish_down ?? null;

                $item->article  = $safe;
                // $f->value is prepared/rendered HTML (prepareValue=true); rawvalue dropped intentionally.
                $item->jcfields = array_map(
                    static fn ($f) => (object) [
                        'id'    => $f->id,
                        'name'  => $f->name,
                        'label' => $f->label,
                        'value' => $f->value,
                        'type'  => $f->type,
                    ],
                    FieldsHelper::getFields('com_content.article', $article, true)
                );
            } else {
                $item->article  = null;
                $item->jcfields = [];
            }
        } else {
            $item->article  = null;
            $item->jcfields = [];
        }

        return $item;
    }
}
