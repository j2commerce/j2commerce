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

use J2Commerce\Component\J2commerce\Administrator\Api\ProductApiException;
use J2Commerce\Component\J2commerce\Administrator\Api\ProductApiSchema;
use J2Commerce\Component\J2commerce\Administrator\Api\ProductPayload;
use J2Commerce\Component\J2commerce\Administrator\Event\Api\ProductReadEvent;
use J2Commerce\Component\J2commerce\Administrator\Helper\FilterSourceHelper;
use J2Commerce\Component\J2commerce\Administrator\Model\ProductModel as AdminProductModel;
use J2Commerce\Component\J2commerce\Administrator\Service\ProductOptionService;
use J2Commerce\Component\J2commerce\Administrator\Service\ProductService;
use J2Commerce\Component\J2commerce\Administrator\Service\VariantMatrixService;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\Exception\ResourceNotFound;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Database\ParameterType;

/** Checks the shared keys, resolves the type's API handler and owns the one transaction around its write. */
class ProductModel extends AdminProductModel
{
    private const JSON_COLUMNS = ['params', 'plugins'];

    private const TEXT_MAX_LENGTH = 255;

    private const FLAG_COLUMNS = ['enabled', 'visibility'];

    /** Row id columns: 0, or an existing row of the table (`enabled` required where the column exists). */
    private const REFERENCE_COLUMNS = [
        'taxprofile_id'   => ['#__j2commerce_taxprofiles', 'j2commerce_taxprofile_id', true],
        'manufacturer_id' => ['#__j2commerce_manufacturers', 'j2commerce_manufacturer_id', false],
        'vendor_id'       => ['#__j2commerce_vendors', 'j2commerce_vendor_id', false],
    ];

    private ?ProductService $productService = null;

    private ?VariantMatrixService $variantService = null;

    public function save($data): bool
    {
        $app = Factory::getApplication();

        if (!$app->isClient('api')) {
            return parent::save($data);
        }

        $posted = $this->postedBody($app);
        $pk     = (int) ($data['j2commerce_product_id'] ?? 0);
        $stored = null;

        if ($pk > 0) {
            $stored = $this->getTable();

            if (!$stored->load($pk)) {
                throw new ResourceNotFound(\sprintf('Product %d does not exist.', $pk), 404);
            }

            // An empty PATCH names nothing to change: nothing is written, the product is answered as it is.
            if ($posted === []) {
                return true;
            }
        }

        $merged = $this->mergeJsonColumns($stored, $posted);

        foreach ($merged as $column => $object) {
            $data[$column] = $object === [] ? '{}' : json_encode($object, JSON_THROW_ON_ERROR);
        }

        return $pk === 0
            ? $this->createThroughHandler($posted, $merged)
            : $this->patchThroughHandler($pk, $stored, $data, $posted, $merged);
    }

    /**
     * The JSON columns rebuilt from the body (the form filter strips their markup out of `$data`),
     * merged key by key over the stored ones: a body cannot delete another plugin's key, only
     * overwrite the ones it names.
     *
     * @return array<string, array>
     */
    private function mergeJsonColumns(?object $stored, array $posted): array
    {
        $merged = [];

        foreach (self::JSON_COLUMNS as $column) {
            $old = json_decode((string) ($stored->$column ?? ''), true);
            $new = $posted[$column] ?? null;

            if (\is_string($new)) {
                $new = trim($new) === '' ? null : (json_decode($new, true) ?? false);
            }

            if ($new !== null && (!\is_array($new) || ($new !== [] && array_is_list($new)))) {
                throw ProductApiException::single(400, '/' . $column, 'must be an object.');
            }

            $merged[$column] = array_replace(\is_array($old) ? $old : [], $new ?? []);
        }

        return $merged;
    }

    /** A `variants` document (`{upsert: [...], delete: [...]}`) on its own, for the products/:id/variants routes. */
    public function applyVariantDocument(int $pk, array $document): void
    {
        $stored = $this->getTable();

        if (!$stored->load($pk)) {
            throw new ResourceNotFound(\sprintf('Product %d does not exist.', $pk), 404);
        }

        $type    = (string) $stored->product_type;
        $handler = $this->requireHandler($type, 'edited');

        if (!\in_array('variants', $handler->apiSchema()->keys('patch'), true)) {
            throw ProductApiException::single(400, '/variants', \sprintf('product_type %s has no variants.', $type));
        }

        $payload = $this->payload($type, $pk, ['variants' => $document], $this->mergeJsonColumns($stored, []), $stored, ['variants']);
        $handler->apiValidatePatch($pk, $payload);

        $this->inTransaction(fn () => $handler->apiPatch($pk, $payload), 'updated');
        $this->cleanCache('com_j2commerce');
    }

