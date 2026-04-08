<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_finder_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\Finder\J2commerce\Extension;

use J2Commerce\Component\J2commerce\Administrator\Helper\ImageHelper;
use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Finder as FinderEvent;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Content\Site\Helper\RouteHelper as ContentRouteHelper;
use Joomla\Component\Finder\Administrator\Indexer\Adapter;
use Joomla\Component\Finder\Administrator\Indexer\Helper;
use Joomla\Component\Finder\Administrator\Indexer\Indexer;
use Joomla\Component\Finder\Administrator\Indexer\Result;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Smart Search adapter for com_j2commerce products.
 *
 * Indexes J2Commerce products linked to com_content articles for Smart Search.
 *
 * @since  6.0.0
 */
final class J2commerce extends Adapter implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * The plugin identifier.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $context = 'J2Commerce';

    /**
     * The extension name.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $extension = 'com_j2commerce';

    /**
     * The sublayout to use when rendering the results.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $layout = 'products';

    /**
     * The task for J2Commerce product view.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $task = 'view';

    /**
     * The type of content that the adapter indexes.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $type_title = 'J2Commerce Products';

    /**
     * The table name.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $table = '#__content';

    /**
     * Load the language file on instantiation.
     *
     * @var    boolean
     * @since  6.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   6.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return array_merge(parent::getSubscribedEvents(), [
            'onFinderCategoryChangeState' => 'onFinderCategoryChangeState',
            'onFinderChangeState'         => 'onFinderChangeState',
            'onFinderAfterDelete'         => 'onFinderAfterDelete',
            'onFinderBeforeSave'          => 'onFinderBeforeSave',
            'onFinderAfterSave'           => 'onFinderAfterSave',
            'onFinderIndexAfterIndex'     => 'onFinderIndexAfterIndex',
        ]);
    }

    /**
     * Method to setup the indexer to be run.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.0
     */
    protected function setup()
    {
        return true;
    }

    /**
     * Method to update the item link information when the item category is
     * changed. This is fired when the item category is published or unpublished
     * from the list view.
     *
     * @param   FinderEvent\AfterCategoryChangeStateEvent  $event  The event instance.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function onFinderCategoryChangeState(FinderEvent\AfterCategoryChangeStateEvent $event): void
    {
        // Handle com_j2commerce categories
        if ($event->getExtension() === 'com_j2commerce') {
            $this->categoryStateChange($event->getPks(), $event->getValue());
        }
    }

    /**
     * Method to update index data on category access level changes.
     *
     * @param   array    $pks    A list of primary key ids of the content that has changed state.
     * @param   integer  $value  The value of the state that the content has been changed to.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function categoryStateChange($pks, $value): void
    {
        $db = $this->getDatabase();

        foreach ($pks as $pk) {
            $pk = (int) $pk;

            $query = clone $this->getStateQuery();
            $query->where($db->quoteName('c.id') . ' = :pk')
                ->bind(':pk', $pk, ParameterType::INTEGER);

            $query->select($db->quoteName('p') . '.*');
            $query->join(
                'INNER',
                $db->quoteName('#__j2commerce_products', 'p') . ' ON ' .
                $db->quoteName('p.product_source') . ' = ' . $db->quote('com_content') .
                ' AND ' . $db->quoteName('p.product_source_id') . ' = ' . $db->quoteName('c.id') .
                ' AND ' . $db->quoteName('p.enabled') . ' = 1'
            );

            $db->setQuery($query);
            $items = $db->loadObjectList();

            foreach ($items as $item) {
                $temp = $this->translateState($item->state, $value);
                $this->change($item->j2commerce_product_id, 'state', $temp);
                $this->reindex($item->j2commerce_product_id);
            }
        }
    }

    /**
     * Method to change the value of a content item's property in the links
     * table. This is used to synchronize published and access states.
     *
     * @param   string   $id        The ID of the item to change.
     * @param   string   $property  The property that is being changed.
     * @param   integer  $value     The new value of that property.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.0
     * @throws  \Exception on database error.
     */
    protected function change($id, $property, $value): bool
    {
        if ($property !== 'state' && $property !== 'access') {
            return true;
        }

        $db = $this->getDatabase();

        // Use RouteHelper for consistent URL matching with indexed items
        // This ensures we match the same URL format stored during index()
        $url  = RouteHelper::getProductRoute((int) $id);
        $item = $db->quote($url);

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__finder_links'))
            ->set($db->quoteName($property) . ' = :value')
            ->where($db->quoteName('url') . ' = ' . $item)
            ->bind(':value', $value, ParameterType::INTEGER);

        $db->setQuery($query);
        $db->execute();

        return true;
    }

    /**
     * Method to remove the link information for items that have been deleted.
     *
     * @param   FinderEvent\AfterDeleteEvent  $event  The event instance.
     *
     * @return  void
     *
     * @since   6.0.0
     * @throws  \Exception on database error.
     */
    public function onFinderAfterDelete(FinderEvent\AfterDeleteEvent $event): void
    {
        $context = $event->getContext();
        $table   = $event->getItem();

        if ($context === 'com_j2commerce.product') {
            $id = $table->id ?? $table->j2commerce_product_id ?? null;
        } elseif ($context === 'com_finder.index') {
            $id = $table->link_id ?? null;
        } else {
            return;
        }

        if ($id !== null) {
            $this->remove($id);
        }
    }

    /**
     * Smart Search after save content method.
     * Reindexes the link information for an item that has been saved.
     *
     * Also handles excluding articles from the index when they have an
     * associated J2Commerce product (to prevent duplicate search results).
     *
     * @param   FinderEvent\AfterSaveEvent  $event  The event instance.
     *
     * @return  void
     *
     * @since   6.0.0
     * @throws  \Exception on database error.
     */
    public function onFinderAfterSave(FinderEvent\AfterSaveEvent $event): void
    {
        $context = $event->getContext();
        $row     = $event->getItem();
        $isNew   = $event->getIsNew();

        // Handle J2Commerce products and articles
        if ($context === 'com_j2commerce.article' || $context === 'com_j2commerce.product') {
            // Check if the access levels are different
            if (!$isNew && $this->old_access != $row->access) {
                $this->itemAccessChange($row);
            }

            $this->reindex($row->id ?? $row->j2commerce_product_id ?? 0);
        }

        // Check for access changes in the category
        if ($context === 'com_categories.category') {
            if (!$isNew && $this->old_cataccess != $row->access) {
                $this->categoryAccessChange($row);
            }
        }

        // Handle excluding com_content articles when they have a J2Commerce product
        // This prevents duplicate search results (article AND product)
        if ($context === 'com_content.article' || $context === 'com_content.form') {
            $articleId = (int) ($row->id ?? 0);

            if ($articleId > 0 && $this->params->get('exclude_linked_articles', 1)) {
                // Check if this article has an associated J2Commerce product
                if ($this->hasJ2CommerceProduct($articleId)) {
                    // Remove the article from the finder index
                    $this->removeArticleFromIndex($articleId);
                }
            }
        }
    }

    /**
     * Smart Search before content save method.
     * This event is fired before the data is actually saved.
     *
     * @param   FinderEvent\BeforeSaveEvent  $event  The event instance.
     *
     * @return  void
     *
     * @since   6.0.0
     * @throws  \Exception on database error.
     */
    public function onFinderBeforeSave(FinderEvent\BeforeSaveEvent $event): void
    {
        $context = $event->getContext();
        $row     = $event->getItem();
        $isNew   = $event->getIsNew();

        // Handle J2Commerce products and articles
        if ($context === 'com_j2commerce.article' || $context === 'com_j2commerce.product') {
            if (!$isNew) {
                $this->checkItemAccess($row);
            }
        }

        // Check for access levels from the category
        if ($context === 'com_categories.category') {
            if (!$isNew) {
                $this->checkCategoryAccess($row);
            }
        }
    }

    /**
     * Method to update the link information for items that have been changed
     * from outside the edit screen.
     *
     * @param   FinderEvent\AfterChangeStateEvent  $event  The event instance.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function onFinderChangeState(FinderEvent\AfterChangeStateEvent $event): void
    {
        $context = $event->getContext();
        $pks     = $event->getPks();
        $value   = $event->getValue();

        // Handle J2Commerce products and articles
        if ($context === 'com_j2commerce.article' || $context === 'com_j2commerce.product') {
            $this->itemStateChange($pks, $value);
        }

        // Handle when the plugin is disabled
        if ($context === 'com_plugins.plugin' && $value === 0) {
            $this->pluginDisable($pks);
        }
    }

    /**
     * Method called after an item has been indexed by Smart Search.
     *
     * This is triggered for ALL items indexed, not just J2Commerce items.
     * We use this to detect when plg_finder_content indexes an article
     * that has an associated J2Commerce product, and remove the duplicate
     * article entry from the index.
     *
     * @param   \Joomla\Event\Event  $event  The event object containing item and linkId.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function onFinderIndexAfterIndex(\Joomla\Event\Event $event): void
    {
        // Only process if exclusion is enabled
        if (!$this->params->get('exclude_linked_articles', 1)) {
            return;
        }

        // Extract arguments from the event
        // Event is triggered with: triggerEvent('onFinderIndexAfterIndex', [$item, $linkId])
        $arguments = $event->getArguments();
        $item      = $arguments[0] ?? null;
        $linkId    = (int) ($arguments[1] ?? 0);

        if (!$item instanceof Result) {
            return;
        }

        // Check if this is a com_content article (indexed by plg_finder_content)
        // The context is set by plg_finder_content when indexing articles
        if (!isset($item->context) || $item->context !== 'com_content.article') {
            return;
        }

        // Get the article ID from the item
        $articleId = (int) ($item->id ?? 0);

        if ($articleId <= 0) {
            return;
        }

        // Check if this article has an associated J2Commerce product
        if ($this->hasJ2CommerceProduct($articleId)) {
            // Remove link data without triggering Taxonomy::removeOrphanNodes().
            // Indexer::remove() rebuilds the nested set (lft/rgt) which deletes/
            // renumbers taxonomy nodes while other plugins are still indexing,
            // causing MapTable::_getNode() failures. Orphan taxonomy nodes are
            // harmless and get cleaned up on the next full purge/reindex.
            $this->removeFinderLink($linkId);
        }
    }

    /**
     * Method to index an item. The item must be a Result object.
     *
     * @param   Result  $item  The item to index as a Result object.
     *
     * @return  void
     *
     * @since   6.0.0
     * @throws  \Exception on database error.
     */
    protected function index(Result $item)
    {
        $item->setLanguage();

        // Check if the extension is enabled
        if (ComponentHelper::isEnabled($this->extension) === false) {
            return;
        }

        // Initialize the item parameters
        $registry     = new Registry($item->params);
        $item->params = clone ComponentHelper::getParams('com_j2commerce', true);
        $item->params->merge($registry);

        $item->metadata = new Registry($item->metadata);

        // Trigger the onContentPrepare event
        $item->summary = Helper::prepareContent($item->summary, $item->params, $item);
        $item->body    = Helper::prepareContent($item->body, $item->params, $item);

        // Determine URL based on redirect setting
        $redirectTo = $this->params->get('redirect_to', 'j2commerce');

        if ($redirectTo === 'article') {
            // Redirect to the article view
            $item->url   = $this->getUrl($item->id, 'com_content', 'article');
            $item->route = ContentRouteHelper::getArticleRoute($item->slug, $item->catid, $item->language);
        } else {
            // Redirect to J2Commerce product view using RouteHelper
            // This generates canonical URLs similar to Joomla article routing
            $productId       = (int) ($item->j2commerce_product_id ?? 0);
            $productAlias    = $item->alias ?? null;
            $productCatid    = isset($item->catid) ? (int) $item->catid : null;
            $productLanguage = ($item->language !== '*') ? $item->language : null;

            if ($productId > 0) {
                // Use RouteHelper for consistent URL generation with category context
                // Including catid enables canonical category-based URLs in modern router
                $item->route = RouteHelper::getProductRoute(
                    $productId,
                    $productAlias,
                    $productCatid,  // Category context for canonical URLs
                    $productLanguage
                );

                // Store URL for finder index (used for change detection)
                $item->url = $item->route;

                // Find appropriate menu item for SEF routing based on product category
                $menuId = $this->findProductMenu($productId);

                if ($menuId) {
                    $item->route .= '&Itemid=' . $menuId;
                }
            } else {
                // Fallback to article route if no product ID
                $item->url   = $this->getUrl($item->id, 'com_content', 'article');
                $item->route = ContentRouteHelper::getArticleRoute($item->slug, $item->catid, $item->language);
            }
        }

        // Build the path
        $item->path = $this->getContentPath($item->route);

        // Add the meta-author
        $item->metaauthor = $item->metadata->get('author');

        // Add the meta-data processing instructions
        $item->addInstruction(Indexer::META_CONTEXT, 'metakey');
        $item->addInstruction(Indexer::META_CONTEXT, 'metadesc');
        $item->addInstruction(Indexer::META_CONTEXT, 'metaauthor');
        $item->addInstruction(Indexer::META_CONTEXT, 'author');
        $item->addInstruction(Indexer::META_CONTEXT, 'created_by_alias');

        // Add SKU and UPC fields to the meta context for searching
        $productId = (int) ($item->j2commerce_product_id ?? 0);
        if ($productId > 0) {
            $variantData = $this->getProductVariantData($productId);

            if (!empty($variantData['skus'])) {
                $item->addInstruction(Indexer::META_CONTEXT, 'skus');
                $item->skus = $variantData['skus'];
            }

            if (!empty($variantData['upcs'])) {
                $item->addInstruction(Indexer::META_CONTEXT, 'upcs');
                $item->upcs = $variantData['upcs'];
            }
        }

        // Translate the state. Articles should only be published if the category is published.
        $item->state = $this->translateState($item->state, $item->cat_state);

        // Add the type taxonomy data
        $item->addTaxonomy('Type', $this->type_title);

        // Add the author taxonomy data
        if (!empty($item->author) || !empty($item->created_by_alias)) {
            $item->addTaxonomy('Author', !empty($item->created_by_alias) ? $item->created_by_alias : $item->author);
        }

        // Add the category taxonomy data
        $item->addTaxonomy('J2Commerce Category', $item->category, $item->cat_state, $item->cat_access);

        // Add the Brand taxonomy data if available
        if (!empty($item->brand)) {
            $item->addTaxonomy('J2Commerce Brand', $item->brand);
        }

        // Set product image for search results
        if ($this->params->get('show_product_image', 1)) {
            $imagePath = $item->tiny_image ?? $item->thumb_image ?? $item->main_image ?? null;

            if ($imagePath && ImageHelper::isValidImagePath($imagePath)) {
                $item->imageUrl = ImageHelper::getProductImage($imagePath, 80, 'raw');
                $item->imageAlt = $item->main_image_alt ?? $item->title;
            }
        }

        // Get content extras (wrapped in try-catch due to Joomla 6 core bug
        // where some finder plugins have outdated service providers that fail in debug mode)
        try {
            Helper::getContentExtras($item);
        } catch (\TypeError $e) {
            // Silently ignore - core finder plugins may fail in debug mode
        }

        // Index the item
        $this->indexer->index($item);
    }

    /**
     * Get SKU and UPC data for a product from the variants table.
     *
     * @param   int  $productId  The J2Commerce product ID.
     *
     * @return  array{skus: array, upcs: array}  Arrays of SKUs and UPCs for the product.
     *
     * @since   6.0.0
     */
    protected function getProductVariantData(int $productId): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName(['sku', 'upc']))
            ->from($db->quoteName('#__j2commerce_variants'))
            ->where($db->quoteName('product_id') . ' = :productId')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);
        $variants = $db->loadObjectList();

        $skus = [];
        $upcs = [];

        foreach ($variants as $variant) {
            if (!empty($variant->sku)) {
                $skus[] = $variant->sku;
            }
            if (!empty($variant->upc)) {
                $upcs[] = $variant->upc;
            }
        }

        return [
            'skus' => $skus,
            'upcs' => $upcs,
        ];
    }

    /**
     * Get the content path for URL building.
     *
     * @param   string  $url  The URL to process.
     *
     * @return  string  The processed path.
     *
     * @since   6.0.0
     */
    protected function getContentPath(string $url): string
    {
        try {
            $router = SiteRouter::getInstance('site');
            $uri    = $router->build($url);
            $route  = $uri->toString(['path', 'query', 'fragment']);
            $route  = str_replace(Uri::base(true) . '/', '', $route);

            return $route;
        } catch (\Exception $e) {
            return $url;
        }
    }

    /**
     * Find a menu item for the product.
     *
     * @param   int  $productId  The product ID.
     *
     * @return  int|null  The menu item ID or null if not found.
     *
     * @since   6.0.0
     */
    protected function findProductMenu(int $productId): ?int
    {
        $app = $this->getApplication();

        try {
            $menu = $app->getMenu('site');

            // Menu may be null in CLI context or during indexing
            if ($menu === null) {
                return null;
            }

            foreach ($menu->getMenu() as $item) {
                $query = $item->query ?? [];

                if (($query['option'] ?? '') === 'com_j2commerce'
                    && \in_array($query['view'] ?? '', ['products', 'product'], true)
                ) {
                    // Check if this menu item has a category that matches the product
                    $catid = $query['catid'] ?? null;

                    if ($catid) {
                        $productCatId = $this->getProductCategoryId($productId);

                        if ($productCatId && \in_array($productCatId, (array) $catid, true)) {
                            return (int) $item->id;
                        }
                    } else {
                        // General products menu without category filter
                        return (int) $item->id;
                    }
                }
            }
        } catch (\Exception $e) {
            // Menu not available in CLI context
        }

        return null;
    }

    /**
     * Get the category ID for a product.
     *
     * @param   int  $productId  The product ID.
     *
     * @return  int|null  The category ID or null if not found.
     *
     * @since   6.0.0
     */
    protected function getProductCategoryId(int $productId): ?int
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select($db->quoteName('c.catid'))
            ->from($db->quoteName('#__j2commerce_products', 'p'))
            ->join(
                'LEFT',
                $db->quoteName('#__content', 'c') . ' ON ' .
                $db->quoteName('c.id') . ' = ' . $db->quoteName('p.product_source_id')
            )
            ->where($db->quoteName('p.j2commerce_product_id') . ' = :productId')
            ->where($db->quoteName('p.product_source') . ' = ' . $db->quote('com_content'))
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);
        $result = $db->loadResult();

        return $result ? (int) $result : null;
    }

    /**
     * Method to get the SQL query used to retrieve the list of content items.
     *
     * @param   mixed  $query  A DatabaseQuery object or null.
     *
     * @return  QueryInterface  A database object.
     *
     * @since   6.0.0
     */
    protected function getListQuery($query = null)
    {
        $db = $this->getDatabase();

        // Build the query
        $query = $query instanceof QueryInterface ? $query : $db->getQuery(true)
            ->select([
                $db->quoteName('a.id'),
                $db->quoteName('a.title'),
                $db->quoteName('a.alias'),
                $db->quoteName('a.introtext', 'summary'),
                $db->quoteName('a.fulltext', 'body'),
                $db->quoteName('a.state'),
                $db->quoteName('a.catid'),
                $db->quoteName('a.created', 'start_date'),
                $db->quoteName('a.created_by'),
                $db->quoteName('a.created_by_alias'),
                $db->quoteName('a.modified'),
                $db->quoteName('a.modified_by'),
                $db->quoteName('a.attribs', 'params'),
                $db->quoteName('a.metakey'),
                $db->quoteName('a.metadesc'),
                $db->quoteName('a.metadata'),
                $db->quoteName('a.language'),
                $db->quoteName('a.access'),
                $db->quoteName('a.version'),
                $db->quoteName('a.ordering'),
                $db->quoteName('a.publish_up', 'publish_start_date'),
                $db->quoteName('a.publish_down', 'publish_end_date'),
                $db->quoteName('c.title', 'category'),
                $db->quoteName('c.published', 'cat_state'),
                $db->quoteName('c.access', 'cat_access'),
            ]);

        // Add product fields
        $query->select($db->quoteName('p') . '.*');

        // Handle the alias CASE WHEN portion of the query
        $a_id                 = $query->castAs('CHAR', 'a.id');
        $case_when_item_alias = ' CASE WHEN ' . $query->charLength('a.alias', '!=', '0');
        $case_when_item_alias .= ' THEN ' . $query->concatenate([$a_id, 'a.alias'], ':');
        $case_when_item_alias .= ' ELSE ' . $a_id . ' END as slug';
        $query->select($case_when_item_alias);

        $c_id                     = $query->castAs('CHAR', 'c.id');
        $case_when_category_alias = ' CASE WHEN ' . $query->charLength('c.alias', '!=', '0');
        $case_when_category_alias .= ' THEN ' . $query->concatenate([$c_id, 'c.alias'], ':');
        $case_when_category_alias .= ' ELSE ' . $c_id . ' END as catslug';
        $query->select($case_when_category_alias);

        // Author name
        $query->select($db->quoteName('u.name', 'author'));

        // Brand name from manufacturer
        $query->select($db->quoteName('addr.company', 'brand'));

        // From tables
        $query->from($db->quoteName('#__content', 'a'));

        // Join with J2Commerce products (only enabled products with com_content source)
        $query->join(
            'INNER',
            $db->quoteName('#__j2commerce_products', 'p') . ' ON ' .
            $db->quoteName('p.product_source') . ' = ' . $db->quote('com_content') .
            ' AND ' . $db->quoteName('p.product_source_id') . ' = ' . $db->quoteName('a.id') .
            ' AND ' . $db->quoteName('p.enabled') . ' = 1'
        );

        // Join with manufacturers
        $query->join(
            'LEFT',
            $db->quoteName('#__j2commerce_manufacturers', 'm') . ' ON ' .
            $db->quoteName('m.j2commerce_manufacturer_id') . ' = ' . $db->quoteName('p.manufacturer_id')
        );

        // Join with addresses to get brand/company name
        $query->join(
            'LEFT',
            $db->quoteName('#__j2commerce_addresses', 'addr') . ' ON ' .
            $db->quoteName('addr.j2commerce_address_id') . ' = ' . $db->quoteName('m.address_id')
        );

        // Join with categories
        $query->join(
            'LEFT',
            $db->quoteName('#__categories', 'c') . ' ON ' .
            $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid')
        );

        // Join with users
        $query->join(
            'LEFT',
            $db->quoteName('#__users', 'u') . ' ON ' .
            $db->quoteName('u.id') . ' = ' . $db->quoteName('a.created_by')
        );

        // Join with product images
        $query->select([
            $db->quoteName('img.main_image'),
            $db->quoteName('img.thumb_image'),
            $db->quoteName('img.tiny_image'),
            $db->quoteName('img.main_image_alt'),
        ]);
        $query->join(
            'LEFT',
            $db->quoteName('#__j2commerce_productimages', 'img') . ' ON ' .
            $db->quoteName('img.product_id') . ' = ' . $db->quoteName('p.j2commerce_product_id')
        );

        return $query;
    }

    /**
     * Get the J2Commerce product URL using RouteHelper.
     *
     * This generates canonical product URLs similar to Joomla's article routing,
     * with proper product ID, optional alias, and category context for SEF URLs.
     *
     * @param   int          $id     The product ID (j2commerce_product_id).
     * @param   string|null  $alias  The product alias (optional, from content article).
     * @param   int|null     $catid  The category ID (optional, for canonical category-based URLs).
     *
     * @return  string  The canonical product URL.
     *
     * @since   6.0.0
     */
    protected function getJ2CommerceProductUrl(int $id, ?string $alias = null, ?int $catid = null): string
    {
        return RouteHelper::getProductRoute($id, $alias, $catid);
    }

    /**
     * Get the URL for an item.
     *
     * For J2Commerce products, uses RouteHelper for canonical URL generation.
     * For other extensions (like com_content), returns standard format.
     *
     * @param   int          $id         The item ID.
     * @param   string       $extension  The extension name.
     * @param   string       $view       The view name.
     * @param   string|null  $alias      The item alias (optional).
     * @param   int|null     $catid      The category ID (optional).
     *
     * @return  string  The URL.
     *
     * @since   6.0.0
     */
    protected function getUrl($id, $extension, $view, ?string $alias = null, ?int $catid = null)
    {
        // For J2Commerce products, use RouteHelper for canonical URLs with category context
        if ($extension === 'com_j2commerce' && $view === 'product') {
            return RouteHelper::getProductRoute((int) $id, $alias, $catid);
        }

        // Standard format for other extensions (com_content, etc.)
        return 'index.php?option=' . $extension . '&view=' . $view . '&id=' . $id;
    }

    /**
     * Remove an article from the Smart Search index.
     *
     * This is called when an article is saved and has an associated
     * J2Commerce product, to prevent duplicate search results.
     *
     * @param   int  $articleId  The article ID
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function removeArticleFromIndex(int $articleId): void
    {
        $db = $this->getDatabase();

        // Build the URL that plg_finder_content would use for this article
        $articleUrl = 'index.php?option=com_content&view=article&id=' . $articleId;

        // Find link IDs for this article URL
        $query = $db->getQuery(true)
            ->select($db->quoteName('link_id'))
            ->from($db->quoteName('#__finder_links'))
            ->where($db->quoteName('url') . ' = :url')
            ->bind(':url', $articleUrl);

        $db->setQuery($query);
        $linkIds = $db->loadColumn();

        // Remove each link without taxonomy rebuild to avoid _getNode() failures
        foreach ($linkIds as $linkId) {
            $this->removeFinderLink((int) $linkId);
        }
    }

    /**
     * Remove a finder link without triggering taxonomy orphan cleanup.
     *
     * Performs the same data cleanup as Indexer::remove() but skips
     * Taxonomy::removeOrphanNodes() which rebuilds the nested set and
     * breaks other finder plugins still indexing in the same batch.
     *
     * @param   int  $linkId  The finder link ID to remove.
     *
     * @return  void
     *
     * @since   6.1.8
     */
    protected function removeFinderLink(int $linkId): void
    {
        $db = $this->getDatabase();

        // Update term link counts
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__finder_terms', 't'))
            ->join(
                'INNER',
                $db->quoteName('#__finder_links_terms', 'm'),
                $db->quoteName('m.term_id') . ' = ' . $db->quoteName('t.term_id')
            )
            ->set($db->quoteName('t.links') . ' = ' . $db->quoteName('t.links') . ' - 1')
            ->where($db->quoteName('m.link_id') . ' = :linkId1')
            ->bind(':linkId1', $linkId, ParameterType::INTEGER);
        $db->setQuery($query);
        $db->execute();

        // Remove term mappings
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__finder_links_terms'))
            ->where($db->quoteName('link_id') . ' = :linkId2')
            ->bind(':linkId2', $linkId, ParameterType::INTEGER);
        $db->setQuery($query);
        $db->execute();

        // Delete orphaned terms
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__finder_terms'))
            ->where($db->quoteName('links') . ' <= 0');
        $db->setQuery($query);
        $db->execute();

        // Remove taxonomy mappings for this link
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__finder_taxonomy_map'))
            ->where($db->quoteName('link_id') . ' = :linkId3')
            ->bind(':linkId3', $linkId, ParameterType::INTEGER);
        $db->setQuery($query);
        $db->execute();

        // Delete the link itself
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__finder_links'))
            ->where($db->quoteName('link_id') . ' = :linkId4')
            ->bind(':linkId4', $linkId, ParameterType::INTEGER);
        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Check if an article has an associated J2Commerce product
     *
     * @param   int  $articleId  The article ID
     *
     * @return  bool
     *
     * @since   6.0.0
     */
    protected function hasJ2CommerceProduct(int $articleId): bool
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__j2commerce_products'))
            ->where($db->quoteName('product_source') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('product_source_id') . ' = :articleId')
            ->where($db->quoteName('enabled') . ' = 1')
            ->bind(':articleId', $articleId, ParameterType::INTEGER)
            ->setLimit(1);

        $db->setQuery($query);

        return (bool) $db->loadResult();
    }
}
