<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_content_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\Content\J2Commerce\Extension;

use J2Commerce\Component\J2commerce\Administrator\Helper\CategoryHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ConfigHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use J2Commerce\Component\J2commerce\Administrator\Service\ProductService;
use J2Commerce\Component\J2commerce\Site\Helper\ProductVisibilityHelper;
use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Content\AfterDisplayEvent;
use Joomla\CMS\Event\Content\BeforeDisplayEvent;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Event\Model\AfterDeleteEvent;
use Joomla\CMS\Event\Model\AfterSaveEvent;
use Joomla\CMS\Event\Model\BeforeSaveEvent;
use Joomla\CMS\Event\Model\PrepareFormEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * J2Commerce Content Plugin - Integrates products with Joomla articles via shortcodes and forms.
 *
 * @since  6.0.0
 */
final class J2Commerce extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * Auto-load plugin language files.
     * Note: This loads from administrator/language/ which is where install copies files.
     *
     * @var bool
     */
    protected $autoloadLanguage = true;

    /**
     * Shortcode option → FileLayout ID mapping for card-style (list) rendering.
     *
     * The `detail` option is NOT in this map — it is special-cased to dispatch
     * the `onJ2CommerceViewProductHtml` event so the active subtemplate plugin
     * (app_bootstrap5 / app_uikit) renders it using its own `view_*.php`
     * templates, matching the real product detail page.
     */
    private const SHORTCODE_LAYOUT_MAP = [
        'full'           => 'list.category.item',
        'card'           => 'list.category.item',
        'title'          => 'list.category.item_title',
        'price'          => 'list.category.item_price',
        'saleprice'      => 'list.category.item_price',
        'regularprice'   => 'list.category.item_price',
        'sku'            => 'list.category.item_sku',
        'stock'          => 'list.category.item_stock',
        'description'    => 'list.category.item_description',
        'desc'           => 'list.category.item_description',
        'images'         => 'list.category.item_images',
        'gallery'        => 'list.category.item_images',
        'mainimage'      => 'list.category.item_images',
        'thumbnail'      => 'list.category.item_images',
        'mainadditional' => 'list.category.item_images',
        'cart'           => 'list.category.item_cart',
        'cartonly'       => 'list.category.item_cart',
        'options'        => 'list.category.item_options',
        'quickview'      => 'list.category.item_quickview',
    ];

    private bool $cacheCleared = false;

    private static array $articleCache = [];

    /**
     * Recursion guard for the prepare_content event dispatch.
     * Set to true while we are inside our own secondary onContentPrepare dispatch
     * so that J2Commerce's handler skips all processing for that inner call.
     *
     * @since  6.5.2
     */
    private static bool $preparingContent = false;

    /** Per-productId counter so shortcode-rendered cart forms get unique ids when a product appears more than once on a page. */
    private static array $cartFormInstanceCounts = [];

    /**
     * Clear the static article cache when an article is saved or deleted.
     *
     * @param   int  $articleId  The article ID to clear from cache.
     *
     * @return  void
     *
     * @since   6.1.0
     */
    private function clearArticleCache(int $articleId): void
    {
        unset(self::$articleCache[$articleId]);
    }

    /** @since 6.0.0 */
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare'       => 'onContentPrepare',
            'onContentPrepareForm'   => 'onContentPrepareForm',
            'onContentBeforeSave'    => 'onContentBeforeSave',
            'onContentAfterSave'     => 'onContentAfterSave',
            'onContentAfterDelete'   => 'onContentAfterDelete',
            'onContentChangeState'   => 'onContentChangeState',
            'onContentBeforeDisplay' => 'onContentBeforeDisplay',
            'onContentAfterDisplay'  => 'onContentAfterDisplay',
            'onContentAfterFieldset' => 'onContentAfterFieldset',
        ];
    }

    /** @since 6.0.0 */
    public function onContentPrepare(ContentPrepareEvent $event): void
    {
        $context = $event->getContext();
        $article = $event->getItem();
        $params  = $event->getParams();

        // Guard against the recursive onContentPrepare event dispatched by our own
        // prepare_content logic at the end of this method.
        if (self::$preparingContent) {
            return;
        }

        // The indexer runs under ConsoleApplication, which has no getDocument()/
        // getWebAssetManager() — the render path below is unsafe here regardless
        // of the strip setting, so always return before reaching it.
        if ($context === 'com_finder.indexer') {
            if ((bool) $this->params->get('shortcode_strip_in_finder', 1) && isset($article->text)) {
                $article->text = preg_replace('/{j2commerce}.*?{\/j2commerce}/s', '', $article->text);
                $article->text = preg_replace('/{j2commerce\s+[^}]*}/s', '', $article->text);
            }

            return;
        }

        // Skip backend and API processing — Route::_() crashes in API context
        if ($this->getApplication()->isClient('administrator') || $this->getApplication()->isClient('api')) {
            return;
        }

        // Skip category list views
        if (strpos($context, 'categories') !== false) {
            return;
        }

        // Called from ProductHelper::prepareProductDescriptions() to run content
        // plugins on product_short_desc / product_long_desc.  Skip all J2Commerce
        // rendering (product blocks, shortcodes, inner dispatch) so the descriptions
        // only get field tags / module tags resolved, never a duplicate product block.
        //
        // Two complementary guards:
        //   1. isPreparingDescriptions() — a definitive static flag in ProductHelper;
        //      reliable regardless of how the event subject is stored/returned.
        //   2. j2commerce_desc_context on the fakeArticle — belt-and-suspenders
        //      fallback for any code path that reaches here without the flag set.
        if (ProductHelper::isPreparingDescriptions() || !empty($article->j2commerce_desc_context)) {
            if (!(bool) $this->params->get('prepare_content', 1)) {
                $this->stripContentPluginTags($article);
            }

            return;
        }

        // When prepare_content is off, strip standard Joomla content-plugin tags
        // (loadposition / loadmodule / loadmoduleid) while preserving any patterns
        // listed in the preserve_tags parameter.
        if (!(bool) $this->params->get('prepare_content', 1)) {
            $this->stripContentPluginTags($article);
        }

        if (strpos($context, 'productlist') !== false) {
            // Product list: strip J2Commerce shortcodes from article descriptions,
            // then fall through to the prepare_content dispatch below so that
            // content-plugin tags in introtext / fulltext are rendered or stripped.
            $this->stripShortcodes($article);
        } else {
            // Clear caches if enabled
            if ($this->params->get('cache_control', 1) && !$this->cacheCleared) {
                $this->clearContentCaches();
            }

            // Get J2Commerce component params for placement setting
            $j2params  = ComponentHelper::getParams('com_j2commerce');
            $placement = $j2params->get('addtocart_placement', 'default');

            // Handle default position placement
            if (strpos($context, 'com_content') !== false) {
                if (\in_array($placement, ['default', 'both'], true)) {
                    if ($this->checkPublishDate($article)) {
                        $this->renderDefaultPosition($context, $article, $params);
                    }
                }
            }

            // Handle tag-based placement
            if (\in_array($placement, ['tag', 'both'], true)) {
                $this->processWithinArticle($article);
            }

            // Always process J2Commerce shortcodes
            $this->processShortcodes($context, $article, $params);
        }

        // Re-dispatch onContentPrepare with the real article so content plugins can
        // render remaining tags ({field 4}, {loadmoduleid 120}, etc.).
        //
        // Fires for all contexts — including productlist — when:
        //   - prepare_content is on  → render all plugin tags in the text, OR
        //   - preserve_tags is set   → stripContentPluginTags already removed the
        //     unwanted tags; we still need to render the ones that were preserved.
        //
        // Using the real article (not a fake stdClass) ensures context-sensitive
        // plugins like plg_content_fields can resolve field values by article ID.
        // The static flag above prevents recursion.
        $hasPreserveTags = trim((string) $this->params->get('preserve_tags', '')) !== '';

        if ((bool) $this->params->get('prepare_content', 1) || $hasPreserveTags) {
            self::$preparingContent = true;
            PluginHelper::importPlugin('content');
            $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);

            try {
                // Dispatch for each text property that carries content so that
                // tags in introtext and fulltext are rendered even in list views
                // where those properties are accessed directly by templates.
                foreach (['text', 'introtext', 'fulltext'] as $prop) {
                    if (!isset($article->$prop) || $article->$prop === '') {
                        continue;
                    }

                    // When the property is text, dispatch normally.
                    // For introtext / fulltext: swap into text, dispatch, swap back
                    // so that content plugins (which only read $article->text) process it.
                    if ($prop !== 'text') {
                        if (!isset($article->text) || $article->$prop === $article->text) {
                            continue;
                        }

                        $savedText     = $article->text ?? '';
                        $article->text = $article->$prop;
                    }

                    $dispatcher->dispatch(
                        'onContentPrepare',
                        new ContentPrepareEvent('onContentPrepare', [
                            'context' => $context,
                            'subject' => $article,
                            'params'  => $params,
                            'page'    => 0,
                        ])
                    );

                    if ($prop !== 'text') {
                        $article->$prop = $article->text;
                        $article->text  = $savedText;
                    }
                }
            } finally {
                self::$preparingContent = false;
            }
        }
    }

    /** @since 6.0.0 */
    public function onContentPrepareForm(PrepareFormEvent $event): void
    {
        $form = $event->getForm();
        $data = $event->getData();

        // Check frontend editing permission
        if ($this->getApplication()->isClient('site')
            && $this->params->get('allow_frontend_product_edit', 0) == 0) {
            return;
        }

        // Verify form is a Joomla Form object
        if (!($form instanceof Form)) {
            return;
        }

        $formName  = $form->getName();
        $option    = $this->getApplication()->getInput()->get('option');
        $extension = $this->getApplication()->getInput()->get('extension');

        // Handle article forms (T1: include frontend form name 'com_content.form')
        if (\in_array($formName, ['com_content.article', 'com_content.form'], true)) {
            // T10: category restriction gate (empty list = allow all)
            $restrictTo = $this->params->get('restrict_to_categories', '');
            if (!empty($restrictTo)) {
                $allowed = array_filter(array_map('intval', (array) $restrictTo));
                if (!empty($allowed)) {
                    $catid = (int) ($data->catid ?? 0);
                    if ($catid === 0) {
                        $catid = $this->getApplication()->getInput()->getInt('catid', 0);
                    }
                    if ($catid > 0 && !\in_array($catid, $allowed, true)) {
                        return;
                    }
                }
            }

            // T4: ACL gate for site client
            if ($this->getApplication()->isClient('site')) {
                $articleId = (int) ($data->id ?? 0);

                if (!$this->canEditFrontend($articleId)) {
                    return;
                }
            }

            $this->injectArticleForm($form);

            return;
        }

        // Handle category forms - use same pattern as app_bulkdiscount
        if ($option === 'com_categories' && $extension === 'com_content') {
            // Trigger the onJ2CommerceBeforeContentPrepareForm event for other plugins
            $this->dispatchJ2CommerceFormEvent($form, $data);

            // Also inject our own category form
            $this->injectCategoryForm($form, $data);
        }
    }

    /**
     * Check whether the current user may edit a frontend article's product data.
     *
     * Core ACL rules are evaluated first, then the result is passed to
     * `onJ2CommerceArticleProductACL` so marketplace and other plugins can
     * OR-allow additional scenarios (e.g., vendor ownership).
     *
     * @param   int  $articleId  Article ID, or 0 for new articles.
     *
     * @since   6.2.3
     */
    private function canEditFrontend(int $articleId): bool
    {
        $identity = $this->getApplication()->getIdentity();

        if (!$identity || $identity->guest) {
            $result = false;
        } elseif ($identity->authorise('core.edit', 'com_content')) {
            $result = true;
        } elseif ($identity->authorise('core.edit.own', 'com_content')) {
            if ($articleId === 0) {
                // New article — allow when user can create in any category
                $result = $identity->authorise('core.create', 'com_content');
            } else {
                $db    = $this->getDatabase();
                $query = $db->getQuery(true)
                    ->select($db->quoteName('created_by'))
                    ->from($db->quoteName('#__content'))
                    ->where($db->quoteName('id') . ' = :id')
                    ->bind(':id', $articleId, ParameterType::INTEGER);
                $db->setQuery($query);
                $createdBy = (int) $db->loadResult();
                $result    = ($createdBy === (int) $identity->id);
            }
        } else {
            $result = false;
        }

        $catid = $articleId === 0
            ? $this->getApplication()->getInput()->getInt('catid', 0)
            : 0;

        $event = J2CommerceHelper::plugin()->event(
            'ArticleProductACL',
            [
                'article_id' => $articleId,
                'user_id'    => (int) $identity?->id,
                'catid'      => $catid,
                'result'     => $result,
            ]
        );

        return (bool) $event->getArgument('result', $result);
    }

    /**
     * Dispatch J2Commerce form event for other plugins to hook into
     */
    protected function dispatchJ2CommerceFormEvent(Form $form, $data): void
    {
        J2CommerceHelper::plugin()->event('BeforeContentPrepareForm', [$form, $data]);
    }

    protected function injectArticleForm(Form $form): void
    {
        $language = Factory::getApplication()->getLanguage();
        $language->load('plg_content_j2commerce', JPATH_ADMINISTRATOR);
        $language->load('com_j2commerce', JPATH_ADMINISTRATOR);

        Form::addFormPath(__DIR__ . '/../../forms');
        Form::addFieldPath(__DIR__ . '/../Field');
        $form->loadFile('j2commerce', false);

        $contentParams  = ComponentHelper::getParams('com_content');
        $messageDisplay = $contentParams->get('show_article_options', 0);

        if (!$messageDisplay) {
            $this->getApplication()->enqueueMessage(
                Text::_('PLG_CONTENT_J2COMMERCE_TAB_NOT_DISPLAYED'),
                'warning'
            );
        }

        // T6: Register frontend assets only on site client
        if ($this->getApplication()->isClient('site')) {
            $wa = $this->getApplication()->getDocument()->getWebAssetManager();
            $wa->registerAndUseScript(
                'plg_content_j2commerce.article-edit',
                'media/plg_content_j2commerce/js/site/article-edit.js',
                [],
                ['defer' => true]
            );
            $wa->registerAndUseStyle(
                'plg_content_j2commerce.article-edit',
                'media/plg_content_j2commerce/css/site/article-edit.css'
            );
        }
    }

    protected function injectCategoryForm(Form $form): void
    {
        $language = Factory::getApplication()->getLanguage();
        $language->load('plg_content_j2commerce', JPATH_ADMINISTRATOR);
        $language->load('com_j2commerce', JPATH_ADMINISTRATOR);

        // Load category form fields from XML file (Joomla 6 best practice)
        $form->loadFile(JPATH_PLUGINS . '/content/j2commerce/forms/category.xml');
    }

    /**
     * Persist J2Commerce accordion params for a category.
     *
     * Joomla's CategoryModel runs Form::filter() before saving, which strips any
     * params sub-keys not explicitly registered in the Form XML.  The accordion
     * fields (category_display, category_elements, …) are rendered as raw HTML by
     * Categoryj2commerceField and are NOT registered, so they would be silently
     * discarded.  We work around this by reading the raw POST value and merging it
     * back into the category item's params before the table is stored.
     *
     * @since  6.1.0
     */
    private function saveCategoryJ2CommerceParams(BeforeSaveEvent $event): void
    {
        $item = $event->getItem();

        if (!\is_object($item)) {
            return;
        }

        // Read accordion data from raw POST — this is the only place it still exists
        // after Joomla's form filter has run.
        // The sub-form controls render as jform[params][j2commerce][params][field]
        // (the XML files have <fields name="params">), so the actual field values
        // live one level deeper at ['params']['j2commerce']['params'].
        $jform       = $this->getApplication()->getInput()->post->get('jform', [], 'array');
        $j2ParamsRaw = $jform['params']['j2commerce']['params'] ?? null;

        if ($j2ParamsRaw === null || !\is_array($j2ParamsRaw)) {
            return;
        }

        // Merge into the category params that will be stored.
        // Result: {"j2commerce": {"display_use_global": "1", ...}}
        $registry = new \Joomla\Registry\Registry($item->params ?? '{}');
        $registry->set('j2commerce', $j2ParamsRaw);
        $item->params = $registry->toString();
    }

    /**
     * Render the J2Commerce accordion template after the fieldset.
     *
     * @since  6.1.0
     */
    public function onContentAfterFieldset($event): string
    {
        // The accordion is rendered by the Categoryj2commerce form field (getInput).
        // Returning empty here avoids a duplicate accordion with a wrong prefix.
        return '';
    }

    /**
     * Handle content before save event.
     *
     * @since 6.0.0
     */
    public function onContentBeforeSave(BeforeSaveEvent $event): void
    {
        $context = $event->getContext();

        // Handle category save: Joomla's form filter strips unregistered params sub-keys,
        // so we read the j2commerce accordion data from the raw POST and inject it back
        // into the category params before the table is stored.
        if ($context === 'com_categories.category') {
            $this->saveCategoryJ2CommerceParams($event);

            return;
        }

        // Only process com_content contexts - ignore all other components
        if (!str_starts_with($context, 'com_content.')) {
            return;
        }

        // Determine valid contexts for our product integration
        $validContexts = ['com_content.article'];
        if ($this->getApplication()->isClient('site')) {
            $validContexts = ['com_content.form'];
        }

        if (!\in_array($context, $validContexts, true)) {
            return;
        }

        $data = $event->getItem();
        $app  = $this->getApplication();

        // Store full attribs for later processing
        $app->getInput()->set('j2commerce_all_attribs', $data->attribs ?? '', 'RAW');

        // Remove j2commerce data from attribs before article save
        $allAttribs = json_decode($data->attribs ?? '{}');
        if (isset($allAttribs->j2commerce)) {
            unset($allAttribs->j2commerce);
        }
        $data->attribs = json_encode($allAttribs);
    }

    /**
     * Handle content after save event.
     *
     * @since 6.0.0
     */
    public function onContentAfterSave(AfterSaveEvent $event): void
    {
        $context = $event->getContext();

        // Only process com_content contexts - ignore all other components
        if (!str_starts_with($context, 'com_content.')) {
            return;
        }

        $data    = $event->getItem();
        $isNew   = $event->getIsNew();

        // Determine valid contexts
        $validContexts = ['com_content.article'];
        if ($this->getApplication()->isClient('site')) {
            $validContexts = ['com_content.form'];
        }

        if (!\in_array($context, $validContexts, true)) {
            return;
        }

        $app       = $this->getApplication();
        $articleId = (int) ($data->id ?? 0);

        if (!$articleId) {
            return;
        }

        // Save-path ACL guard: block crafted POST submissions from users without editproducts.
        // The tab is already hidden for these users, but this prevents bypassing the UI gate.
        if (!J2CommerceHelper::canAccess('j2commerce.editproducts')) {
            return;
        }

        // Get stored attribs
        $allAttribs = $app->getInput()->get('j2commerce_all_attribs', '', 'RAW');
        $attribs    = json_decode($allAttribs);

        if (!isset($attribs->j2commerce)) {
            return;
        }

        $j2data = $attribs->j2commerce;

        // Check if product already exists for this article
        $existingProduct = $this->getProductBySource('com_content', $articleId);

        // If being disabled, update existing product record and stop
        if (empty($j2data->enabled)) {
            if ($existingProduct && !empty($existingProduct->j2commerce_product_id)) {
                $db        = $this->getDatabase();
                $productId = (int) $existingProduct->j2commerce_product_id;
                $query     = $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_products'))
                    ->set($db->quoteName('enabled') . ' = 0')
                    ->where($db->quoteName('j2commerce_product_id') . ' = :productId')
                    ->bind(':productId', $productId, ParameterType::INTEGER);
                $db->setQuery($query)->execute();
            }

            return;
        }

        if (empty($j2data->product_type)) {
            return;
        }

        $alreadyExists = ($existingProduct && !empty($existingProduct->enabled));

        // Only save if enabled or already exists
        if ($j2data->enabled != 1 && !$alreadyExists) {
            return;
        }

        // Handle save2copy task
        $task = $app->getInput()->getString('task', '');
        if ($task === 'save2copy') {
            // Skip variable product types on copy
            $variableTypes = ['variable', 'advancedvariable', 'flexivariable', 'variablesubscriptionproduct'];
            if (\in_array($j2data->product_type, $variableTypes, true)) {
                return;
            }

            // Reset IDs for new product
            $j2data->j2commerce_product_id        = null;
            $j2data->j2commerce_variant_id        = null;
            $j2data->j2commerce_productimage_id   = null;
            if (isset($j2data->quantity)) {
                $j2data->quantity->j2commerce_productquantity_id = null;
            }
            unset($j2data->item_options);
        }

        // Set product source information
        $j2data->product_source    = 'com_content';
        $j2data->product_source_id = $articleId;

        // First save of a brand-new product: apply category-level defaults,
        // falling back to global config. Create-only — values explicitly chosen
        // by the user (e.g. "Not Taxable" = taxprofile 0) are left unchanged.
        if (!$existingProduct) {
            $catid = (int) ($data->catid ?? 0);

            if (empty($j2data->taxprofile_id)) {
                $defaultTaxprofileId = $this->resolveDefaultTaxprofileId($catid);
                if ($defaultTaxprofileId > 0) {
                    $j2data->taxprofile_id = $defaultTaxprofileId;
                }
            }

            if (empty($j2data->manufacturer_id)) {
                $defaultManufacturerId = $this->resolveDefaultManufacturerId($catid);
                if ($defaultManufacturerId > 0) {
                    $j2data->manufacturer_id = $defaultManufacturerId;
                }
            }

            if (!isset($j2data->visibility) || $j2data->visibility === '') {
                $defaultVisibility = $this->resolveDefaultVisibility($catid);
                if ($defaultVisibility !== null) {
                    $j2data->visibility = $defaultVisibility;
                }
            }
        }

        // Save product using native MVC
        $this->saveProduct($j2data);

        // Newly created product: give its article a real per-category ordering value.
        // Joomla leaves a new article's ordering at 0, which makes the frontend product
        // list "Ordering" sort meaningless (every new product ties at 0).
        if (!$existingProduct) {
            $this->assignArticleOrderingIfUnset($articleId, (int) ($data->catid ?? 0));
        }

        // Clear the static article cache to ensure fresh data on next load
        $this->clearArticleCache($articleId);
    }

    /**
     * Resolve the default tax-profile ID for a new product in the given category.
     *
     * Sentinel contract (matches TaxprofileField values):
     *   null / absent key → category has no override → use global config
     *   ''               → admin explicitly chose "Use Global" → use global config
     *   '0' / 0          → admin explicitly chose "Not Taxable" → return 0
     *   > 0              → specific tax-profile ID → return that ID
     */
    private function resolveDefaultTaxprofileId(int $catid): int
    {
        if ($catid > 0) {
            // No third-arg default: returns null when the key is absent entirely.
            $raw = CategoryHelper::getCategoryParam($catid, 'default_taxprofile_id');

            if ($raw !== null && $raw !== '') {
                return (int) $raw;
            }
        }

        return (int) ConfigHelper::get('default_taxprofile_id', 0);
    }

    /** Category `default_manufacturer_id` → global config default. */
    private function resolveDefaultManufacturerId(int $catid): int
    {
        $categoryDefault = $catid > 0
            ? (int) CategoryHelper::getCategoryParam($catid, 'default_manufacturer_id', 0)
            : 0;

        return $categoryDefault > 0
            ? $categoryDefault
            : (int) ConfigHelper::get('default_manufacturer_id', 0);
    }

    /**
     * Category `default_visibility` → product integer visibility (0/1).
     * Returns null when no category or global default is configured (caller skips override).
     * The category form stores a string ('visible', 'catalog', 'search', 'hidden');
     * the product table uses 0 = hidden, 1 = visible.
     */
    private function resolveDefaultVisibility(int $catid): ?int
    {
        $raw = $catid > 0
            ? (string) CategoryHelper::getCategoryParam($catid, 'default_visibility', '')
            : '';

        if ($raw === '') {
            $raw = (string) ConfigHelper::get('default_visibility', '');
        }

        return match ($raw) {
            'visible', 'catalog', 'search' => 1,
            'hidden'                       => 0,
            default                        => null,
        };
    }

    /**
     * Set #__content.ordering to (max in category + 1) for a product article whose
     * ordering is still 0, so the frontend "Ordering" sort produces a stable result.
     */
    private function assignArticleOrderingIfUnset(int $articleId, int $catid): void
    {
        if ($articleId <= 0 || $catid <= 0) {
            return;
        }

        $db = $this->getDatabase();

        $current = (int) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('ordering'))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $articleId, ParameterType::INTEGER)
        )->loadResult();

        if ($current > 0) {
            return;
        }

        // MAX is taken across the whole category (all articles, not just products) by
        // design, so products share the same Joomla per-category ordering space.
        $max = (int) $db->setQuery(
            $db->getQuery(true)
                ->select('MAX(' . $db->quoteName('ordering') . ')')
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('catid') . ' = :catid')
                ->bind(':catid', $catid, ParameterType::INTEGER)
        )->loadResult();

        $newOrdering = $max + 1;

        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__content'))
                ->set($db->quoteName('ordering') . ' = :ordering')
                ->where($db->quoteName('id') . ' = :aid')
                ->bind(':ordering', $newOrdering, ParameterType::INTEGER)
                ->bind(':aid', $articleId, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * Handle content after delete event.
     *
     * @since 6.0.0
     */
    public function onContentAfterDelete(AfterDeleteEvent $event): void
    {
        $context = $event->getContext();

        // Only process com_content contexts - ignore all other components
        if (!str_starts_with($context, 'com_content.')) {
            return;
        }

        $data      = $event->getItem();
        $articleId = $data->id ?? 0;

        if (!$articleId) {
            return;
        }

        // Delete or disable products linked to this article
        if ($this->params->get('purge_on_delete', 0)) {
            $this->deleteProductsBySource('com_content', (int) $articleId);
        } else {
            $this->disableProductsBySource('com_content', (int) $articleId);
        }

        // Clear the static article cache
        $this->clearArticleCache((int) $articleId);
    }

    /** @since 6.2.3 */
    public function onContentChangeState($event): void
    {
        if (!$this->params->get('mirror_state', 1)) {
            return;
        }

        $context = $event->getArgument('0') ?? $event->getArgument('context');
        if ($context !== 'com_content.article') {
            return;
        }

        $pks   = $event->getArgument('1') ?? $event->getArgument('pks') ?? [];
        $value = (int) ($event->getArgument('2') ?? $event->getArgument('value') ?? 0);

        if (empty($pks)) {
            return;
        }

        // Only state=1 (published) enables the product; anything else disables it
        $enabled    = ($value === 1) ? 1 : 0;
        $articleIds = array_map('intval', (array) $pks);

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_products'))
            ->set($db->quoteName('enabled') . ' = :enabled')
            ->where($db->quoteName('product_source') . ' = ' . $db->quote('com_content'))
            ->whereIn($db->quoteName('product_source_id'), $articleIds)
            ->bind(':enabled', $enabled, ParameterType::INTEGER);
        $db->setQuery($query)->execute();

        foreach ($articleIds as $articleId) {
            $this->clearArticleCache($articleId);
        }
    }

    /** @since 6.0.0 */
    public function onContentBeforeDisplay(BeforeDisplayEvent $event): void
    {
        $context = $event->getContext();
        $item    = $event->getItem();
        $params  = $event->getParams();

        if (!$this->checkPublishDate($item)) {
            return;
        }

        $j2params  = ComponentHelper::getParams('com_j2commerce');
        $placement = $j2params->get('addtocart_placement', 'default');

        if ($placement === 'tag') {
            return;
        }

        $html = $this->getProductImages('beforecontent', $context, $item, $params);
        $event->addResult($html);
    }

    /** @since 6.0.0 */
    public function onContentAfterDisplay(AfterDisplayEvent $event): void
    {
        $context = $event->getContext();
        $item    = $event->getItem();
        $params  = $event->getParams();

        if (strpos($context, 'com_content') === false) {
            return;
        }

        if (!$this->checkPublishDate($item)) {
            return;
        }

        $j2params  = ComponentHelper::getParams('com_j2commerce');
        $placement = $j2params->get('addtocart_placement', 'default');

        if ($placement === 'tag') {
            return;
        }

        // Skip product list context
        if ($context === 'com_content.category.productlist') {
            return;
        }

        $html = '';

        // Determine position setting
        if (\in_array($context, ['com_content.category', 'com_content.featured'], true)) {
            $position = $this->params->get('category_product_block_position', 'bottom');
        } else {
            $position = $this->params->get('item_product_block_position', 'bottom');
        }

        // Only render if position is afterdisplaycontent
        if (isset($item->id) && $item->id > 0 && $position === 'afterdisplaycontent') {
            $product = $this->getProductBySource('com_content', (int) $item->id);

            if ($product) {
                $html .= $this->getProductImageHtml($product, $context, $item, $params);
                $html .= $this->getProductBlock($product, $context);
            }
        }

        $html .= $this->getProductImages('aftercontent', $context, $item, $params);
        $event->addResult($html);
    }

    /**
     * @since   6.0.0
     */
    private function clearContentCaches(): void
    {
        try {
            $cacheFactory = Factory::getContainer()->get(CacheControllerFactoryInterface::class);

            $contentCache = $cacheFactory->createCacheController('', ['defaultgroup' => 'com_content']);
            $contentCache->clean();

            $j2commerceCache = $cacheFactory->createCacheController('', ['defaultgroup' => 'com_j2commerce']);
            $j2commerceCache->clean();

            $this->cacheCleared = true;
        } catch (\Throwable $e) {
            // Silently fail on cache clear
        }
    }

    /**
     * @since   6.0.0
     */
    private function stripShortcodes(object $article): void
    {
        if (!isset($article->text)) {
            return;
        }

        $matches = $this->parseShortcodes($article->text);

        if (empty($matches)) {
            return;
        }

        foreach ($matches as $match) {
            $article->text = str_replace($match[0], '', $article->text);
        }
    }

    /**
     * Strip all Joomla content-plugin-style tags from article text when prepare_content
     * is disabled. Handles both the paired form ({tagname}…{/tagname}) and the
     * self-closing form ({tagname} / {tagname attributes}).
     *
     * J2Commerce's own {j2commerce} shortcodes are intentionally skipped here — they
     * are processed or stripped elsewhere in the pipeline. Any tag whose raw text
     * contains a fragment listed in the preserve_tags plugin parameter is also kept.
     *
     * @since   6.4.0
     */
    private function stripContentPluginTags(object $article): void
    {
        // Build the preserve list from the plugin parameter.
        $preserveRaw  = trim((string) $this->params->get('preserve_tags', ''));
        $preserveList = $preserveRaw !== ''
            ? array_filter(array_map('trim', explode(',', $preserveRaw)))
            : [];

        // Returns true when the matched tag should be stripped.
        $shouldStrip = static function (string $raw, string $tagName) use ($preserveList): bool {
            if (strtolower($tagName) === 'j2commerce') {
                return false;
            }

            foreach ($preserveList as $fragment) {
                if ($fragment !== '' && stripos($raw, $fragment) !== false) {
                    return false;
                }
            }

            return true;
        };

        $strip = static function (string $text) use ($shouldStrip): string {
            // Paired form first: {tagname …}…{/tagname}
            $text = preg_replace_callback(
                '/\{([a-z][a-z0-9_-]*)(?:\s[^{}]*)?\}.*?\{\/\1\}/is',
                static function (array $m) use ($shouldStrip): string {
                    return $shouldStrip($m[0], $m[1]) ? '' : $m[0];
                },
                $text
            ) ?? $text;

            // Self-closing form: {tagname} or {tagname attributes}
            return preg_replace_callback(
                '/\{([a-z][a-z0-9_-]*)(?:\s[^{}]*)?\}/i',
                static function (array $m) use ($shouldStrip): string {
                    return $shouldStrip($m[0], $m[1]) ? '' : $m[0];
                },
                $text
            ) ?? $text;
        };

        // Process all three text properties so that tags are stripped regardless
        // of which property is rendered by the active view template.
        foreach (['text', 'introtext', 'fulltext'] as $prop) {
            if (isset($article->$prop) && $article->$prop !== '') {
                $article->$prop = $strip($article->$prop);
            }
        }
    }

    /**
     * @since   6.0.0
     */
    private function renderDefaultPosition(string $context, object $article, object $params): void
    {
        // Determine position setting
        if (\in_array($context, ['com_content.category', 'com_content.featured'], true)) {
            $position = $this->params->get('category_product_block_position', 'bottom');
        } else {
            $position = $this->params->get('item_product_block_position', 'bottom');
        }

        if (!isset($article->id) || !$article->id || $position === 'afterdisplaycontent') {
            return;
        }

        // Never inject a product block while ProductHelper is preparing product
        // descriptions — this is the definitive last-resort guard that prevents
        // a duplicate product from appearing inside its own short/long description.
        if (ProductHelper::isPreparingDescriptions() || !empty($article->j2commerce_desc_context)) {
            return;
        }

        $product = $this->getProductBySource('com_content', (int) $article->id);

        if (!$product) {
            return;
        }

        $html      = $this->getProductBlock($product, $context);
        $imageHtml = $this->getProductImageHtml($product, $context, $article, $params);

        $productHtml = $imageHtml . $html;

        /*
         * Always append to $article->text — never to introtext or fulltext.
         *
         * The list views (category, featured, archive) seed $item->text from
         * introtext, dispatch onContentPrepare, then copy the result straight
         * back with $item->introtext = $item->text. Anything written to
         * introtext during the dispatch is therefore discarded before
         * blog_item.php ever renders it, while ->text survives on every surface.
         *
         * ONLY append. Do not overwrite introtext/fulltext and do not reassemble
         * text from introtext . fulltext — that caused the fulltext bleed in the
         * blog view.
         *
         * top:    product  →  existing content
         * bottom: existing content  →  product
         */
        if (!isset($article->text)) {
            $article->text = '';
        }

        if ($position === 'top') {
            $article->text = $productHtml . $article->text;
        } else {
            $article->text .= $productHtml;
        }
    }

    /**
     * Renders the product block via ProductLayoutService so that all product types
     * (simple, variable, flexivariable, configurable, …) use their correct layout,
     * JS handlers and asset loading — identical to the category list rendering.
     *
     * @since   6.0.0
     * @since   6.2.3  Replaced manual HTML generation with ProductLayoutService
     */
    private function getProductBlock(object $product, string $context): string
    {
        // Ensure J2Commerce language strings are loaded — on category blog pages
        // the component controller does not run (unlike product list/detail views),
        // so we replicate what ProductsController does: load('com_j2commerce', JPATH_SITE).
        Factory::getApplication()->getLanguage()->load('com_j2commerce', JPATH_SITE);

        // Load required JS assets — StrapperHelper::loadFrontendScripts() normally does
        // this on J2Commerce component pages, but never runs on category blog pages.
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseScript(
            'com_j2commerce.dom',
            'media/com_j2commerce/js/site/j2commerce-dom.js',
            [],
            ['defer' => true]
        );
        $wa->registerAndUseScript(
            'com_j2commerce.site',
            'media/com_j2commerce/js/site/j2commerce.js',
            [],
            ['defer' => true],
            ['com_j2commerce.dom']
        );

        $wa->registerAndUseScript(
            'com_j2commerce.a11y',
            'media/com_j2commerce/js/site/j2commerce-a11y.js',
            [],
            ['defer' => true]
        );

        if (($product->product_type ?? '') === 'flexivariable') {
            $wa->registerAndUseScript(
                'plg_j2commerce_app_flexivariable.flexivariable',
                'media/plg_j2commerce_app_flexivariable/js/flexivariable.js',
                [],
                ['defer' => true]
            );
        }

        $allOptions  = ['full', 'price', 'cart', 'options', 'sku', 'stock'];
        $displayData = $this->buildDisplayData($product, 'full', $allOptions);

        // The list layouts already implement the three add-to-cart modes, keyed on the
        // component's list_show_cart, so map this plugin's own setting onto that key.
        // List surfaces only — the setting lives in the categoryview fieldset and must
        // not reach a single article. buildArticleParams() hands back a fresh clone per
        // call, so shortcodes elsewhere keep the component default.
        if (\in_array($context, ['com_content.category', 'com_content.featured'], true)) {
            $displayData['params']->set(
                'list_show_cart',
                (int) $this->params->get('category_product_options', 1)
            );
        }

        $displayData['showCart']    = true;
        $displayData['showPrice']   = true;
        $displayData['showTitle']   = false;
        $displayData['showImage']   = false;
        // Do NOT render the description: the article text (introtext/fulltext) is
        // already output by com_content. Since the product description for article-based
        // products is populated from exactly that text, rendering it here would cause
        // it to appear as a duplicate (double introtext).
        $displayData['showDescription'] = false;

        return ProductLayoutService::renderLayout('list.category.item', $displayData);
    }

    /** @since 6.0.0 */
    private function getProductImageHtml(object $product, string $context, object $item, object $params): string
    {
        $html = '';

        // Determine settings based on context. Only list surfaces (category/featured)
        // link the image to the product — on a single-article view the image belongs
        // to the article's own product, so a link would point at the current page.
        if (\in_array($context, ['com_content.category', 'com_content.featured'], true)) {
            $showImage       = $this->params->get('category_display_j2commerce_images', 1);
            $imageType       = $this->params->get('category_image_type', 'thumbnail');
            $imageLocation   = 'default';
            $mainWidth       = (int) $this->params->get('list_image_thumbnail_width', 120);
            $additionalWidth = (int) $this->params->get('list_product_additional_image_width', 80);
            $enableZoom      = (int) $this->params->get('category_enable_image_zoom', 1) === 1;
            $linkImage       = (int) $this->params->get('category_link_image_to_product', 1) === 1;
        } else {
            $showImage       = $this->params->get('item_display_j2commerce_images', 1);
            $imageType       = $this->params->get('item_image_type', 'main');
            $imageLocation   = $this->params->get('item_image_placement', 'default');
            $mainWidth       = (int) $this->params->get('item_product_main_image_width', 300);
            $additionalWidth = (int) $this->params->get('item_product_additional_image_width', 100);
            $enableZoom      = (int) $this->params->get('item_enable_image_zoom', 1) === 1;
            $linkImage       = false;
        }

        if (!$showImage || $imageLocation !== 'default') {
            return $html;
        }

        // A linked image navigates on click, and the zoom handler cancels that default,
        // so the two cannot both own the click. The link wins.
        $enableZoom = $enableZoom && !$linkImage;

        $images = $this->getProductImagesData($product->j2commerce_product_id, $imageType);
        // example: array(1) { [0]=> object(stdClass)#1894 (2) { ["image_path"]=> string(0) "" ["thumb_image"]=> string(0) "" } }

        if (!empty($images)) {
            $productLink = '';

            if ($linkImage) {
                // xhtml=false: escape() below handles attribute encoding.
                $productLink = $product->product_link ?? Route::_(RouteHelper::getProductRoute(
                    (int) $product->j2commerce_product_id,
                    $product->alias ?? null,
                    (int) ($product->catid ?? 0) ?: null
                ), false);
            }

            $html .= '<div class="j2commerce-product-images">';
            foreach ($images as $index => $image) {
                if (!empty($image->image_path)) {
                    $img = $this->renderProductImageTag(
                        $image->image_path,
                        $index === 0 ? $mainWidth : $additionalWidth,
                        $enableZoom
                    );

                    $html .= $productLink !== ''
                        ? '<a href="' . $this->escape($productLink) . '">' . $img . '</a>'
                        : $img;
                }
            }
            $html .= '</div>';
        }

        return $html;
    }

    /** @since 6.0.0 */
    private function getProductImages(string $event, string $context, object $item, object $params): string
    {
        $imageLocation = $this->params->get('item_image_placement', 'default');
        $showImage     = $this->params->get('item_display_j2commerce_images', 1);

        if ($imageLocation !== $event || !$showImage || !isset($item->id) || $item->id < 1) {
            return '';
        }

        if (!str_contains($context, 'com_content.article')) {
            return '';
        }

        $j2params  = ComponentHelper::getParams('com_j2commerce');
        $placement = $j2params->get('addtocart_placement', 'default');

        if (!\in_array($placement, ['default', 'both'], true)) {
            return '';
        }

        $product = $this->getProductBySource('com_content', (int) $item->id);

        if (!$product) {
            return '';
        }

        $imageType       = $this->params->get('item_image_type', 'main');
        $mainWidth       = (int) $this->params->get('item_product_main_image_width', 300);
        $additionalWidth = (int) $this->params->get('item_product_additional_image_width', 100);
        $enableZoom      = (int) $this->params->get('item_enable_image_zoom', 1) === 1;
        $images          = $this->getProductImagesData($product->j2commerce_product_id, $imageType);
        $html            = '';

        if (!empty($images)) {
            $html .= '<div class="j2commerce-product-images">';
            foreach ($images as $index => $image) {
                $html .= $this->renderProductImageTag(
                    $image->image_path,
                    $index === 0 ? $mainWidth : $additionalWidth,
                    $enableZoom
                );
            }
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * The first image is the main one; every image after it is an additional image
     * and takes the narrower additional width.
     *
     * @since   6.5.1
     */
    private function renderProductImageTag(string $imagePath, int $width, bool $enableZoom): string
    {
        if ($enableZoom) {
            $this->registerZoomAssets();
        }

        return '<img src="' . $this->escape($imagePath) . '" alt="" class="j2commerce-product-image"'
            . ' style="max-width: ' . $width . 'px;"'
            . ($enableZoom ? ' data-action="zoom"' : '') . '>';
    }

    /** Guarded — a category listing renders this once per product image. */
    private function registerZoomAssets(): void
    {
        static $registered = false;

        if ($registered) {
            return;
        }

        $registered = true;

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('com_j2commerce.vendor.zoom.css', 'media/com_j2commerce/vendor/zoom-vanilla/css/zoom.css');
        $wa->registerAndUseScript('com_j2commerce.vendor.zoom', 'media/com_j2commerce/vendor/zoom-vanilla/js/zoom-vanilla.min.js', [], ['defer' => true]);
    }

    /** @since 6.0.0 */
    private function processWithinArticle(object $article): void
    {
        if (!isset($article->text) || !str_contains($article->text, '{j2commerce')) {
            return;
        }

        $this->processShortcodes('', $article, new Registry());
    }

    /** @since 6.0.0 */
    private function processShortcodes(string $context, object $article, object $params): void
    {
        if (!isset($article->text) || !str_contains($article->text, '{j2commerce')) {
            return;
        }

        // Render contexts outside the normal component controller (custom fields,
        // page builders) never boot com_j2commerce, so the site language and JS
        // assets are not guaranteed to be loaded — mirrors getProductBlock().
        Factory::getApplication()->getLanguage()->load('com_j2commerce', JPATH_SITE);

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseScript(
            'com_j2commerce.dom',
            'media/com_j2commerce/js/site/j2commerce-dom.js',
            [],
            ['defer' => true]
        );
        $wa->registerAndUseScript(
            'com_j2commerce.site',
            'media/com_j2commerce/js/site/j2commerce.js',
            [],
            ['defer' => true],
            ['com_j2commerce.dom']
        );
        $wa->registerAndUseScript(
            'com_j2commerce.a11y',
            'media/com_j2commerce/js/site/j2commerce-a11y.js',
            [],
            ['defer' => true]
        );

        // WYSIWYG editors (TinyMCE, JCE) often wrap pasted shortcodes in <pre>
        // or <code> tags which would render the product HTML inside a monospace
        // block. Unwrap them before parsing.
        $article->text = $this->unwrapShortcodes($article->text);

        $matches = $this->parseShortcodes($article->text);

        if (empty($matches)) {
            return;
        }

        foreach ($matches as $match) {
            if (empty($match['body'])) {
                continue;
            }

            $values = explode('|', $match['body']);

            // First value is always the product ID
            if (!isset($values[0]) || !is_numeric($values[0])) {
                Log::add(
                    \sprintf('Malformed {j2commerce} shortcode body "%s" — expected a numeric product ID separated by |.', $match['body']),
                    Log::WARNING,
                    'j2commerce'
                );
                $article->text = $this->replaceAtPosition($article->text, $match['raw'], '');
                continue;
            }

            $productId = (int) $values[0];

            // Same gate the |detail branch resolves through, so both option
            // branches answer identically on the same request.
            if (!ProductVisibilityHelper::isViewable($productId)) {
                $article->text = $this->replaceAtPosition($article->text, $match['raw'], '');
                continue;
            }

            $product = $this->getProductById($productId);

            if (!$product) {
                $article->text = $this->replaceAtPosition($article->text, $match['raw'], '');
                continue;
            }

            // Check publish date if product has source article
            if (!empty($product->product_source_id)) {
                $sourceArticle = $this->getArticle((int) $product->product_source_id);

                if (!$this->checkPublishDate($sourceArticle)) {
                    $article->text = $this->replaceAtPosition($article->text, $match['raw'], '');
                    continue;
                }
            }

            // Options are everything after the product ID
            $options     = \array_slice($values, 1);
            $productType = $product->product_type ?? '';
            $html        = '<div class="com_j2commerce j2commerce-single-product j2commerce-shortcode j2commerce-shortcode-article">';

            // Apply the shortcode-specific subtemplate override so that
            // ProductLayoutService::renderLayout() picks up the correct layout
            // paths for non-detail options (|full, |cart, |options, etc.).
            // The |detail branch handles this independently via the view params.
            //
            // An inline token like |subtemplate:uikit takes precedence over the
            // plugin-level shortcode_subtemplate param, letting each shortcode on
            // the same page use a different subtemplate.
            $inlineSubtemplate = null;
            foreach ($options as $_opt) {
                $_opt = strtolower(trim($_opt));
                if (str_starts_with($_opt, 'subtemplate:')) {
                    $inlineSubtemplate = substr($_opt, \strlen('subtemplate:'));
                    break;
                }
            }

            $shortcodeSubtemplate = $inlineSubtemplate !== null && $inlineSubtemplate !== ''
                ? $inlineSubtemplate
                : trim((string) $this->params->get('shortcode_subtemplate', ''));

            if ($shortcodeSubtemplate !== '') {
                ProductLayoutService::setSubtemplateOverride($shortcodeSubtemplate);
            }

            foreach ($options as $option) {
                $option = strtolower(trim($option));

                // Special case: |detail dispatches onJ2CommerceViewProductHtml so the
                // active subtemplate plugin renders it with its own view_*.php files.
                if ($option === 'detail') {
                    $html .= $this->renderProductDetail($productId);
                    continue;
                }

                if (!isset(self::SHORTCODE_LAYOUT_MAP[$option])) {
                    continue;
                }

                $layoutId = self::SHORTCODE_LAYOUT_MAP[$option];

                // Route variable product types to their type-specific layouts
                if (\in_array($productType, ['flexivariable'], true)) {
                    if (\in_array($option, ['price', 'saleprice', 'regularprice'], true)) {
                        $layoutId = 'list.category.item_flexiprice';
                    } elseif (\in_array($option, ['full', 'card'], true)) {
                        $layoutId = 'list.category.item';
                    }
                }

                $displayData = $this->buildDisplayData($product, $option, $options);

                // Shortcode surfaces only — buildDisplayData() also serves the article
                // product block, which follows the component's own settings. 'cartonly'
                // routes to the bare cart partial and carries no option block either way.
                $displayData['showOptions'] = $option !== 'cartonly'
                    && (int) $this->params->get('shortcode_show_cart_options', 1) === 1;

                $rendered = ProductLayoutService::renderLayout($layoutId, $displayData);

                // 'cart'/'cartonly' render the bare cart-button partial, which has no
                // <form> of its own (unlike 'full'/'card', which route through the full
                // item layout that supplies one) — wrap it so the add-to-cart JS submit
                // handler (which looks for the nearest .j2commerce-addtocart-form) fires.
                if (\in_array($option, ['cart', 'cartonly'], true)) {
                    if ($productType === 'flexivariable') {
                        $wa->registerAndUseScript(
                            'plg_j2commerce_app_flexivariable.flexivariable',
                            'media/plg_j2commerce_app_flexivariable/js/flexivariable.js',
                            [],
                            ['defer' => true]
                        );
                    }

                    $rendered = $this->wrapCartForm($product, $rendered);
                }

                $html .= $rendered;
            }

            if ($shortcodeSubtemplate !== '') {
                ProductLayoutService::clearSubtemplateOverride();
            }

            $html .= '</div>';

            $article->text = $this->replaceAtPosition($article->text, $match['raw'], $html);
        }
    }

    /**
     * Wraps a rendered cart-button partial in the same form markup the full item
     * layouts (item_simple.php et al.) provide, so the add-to-cart JS submit handler
     * has a `.j2commerce-addtocart-form` ancestor to bind to.
     *
     * @since   6.3.7
     */
    private function wrapCartForm(object $product, string $cartHtml): string
    {
        $productId   = (int) $product->j2commerce_product_id;
        $productType = htmlspecialchars($product->product_type ?? '', ENT_QUOTES, 'UTF-8');
        $action      = htmlspecialchars($product->cart_form_action ?? '', ENT_QUOTES, 'UTF-8');

        self::$cartFormInstanceCounts[$productId] = (self::$cartFormInstanceCounts[$productId] ?? 0) + 1;
        $formId                                   = 'j2commerce-addtocart-form-' . $productId . '-' . self::$cartFormInstanceCounts[$productId];

        return '<form action="' . $action . '" method="post" class="j2commerce-addtocart-form" id="' . $formId . '" '
            . 'data-product_id="' . $productId . '" data-product_type="' . $productType . '" enctype="multipart/form-data">'
            . $cartHtml . '</form>';
    }

    /**
     * Strip `<pre>` / `<code>` wrappers that WYSIWYG editors add around
     * shortcodes. This lets the replaced product HTML render as a block-level
     * element instead of inside a monospace formatted block.
     *
     * Handles both paired `{j2commerce}...{/j2commerce}` and inline
     * `{j2commerce 5|opts}` forms, with optional whitespace/&nbsp; around them.
     *
     * @since  6.0.0
     */
    private function unwrapShortcodes(string $text): string
    {
        $whitespace = '(?:\s|&nbsp;|&#160;)*';
        $shortcode  = '(?:{j2commerce}.*?{\/j2commerce}|{j2commerce\s+[0-9]+(?:\|[^}]*)?})';

        $patterns = [
            // <pre>{shortcode}</pre> with optional attributes and whitespace
            '~<pre\b[^>]*>' . $whitespace . '(' . $shortcode . ')' . $whitespace . '</pre>~is',
            // <code>{shortcode}</code>
            '~<code\b[^>]*>' . $whitespace . '(' . $shortcode . ')' . $whitespace . '</code>~is',
            // <p>{shortcode}</p> — when the shortcode is the only content in
            // its paragraph, unwrapping avoids a <div> inside <p> (invalid HTML)
            '~<p\b[^>]*>' . $whitespace . '(' . $shortcode . ')' . $whitespace . '</p>~is',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '$1', $text) ?? $text;
        }

        return $text;
    }

    /** @since 6.0.0 */
    private function parseShortcodes(string $text): array
    {
        $matches = [];

        // Paired form: {j2commerce}ID|opt1|opt2{/j2commerce}
        preg_match_all('/{j2commerce}(.*?){\/j2commerce}/', $text, $paired, PREG_SET_ORDER);
        foreach ($paired as $m) {
            $matches[] = ['raw' => $m[0], 'body' => $m[1]];
        }

        // Inline form: {j2commerce ID|opt1|opt2}
        preg_match_all('/{j2commerce\s+([0-9]+(?:\|[^}]*)?)}/', $text, $inline, PREG_SET_ORDER);
        foreach ($inline as $m) {
            $matches[] = ['raw' => $m[0], 'body' => $m[1]];
        }

        return $matches;
    }

    /**
     * Render the full product detail view through the active subtemplate plugin.
     *
     * Manufactures a Site\View\Product\HtmlView + ProductModel via the component MVC
     * factory, hydrates it with the target product, then dispatches
     * `onJ2CommerceViewProductHtml` so the active subtemplate plugin (app_bootstrap5
     * / app_uikit) renders the detail view using its own subtemplate view_* files.
     *
     * @param   int  $productId  Product ID to render.
     *
     * @return  string  Rendered HTML, or empty string on failure.
     *
     * @since   6.0.0
     */
    private function renderProductDetail(int $productId): string
    {
        try {
            $app        = $this->getApplication();
            $mvcFactory = $app->bootComponent('com_j2commerce')->getMVCFactory();

            /** @var \J2Commerce\Component\J2commerce\Site\Model\ProductModel $model */
            $model = $mvcFactory->createModel('Product', 'Site', ['ignore_request' => true]);

            if (!$model) {
                return '';
            }

            $model->setState('product.id', $productId);
            $model->setState('params', $app->getParams());

            $item = $model->getItem();
            if (!$item) {
                return '';
            }

            /** @var \J2Commerce\Component\J2commerce\Site\View\Product\HtmlView $view */
            $view = $mvcFactory->createView('Product', 'Site', 'Html');

            if (!$view) {
                return '';
            }

            $view->setModel($model, true);

            // Seed the view with the same properties Site\View\Product\HtmlView::display()
            // sets before dispatching the event. The subtemplate plugin reads these.
            $params      = $this->buildArticleParams();
            $subtemplate = trim((string) $this->params->get('shortcode_subtemplate', ''));
            if ($subtemplate !== '') {
                // Strip the 'app_' prefix — subtemplate plugins expect short names
                // like 'bootstrap5' in $view->params->get('subtemplate').
                $short = str_starts_with($subtemplate, 'app_') ? substr($subtemplate, 4) : $subtemplate;
                $params->set('subtemplate', $short);
            }

            // `item`, `state`, and `user` are protected on ProductView. Bind a closure
            // to the view class so we can write them without reflection overhead.
            $state    = $model->getState();
            $identity = $app->getIdentity();
            $setter   = \Closure::bind(
                function ($item, $params, $state, $user) {
                    $this->item    = $item;
                    $this->product = $item;
                    $this->params  = $params;
                    $this->state   = $state;
                    $this->user    = $user;
                },
                $view,
                \get_class($view)
            );
            $setter($item, $params, $state, $identity);

            // Dispatch the event directly — subtemplate plugin renders via its
            // view_*.php files and sets the 'html' argument. We don't use
            // J2CommerceHelper::plugin()->eventWithHtml() because it overwrites
            // the 'html' argument with the concat of 'result' entries after dispatch.
            PluginHelper::importPlugin('j2commerce');
            $dispatcher  = Factory::getContainer()->get(DispatcherInterface::class);
            $pluginEvent = new \J2Commerce\Component\J2commerce\Administrator\Event\PluginEvent(
                'onJ2CommerceViewProductHtml',
                [null, &$view, $model]
            );
            $dispatcher->dispatch('onJ2CommerceViewProductHtml', $pluginEvent);

            return (string) $pluginEvent->getArgument('html', '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function buildDisplayData(object $product, string $option, array $allOptions): array
    {
        $params = $this->buildArticleParams();
        $itemId = $this->resolveItemId();

        return [
            'product'         => $product,
            'params'          => $params,
            'context'         => ProductLayoutService::CONTEXT_ARTICLE,
            'contextBase'     => 'article',
            'contextSub'      => null,
            'contextChain'    => ['article', 'list'],
            'itemId'          => $itemId,
            'columns'         => 1,
            'imageWidth'      => (int) $this->params->get('shortcode_image_width', 300),
            'showImage'       => $this->optionsContainAny($allOptions, ['images', 'gallery', 'mainimage', 'thumbnail', 'mainadditional', 'full', 'card', 'detail']),
            'showTitle'       => $this->optionsContainAny($allOptions, ['title', 'full', 'card', 'detail']),
            'showPrice'       => $this->optionsContainAny($allOptions, ['price', 'saleprice', 'regularprice', 'full', 'card', 'detail']),
            'showCart'        => $this->optionsContainAny($allOptions, ['cart', 'cartonly', 'full', 'card', 'detail']),
            'showQtyField'    => !\in_array('noqty', $allOptions, true),
            'showSku'         => $this->optionsContainAny($allOptions, ['sku', 'full', 'card', 'detail']),
            'showUpc'         => $this->optionsContainAny($allOptions, ['upc', 'full', 'card', 'detail']),
            'showStock'       => $this->optionsContainAny($allOptions, ['stock', 'full', 'card', 'detail']),
            'showDescription' => $this->optionsContainAny($allOptions, ['description', 'desc', 'full', 'card', 'detail']),
            'showQuickview'   => \in_array('quickview', $allOptions, true),
            'linkTitle'       => true,
            'linkImage'       => true,
            'productLink'     => $product->product_link ?? null,
            'cartText'        => Text::_('COM_J2COMMERCE_ADD_TO_CART'),
            'layoutBasePath'  => '',
            'shortcodeOption' => $option,
            'priceMode'       => $this->resolvePriceMode($option),
            'imageMode'       => $this->resolveImageMode($option),
            'showOptions'     => true,
            'sourceContext'   => 'article',
        ];
    }

    private function buildArticleParams(): \Joomla\Registry\Registry
    {
        // Clone the real component config (never mutate the shared cached instance)
        // so shortcode-rendered layouts respect admin-configured options such as
        // addtocart_button_class instead of always falling back to layout defaults.
        $params = clone ComponentHelper::getParams('com_j2commerce');
        $params->set('list_no_of_columns', 1);
        $params->set('list_show_image', 1);
        $params->set('list_show_title', 1);
        $params->set('list_show_description', 0);
        $params->set('list_show_product_sku', 0);
        $params->set('list_show_product_upc', 0);
        $params->set('list_show_product_stock', 0);
        $params->set('list_enable_quickview', 0);
        $params->set('list_link_title', 1);
        $params->set('list_image_link_to_product', 1);
        $params->set('list_image_width', (int) $this->params->get('shortcode_image_width', 300));

        return $params;
    }

    private function resolveItemId(): int
    {
        try {
            $app  = $this->getApplication();
            $menu = $app->getMenu();

            return $menu ? (int) ($menu->getActive()?->id ?? 0) : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function optionsContainAny(array $options, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (\in_array($needle, $options, true)) {
                return true;
            }
        }

        return false;
    }

    private function resolvePriceMode(string $option): ?string
    {
        return match ($option) {
            'saleprice'    => 'sale',
            'regularprice' => 'regular',
            default        => null,
        };
    }

    private function resolveImageMode(string $option): ?string
    {
        return match ($option) {
            'mainimage'      => 'main',
            'thumbnail'      => 'thumb',
            'mainadditional' => 'mainadditional',
            default          => null,
        };
    }

    private function replaceAtPosition(string $haystack, string $needle, string $replacement): string
    {
        $pos = strpos($haystack, $needle);

        if ($pos === false) {
            return $haystack;
        }

        return substr_replace($haystack, $replacement, $pos, \strlen($needle));
    }

    /** @since 6.0.0 */
    private function checkPublishDate(?object $article): bool
    {
        if (!$this->params->get('check_publish_date', 0)) {
            return true;
        }

        if (!$article || !isset($article->publish_up) || !isset($article->publish_down)) {
            return true;
        }

        $db       = $this->getDatabase();
        $nullDate = $db->getNullDate();
        $nowDate  = Factory::getDate('now')->toSql();

        $publishUp   = $article->publish_up;
        $publishDown = $article->publish_down;

        $validUp   = ($publishUp === $nullDate || $publishUp <= $nowDate);
        $validDown = ($publishDown === $nullDate || $publishDown >= $nowDate);

        return $validUp && $validDown;
    }

    /**
     * Get full product by source using centralized ProductHelper.
     *
     * @since 6.0.0
     * @since 6.0.8 Uses centralized ProductHelper::getFullProductBySource()
     */
    private function getProductBySource(string $source, int $sourceId): ?object
    {
        return ProductHelper::getFullProductBySource($source, $sourceId);
    }

    /**
     * Get full product by ID using centralized ProductHelper.
     *
     * @since 6.0.0
     * @since 6.0.8 Uses centralized ProductHelper::getFullProduct()
     */
    private function getProductById(int $productId): ?object
    {
        return ProductHelper::getFullProduct($productId);
    }

    /** @since 6.0.0 */
    private function getProductsByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_products'))
            ->whereIn($db->quoteName('j2commerce_product_id'), $ids)
            ->where($db->quoteName('enabled') . ' = 1');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /** @since 6.0.0 */
    private function getProductVariant(int $productId): ?object
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_variants'))
            ->where($db->quoteName('product_id') . ' = :productId')
            ->where($db->quoteName('is_master') . ' = 1')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    /** @since 6.0.0 */
    private function getProductImagesData(int $productId, string $imageType): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('main_image', 'image_path'),
                $db->quoteName('thumb_image'),
            ])
            ->from($db->quoteName('#__j2commerce_productimages'))
            ->where($db->quoteName('product_id') . ' = :productId')
            ->bind(':productId', $productId, ParameterType::INTEGER)
            ->order($db->quoteName('j2commerce_productimage_id') . ' ASC');

        $db->setQuery($query);
        $images = $db->loadObjectList() ?: [];

        // Filter by type
        $result = [];
        foreach ($images as $index => $image) {
            if ($imageType === 'thumbnail' && !empty($image->thumb_image)) {
                $image->image_path = $image->thumb_image;
                $result[]          = $image;
            } elseif ($imageType === 'main' && $index === 0) {
                $result[] = $image;
            } elseif ($imageType === 'mainadditional') {
                $result[] = $image;
            }
        }

        return $result;
    }

    /** @since 6.0.0 */
    private function getManufacturer(int $manufacturerId): ?object
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('m.j2commerce_manufacturer_id'),
                $db->quoteName('a.company'),
            ])
            ->from($db->quoteName('#__j2commerce_manufacturers', 'm'))
            ->join('LEFT', $db->quoteName('#__j2commerce_addresses', 'a'), $db->quoteName('m.address_id') . ' = ' . $db->quoteName('a.j2commerce_address_id'))
            ->where($db->quoteName('m.j2commerce_manufacturer_id') . ' = :manufacturerId')
            ->bind(':manufacturerId', $manufacturerId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    /** @since 6.0.0 */
    private function getArticle(int $articleId): ?object
    {
        if (isset(self::$articleCache[$articleId])) {
            return self::$articleCache[$articleId];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                'a.*',
                $db->quoteName('c.title', 'category_title'),
                $db->quoteName('c.alias', 'category_alias'),
                $db->quoteName('c.access', 'category_access'),
            ])
            ->from($db->quoteName('#__content', 'a'))
            ->join('LEFT', $db->quoteName('#__categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid'))
            ->where($db->quoteName('a.id') . ' = :articleId')
            ->bind(':articleId', $articleId, ParameterType::INTEGER);

        $db->setQuery($query);
        $article = $db->loadObject();

        self::$articleCache[$articleId] = $article ?: null;

        return self::$articleCache[$articleId];
    }

    /**
     * Save product using the behavior system via ProductService.
     *
     * This replaces the previous direct SQL approach with a behavior-based approach
     * that ensures all product types (simple, downloadable, flexivariable, etc.)
     * trigger appropriate lifecycle events and are handled consistently with
     * admin component saves.
     *
     * @param   object  $data  The product data from the article form.
     *
     * @return  bool  True on success, false on failure.
     *
     * @since   6.0.0
     * @since   6.0.11 Refactored to use ProductService with behavior system
     */
    private function saveProduct(object $data): bool
    {
        try {
            // Use ProductService directly (same pattern as ProductController and ProductHelper)
            $productService = new ProductService();

            // Save the product using the behavior system
            // The ProductService will:
            // 1. Get the appropriate behavior based on product_type
            // 2. Call onBeforeSave to validate/transform data
            // 3. Save the main product record via Table class
            // 4. Call onAfterSave to save related data (variants, options, images, filters)
            $productId = $productService->saveProduct($data);

            if ($productId === false) {
                return false;
            }

            // Update the product ID in data if needed
            $data->j2commerce_product_id = $productId;

            return true;
        } catch (\Exception $e) {
            $this->getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Soft-disable products linked to a source by setting enabled=0.
     *
     * @since   6.2.3
     */
    private function disableProductsBySource(string $source, int $sourceId): bool
    {
        try {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_products'))
                ->set($db->quoteName('enabled') . ' = 0')
                ->where($db->quoteName('product_source') . ' = :source')
                ->where($db->quoteName('product_source_id') . ' = :sourceId')
                ->bind(':source', $source)
                ->bind(':sourceId', $sourceId, ParameterType::INTEGER);
            $db->setQuery($query)->execute();

            return true;
        } catch (\Exception $e) {
            $this->getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Delete products linked to a source using the behavior system.
     *
     * This replaces the previous direct SQL approach with a behavior-based approach
     * that ensures proper cleanup of product-type-specific data (e.g., flexivariable
     * variants, downloadable files, etc.) via the onBeforeDelete behavior event.
     *
     * @param   string  $source    The product source (e.g., 'com_content').
     * @param   int     $sourceId  The source ID (e.g., article ID).
     *
     * @return  bool  True on success, false on failure.
     *
     * @since   6.0.0
     * @since   6.0.11 Refactored to use ProductService with behavior system
     */
    private function deleteProductsBySource(string $source, int $sourceId): bool
    {
        try {
            $db = $this->getDatabase();

            // Find product IDs linked to this source
            $query = $db->getQuery(true)
                ->select($db->quoteName('j2commerce_product_id'))
                ->from($db->quoteName('#__j2commerce_products'))
                ->where($db->quoteName('product_source') . ' = :source')
                ->where($db->quoteName('product_source_id') . ' = :sourceId')
                ->bind(':source', $source)
                ->bind(':sourceId', $sourceId, ParameterType::INTEGER);

            $db->setQuery($query);
            $productIds = $db->loadColumn();

            if (empty($productIds)) {
                return true;
            }

            // Use ProductService directly (same pattern as saveProduct)
            // This ensures onBeforeDelete is called for each product type
            // to properly clean up variants, options, images, etc.
            $productService = new ProductService();

            foreach ($productIds as $productId) {
                $productService->deleteProduct((int) $productId);
            }

            return true;
        } catch (\Exception $e) {
            $this->getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }
    }

    /** @since 6.0.0 */
    private function formatPrice(float $price): string
    {
        // Get default currency settings
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('currency_symbol'),
                $db->quoteName('currency_position'),
                $db->quoteName('currency_num_decimals'),
                $db->quoteName('currency_decimal'),
                $db->quoteName('currency_thousands'),
            ])
            ->from($db->quoteName('#__j2commerce_currencies'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->order($db->quoteName('j2commerce_currency_id') . ' ASC')
            ->setLimit(1);

        $db->setQuery($query);
        $currency = $db->loadObject();

        if (!$currency) {
            return number_format($price, 2, '.', ',');
        }

        $formattedPrice = number_format(
            $price,
            (int) ($currency->currency_num_decimals ?? 2),
            $currency->currency_decimal ?? '.',
            $currency->currency_thousands ?? ','
        );

        $symbol = $currency->currency_symbol ?? '$';

        if ($currency->currency_position === 'post') {
            return $formattedPrice . $symbol;
        }

        return $symbol . $formattedPrice;
    }

    /** @since 6.0.0 */
    private function escape(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Price for <option>-Text (plain text, kein HTML).
     *
     * @since   6.0.0
     */
    private function formatOptionPriceInline(array $value): string
    {
        $j2params = ComponentHelper::getParams('com_j2commerce');
        $price    = (float) ($value['product_optionvalue_price'] ?? 0);

        if (!$j2params->get('product_option_price', 1) || $price <= 0) {
            return '';
        }

        $prefix = '';
        if ($j2params->get('product_option_price_prefix', 1)) {
            $prefix = (string) ($value['product_optionvalue_prefix'] ?? '+');
        }

        return ' (' . $prefix . $this->formatPrice($price) . ')';
    }

    /**
     * Price-HTML for Radio/Checkbox-Labels with <span>.
     *
     * @since   6.0.0
     */
    private function formatOptionPriceLabel(array $value): string
    {
        $j2params = ComponentHelper::getParams('com_j2commerce');
        $price    = (float) ($value['product_optionvalue_price'] ?? 0);

        if (!$j2params->get('product_option_price', 1) || $price <= 0) {
            return '';
        }

        $prefix = (string) ($value['product_optionvalue_prefix'] ?? '+');

        return ' ' . $this->escape($prefix)
            . ' <span class="j2commerce-product-price">'
            . $this->formatPrice($price)
            . '</span>';
    }
}