    private function createThroughHandler(array $posted, array $merged): bool
    {
        $type = $posted['product_type'] ?? null;

        if (!\is_string($type) || trim($type) === '') {
            throw ProductApiException::single(400, '/product_type', \sprintf('is required. Creatable types: %s.', implode(', ', $this->productService()->creatableTypes())));
        }

        $type                   = trim($type);
        $posted['product_type'] = $type;
        $handler                = $this->requireHandler($type, 'created');
        $schema                 = ProductApiSchema::shared()->merge($handler->apiSchema());

        $this->assertKnownKeys($posted, $schema->keys('create'));

        if (($posted['product_source'] ?? 'com_content') !== 'com_content') {
            throw ProductApiException::single(400, '/product_source', 'must be com_content.');
        }

        $articleId = $this->freeArticleId($posted['product_source_id'] ?? null);
        $payload   = $this->payload($type, 0, $posted + ['product_source' => 'com_content', 'product_source_id' => $articleId], $merged, null, $schema->keys('create'));
        $handler->apiValidateCreate($payload);

        $id = $this->inTransaction(function () use ($handler, $payload): int {
            $id = $handler->apiCreate($payload);

            if ($id < 1) {
                throw new \RuntimeException('The product was not created.');
            }

            $this->assertMasterStored($id, $payload);

            return $id;
        }, 'created');

        $this->setState($this->getName() . '.id', $id);
        $this->cleanCache('com_j2commerce');

        return true;
    }

    /** The article id when it exists and no product is built on it. @throws ProductApiException  400 or 409 */
    private function freeArticleId(mixed $value): int
    {
        $articleId = ProductOptionService::uint($value) ? (int) $value : 0;
        $db        = $this->getDatabase();
        $query     = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $articleId, ParameterType::INTEGER);

        if ($articleId < 1 || !$db->setQuery($query, 0, 1)->loadResult()) {
            throw ProductApiException::single(400, '/product_source_id', 'must be an existing article id.');
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_product_id'))
            ->from($db->quoteName('#__j2commerce_products'))
            ->where($db->quoteName('product_source') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('product_source_id') . ' = :id')
            ->bind(':id', $articleId, ParameterType::INTEGER);

        if ($existing = (int) $db->setQuery($query, 0, 1)->loadResult()) {
            throw ProductApiException::single(409, '/product_source_id', \sprintf('Article %d already has product %d.', $articleId, $existing));
        }

        return $articleId;
    }

    /** Product columns and images can be patched without a handler; a type-owned key then needs one and is refused. */
    private function patchThroughHandler(int $pk, object $stored, array $data, array $posted, array $merged): bool
    {
        $type = (string) $stored->product_type;

        if (\array_key_exists('product_type', $posted) && (!\is_scalar($posted['product_type']) || (string) $posted['product_type'] !== $type)) {
            throw ProductApiException::single(400, '/product_type', 'cannot be changed through the API; use the admin Change Product Type.');
        }

        $data['product_type'] = $type;
        $handler              = $this->productService()->apiHandler($type);
        $schema               = ProductApiSchema::shared();

        if ($handler) {
            $schema = $schema->merge($handler->apiSchema());
        }

        $allowed = $schema->keys('patch');

        if (!$handler) {
            $allowed = array_values(array_diff($allowed, ['price', 'sku', 'upc', 'quantity']));
        }

        $this->assertKnownKeys($posted, array_merge($allowed, ['product_type']), $handler ? '' : \sprintf(' (%s has no API handler; only product columns and images can be patched)', $type));

        $payload = $this->payload($type, $pk, $posted, $merged, $stored, $allowed);
        $handler?->apiValidatePatch($pk, $payload);

        $save = $this->saveInput($pk, $type, $stored, $data, $posted);

        $this->inTransaction(function () use ($save, $pk, $payload, $handler): void {
            if (!parent::save($save)) {
                throw new \RuntimeException((string) $this->getError());
            }

            $this->applyImages($pk, $payload->images);
            $handler?->apiPatch($pk, $payload);
            $this->assertMasterStored($pk, $payload);
        }, 'updated');

        $this->cleanCache('com_j2commerce');

        return true;
    }

