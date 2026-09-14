<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Model;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use J2Commerce\Component\J2commerce\Site\Helper\ProductVisibilityHelper;
use J2Commerce\Component\J2commerce\Site\Helper\TagTreeHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Registry\Registry;

/**
 * Product Tags View model: the child tags of a parent tag, with product counts, the products
 * tagged with the parent itself, and the trending products across the parent's subtree.
 * The tag counterpart of CategoriesModel.
 */
class TagsModel extends BaseDatabaseModel
{
    protected $_context = 'com_j2commerce.tags';

    private ?object $parent = null;

    private ?array $items = null;

    private ?array $products = null;

    protected function populateState(): void
    {
        $app    = Factory::getApplication();
        $params = $app->getParams();

        $this->setState('params', $params);

        // Resolve parent tag: URL input -> menu query -> root
        $parentId = $app->getInput()->getInt('id', 0);

        if ($parentId === 0) {
            $parentId = (int) ($app->getMenu()->getActive()?->query['id'] ?? 0);
        }

        $parentId = $parentId > 1 ? $parentId : TagTreeHelper::ROOT_ID;

        $this->setState('filter.parent_id', $parentId);

        // Merge tag-level param overrides for filter settings, the tag counterpart of the
        // same merge in CategoriesModel. The HtmlView does this same merge for template
        // params, but getItems() runs before the view can apply overrides.
        if ($parentId > TagTreeHelper::ROOT_ID) {
            $tag = TagTreeHelper::get($parentId);

            if ($tag) {
                $tagParams = new Registry($tag->params ?? '{}');

                foreach (['tag_view_type', 'show_child_tags', 'child_tag_levels', 'show_empty_tags'] as $key) {
                    $value = $tagParams->get($key, '');
                    if ($value !== '' && $value !== null) {
                        $params->set($key, $value);
                    }
                }
            }
        }

        $this->setState('filter.show_child_tags', (int) $params->get('show_child_tags', 1));
        $this->setState('filter.child_tag_levels', (int) $params->get('child_tag_levels', 1));
        $this->setState('filter.show_empty', (int) $params->get('show_empty_tags', 0));
        $this->setState('filter.access', $this->getCurrentUser()->getAuthorisedViewLevels());
    }

    /** The parent tag, or null when it is unpublished or outside the visitor's access levels. */
    public function getParent(): ?object
    {
        if ($this->parent !== null) {
            return $this->parent;
        }

        $parentId = (int) $this->getState('filter.parent_id', TagTreeHelper::ROOT_ID);

        if (TagTreeHelper::isViewable($parentId, $this->getState('filter.access', [1]))) {
            $this->parent = $this->buildTagItem(TagTreeHelper::get($parentId));
        }

        return $this->parent;
    }

    public function getItems(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }

        $parent = $this->getParent();

        if (!$parent) {
            return $this->items = [];
        }

        $levels = $this->getState('filter.show_child_tags', 1) ? (int) $this->getState('filter.child_tag_levels', 1) : 0;

