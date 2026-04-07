<?php

/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_products
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Module\Products\Site\Helper;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

class ProductsHelper
{
    public function getProducts(Registry $params): array
    {
        if (!ComponentHelper::isEnabled('com_j2commerce')) {
            return [];
        }

        $source = $params->get('product_source', 'latest');

        // Selected products bypass SQL query — use explicit IDs in order
        if ($source === 'selected_products') {
            return $this->getSelectedProducts($params);
        }

        $productIds = $this->getProductIds($params);

        if (empty($productIds)) {
            return [];
        }

        $products = [];

        foreach ($productIds as $productId) {
            try {
                $product = ProductHelper::getFullProduct((int) $productId);
            } catch (\Throwable) {
                $product = null;
            }

            if ($product !== null) {
                $products[] = $product;
            }
        }

        return $products;
    }

    private function getSelectedProducts(Registry $params): array
    {
        $productIds = $params->get('product_ids', []);

        if (\is_string($productIds)) {
            $productIds = explode(',', $productIds);
        }

        $productIds = array_map('intval', array_filter((array) $productIds));
        $count      = (int) $params->get('count', 8);
        $productIds = \array_slice($productIds, 0, $count);

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

    private function getProductIds(Registry $params): array
    {
        $source = $params->get('product_source', 'latest');
        $count  = (int) $params->get('count', 8);
        $db     = $this->getDb();

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

        // Product type filter — [""] from empty multi-select must be filtered out
        $productTypes = $params->get('product_types', '');

        if (!empty($productTypes)) {
            $types = \is_array($productTypes) ? $productTypes : explode(',', (string) $productTypes);
            $types = array_values(array_filter($types, fn ($t) => trim((string) $t) !== ''));

            if (!empty($types)) {
                $query->where($db->quoteName('a.product_type') . ' IN (' . implode(',', array_map(
                    fn ($t) => $db->quote(trim((string) $t)),
                    $types
                )) . ')');
            }
        }

        // Featured-only filter (applies to any source except "featured" which already filters)
        if ((int) $params->get('featured_only', 0) === 1 && $source !== 'featured') {
            $query->where($db->quoteName('c.featured') . ' = 1');
        }

        // Source-specific WHERE filters
        match ($source) {
            'featured' => $query->where($db->quoteName('c.featured') . ' = 1'),
            'category' => $this->applyCategoryFilter($query, $params, $db),
            'tag'      => $this->applyTagFilter($query, $params, $db),
            default    => null,
        };

        // Ordering: source-driven sources override list_ordering
        $ordering = match ($source) {
            'bestselling' => 'bestselling',
            'popular'     => 'popular',
            default       => $params->get('list_ordering', 'latest'),
        };

        $this->applyOrdering($query, $ordering, $db);

        $query->setLimit($count);
        $db->setQuery($query);

        return $db->loadColumn() ?: [];
    }

    private function applyCategoryFilter(\Joomla\Database\QueryInterface $query, Registry $params, DatabaseInterface $db): void
    {
        $categoryIds = $params->get('category_ids', '');

        if (empty($categoryIds)) {
            return;
        }

        $ids = \is_array($categoryIds)
            ? array_map('intval', $categoryIds)
            : array_map('intval', explode(',', (string) $categoryIds));

        $ids = array_values(array_filter($ids));

        if (empty($ids)) {
            return;
        }

        $placeholders = [];

        foreach ($ids as $i => $catId) {
            $key = ':catId' . $i;
            $query->bind($key, $ids[$i], ParameterType::INTEGER);
            $placeholders[] = $key;
        }

        $query->where($db->quoteName('c.catid') . ' IN (' . implode(',', $placeholders) . ')');
    }

    private function applyTagFilter(\Joomla\Database\QueryInterface $query, Registry $params, DatabaseInterface $db): void
    {
        $tagIds = $params->get('tag_ids', '');

        if (empty($tagIds)) {
            return;
        }

        $ids = \is_array($tagIds)
            ? array_map('intval', $tagIds)
            : array_map('intval', explode(',', (string) $tagIds));

        $ids = array_values(array_filter($ids));

        if (empty($ids)) {
            return;
        }

        $placeholders = [];

        foreach ($ids as $i => $tagId) {
            $key = ':tagId' . $i;
            $query->bind($key, $ids[$i], ParameterType::INTEGER);
            $placeholders[] = $key;
        }

        // Join content tag map — articles tagged with selected tags
        $query->join(
            'INNER',
            $db->quoteName('#__contentitem_tag_map', 'tm')
            . ' ON ' . $db->quoteName('tm.content_item_id') . ' = ' . $db->quoteName('c.id')
            . ' AND ' . $db->quoteName('tm.type_alias') . ' = ' . $db->quote('com_content.article')
        )
        ->where($db->quoteName('tm.tag_id') . ' IN (' . implode(',', $placeholders) . ')')
        ->group($db->quoteName('a.j2commerce_product_id'));
    }

    private function applyOrdering(\Joomla\Database\QueryInterface $query, string $ordering, DatabaseInterface $db): void
    {
        match ($ordering) {
            'title_asc'        => $query->order($db->quoteName('c.title') . ' ASC'),
            'title_desc'       => $query->order($db->quoteName('c.title') . ' DESC'),
            'article_ordering' => $query->order($db->quoteName('c.ordering') . ' ASC'),
            'random'           => $query->order('RAND()'),
            'popular'          => $query->order($db->quoteName('a.hits') . ' DESC'),
            'bestselling'      => $this->applyBestsellingJoinAndOrder($query, $db),
            default            => $query->order($db->quoteName('a.j2commerce_product_id') . ' DESC'),
        };
    }

    private function applyBestsellingJoinAndOrder(\Joomla\Database\QueryInterface $query, DatabaseInterface $db): void
    {
        $query->join(
            'LEFT',
            '(SELECT ' . $db->quoteName('product_id')
            . ', SUM(' . $db->quoteName('orderitem_quantity') . ') AS sale_count'
            . ' FROM ' . $db->quoteName('#__j2commerce_orderitems')
            . ' GROUP BY ' . $db->quoteName('product_id') . ') AS oi'
            . ' ON oi.' . $db->quoteName('product_id')
            . ' = ' . $db->quoteName('a.j2commerce_product_id')
        )
        ->order('COALESCE(oi.sale_count, 0) DESC');
    }
}