    /**
     * What parent::save() writes: the stored row, with the validated value of each key the checked
     * document named laid over it. The controller's `$data` is read for those keys only, so a key that
     * reached it outside the document cannot reach the row.
     *
     * @return array<string, mixed>
     */
    private function saveInput(int $pk, string $type, object $stored, array $data, array $posted): array
    {
        $save = [];

        foreach (array_keys($stored->getFields()) as $column) {
            $save[$column] = $stored->$column;
        }

        foreach (array_keys($posted) as $key) {
            if (\array_key_exists($key, $data)) {
                $save[$key] = $data[$key];
            }
        }

        foreach (self::JSON_COLUMNS as $column) {
            $save[$column] = $data[$column];
        }

        $save['j2commerce_product_id'] = $pk;
        $save['product_type']          = $type;

        return $save;
    }

    /**
     * The behaviors' onAfterSave() returns quietly when the master variant store is refused, and a
     * non-strict driver clamps an out-of-range decimal: the row is read back so neither answers 200.
     *
     * @throws \RuntimeException  Rolled back and reported by inTransaction().
     */
    private function assertMasterStored(int $pk, ProductPayload $payload): void
    {
        $master = $this->variants()->masterVariant($pk);

        if ($master === null) {
            throw new \RuntimeException('The master variant was not created.');
        }

        if ($payload->price !== null) {
            $this->variants()->assertPriceStored((int) $master->j2commerce_variant_id, $payload->price);
        }
    }

    /** @throws ProductApiException  400 naming the type and the creatable types. */
    private function requireHandler(string $type, string $verb): object
    {
        $service = $this->productService();
        $handler = $service->apiHandler($type);

        if ($handler === null) {
            throw ProductApiException::single(400, '/product_type', \sprintf('%s has no API handler and cannot be %s through the API. Creatable types: %s.', $type, $verb, implode(', ', $service->creatableTypes())));
        }

        return $handler;
    }

    /** @param list<string> $allowed */
    private function assertKnownKeys(array $posted, array $allowed, string $suffix = ''): void
    {
        $errors = [];

        foreach (array_keys($posted) as $key) {
            if (!\in_array((string) $key, $allowed, true)) {
                $errors[] = ['pointer' => '/' . $key, 'detail' => 'not accepted' . $suffix . '.'];
            }
        }

        if ($errors !== []) {
            throw new ProductApiException(400, $errors);
        }
    }

    /**
     * Checks the shared keys (price, sku, upc, quantity, images, product columns), every problem
     * reported at once; `$accepted` names the keys `raw` keeps for the handler.
     *
     * @throws ProductApiException
     */
    private function payload(string $type, int $pk, array $posted, array $merged, ?object $stored, array $accepted): ProductPayload
    {
        $errors = [];
        $row    = $this->rowColumns($posted, $merged, $stored, $errors);
        $master = $this->masterFields($posted, $errors);
        $images = $this->imageFields($posted, $errors);

        if ($errors !== []) {
            throw new ProductApiException(400, $errors);
        }

        $masterRow = $pk > 0 ? $this->variants()->masterVariant($pk) : null;
        $this->assertMasterSkuFree($pk, $master['sku'], $masterRow, $posted);

        $storedRow = [];

        if ($stored) {
            foreach (array_keys($stored->getFields()) as $column) {
                $storedRow[$column] = $stored->$column;
            }
        }

        return new ProductPayload(
            $type,
            $pk,
            $row,
            $master['price'],
            $master['sku'],
            $master['upc'],
            $master['quantity'],
            $images,
            array_intersect_key($posted, array_flip($accepted)),
            $this->getCurrentUser(),
            $storedRow,
            (array) ($masterRow ?? [])
        );
    }