        return $this->items = $this->getChildTags($parent->id, $levels, $this->getProductCounts());
    }

    /** @param array<int, int> $productCounts */
    private function getChildTags(int $parentId, int $levelsRemain, array $productCounts): array
    {
        if ($levelsRemain < 0) {
            return [];
        }

        $showEmpty    = (int) $this->getState('filter.show_empty', 0);
        $accessLevels = array_map('intval', $this->getState('filter.access', [1]));
        $items        = [];

        foreach (TagTreeHelper::children($parentId) as $tag) {
            if (!TagTreeHelper::isViewable($tag->id, $accessLevels)) {
                continue;
            }

            $item                = $this->buildTagItem($tag);
            $item->product_count = $productCounts[$tag->id] ?? 0;

            if (!$showEmpty && $item->product_count === 0 && !$this->hasProductsInChildren($tag->id, $productCounts)) {
                continue;
            }

            $item->children = $this->getChildTags($tag->id, $levelsRemain - 1, $productCounts);
            $items[]        = $item;
        }

        return $items;
    }

    private function buildTagItem(object $tag): object
    {
        $images = new Registry($tag->images);
        $image  = (string) ($images->get('image_intro') ?: $images->get('image_fulltext', ''));

        return (object) [
            'id'            => $tag->id,
            'title'         => $tag->title,
            'alias'         => $tag->alias,
            'description'   => $tag->description,
            'parent_id'     => $tag->parent_id,
            'level'         => $tag->level,
            'access'        => $tag->access,
            'language'      => $tag->language,
            'metadesc'      => $tag->metadesc,
            'metakey'       => $tag->metakey,
            'image'         => $image !== '' ? HTMLHelper::_('cleanImageURL', $image)->url : '',
            'image_alt'     => (string) ($images->get('image_intro') ? $images->get('image_intro_alt', '') : $images->get('image_fulltext_alt', '')),
            'product_count' => 0,
            'children'      => [],
            'params'        => new Registry($tag->params ?? '{}'),
        ];
    }

    /** @param array<int, int> $productCounts */
    private function hasProductsInChildren(int $tagId, array $productCounts): bool
    {
        foreach (TagTreeHelper::descendantIds($tagId, \PHP_INT_MAX) as $id) {
            if (($productCounts[$id] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, int> Product counts keyed by tag ID. */
    private function getProductCounts(): array
    {
        $db       = $this->getDatabase();
        $isEditor = ProductVisibilityHelper::isEditor();

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('m.tag_id'),
                'COUNT(DISTINCT ' . $db->quoteName('p.j2commerce_product_id') . ') AS ' . $db->quoteName('product_count'),
            ])
            ->from($db->quoteName('#__contentitem_tag_map', 'm'))
            ->join(
                'INNER',
                $db->quoteName('#__content', 'a'),
                $db->quoteName('a.id') . ' = ' . $db->quoteName('m.content_item_id')
                    . ($isEditor ? '' : ' AND ' . $db->quoteName('a.state') . ' = 1')
            )
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_products', 'p'),
                $db->quoteName('p.product_source_id') . ' = ' . $db->quoteName('a.id')
                    . ' AND ' . $db->quoteName('p.product_source') . ' = ' . $db->quote('com_content')
                    . ($isEditor ? '' : ' AND ' . $db->quoteName('p.enabled') . ' = 1')
                    . ' AND ' . $db->quoteName('p.visibility') . ' = 1'
            )
            ->where($db->quoteName('m.type_alias') . ' = ' . $db->quote('com_content.article'))
            ->group($db->quoteName('m.tag_id'));

        return array_map('intval', $db->setQuery($query)->loadAssocList('tag_id', 'product_count'));
    }

    /** Products tagged with the parent tag itself, not its children. */
    public function getProducts(): array
    {
        if ($this->products !== null) {
            return $this->products;
        }

        $parent = $this->getParent();

        if (!$parent || $parent->id === TagTreeHelper::ROOT_ID) {
            return $this->products = [];
        }

        $db    = $this->getDatabase();
        $query = $this->getProductQuery([$parent->id]);

        [$orderMapping, $orderDirection] = ProductsModel::resolveMenuOrdering($this->getState('params'));

        // The resolver already filtered the column; this model keeps no list.ordering state to
        // guard it a second time at the sink, so it is checked here too.
        if (!\in_array($orderMapping, ProductsModel::ORDER_COLUMNS, true)) {
            $orderMapping = 'a.ordering';
        }

        $query->order($db->quoteName($orderMapping) . ' ' . $orderDirection);

        return $this->products = $this->hydrate($db->setQuery($query)->loadObjectList());
    }

    /** The most-viewed products tagged anywhere in the tag's subtree. */
    public function getPopularProducts(int $tagId, int $limit = 12): array
    {
        $db    = $this->getDatabase();
        $query = $this->getProductQuery([$tagId, ...TagTreeHelper::descendantIds($tagId, \PHP_INT_MAX)])
            ->order($db->quoteName('a.hits') . ' DESC')
            ->setLimit($limit);

        return $this->hydrate($db->setQuery($query)->loadObjectList());
    }

    /** @param int[] $tagIds */
    private function getProductQuery(array $tagIds): QueryInterface
    {
        $db     = $this->getDatabase();
        $groups = $this->getCurrentUser()->getAuthorisedViewLevels();

        $tagged = $db->getQuery(true)
            ->select($db->quoteName('content_item_id'))
            ->from($db->quoteName('#__contentitem_tag_map'))
            ->where($db->quoteName('type_alias') . ' = ' . $db->quote('com_content.article'))
            ->where($db->quoteName('tag_id') . ' IN (' . implode(',', array_map('intval', $tagIds)) . ')');

        $query = $db->getQuery(true)
            ->select($db->quoteName(['p.j2commerce_product_id', 'a.ordering', 'a.hits', 'a.featured']))
            ->from($db->quoteName('#__j2commerce_products', 'p'))
            ->join(
                'INNER',
                $db->quoteName('#__content', 'a'),
                $db->quoteName('a.id') . ' = ' . $db->quoteName('p.product_source_id')
                    . ' AND ' . $db->quoteName('p.product_source') . ' = ' . $db->quote('com_content')
            )
            ->join('LEFT', $db->quoteName('#__categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_variants', 'v'),
                $db->quoteName('v.product_id') . ' = ' . $db->quoteName('p.j2commerce_product_id')
                    . ' AND ' . $db->quoteName('v.is_master') . ' = 1'
            )
            ->where($db->quoteName('p.visibility') . ' = 1')
            ->where($db->quoteName('a.id') . ' IN (' . $tagged . ')')
            ->whereIn($db->quoteName('a.access'), $groups)
            ->whereIn($db->quoteName('c.access'), $groups);

        // Users who may edit products keep the unpublished ones in the list so an
        // upcoming release can be previewed, same as com_content does for articles.
        if (!ProductVisibilityHelper::isEditor()) {
            $nowDate = Factory::getDate()->toSql();

            $query->where($db->quoteName('p.enabled') . ' = 1')
                ->where($db->quoteName('a.state') . ' = 1')
                ->where($db->quoteName('c.published') . ' = 1')
                ->where('(' . $db->quoteName('a.publish_up') . ' IS NULL OR ' . $db->quoteName('a.publish_up') . ' <= :publishUp)')
                ->where('(' . $db->quoteName('a.publish_down') . ' IS NULL OR ' . $db->quoteName('a.publish_down') . ' >= :publishDown)')
                ->bind(':publishUp', $nowDate)
                ->bind(':publishDown', $nowDate);
        }

        if (Multilanguage::isEnabled()) {
            $query->whereIn(
                $db->quoteName('a.language'),
                [Factory::getApplication()->getLanguage()->getTag(), '*'],
                ParameterType::STRING
            );
        }

        return $query;
    }

    private function hydrate(array $rows): array
    {
        $products = [];

        foreach ($rows as $row) {
            $product = ProductHelper::getFullProduct((int) $row->j2commerce_product_id, false, false);

            if ($product) {
                $product->article_ordering = $row->ordering ?? 0;
                $product->article_hits     = $row->hits ?? 0;
                $product->article_featured = $row->featured ?? 0;

                $products[] = $product;
            }
        }

        return $products;
    }
}
