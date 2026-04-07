<?php

/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_relatedproducts
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Module\RelatedProducts\Site\Helper;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CartHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

class RelatedProductsHelper
{
    public function getRelatedProducts(Registry $params): array
    {
        if (!ComponentHelper::isEnabled('com_j2commerce')) {
            return [];
        }

        $relationType = $params->get('relation_type', 'cross_sells');
        $count        = (int) $params->get('count', 4);
        $ordering     = $params->get('list_ordering', 'random');

        $cartProductIds = $this->getCartProductIds();

        if (empty($cartProductIds)) {
            return $this->handleEmptyCart($params, $count);
        }

        $relatedIds = $this->collectRelatedIds($cartProductIds, $relationType);
        $relatedIds = array_values(array_diff(array_unique($relatedIds), $cartProductIds));

        if (empty($relatedIds)) {
            return $this->handleEmptyCart($params, $count);
        }

        $relatedIds = $this->applyOrdering($relatedIds, $ordering);
        $relatedIds = \array_slice($relatedIds, 0, $count);

        return $this->loadProducts($relatedIds);
    }

    public static function getRelatedHtmlAjax(): string
    {
        $app      = Factory::getApplication();
        $moduleId = $app->getInput()->getInt('module_id', 0);

        if ($moduleId <= 0) {
            return '';
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('id') . ' = :moduleId')
            ->where($db->quoteName('published') . ' = 1')
            ->bind(':moduleId', $moduleId, ParameterType::INTEGER);

        $db->setQuery($query);
        $module = $db->loadObject();

        if (!$module) {
            return '';
        }

        $renderer       = $app->getDocument()->loadRenderer('module');
        $module->params = $module->params ?: '{}';

        return $renderer->render($module);
    }

    private function getCartProductIds(): array
    {
        try {
            $cartHelper = CartHelper::getInstance();
            $cart       = $cartHelper->getCart(0, false);

            if (!$cart) {
                return [];
            }

            $cartId = (int) $cart->j2commerce_cart_id;
            $db     = $this->getDb();
            $query  = $db->getQuery(true);

            $query->select('DISTINCT ' . $db->quoteName('product_id'))
                ->from($db->quoteName('#__j2commerce_cartitems'))
                ->where($db->quoteName('cart_id') . ' = :cartId')
                ->bind(':cartId', $cartId, ParameterType::INTEGER);

            $db->setQuery($query);

            return array_map('intval', $db->loadColumn() ?: []);
        } catch (\Throwable) {
            return [];
        }
    }

    private function collectRelatedIds(array $cartProductIds, string $relationType): array
    {
        if (empty($cartProductIds)) {
            return [];
        }

        $db    = $this->getDb();
        $query = $db->getQuery(true);

        $columns = match ($relationType) {
            'up_sells' => ['up_sells'],
            'both'     => ['cross_sells', 'up_sells'],
            default    => ['cross_sells'],
        };

        $query->select(array_map([$db, 'quoteName'], $columns))
            ->from($db->quoteName('#__j2commerce_products'));

        $bindValues   = [];
        $placeholders = [];
        foreach ($cartProductIds as $i => $productId) {
            $key            = ':pid' . $i;
            $bindValues[$i] = $productId;
            $query->bind($key, $bindValues[$i], ParameterType::INTEGER);
            $placeholders[] = $key;
        }

        $query->where($db->quoteName('j2commerce_product_id') . ' IN (' . implode(',', $placeholders) . ')')
            ->where($db->quoteName('enabled') . ' = 1');

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $relatedIds = [];
        foreach ($rows as $row) {
            foreach ($columns as $col) {
                $csv = $row->{$col} ?? '';
                if ($csv === '') {
                    continue;
                }
                foreach (explode(',', $csv) as $id) {
                    $id = (int) trim($id);
                    if ($id > 0) {
                        $relatedIds[] = $id;
                    }
                }
            }
        }

        return $relatedIds;
    }

    private function applyOrdering(array $ids, string $ordering): array
    {
        if ($ordering === 'random') {
            shuffle($ids);
            return $ids;
        }

        if (empty($ids)) {
            return [];
        }

        $db    = $this->getDb();
        $query = $db->getQuery(true);

        $query->select([
                $db->quoteName('p.j2commerce_product_id'),
                $db->quoteName('c.title'),
            ])
            ->from($db->quoteName('#__j2commerce_products', 'p'))
            ->join(
                'INNER',
                $db->quoteName('#__content', 'c')
                . ' ON ' . $db->quoteName('c.id')
                . ' = ' . $db->quoteName('p.product_source_id')
            )
            ->where($db->quoteName('p.enabled') . ' = 1')
            ->where($db->quoteName('p.visibility') . ' = 1')
            ->where($db->quoteName('c.state') . ' = 1');

        $bindValues   = [];
        $placeholders = [];
        foreach ($ids as $i => $id) {
            $key            = ':rid' . $i;
            $bindValues[$i] = $id;
            $query->bind($key, $bindValues[$i], ParameterType::INTEGER);
            $placeholders[] = $key;
        }

        $query->where($db->quoteName('p.j2commerce_product_id') . ' IN (' . implode(',', $placeholders) . ')');

        match ($ordering) {
            'title_asc'  => $query->order($db->quoteName('c.title') . ' ASC'),
            'title_desc' => $query->order($db->quoteName('c.title') . ' DESC'),
            default      => $query->order($db->quoteName('p.j2commerce_product_id') . ' DESC'),
        };

        $db->setQuery($query);

        return array_map('intval', $db->loadColumn() ?: []);
    }

    private function handleEmptyCart(Registry $params, int $count): array
    {
        $fallback = $params->get('empty_cart_behavior', 'hide');

        if ($fallback === 'hide') {
            return [];
        }

        $db    = $this->getDb();
        $query = $db->getQuery(true);

        $query->select($db->quoteName('a.j2commerce_product_id'))
            ->from($db->quoteName('#__j2commerce_products', 'a'))
            ->join(
                'INNER',
                $db->quoteName('#__content', 'c')
                . ' ON ' . $db->quoteName('c.id')
                . ' = ' . $db->quoteName('a.product_source_id')
            )
            ->where($db->quoteName('a.enabled') . ' = 1')
            ->where($db->quoteName('a.visibility') . ' = 1')
            ->where($db->quoteName('c.state') . ' = 1');

        if ($fallback === 'fallback_popular') {
            $query->order($db->quoteName('a.hits') . ' DESC');
        } else {
            $query->order($db->quoteName('a.j2commerce_product_id') . ' DESC');
        }

        $query->setLimit($count);
        $db->setQuery($query);

        $ids = array_map('intval', $db->loadColumn() ?: []);

        return $this->loadProducts($ids);
    }

    private function loadProducts(array $productIds): array
    {
        $products = [];

        foreach ($productIds as $productId) {
            try {
                $product = ProductHelper::getFullProduct($productId);
            } catch (\Throwable) {
                $product = null;
            }

            if ($product !== null) {
                $products[] = $product;
            }
        }

        return $products;
    }

    private function getDb(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }
}