    /**
     * A master SKU that changes is checked with the variant SKUs of the same request (create:
     * `/variants/n/sku`; patch: `/variants/upsert/n/sku`), so a master repeating one of its own
     * variants is caught; a master keeping its stored SKU is never refused (legacy duplicates). On a
     * patch the children the document renames or deletes no longer hold their stored SKU.
     *
     * @throws ProductApiException  400 at the pointer of the SKU.
     */
    private function assertMasterSkuFree(int $pk, ?string $sku, ?object $masterRow, array $posted): void
    {
        if ($sku === null || $sku === '' || trim((string) ($masterRow?->sku ?? '')) === $sku) {
            return;
        }

        $skus     = ['/sku' => $sku];
        $exclude  = $masterRow ? [(int) $masterRow->j2commerce_variant_id] : [];
        $document = $posted['variants'] ?? null;
        $entries  = $pk > 0 ? ($document['upsert'] ?? null) : $document;
        $prefix   = $pk > 0 ? '/variants/upsert/' : '/variants/';

        if (!\is_array($entries) || !array_is_list($entries)) {
            $entries = [];
        }

        foreach ($entries as $n => $entry) {
            if (!\is_array($entry) || !\array_key_exists('sku', $entry)) {
                continue;
            }

            if (\is_string($entry['sku']) && trim($entry['sku']) !== '') {
                $skus[$prefix . $n . '/sku'] = $entry['sku'];
            }

            if ($pk > 0 && ProductOptionService::uint($entry['id'] ?? null)) {
                $exclude[] = (int) $entry['id'];
            }
        }

        if ($pk > 0 && \is_array($document['delete'] ?? null)) {
            foreach ($document['delete'] as $id) {
                if (ProductOptionService::uint($id)) {
                    $exclude[] = (int) $id;
                }
            }
        }

        $this->variants()->assertSkusFree($skus, $exclude);
    }

    /**
     * The master variant scalars sent: price formatted, sku/upc trimmed, quantity as an int; null when not sent.
     *
     * @param list<array{pointer: string, detail: string}> $errors  Appended to.
     * @return array{price: ?string, sku: ?string, upc: ?string, quantity: ?int}
     */
    private function masterFields(array $posted, array &$errors): array
    {
        $out = ['price' => null, 'sku' => null, 'upc' => null, 'quantity' => null];

        if (\array_key_exists('price', $posted)) {
            $out['price'] = ProductApiSchema::money($posted['price']);

            if ($out['price'] === null) {
                $errors[] = ['pointer' => '/price', 'detail' => \sprintf('must be a number from 0 to %s.', number_format(ProductApiSchema::PRICE_MAX, ProductApiSchema::PRICE_SCALE, '.', ''))];
            }
        }

        foreach (['sku', 'upc'] as $field) {
            if (\array_key_exists($field, $posted)) {
                if (!(\is_string($posted[$field]) || \is_int($posted[$field])) || mb_strlen(trim((string) $posted[$field])) > self::TEXT_MAX_LENGTH) {
                    $errors[] = ['pointer' => '/' . $field, 'detail' => \sprintf('must be a string of %d characters or fewer.', self::TEXT_MAX_LENGTH)];
                } else {
                    $out[$field] = trim((string) $posted[$field]);
                }
            }
        }

        if (\array_key_exists('quantity', $posted)) {
            if (!ProductOptionService::uint($posted['quantity'])) {
                $errors[] = ['pointer' => '/quantity', 'detail' => 'must be a whole number of 0 or more.'];
            } else {
                $out['quantity'] = (int) $posted['quantity'];
            }
        }

        return $out;
    }

    /**
     * The product columns sent: flags as 0/1, row ids as 0 or an existing row, JSON columns merged.
     *
     * @param list<array{pointer: string, detail: string}> $errors  Appended to.
     * @return array<string, mixed>
     */
    private function rowColumns(array $posted, array $merged, ?object $stored, array &$errors): array
    {
        $pk  = (int) ($stored?->j2commerce_product_id ?? 0);
        $row = [];

        foreach (ProductApiSchema::ROW_COLUMNS as $column) {
            if (\array_key_exists($column, $posted)) {
                $row[$column] = $posted[$column];
            }
        }

        foreach (self::FLAG_COLUMNS as $column) {
            if (\array_key_exists($column, $row)) {
                if (!\in_array($row[$column], [0, 1, '0', '1'], true)) {
                    $errors[] = ['pointer' => '/' . $column, 'detail' => 'must be 0 or 1.'];
                } else {
                    $row[$column] = (int) $row[$column];
                }
            }
        }

        $this->referenceColumns($row, $errors);

        if (\array_key_exists('productfilter_ids', $row)) {
            $row['productfilter_ids'] = $this->filterIds($row['productfilter_ids'], $pk, $errors);
        }

        foreach (['cross_sells', 'up_sells'] as $column) {
            if (\array_key_exists($column, $row)) {
                $row[$column] = $this->productIdList($row[$column], $pk, '/' . $column, $this->csvIds($stored?->$column ?? null), $errors);
            }
        }

        $row['params']  = $merged['params'];
        $row['plugins'] = $merged['plugins'] === [] ? '{}' : json_encode($merged['plugins'], JSON_THROW_ON_ERROR);

        if ($pk === 0) {
            $row['product_source']    = 'com_content';
            $row['product_source_id'] = (int) $posted['product_source_id'];
        }

        return $row;
    }

