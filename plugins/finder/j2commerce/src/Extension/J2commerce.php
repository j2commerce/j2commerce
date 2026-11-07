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
use J2Commerce\Component\J2commerce\Administrator\Service\ProductService;
use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Finder as FinderEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Content\Site\Helper\RouteHelper as ContentRouteHelper;
use Joomla\Component\Finder\Administrator\Indexer\Adapter;
use Joomla\Component\Finder\Administrator\Indexer\Helper;
use Joomla\Component\Finder\Administrator\Indexer\Indexer;
use Joomla\Component\Finder\Administrator\Indexer\Result;
use Joomla\Component\Finder\Administrator\Indexer\Taxonomy;
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
        // Product articles live in com_content categories.
        if (\in_array($event->getExtension(), ['com_content', 'com_j2commerce'], true)) {
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

            $query = $db->getQuery(true)
                ->select($db->quoteName(['a.id', 'a.state', 'p.j2commerce_product_id', 'p.enabled']))
                ->from($db->quoteName('#__content', 'a'))
                ->join(
                    'INNER',
                    $db->quoteName('#__j2commerce_products', 'p') . ' ON ' .
                    $db->quoteName('p.product_source') . ' = ' . $db->quote('com_content') .
                    ' AND ' . $db->quoteName('p.product_source_id') . ' = ' . $db->quoteName('a.id')
                )
                ->where($db->quoteName('a.catid') . ' = :pk')
                ->bind(':pk', $pk, ParameterType::INTEGER);

            // A category state change moves only the link state, as in core; a disabled product keeps no entry.
            foreach ($db->setQuery($query)->loadObjectList() as $row) {
                if ((int) $row->enabled === 1) {
                    $this->change((int) $row->id, 'state', $this->translateState((int) $row->state, (int) $value), (int) $row->j2commerce_product_id);
                } else {
                    $this->remove((int) $row->id, true, (int) $row->j2commerce_product_id);
                }
            }
        }
    }

    // Item ids in this adapter are article ids (#__content.id), matching getItem() and the core state queries.
    protected function change($id, $property, $value, ?int $productId = null): bool
    {
        if ($property !== 'state' && $property !== 'access') {
            return true;
        }

        $linkIds = array_keys($this->getLinks((int) $id, $productId));

        if ($linkIds === []) {
            return true;
        }

        $db    = $this->getDatabase();
        $value = (int) $value;
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__finder_links'))
            ->set($db->quoteName($property) . ' = :value')
            ->whereIn($db->quoteName('link_id'), $linkIds)
            ->bind(':value', $value, ParameterType::INTEGER);

        $db->setQuery($query)->execute();

        return true;
    }

    protected function remove($id, $removeTaxonomies = true, ?int $productId = null)
    {
        $linkIds = array_keys($this->getLinks((int) $id, $productId));

        if ($linkIds === []) {
            // Same notification core Adapter::remove() sends when no link exists.
            Factory::getApplication()->triggerEvent('onFinderIndexAfterDelete', [$id]);
        }

        foreach ($linkIds as $linkId) {
            $this->indexer->remove($linkId, $removeTaxonomies);
        }

        return true;
    }

    /** Indexes an enabled product, drops the entry of a disabled or missing one, and keeps its article out. */
    protected function reindex($id, ?int $productId = null)
    {
        $articleId = (int) $id;
        $productId ??= $this->getProductIdForArticle($articleId);

        $this->setup();

        $item = $this->getItem($articleId);

        if (empty($item->j2commerce_product_id)) {
            $this->remove($articleId, true, $productId);
        } else {
            $this->index($item);

            // index() upserts by url, so an alias or category change leaves the previous url behind.
            foreach ($this->getLinks($articleId, $productId) as $linkId => $url) {
                if ($url !== $item->url) {
                    $this->indexer->remove($linkId, false);
                }
            }

            Taxonomy::removeOrphanNodes();
        }

        if ($productId > 0 && $this->params->get('exclude_linked_articles', 1)) {
            $this->removeArticleFromIndex($articleId);
        }
    }

    public function onStartIndex()
    {
        parent::onStartIndex();

        // A full run only adds and updates; entries of products that left the catalog are dropped here.
        $this->removeOrphanedLinks(false);
    }

    public function onFinderGarbageCollection()
    {
        return $this->removeOrphanedLinks(true);
    }

    /** @return array<int, string>  link_id => url of this adapter's entries for the article or its product */
    protected function getLinks(int $articleId, ?int $productId = null): array
    {
        $typeId = (int) $this->type_id;

        if ($typeId < 1) {
            return [];
        }

        $productId ??= $this->getProductIdForArticle($articleId);

        $db         = $this->getDatabase();
        $articleUrl = 'index.php?option=com_content&view=article&id=' . $articleId;
        $query      = $db->getQuery(true)
            ->select($db->quoteName(['link_id', 'url']))
            ->from($db->quoteName('#__finder_links'))
            ->where($db->quoteName('type_id') . ' = :typeId')
            ->bind(':typeId', $typeId, ParameterType::INTEGER);

        $match = [$db->quoteName('url') . ' = :articleUrl'];
        $query->bind(':articleUrl', $articleUrl);

        if ($productId > 0) {
            // Stored urls carry the alias and catid (id=78:alias&catid=2), so match the id as a prefix.
            $productUrl      = 'index.php?option=com_j2commerce&view=product&id=' . $productId;
            // The prefix is matched literally: `_` and `%` in it are LIKE wildcards otherwise.
            $likePrefix      = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $productUrl);
            $productUrlAlias = $likePrefix . ':%';
            $productUrlQuery = $likePrefix . '&%';

            $match[] = $db->quoteName('url') . ' = :productUrl';
            $match[] = $db->quoteName('url') . ' LIKE :productUrlAlias';
            $match[] = $db->quoteName('url') . ' LIKE :productUrlQuery';
            $query->bind(':productUrl', $productUrl)
                ->bind(':productUrlAlias', $productUrlAlias)
                ->bind(':productUrlQuery', $productUrlQuery);
        }

        $query->extendWhere('AND', $match, 'OR');

        return array_map('strval', $db->setQuery($query)->loadAssocList('link_id', 'url'));
    }

    protected function getProductIdForArticle(int $articleId): int
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_product_id'))
            ->from($db->quoteName('#__j2commerce_products'))
            ->where($db->quoteName('product_source') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('product_source_id') . ' = :articleId')
            ->bind(':articleId', $articleId, ParameterType::INTEGER)
            ->setLimit(1);

        return (int) $db->setQuery($query)->loadResult();
    }

    /** @return array{id: int, enabled: bool}|null */
    protected function getProductState(int $articleId): ?array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_product_id', 'enabled']))
            ->from($db->quoteName('#__j2commerce_products'))
            ->where($db->quoteName('product_source') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('product_source_id') . ' = :articleId')
            ->bind(':articleId', $articleId, ParameterType::INTEGER)
            ->setLimit(1);

        $row = $db->setQuery($query)->loadObject();

        return $row ? ['id' => (int) $row->j2commerce_product_id, 'enabled' => (int) $row->enabled === 1] : null;
    }

    protected function getArticleIdForProduct(object $row): int
    {
        if (isset($row->product_source_id) && ($row->product_source ?? 'com_content') === 'com_content') {
            return (int) $row->product_source_id;
        }

        return $this->getArticleIdForProductId((int) ($row->j2commerce_product_id ?? 0));
    }

    protected function getArticleIdForProductId(int $productId): int
    {
        if ($productId < 1) {
            return 0;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('product_source_id'))
            ->from($db->quoteName('#__j2commerce_products'))
            ->where($db->quoteName('j2commerce_product_id') . ' = :productId')
            ->where($db->quoteName('product_source') . ' = ' . $db->quote('com_content'))
            ->bind(':productId', $productId, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult();
    }

    /** Removes this adapter's entries whose product is disabled or gone. */
    protected function removeOrphanedLinks(bool $removeTaxonomies): int
    {
        $db     = $this->getDatabase();
        $typeId = (int) $this->type_id;

        if ($typeId < 1) {
            return 0;
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_product_id', 'product_source_id']))
            ->from($db->quoteName('#__j2commerce_products'))
            ->where($db->quoteName('product_source') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('enabled') . ' = 1');

        $enabled = $db->setQuery($query)->loadAssocList('j2commerce_product_id', 'product_source_id');
        $sources = array_flip(array_map('intval', $enabled));

        $query = $db->getQuery(true)
            ->select($db->quoteName(['link_id', 'url']))
            ->from($db->quoteName('#__finder_links'))
            ->where($db->quoteName('type_id') . ' = :typeId')
            ->bind(':typeId', $typeId, ParameterType::INTEGER);

        $removed = 0;

        foreach ($db->setQuery($query)->loadObjectList() as $link) {
            if (preg_match('/option=com_j2commerce&view=product&id=(\d+)/', $link->url, $m)) {
                $valid = isset($enabled[(int) $m[1]]);
            } elseif (preg_match('/option=com_content&view=article&id=(\d+)/', $link->url, $m)) {
                $valid = isset($sources[(int) $m[1]]);
            } else {
                $valid = false;
            }

            if (!$valid) {
                $this->indexer->remove((int) $link->link_id, false);
                $removed++;
            }
        }

        if ($removeTaxonomies && $removed > 0) {
            Taxonomy::removeOrphanNodes();
        }

        return $removed;
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

        // com_finder.index deletes the link itself; its link_id is not an item id.
        if ($context === 'com_j2commerce.product') {
            // The product row is gone by now, so the subject carries its ids.
            $this->remove($this->getArticleIdForProduct($table), true, (int) ($table->j2commerce_product_id ?? 0));
        } elseif ($context === 'com_content.article') {
            $this->remove((int) ($table->id ?? 0));
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

        // Indexing truncates the token tables, which commits an open transaction; ProductService replays the event after commit.
        if ($context === 'com_j2commerce.product' && ProductService::deferSearchEvent($event)) {
            return;
        }

        // reindex() rewrites state and access from the article, so no separate access sync is needed.
        $articleId = match ($context) {
            'com_j2commerce.product'                                            => $this->getArticleIdForProduct($row),
            'com_j2commerce.article', 'com_content.article', 'com_content.form' => (int) ($row->id ?? 0),
            default                                                             => 0,
        };
        $productId = $articleId > 0 ? $this->getProductIdForArticle($articleId) : 0;

        if ($productId > 0) {
            $this->reindex($articleId, $productId);
        }

        // Check for access changes in the category
        if ($context === 'com_categories.category') {
            if (!$isNew && $this->old_cataccess != $row->access) {
                $this->categoryAccessChange($row);
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

        if (\in_array($context, ['com_j2commerce.article', 'com_j2commerce.product', 'com_content.article'], true)) {
            $stateOnly = [];

            foreach ($pks as $pk) {
                $articleId = $context === 'com_j2commerce.product' ? $this->getArticleIdForProductId((int) $pk) : (int) $pk;
                $product   = $articleId > 0 ? $this->getProductState($articleId) : null;

                if ($product === null) {
                    continue;
                }

                // An article state change on an enabled product with an entry only moves the link state, as in core.
                // A product context carries the enabled flag, not an article state, so it always reindexes.
                if ($context !== 'com_j2commerce.product' && $product['enabled'] && $this->getLinks($articleId, $product['id']) !== []) {
                    $stateOnly[] = $articleId;
                } else {
                    $this->reindex($articleId, $product['id']);
                }
            }

            if ($stateOnly !== []) {
                $this->itemStateChange($stateOnly, $value);
            }
        }

        // Handle when the plugin is disabled
        if ($context === 'com_plugins.plugin' && $value === 0) {
            $this->pluginDisable($pks);
        }
    }

    /**
     * Method to index a batch of content items.
     *
     * After each batch, removes any com_content article entries from
     * the finder index when they have an associated J2Commerce product.
     * This prevents duplicate search results (article AND product).
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.0
     */
    public function onBuildIndex()
    {
        // Purge BEFORE parent so it runs on every batch request, even after
        // J2Commerce items are fully indexed (parent returns early at offset==total).
        // This catches articles indexed by plg_finder_content in any prior batch.
        if ($this->params->get('exclude_linked_articles', 1)) {
            $this->purgeLinkedArticlesFromIndex();
        }

        return parent::onBuildIndex();
    }

    /**
     * Remove all com_content article entries from the finder index
     * when they have an associated J2Commerce product, enabled or not.
     *
     * Uses a direct DELETE rather than Indexer::remove() to avoid
     * Taxonomy::removeOrphanNodes() which rebuilds the nested set
     * and breaks other plugins still indexing in the same batch.
     * Orphaned terms and taxonomy maps are cleaned up by the
     * Indexer::optimize() step that runs after all batches complete.
     *
     * @return  void
     *
     * @since   6.1.8
     */
    protected function purgeLinkedArticlesFromIndex(): void
    {
        try {
            $db = $this->getDatabase();

            // Every product article stays out, the product enabled or not; only deleting the product releases it.
            $subQuery = $db->getQuery(true)
                ->select($db->quoteName('product_source_id'))
                ->from($db->quoteName('#__j2commerce_products'))
                ->where($db->quoteName('product_source') . ' = ' . $db->quote('com_content'));

            $db->setQuery($subQuery);
            $articleIds = $db->loadColumn();

            if (empty($articleIds)) {
                return;
            }

            // Build the content URLs that plg_finder_content would use
            $urls = [];

            foreach ($articleIds as $id) {
                $urls[] = 'index.php?option=com_content&view=article&id=' . (int) $id;
            }

            // Never this adapter's own rows: with redirect_to=article a product is stored under its article url.
            $typeId = (int) $this->type_id;
            $query  = $db->getQuery(true)
                ->delete($db->quoteName('#__finder_links'))
                ->whereIn($db->quoteName('url'), $urls, ParameterType::STRING)
                ->where($db->quoteName('type_id') . ' <> :typeId')
                ->bind(':typeId', $typeId, ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();
        } catch (\Throwable $e) {
            // Silently ignore — don't break the indexing process
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

        // Set product image for search results. Both fields hold the raw relative
        // path — matching every other core finder adapter — so a consumer resolves
        // the derivative it wants at render time via ImageHelper::getProductImage().
        // Storing an absolute URL here breaks under CLI, where Uri::root() resolves
        // to the ConsoleApplication placeholder host instead of the real site.
        if ($this->params->get('show_product_image', 1)) {
            $imagePath = ($item->main_image ?? '') ?: (($item->thumb_image ?? '') ?: ($item->tiny_image ?? ''));

            // Off by default: a placeholder in a search result says nothing about the product.
            if ($imagePath === '' && $this->params->get('use_placeholder_image', 0)) {
                $imagePath = ImageHelper::getDefaultProductImage() ?: 'media/com_j2commerce/images/placeholder.svg';
            }

            if ($imagePath !== '' && ImageHelper::isValidImagePath($imagePath)) {
                $item->imagePath = $imagePath;
                $item->imageUrl  = $imagePath;
                $item->imageAlt  = ($item->main_image_alt ?? '') ?: $item->title;
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
     * @return  array{skus: array, upcs: array}
     *
     * @since   6.0.0
     */
    protected function getProductVariantData(int $productId): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName(['j2commerce_variant_id', 'sku', 'upc']))
            ->from($db->quoteName('#__j2commerce_variants'))
            ->where($db->quoteName('product_id') . ' = :productId')
            ->bind(':productId', $productId, ParameterType::INTEGER)
            ->order($db->quoteName('j2commerce_variant_id') . ' ASC');

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
        $app = $this->getApplication();

        // ConsoleApplication has no menu; SiteRouter::__construct() calls
        // $this->app->getMenu(), which only CMSApplication/ApiApplication define,
        // so CLI indexing (finder:index) cannot build a routed path.
        // DELETE WHEN: core provides a CLI-safe menu accessor.
        if (!method_exists($app, 'getMenu')) {
            return $url;
        }

        try {
            $router = SiteRouter::getInstance('site');
            $uri    = $router->build($url);
            $route  = $uri->toString(['path', 'query', 'fragment']);
            $route  = str_replace(Uri::base(true) . '/', '', $route);

            return $route;
        } catch (\Throwable) {
            // One item that cannot be routed keeps its raw URL instead of stopping the whole index run.
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

        // ConsoleApplication has no menu; only CMSApplication/ApiApplication define
        // getMenu(), so CLI indexing (finder:index) cannot resolve an Itemid.
        // DELETE WHEN: core provides a CLI-safe menu accessor.
        if (!method_exists($app, 'getMenu')) {
            return null;
        }

        try {
            $menu = $app->getMenu('site');

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

        // p.* re-introduces product columns whose names collide with the content
        // article columns selected above (access, params, created_by, modified_by,
        // ordering). On a duplicate column name the later one wins in the result
        // row, so p.* would clobber the article values — critically a.access, since
        // #__j2commerce_products.access defaults to 0 (an invalid view level), which
        // drops every product from frontend Smart Search. Re-select the article
        // columns last so the content values win.
        $query->select([
            $db->quoteName('a.access', 'access'),
            $db->quoteName('a.attribs', 'params'),
            $db->quoteName('a.created_by', 'created_by'),
            $db->quoteName('a.modified_by', 'modified_by'),
            $db->quoteName('a.ordering', 'ordering'),
        ]);

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
        $typeId     = (int) $this->type_id;

        // Delete directly — orphaned terms/maps are cleaned up by optimize()
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__finder_links'))
            ->where($db->quoteName('url') . ' = :url')
            ->where($db->quoteName('type_id') . ' <> :typeId')
            ->bind(':url', $articleUrl)
            ->bind(':typeId', $typeId, ParameterType::INTEGER);

        $db->setQuery($query);
        $db->execute();
    }
}