    /**
     * A list or CSV of ids as ints, or null (error appended) when it is not one or when it would not
     * fit the varchar(255) CSV column it is stored in.
     *
     * @param list<array{pointer: string, detail: string}> $errors  Appended to.
     * @return list<int>|null
     */
    private function idList(mixed $value, string $pointer, string $what, array &$errors): ?array
    {
        $ids = match (true) {
            \is_array($value)  => $value,
            \is_string($value) => array_map('trim', explode(',', $value)),
            \is_int($value)    => [$value],
            default            => null,
        };

        if ($ids === null || \count($ids) > ProductApiSchema::MAX_LIST || !$this->allPositiveInts($ids)) {
            $errors[] = ['pointer' => $pointer, 'detail' => \sprintf('must be a list of %s ids.', $what)];

            return null;
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (\strlen(implode(',', $ids)) > ProductApiSchema::CSV_COLUMN_LENGTH) {
            $errors[] = ['pointer' => $pointer, 'detail' => \sprintf('is stored as a CSV of %d characters at most; send fewer ids.', ProductApiSchema::CSV_COLUMN_LENGTH)];

            return null;
        }

        return $ids;
    }

    /** A list whose every item is a whole number above 0 (ints or digit strings). */
    private function allPositiveInts(array $ids): bool
    {
        if (!array_is_list($ids)) {
            return false;
        }

        foreach ($ids as $id) {
            if (!ProductOptionService::uint($id) || (int) $id < 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * The filter ids sent as the CSV the behaviors read; every id must be an existing filter. A value of a
     * source-maintained group (a tag group) is linked by its source plugin from the article, never by this
     * write, so it is accepted only when the product already carries it.
     *
     * @param list<array{pointer: string, detail: string}> $errors  Appended to.
     */
    private function filterIds(mixed $value, int $pk, array &$errors): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        $ids = $this->idList($value, '/productfilter_ids', 'filter', $errors);

        if ($ids === null) {
            return '';
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['f.j2commerce_filter_id', 'g.source']))
            ->from($db->quoteName('#__j2commerce_filters', 'f'))
            ->join('INNER', $db->quoteName('#__j2commerce_filtergroups', 'g'), $db->quoteName('g.j2commerce_filtergroup_id') . ' = ' . $db->quoteName('f.group_id'))
            ->whereIn($db->quoteName('f.j2commerce_filter_id'), $ids);
        $found = $db->setQuery($query)->loadAssocList('j2commerce_filter_id', 'source');

        if ($missing = array_diff($ids, array_map('intval', array_keys($found)))) {
            $errors[] = ['pointer' => '/productfilter_ids', 'detail' => \sprintf('filters %s do not exist.', implode(', ', $missing))];

            return '';
        }

        $sourced = array_map('intval', array_keys(array_filter($found, static fn ($source): bool => $source !== FilterSourceHelper::NATIVE)));
        $linked  = $pk > 0 && $sourced !== [] ? $this->filterLinks($pk) : [];

        if ($unlinked = array_diff($sourced, $linked)) {
            $errors[] = ['pointer' => '/productfilter_ids', 'detail' => \sprintf('filters %s belong to a group kept in step with the article\'s tags; tag the article instead.', implode(', ', $unlinked))];
        }

        return implode(',', $ids);
    }

    /**
     * Cross-sell / up-sell ids as the CSV the admin form stores, never the product itself. An id the
     * column already holds round-trips (the list a client just read can be sent back whatever the
     * product's state since); a new id must be an existing product with `enabled` = 1.
     *
     * @param list<int>                                    $storedIds  The ids the column holds now.
     * @param list<array{pointer: string, detail: string}> $errors     Appended to.
     */
    private function productIdList(mixed $value, int $pk, string $pointer, array $storedIds, array &$errors): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        $ids = $this->idList($value, $pointer, 'product', $errors);

        if ($ids === null) {
            return '';
        }

        if ($pk > 0 && \in_array($pk, $ids, true)) {
            $errors[] = ['pointer' => $pointer, 'detail' => 'cannot include the product itself.'];

            return '';
        }

        $new = array_values(array_diff($ids, $storedIds));

        if ($new !== []) {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select($db->quoteName('j2commerce_product_id'))
                ->from($db->quoteName('#__j2commerce_products'))
                ->where($db->quoteName('enabled') . ' = 1')
                ->whereIn($db->quoteName('j2commerce_product_id'), $new);

            if ($missing = array_diff($new, array_map('intval', $db->setQuery($query)->loadColumn()))) {
                $errors[] = ['pointer' => $pointer, 'detail' => \sprintf('products %s do not exist or are not enabled.', implode(', ', $missing))];

                return '';
            }
        }

        return implode(',', $ids);
    }

    /**
     * The product's filter links read fresh: ProductHelper::getProductFilterIds() caches per request,
     * and the response to a PATCH of `productfilter_ids` has to carry the links the write made.
     *
     * @return list<int>
     */
    private function filterLinks(int $pk): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('filter_id'))
            ->from($db->quoteName('#__j2commerce_product_filters'))
            ->where($db->quoteName('product_id') . ' = :pk')
            ->order($db->quoteName('filter_id'))
            ->bind(':pk', $pk, ParameterType::INTEGER);

        return array_map('intval', $db->setQuery($query)->loadColumn());
    }

    /** Row id columns sent become ints after the row they name is found; `$errors` is appended to. */
    private function referenceColumns(array &$row, array &$errors): void
    {
        foreach (self::REFERENCE_COLUMNS as $column => [$table, $key, $enabled]) {
            if (!\array_key_exists($column, $row)) {
                continue;
            }

            if (!ProductOptionService::uint($row[$column]) || !$this->referenceExists($table, $key, (int) $row[$column], $enabled)) {
                $errors[] = ['pointer' => '/' . $column, 'detail' => \sprintf('must be 0 or the id of an existing%s row.', $enabled ? ' enabled' : '')];
            } else {
                $row[$column] = (int) $row[$column];
            }
        }
    }

    private function referenceExists(string $table, string $key, int $id, bool $enabled): bool
    {
        if ($id === 0) {
            return true;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName($key))
            ->from($db->quoteName($table))
            ->where($db->quoteName($key) . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        if ($enabled) {
            $query->where($db->quoteName('enabled') . ' = 1');
        }

        return (int) $db->setQuery($query, 0, 1)->loadResult() > 0;
    }

    /**
     * The image fields sent: alt text stripped and clipped, paths checked against the store image folders.
     *
     * @param list<array{pointer: string, detail: string}> $errors  Appended to.
     * @return array<string, string>
     */
    private function imageFields(array $posted, array &$errors): array
    {
        $images = [];

        foreach (ProductApiSchema::IMAGE_FIELDS as $field) {
            if (!\array_key_exists($field, $posted)) {
                continue;
            }

            if ($posted[$field] !== null && !\is_scalar($posted[$field])) {
                $errors[] = ['pointer' => '/' . $field, 'detail' => 'must be a string.'];

                continue;
            }

            $value = trim((string) $posted[$field]);

            if (str_ends_with($field, '_alt')) {
                $images[$field] = mb_substr(strip_tags($value), 0, self::TEXT_MAX_LENGTH);
            } elseif ($value !== '' && !ProductApiSchema::isLocalImage($value)) {
                $errors[] = ['pointer' => '/' . $field, 'detail' => 'must be a file in one of the store image folders.'];
            } else {
                $images[$field] = $value;
            }
        }

        return $images;
    }

    /** One transaction around the write; a ProductApiException keeps its status, the rest is a logged server fault with a generic message. */
    private function inTransaction(callable $write, string $verb): mixed
    {
        $db = $this->getDatabase();
        $db->transactionStart();

        try {
            $result = $write();
            $db->transactionCommit();

            return $result;
        } catch (ProductApiException $e) {
            $db->transactionRollback();

            throw $e;
        } catch (\Throwable $e) {
            $db->transactionRollback();
            Log::add('API product ' . $verb . ': ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');

            throw ProductApiException::single(500, '/', 'The product could not be ' . $verb . '.');
        }
    }

    private function applyImages(int $pk, array $images): void
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
        $row = (object) $images;

        if ($imageId = (int) $db->setQuery($query, 0, 1)->loadResult()) {
            $row->j2commerce_productimage_id = $imageId;
            $db->updateObject('#__j2commerce_productimages', $row, 'j2commerce_productimage_id');
        } else {
            $row->product_id = $pk;
            $row->main_image_alt  ??= '';
            $row->thumb_image_alt ??= '';
            $db->insertObject('#__j2commerce_productimages', $row);
        }
    }

    /**
     * The keys the caller actually named, read in the order ApiController::save() reads them: a `data`
     * array in the query or form post first, else the JSON object in the body, else nothing. The
     * allow-list and the write then see one document, so a key cannot ride in beside the one that was
     * checked. Never `jform`: ApiController::save() fills it with every stored column on PATCH, which
     * would make an empty or non-JSON body read as a request to write them all.
     */
    private function postedBody(CMSApplicationInterface $app): array
    {
        $input = $app->getInput();
        $body  = $input->exists('data') ? $input->get('data', null, 'array') : json_decode((string) $input->json->getRaw(), true);

        return \is_array($body) ? $body : [];
    }

    private function productService(): ProductService
    {
        return $this->productService ??= new ProductService($this->getMVCFactory());
    }

    private function variants(): VariantMatrixService
    {
        return $this->variantService ??= new VariantMatrixService($this->getMVCFactory(), $this->getDatabase());
    }

    public function getItem($pk = null): object|bool
    {
        $item = parent::getItem($pk);

        if (!$item || empty($item->j2commerce_product_id)) {
            return $item;
        }

        $id       = (int) $item->j2commerce_product_id;
        $type     = (string) ($item->product_type ?? '');
        $variants = $this->variants();
        $options  = new ProductOptionService($this->getMVCFactory(), $this->getDatabase());
        $attached = $options->productOptions($id);

        // The admin model hands params over as a JSON string for its hidden form field; the API returns the objects.
        $item->params  = $this->jsonObject($item->params ?? null);
        $item->plugins = $this->jsonObject($item->plugins ?? null);

        // The master variant's price; a product priced per variant reports its tiers in `variants`.
        $item->price    = $variants->masterVariant($id)?->price;
        $item->variants = $variants->variantsOf($id, $attached);
        $item->options  = $options->forApi($id, $attached);

        // Filters come from the link table (the column is not kept in step); sells are the stored CSVs as int lists.
        $item->productfilter_ids = $this->filterLinks($id);
        $item->cross_sells       = $this->csvIds($item->cross_sells ?? null);
        $item->up_sells          = $this->csvIds($item->up_sells ?? null);

        $handler  = $this->productService()->apiHandler($type);
        $typeData = $handler ? $handler->apiRead($item) : [];

        PluginHelper::importPlugin('j2commerce');
        $event = new ProductReadEvent($item, $type);
        Factory::getApplication()->getDispatcher()->dispatch(ProductReadEvent::NAME, $event);

        $item->type_data = (object) array_replace($typeData, $event->getBlocks());
        $this->addArticle($item);

        return $item;
    }

    /** `article` and `jcfields` from the source article, when it is published and visible to the caller. */
    private function addArticle(object $item): void
    {
        $item->article  = null;
        $item->jcfields = [];
        $article        = $item->source ?? null;

        if ($article === null || !isset($article->id) || !$this->articleVisible($article)) {
            return;
        }

        $item->article = $this->articleBlock($article);
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
    }

    /** @return list<int> */
    private function csvIds(mixed $csv): array
    {
        $ids = array_filter(array_map('trim', explode(',', (string) $csv)), static fn (string $id): bool => ctype_digit($id) && (int) $id > 0);

        return array_values(array_map('intval', $ids));
    }

    private function jsonObject(mixed $json): object
    {
        $decoded = json_decode((string) $json, false);

        return \is_object($decoded) ? $decoded : new \stdClass();
    }

    private function articleVisible(object $article): bool
    {
        return (int) ($article->state ?? 0) === 1
            && \in_array((int) ($article->access ?? 0), $this->getCurrentUser()->getAuthorisedViewLevels(), true);
    }

    private function articleBlock(object $article): object
    {
        $safe = new \stdClass();

        foreach (['id', 'title', 'alias', 'introtext', 'fulltext', 'state', 'catid', 'language', 'created', 'modified', 'publish_up', 'publish_down'] as $field) {
            $safe->$field = $article->$field ?? null;
        }

        return $safe;
    }
}
