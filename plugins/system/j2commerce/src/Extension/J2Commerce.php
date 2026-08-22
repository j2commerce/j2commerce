<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_system_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\System\J2Commerce\Extension;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CartHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\SetupGuideHelper;
use J2Commerce\Component\J2commerce\Site\Context\AdminOrderCheckoutContext;
use J2Commerce\Component\J2commerce\Site\Event\CheckoutContextEvent;
use J2Commerce\Component\J2commerce\Site\Helper\ProductVisibilityHelper;
use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Event\Menu\AfterGetMenuTypeOptionsEvent;
use Joomla\CMS\Event\Result\ResultAwareInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\User\UserHelper;
use Joomla\Component\Content\Site\Helper\RouteHelper as ContentRouteHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Event\Priority;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

/**
 * J2Commerce System Plugin
 *
 *
 * @since  6.0.0
 */
class J2Commerce extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * Auto-load plugin language files
     *
     * @var    boolean
     * @since  6.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * J2Commerce version constant name
     *
     * @var    string
     * @since  6.0.0
     */
    private const VERSION_CONSTANT = 'J2COMMERCE_VERSION';

    /**
     * Component name for J2Commerce
     *
     * @var    string
     * @since  6.0.0
     */
    private const COMPONENT_NAME = 'com_j2commerce';

    /**
     * Session namespace for J2Commerce
     *
     * @var    string
     * @since  6.0.0
     */
    private const SESSION_NAMESPACE = 'j2commerce';

    /**
     * Timestamp parameter key for inventory control
     *
     * @var    string
     * @since  6.0.0
     */
    private const INVENTORY_TIMESTAMP_PARAM = 'plg_j2commerce_inventory_control_timestamp';

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   6.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterInitialise'                  => 'onAfterInitialise',
            'onAfterRoute'                       => 'onAfterRoute',
            'onAfterRender'                      => 'onAfterRender',
            'onContentPrepare'                   => 'onContentPrepare',
            'onUserLogin'                        => 'onUserLogin',
            'onBeforeCompileHead'                => ['onBeforeCompileHead', Priority::LOW],
            'onJ2CommerceAfterUpdateCart'        => 'onJ2CommerceAfterUpdateCart',
            'onJ2CommerceBeforeGetPrice'         => 'onJ2CommerceBeforeGetPrice',
            'onJ2CommerceCalculateFees'          => 'onJ2CommerceCalculateFees',
            'onJ2CommerceProcessCron'            => 'onJ2CommerceProcessCron',
            'onJ2CommerceGetDashboardMessages'   => 'onGetDashboardMessages',
            'onJ2CommerceResolveCheckoutContext' => 'onResolveCheckoutContext',
            'onAjaxJ2commerce'                   => 'onAjaxJ2commerce',
            'onPageCacheIsExcluded'              => 'onPageCacheIsExcluded',
            'onPageCacheGetKey'                  => 'onPageCacheGetKey',
            'onAfterGetMenuTypeOptions'          => 'onAfterGetMenuTypeOptions',
            'onTableAfterReset'                  => 'onTableAfterReset',
        ];
    }

    /**
     * Clear the literal default a reset leaves on a timestamp column.
     *
     * Table::reset() seeds every property from its column's declared default, so a column
     * declared `DEFAULT CURRENT_TIMESTAMP` arrives holding that word as a string rather than
     * a date. A reset runs before every load, and on a load that matches nothing the word is
     * still there at store() time, where it goes into the INSERT and the server rejects it.
     * Nothing reads the result of that store, so the row is simply never written.
     *
     * Clearing the property restores what a freshly constructed table already has: the column
     * is left out of the INSERT entirely and the server applies the default itself. It also
     * lets the `empty()` date stamping in the table classes run, which the word defeats.
     *
     * Matched on the value rather than a list of column names, because the columns carrying
     * this default are not named consistently across the schema.
     */
    public function onTableAfterReset(Event $event): void
    {
        $table = $event->getArgument('subject');

        if (!$table instanceof Table || !str_starts_with($table->getTableName(), '#__j2commerce')) {
            return;
        }

        foreach (array_keys($table->getFields()) as $field) {
            $value = $table->$field ?? null;

            // MariaDB reports this as current_timestamp(); MySQL as CURRENT_TIMESTAMP.
            if (\is_string($value) && preg_match('/^current_timestamp(\(\))?$/i', trim($value)) === 1) {
                $table->$field = null;
            }
        }
    }

    /**
     * Menu item types are discovered by scanning `components/com_j2commerce/tmpl/*` for
     * layout XML, which is pure filesystem work: no component code runs, and the only
     * language file loaded is com_j2commerce.sys. J2Commerce app plugins deploy their own
     * layout XML into that tree, so their types are offered whether or not the owning
     * plugin is installed, and their PLG_* labels render as raw keys because nothing ever
     * loads a plugin language file on a com_menus screen. This is the one hook Joomla
     * gives us to correct both.
     */
    public function onAfterGetMenuTypeOptions(AfterGetMenuTypeOptionsEvent $event): void
    {
        $items    = $event->getItems();
        $language = $this->getApplication()->getLanguage();
        $loaded   = [];
        $changed  = false;

        foreach ($items as $componentName => $options) {
            $kept = [];

            foreach ($options as $option) {
                $request = (array) ($option->request ?? []);
                $title   = (string) ($option->title ?? '');

                // Only J2Commerce's own entries are ours to judge.
                if (($request['option'] ?? '') !== self::COMPONENT_NAME || !str_starts_with($title, 'PLG_J2COMMERCE_')) {
                    $kept[] = $option;
                    continue;
                }

                $element = $this->resolveMenuTypeOwner($title);

                // No enabled owner: the files are left over from an extension this site
                // does not run, so the type must not be offered.
                if ($element === null) {
                    $changed = true;
                    continue;
                }

                if (!isset($loaded[$element])) {
                    $language->load('plg_j2commerce_' . $element, JPATH_ADMINISTRATOR)
                        || $language->load('plg_j2commerce_' . $element, JPATH_PLUGINS . '/j2commerce/' . $element);
                    $loaded[$element] = true;
                }

                $kept[] = $option;
            }

            if (\count($kept) === \count($options)) {
                continue;
            }

            if ($kept === []) {
                unset($items[$componentName]);
            } else {
                $items[$componentName] = array_values($kept);
            }
        }

        if ($changed) {
            $event->updateItems($items);
        }
    }

    /**
     * Maps a PLG_J2COMMERCE_<GROUP>_<NAME>_… label back to the j2commerce plugin element
     * that owns it, returning null when no candidate is an enabled plugin. Candidates are
     * built up segment by segment because an element may itself contain an underscore
     * (app_localization_data), and the filesystem is never consulted — a leaked layout
     * whose plugin was never installed has no folder to find.
     */
    private function resolveMenuTypeOwner(string $title): ?string
    {
        $segments   = explode('_', strtolower(substr($title, \strlen('PLG_J2COMMERCE_'))));
        $candidates = [];
        $candidate  = '';

        foreach ($segments as $index => $segment) {
            $candidate = $candidate === '' ? $segment : $candidate . '_' . $segment;

            // A bare group name ('app', 'payment') is never an element.
            if ($index === 0) {
                continue;
            }

            $candidates[] = $candidate;
        }

        // Longest first: shipping_ups and shipping_ups_global_checkout both exist, and
        // the shorter name is a prefix of the longer one. Matching shortest-first would
        // hand the longer plugin's entry to its neighbour — wrong language file, and the
        // entry kept alive on the strength of a sibling being enabled.
        foreach (array_reverse($candidates) as $element) {
            if (PluginHelper::isEnabled('j2commerce', $element)) {
                return $element;
            }
        }

        return null;
    }

    /**
     * Resolve the 'admin_order' pseudo-checkout context (admin "Take Payment" flow).
     * The HMAC grant inside the payload is re-verified by the context's validate()
     * on every request, so a stale or forged payload clears itself.
     */
    public function onResolveCheckoutContext(CheckoutContextEvent $event): void
    {
        if ($event->getResolved() !== null) {
            return;
        }

        $payload = $event->getContextPayload();

        if (($payload['provider'] ?? '') !== 'admin_order' || !class_exists(AdminOrderCheckoutContext::class)) {
            return;
        }

        $event->assignResolved(new AdminOrderCheckoutContext($payload));
    }

    /**
     * Called when Joomla is initializing.
     *
     * Triggers inventory control cron task if needed.
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function onAfterInitialise(Event $event): void
    {
        // Check if the J2Commerce component is enabled
        if (!ComponentHelper::isEnabled(self::COMPONENT_NAME)) {
            return;
        }

        $this->registerLogger();

        // Import all j2commerce group plugins so they can subscribe to
        // system events (onContentAfterSave, onAfterPayment, etc.).
        PluginHelper::importPlugin('j2commerce');

        // Load language files for j2commerce plugins that have registered
        // for early admin language loading (needed for menu item type
        // discovery and other admin contexts where plugin language isn't
        // auto-loaded by Joomla's core language loader).
        $this->loadPluginLanguageFiles();

        // Check if we need to run the inventory control cron
        if ($this->shouldRunInventoryCron()) {
            $this->runInventoryControl();
        }

    }

    /**
     * Gives the com_j2commerce log category somewhere to write.
     *
     * Log::addLogEntry() only reaches loggers registered for the entry's category, so
     * without one every Log::add(..., 'com_j2commerce') call is discarded silently.
     *
     * Global Configuration wins where it says anything: if the site logs almost
     * everything, or names this category under Log Categories, those settings already
     * cover us and their priorities and mode apply untouched. This only fills the gap
     * they leave, so a default install still has something to read after a failure --
     * logs are not retroactive, and the setting is otherwise turned on after the fact.
     */
    private function registerLogger(): void
    {
        $app = $this->getApplication();

        if ($app->get('log_everything')) {
            return;
        }

        // Same split core uses on the setting, so the two read the value identically.
        // An empty list means core registers no custom logger at all, and the mode is moot.
        $categories = preg_split('/[^\w.-]+/', (string) $app->get('log_categories', ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($categories) {
            // Category mode is core's exclude flag. In exclude mode the site is already
            // logging every category it did not name, so either we are covered or we are
            // the one it named -- and being named there is an instruction, not a gap.
            if ((bool) $app->get('log_category_mode', false)) {
                return;
            }

            if (\in_array(self::COMPONENT_NAME, $categories, true)) {
                return;
            }
        }

        // Same priority policy core gives its own always-on logger: errors always, the
        // rest only under Site Debug. Some entries are written before a request has been
        // accepted -- carts.shippingUpdate records a missing token to name the stale
        // template override behind it -- so recording every priority by default would let
        // a caller grow the file at will, and Joomla rotates logs only when asked to.
        $priorities = $app->get('debug')
            ? Log::ALL
            : Log::ERROR | Log::CRITICAL | Log::ALERT | Log::EMERGENCY;

        Log::addLogger(['text_file' => 'com_j2commerce.log.php'], $priorities, [self::COMPONENT_NAME]);
    }

    /**
     * Loads language files for j2commerce plugins that have opted in to
     * early admin language loading. Plugins register by adding their
     * extension name to the JSON registry at
     * administrator/components/com_j2commerce/language_registry.json
     * via their installer scripts.
     *
     * This enables translated strings for plugin-registered menu item
     * types, admin views, and other contexts where Joomla only loads the
     * component's language by default.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    private function loadPluginLanguageFiles(): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('administrator')) {
            return;
        }

        $lang = $app->getLanguage();

        // The component installs its language into its own folder, not administrator/language,
        // so the first load only succeeds where a translation pack happens to have written a
        // copy there -- never for English. Fall back the way ComponentDispatcher::loadLanguage()
        // does, otherwise COM_J2COMMERCE_* keys used by plugin manifests render as raw keys on
        // every admin page outside the component, the Plugins manager included.
        $lang->load('com_j2commerce', JPATH_ADMINISTRATOR)
            || $lang->load('com_j2commerce', JPATH_ADMINISTRATOR . '/components/com_j2commerce');

        $registryFile = JPATH_ADMINISTRATOR . '/components/com_j2commerce/language_registry.json';

        if (!is_file($registryFile)) {
            return;
        }

        $extensions = json_decode((string) file_get_contents($registryFile), true);

        if (empty($extensions) || !\is_array($extensions)) {
            return;
        }

        foreach ($extensions as $extension) {
            $lang->load($extension . '.sys', JPATH_ADMINISTRATOR);
        }
    }

    /**
     * Called after Joomla has routed the request.
     *
     * Injects the j2commerceURL JavaScript variable and handles coupon codes.
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function onAfterRoute(Event $event): void
    {
        // Check if the J2Commerce component is enabled
        if (!ComponentHelper::isEnabled(self::COMPONENT_NAME)) {
            return;
        }

        $app = $this->getApplication();

        if ($app === null) {
            return;
        }

        // Add j2commerceURL JavaScript variable for AJAX calls
        $this->addBaseUrlScript();

        // Handle coupon codes from URL on frontend only
        if ($app->isClient('site')) {
            $this->handleUrlCouponCode();
            $this->loadJ2CommercePluginLanguages();
            $this->redirectToPreferredProductSurface();
        }
    }

    /**
     * Called after Joomla has rendered the page.
     *
     * Adds J2Commerce version class to body tag on frontend J2Commerce pages.
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function onAfterRender(Event $event): void
    {
        $app = $this->getApplication();

        if ($app === null) {
            return;
        }

        if ($app->isClient('administrator')) {
            $this->injectPluginReturnField($app);

            return;
        }

        if (!$app->isClient('site')) {
            return;
        }

        $input = $app->getInput();

        if ($input->get('option', '') !== self::COMPONENT_NAME) {
            return;
        }

        if (!\defined(self::VERSION_CONSTANT)) {
            return;
        }

        $enableVersionClass = (int) $this->params->get('enable_version_class', 1);

        if ($enableVersionClass !== 1) {
            return;
        }

        $body          = $app->getBody();
        $version       = \constant(self::VERSION_CONSTANT);
        $classAddition = 'j2c-' . str_replace('.', '-', $version);

        // When rendered inside tmpl=component (Fancybox quickview iframe), the Cassiopeia
        // component.php template sets <body class="contentpane component"> without the
        // option-based class. Add com_j2commerce so CSS rules scoped to .com_j2commerce apply.
        if ($input->getCmd('tmpl') === 'component') {
            $body = $this->addBodyClass($body, 'com_j2commerce');
        }

        $body = $this->addBodyClass($body, $classAddition);
        $app->setBody($body);
    }

    /**
     * Inject a hidden return field into com_plugins edit form so Save & Close / Close
     * redirects back to the J2Commerce apps view instead of the plugins list.
     */
    private function injectPluginReturnField($app): void
    {
        $input = $app->getInput();

        if ($input->get('option') !== 'com_plugins' || $input->get('view') !== 'plugin') {
            return;
        }

        $return = $input->get('return', '', 'base64');

        if (empty($return)) {
            return;
        }

        $decoded = base64_decode($return);

        if (!$decoded || strpos($decoded, 'com_j2commerce') === false) {
            return;
        }

        $body   = $app->getBody();
        $hidden = '<input type="hidden" name="return" value="' . htmlspecialchars($return, ENT_QUOTES, 'UTF-8') . '">';
        $body   = str_replace('</form>', $hidden . '</form>', $body);
        $app->setBody($body);
    }

    /**
     * Called when content is being prepared.
     *
     * Bridges Joomla's content prepare event to J2Commerce internal events.
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function onContentPrepare(Event $event): void
    {
        if (!ComponentHelper::isEnabled(self::COMPONENT_NAME)) {
            return;
        }

        $context = $event->getArgument(0, '');
        $article = $event->getArgument(1, null);
        $params  = $event->getArgument(2, null);

        $app = $this->getApplication();

        if ($app === null) {
            return;
        }

        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        $j2Event    = new Event(
            'onJ2CommerceContentPrepare',
            [
                'context' => $context,
                'article' => $article,
                'params'  => $params,
            ]
        );

        $dispatcher->dispatch('onJ2CommerceContentPrepare', $j2Event);
    }

    /**
     * Called when a user logs in.
     *
     * Migrates guest cart to user cart on login.
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function onUserLogin(Event $event): void
    {
        $app = $this->getApplication();

        if ($app === null) {
            return;
        }

        // Skip administrator logins
        if ($app->isClient('administrator')) {
            return;
        }

        // Check if the J2Commerce component is enabled
        if (!ComponentHelper::isEnabled(self::COMPONENT_NAME)) {
            return;
        }

        // Get user data from event
        $user = $event->getArgument(0, []);

        if (empty($user['username'])) {
            return;
        }

        $session = Factory::getApplication()->getSession();
        $userId  = (int) UserHelper::getUserId($user['username']);

        if ($userId === 0) {
            return;
        }

        // Migrate cart to logged-in user
        $this->migrateCartOnLogin($userId, $session->getId());
    }

    /**
     * Called after cart is updated.
     *
     * Clears page cache to ensure cart displays current data.
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function onJ2CommerceAfterUpdateCart(Event $event): void
    {
        // Check if cache clearing is enabled
        $enableCacheClear = (int) $this->params->get('enable_cart_cache_clear', 1);

        if ($enableCacheClear !== 1) {
            return;
        }

        // Get system cache plugin
        $plugin = PluginHelper::getPlugin('system', 'cache');

        if (!$plugin) {
            return;
        }

        $pluginParams = new Registry($plugin->params ?? '{}');
        $options      = [
            'defaultgroup' => 'page',
            'browsercache' => $pluginParams->get('browsercache', false),
            'caching'      => false,
        ];


        $cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController('page', $options);
        $cache->clean();
    }

    /**
     * Called before getting product price.
     *
     * Handles user group pricing for the admin views product for specific user.
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function onJ2CommerceBeforeGetPrice(Event $event): void
    {
        $app = $this->getApplication();

        if ($app === null) {
            return;
        }

        $input  = $app->getInput();
        $userId = $input->getInt('user_id', 0);

        if ($userId === 0) {
            return;
        }

        $view = $input->get('view', '');
        $task = $input->get('task', '');

        // Only apply in the admin context for specific views/tasks
        $adminTasks = [];
        $adminViews = ['products'];

        if (!\in_array($task, $adminTasks, true) || !\in_array($view, $adminViews, true)) {
            return;
        }

        // Get calculator from event arguments
        $calculator = $event->getArgument('calculator');

        if ($calculator === null) {
            return;
        }

        // Get user's group IDs
        $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
        $user        = $userFactory->loadUserById((int) $userId);
        $groupIds    = implode(',', Access::getGroupsByUser($user->id));

        // Set group ID on calculator if it has the method
        if (\is_object($calculator) && method_exists($calculator, 'setGroupId')) {
            $calculator->setGroupId($groupIds);
        } elseif (\is_object($calculator) && method_exists($calculator, 'set')) {
            $calculator->set('group_id', $groupIds);
        }
    }

    /**
     * Called when calculating order fees.
     *
     * Loads and applies saved order fees in admin order view.
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function onJ2CommerceCalculateFees(Event $event): void
    {
        $app = $this->getApplication();

        if ($app === null) {
            return;
        }

        // Only in admin context
        if (!$app->isClient('administrator')) {
            return;
        }

        $input  = $app->getInput();
        $option = $input->get('option', '');

        if ($option !== self::COMPONENT_NAME) {
            return;
        }

        $view       = $input->get('view', '');
        $orderViews = ['orders', 'order'];

        if (!\in_array($view, $orderViews, true)) {
            return;
        }

        // Get order from event arguments
        $order = $event->getArgument('order');

        if ($order === null) {
            return;
        }

        // Check order type
        if (!isset($order->order_type) || $order->order_type !== 'normal') {
            return;
        }

        // Load order fees from database
        $this->loadOrderFees($order);
    }

    /**
     * Called for J2Commerce cron tasks.
     *
     * Executes the inventory control task.
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function onJ2CommerceProcessCron(Event $event): void
    {
        $command = $event->getArgument('command', '');

        if ($command !== 'inventorycontrol') {
            return;
        }

        $this->runInventoryControl();
    }

    /**
     * Adds the j2commerceURL JavaScript variable for AJAX calls.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function addBaseUrlScript(): void
    {
        $app = $this->getApplication();

        if ($app === null) {
            return;
        }

        try {
            $document = $app->getDocument();

            // Document may be null during early lifecycle or non-HTML contexts
            if ($document === null) {
                return;
            }

            // Only add scripts for HTML documents
            if (!method_exists($document, 'getWebAssetManager')) {
                return;
            }

            $wa      = $document->getWebAssetManager();
            $baseUrl = Uri::root();

            // Add J2Commerce URL variable for JavaScript AJAX calls
            $script = "var j2commerceURL = '{$baseUrl}';";
            $wa->addInlineScript($script);
        } catch (\Exception $e) {
            // Silently fail if the document is not available (e.g., CLI or JSON format)
        }
    }

    /**
     * Handles coupon codes passed via URL parameter.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function handleUrlCouponCode(): void
    {
        $app = $this->getApplication();

        if ($app === null) {
            return;
        }

        $coupon = $app->getInput()->getString('coupon', '');

        if (empty($coupon)) {
            return;
        }

        // Store coupon in session for later application
        // Note: Full coupon application requires CouponsModel to be implemented
        $session = Factory::getApplication()->getSession();
        $session->set('pending_coupon', $coupon, self::SESSION_NAMESPACE);

        // Attempt to apply coupon via component model if available
        try {
            $component  = $app->bootComponent(self::COMPONENT_NAME);
            $mvcFactory = $component->getMVCFactory();
            $model      = $mvcFactory->createModel('Coupons', 'Site', ['ignore_request' => true]);

            if ($model !== null && method_exists($model, 'setCoupon')) {
                $model->setCoupon($coupon);
            }
        } catch (\Exception $e) {
            // Model not yet implemented, coupon stored in session for later
        }
    }

    private function loadJ2CommercePluginLanguages(): void
    {
        $plugins = PluginHelper::getPlugin('j2commerce');
        $lang    = $this->getApplication()->getLanguage();

        foreach ($plugins as $plugin) {
            $extension = 'plg_j2commerce_' . $plugin->name;
            $lang->load($extension, JPATH_ADMINISTRATOR)
                || $lang->load($extension, JPATH_PLUGINS . '/j2commerce/' . $plugin->name);
        }
    }

    /**
     * Adds a CSS class to the body tag.
     *
     * @param   string  $body       The HTML body content
     * @param   string  $className  The class name to add
     *
     * @return  string  The modified body content
     *
     * @since   6.0.0
     */
    private function addBodyClass(string $body, string $className): string
    {
        $className = trim($className);

        if ($className === '') {
            return $body;
        }

        // Match <body ... class="..." ...> or class='...'
        if (preg_match('/<body\b[^>]*\bclass=(["\'])(.*?)\1/i', $body, $m)) {
            $quote    = $m[1];
            $existing = trim($m[2]);

            // Prevent duplicates
            $classes = preg_split('/\s+/', $existing, -1, PREG_SPLIT_NO_EMPTY);

            if (!\in_array($className, $classes, true)) {
                $classes[] = $className;
            }

            $newClass = implode(' ', $classes);

            // Replace only the class attribute value, preserving quote type
            return preg_replace(
                '/(<body\b[^>]*\bclass=)(["\'])(.*?)(\2)/i',
                '$1' . $quote . $newClass . $quote,
                $body,
                1
            );
        }

        // No class attribute at all → add one
        return preg_replace(
            '/<body\b([^>]*)>/i',
            '<body$1 class="' . $className . '">',
            $body,
            1
        );
    }


    /**
     * Migrates cart from guest session to logged-in user.
     *
     * Uses the persistent cart cookie (set when the guest cart was created) to locate
     * and claim the guest cart. The cookie survives Joomla's session regeneration on
     * login, making it the reliable bridge between guest and authenticated state.
     *
     * @param   int     $userId          The user ID
     * @param   string  $newSessionId    The current session ID
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function migrateCartOnLogin(int $userId, string $newSessionId): void
    {
        $db = $this->getDatabase();

        try {
            // Locate the guest cart via the persistent cookie (30-day browser cookie that
            // survives Joomla's session regeneration on login).
            $app          = $this->getApplication();
            $rawCookie    = (string) $app->getInput()->cookie->getString('j2commerce_cart_id', '');
            $guestCart    = null;

            if ($rawCookie !== '') {
                $cookieCartId = $this->verifyCartCookieId($rawCookie, (string) $app->get('secret', ''));

                if ($cookieCartId > 0) {
                    $guestCartQuery = $db->getQuery(true)
                        ->select('*')
                        ->from($db->quoteName('#__j2commerce_carts'))
                        ->where($db->quoteName('j2commerce_cart_id') . ' = :cartId')
                        ->where($db->quoteName('user_id') . ' = 0')
                        ->bind(':cartId', $cookieCartId, ParameterType::INTEGER)
                        ->setLimit(1);

                    $db->setQuery($guestCartQuery);
                    $guestCart = $db->loadObject() ?: null;
                }
            }

            // Find the user's existing cart (if any).
            $userCartQuery = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__j2commerce_carts'))
                ->where($db->quoteName('user_id') . ' = :userId')
                ->where($db->quoteName('cart_type') . ' = ' . $db->quote('cart'))
                ->bind(':userId', $userId, ParameterType::INTEGER)
                ->setLimit(1);

            $db->setQuery($userCartQuery);
            $userCart = $db->loadObject() ?: null;

            $cartHelper = CartHelper::getInstance();

            if ($guestCart !== null && $userCart !== null) {
                // Both carts exist: merge guest items into the user's existing cart so
                // only one cart remains.  Claiming the guest cart raw would leave two
                // user-owned carts, and only one is cleared on order placement —
                // causing "ghost" items to reappear from the survivor.
                $cartHelper->updateCartitemEntry($guestCart, $userCart);

                $guestCartId = (int) $guestCart->j2commerce_cart_id;

                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName('#__j2commerce_carts'))
                        ->where($db->quoteName('j2commerce_cart_id') . ' = :cartId')
                        ->bind(':cartId', $guestCartId, ParameterType::INTEGER)
                );
                $db->execute();
            } elseif ($guestCart !== null) {
                // No existing user cart: claim the guest cart.
                $guestCartId = (int) $guestCart->j2commerce_cart_id;

                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__j2commerce_carts'))
                        ->set($db->quoteName('user_id') . ' = :userId')
                        ->set($db->quoteName('session_id') . ' = :newSessionId')
                        ->where($db->quoteName('j2commerce_cart_id') . ' = :cartId')
                        ->bind(':userId', $userId, ParameterType::INTEGER)
                        ->bind(':newSessionId', $newSessionId)
                        ->bind(':cartId', $guestCartId, ParameterType::INTEGER)
                );
                $db->execute();
            }

            // Sync the session ID on whichever cart the user now owns.
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_carts'))
                    ->set($db->quoteName('session_id') . ' = :newSessionId')
                    ->where($db->quoteName('user_id') . ' = :userId')
                    ->bind(':newSessionId', $newSessionId)
                    ->bind(':userId', $userId, ParameterType::INTEGER)
            );
            $db->execute();
        } catch (\Exception $e) {
            // Silently fail if cart table doesn't exist yet or other DB error
            // This allows the plugin to work during initial migration
        }

        // The unavailable-item list CartModel::getItems() keeps was built against the
        // guest's view levels, and logging in widens them. Drop it so the loader decides
        // afresh rather than suppressing a message this identity has not been shown.
        $this->getApplication()->getSession()->clear('unavailable_announced', 'j2commerce');
    }

    /**
     * Verifies an HMAC-signed cart cookie value and returns the cart ID, or 0 on failure.
     *
     * Cookie format: "<cartId>.<sha256-hmac>"
     *
     * @param   string  $raw     Raw cookie value.
     * @param   string  $secret  Joomla site secret.
     *
     * @return  int  Cart ID, or 0 if the signature is invalid.
     *
     * @since   6.0.0
     */
    private function verifyCartCookieId(string $raw, string $secret): int
    {
        if (!preg_match('/^(\d+)\.([0-9a-f]{64})$/', $raw, $m)) {
            return 0;
        }

        $expected = hash_hmac('sha256', $m[1], $secret);

        return hash_equals($expected, $m[2]) ? (int) $m[1] : 0;
    }

    /**
     * Checks if the inventory control cron should run.
     *
     * @return  bool  True if cron should run, false otherwise
     *
     * @since   6.0.0
     */
    private function shouldRunInventoryCron(): bool
    {
        // Check if cron is enabled in plugin params
        $enableCron = (int) $this->params->get('enable_cron', 1);

        if ($enableCron !== 1) {
            return false;
        }

        $params      = ComponentHelper::getParams(self::COMPONENT_NAME);
        $lastRunUnix = (int) $params->get(self::INVENTORY_TIMESTAMP_PARAM, 0);

        if ($lastRunUnix === 0) {
            return true;
        }

        $dateInfo    = getdate($lastRunUnix);
        $nextRunUnix = mktime(0, 0, 0, $dateInfo['mon'], $dateInfo['mday'], $dateInfo['year']);
        $nextRunUnix += 24 * 3600; // Add 24 hours

        return time() >= $nextRunUnix;
    }

    /**
     * Runs the inventory control task.
     *
     * Cancels unpaid orders past their deadline and updates inventory.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function runInventoryControl(): void
    {
        $params = ComponentHelper::getParams(self::COMPONENT_NAME);

        // Check if inventory control is enabled
        if ((int) $params->get('enable_inventory', 0) !== 1) {
            return;
        }

        // Check if order cancellation is enabled
        if ((int) $params->get('cancel_order', 0) !== 1) {
            return;
        }

        // Stamp the run before doing the work, not after. This runs from onAfterInitialise,
        // so any request — including an anonymous front-end hit — reaches it, and several
        // arriving as the window opens would otherwise all pass shouldRunInventoryCron()
        // and all sweep the same orders. Stamping first closes that window; the status
        // compare-and-swap in OrderModel::updateOrderStatus() covers what still slips
        // through. A sweep that then fails costs at most one skipped daily run.
        $this->updateLastRunTimestamp();

        // Attempt to cancel unpaid orders via OrdersModel
        try {
            $app = $this->getApplication();

            if ($app === null) {
                return;
            }

            $component  = $app->bootComponent(self::COMPONENT_NAME);
            $mvcFactory = $component->getMVCFactory();
            $model      = $mvcFactory->createModel('Orders', 'Administrator', ['ignore_request' => true]);

            if ($model !== null && method_exists($model, 'cancelUnpaidOrders')) {
                $model->cancelUnpaidOrders();
            }
        } catch (\Exception $e) {
            // Model not yet implemented, skip for now
        }
    }

    /**
     * Updates the timestamp of the last inventory control run.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function updateLastRunTimestamp(): void
    {
        $lastRun = time();
        $params  = ComponentHelper::getParams(self::COMPONENT_NAME);
        $params->set(self::INVENTORY_TIMESTAMP_PARAM, $lastRun);

        $db   = $this->getDatabase();
        $data = $params->toString();

        $element = self::COMPONENT_NAME;
        $type    = 'component';

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('element') . ' = :element')
            ->where($db->quoteName('type') . ' = :type')
            ->bind(':params', $data)
            ->bind(':element', $element)
            ->bind(':type', $type);

        $db->setQuery($query);

        try {
            $db->execute();
        } catch (\Exception $e) {
            // Silently fail if unable to update timestamp
        }
    }

    /**
     * Loads order fees from database and adds them to the order object.
     *
     * @param   object  $order  The order object
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function loadOrderFees(object $order): void
    {
        if (!isset($order->order_id)) {
            return;
        }

        $db      = $this->getDatabase();
        $orderId = $order->order_id;

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_orderfees'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);

        $db->setQuery($query);

        try {
            $fees = $db->loadObjectList();

            if (empty($fees)) {
                return;
            }

            foreach ($fees as $fee) {
                // Add fee to order if the method exists
                if (method_exists($order, 'addFee')) {
                    $order->addFee(
                        $fee->name ?? '',
                        (float) ($fee->amount ?? 0),
                        (bool) ($fee->taxable ?? false),
                        (int) ($fee->tax_class_id ?? 0)
                    );
                } elseif (method_exists($order, 'add_fee')) {
                    // Legacy method support
                    $order->add_fee(
                        $fee->name ?? '',
                        $fee->amount ?? 0,
                        $fee->taxable ?? 0,
                        $fee->tax_class_id ?? 0
                    );
                }
            }
        } catch (\Exception $e) {
            // Silently fail if the table doesn't exist or other DB error
        }
    }

    public function onGetDashboardMessages(Event $event): void
    {
        $params = ComponentHelper::getParams(self::COMPONENT_NAME);
        $result = $event->getArgument('result', []);

        if (empty($params->get('downloadid', ''))) {
            $result[] = [
                'id'          => 'j2commerce_download_id',
                'text'        => Text::_('COM_J2COMMERCE_DOWNLOAD_ID_DESC'),
                'type'        => 'danger',
                'icon'        => 'fa-solid fa-fingerprint',
                'dismissible' => 'none',
                'link'        => Route::_('index.php?option=com_config&view=component&component=com_j2commerce'),
                'linkText'    => Text::_('COM_J2COMMERCE_DOWNLOAD_ID_BTN'),
                'priority'    => 100,
            ];
        }

        if (!SetupGuideHelper::isComplete()) {
            $progress  = SetupGuideHelper::getProgress();
            $remaining = $progress['total'] - $progress['passed'];
            $percent   = $progress['percent'];

            [$type, $langKey] = match (true) {
                $percent < 33  => ['danger',  'COM_J2COMMERCE_SETUP_GUIDE_MSG_LOW'],
                $percent <= 66 => ['warning', 'COM_J2COMMERCE_SETUP_GUIDE_MSG_MEDIUM'],
                default        => ['info',    'COM_J2COMMERCE_SETUP_GUIDE_MSG_HIGH'],
            };

            $result[] = [
                'id'          => 'j2commerce_setup_guide',
                'text'        => Text::sprintf($langKey, $remaining),
                'type'        => $type,
                'icon'        => 'fa-solid fa-wand-magic-sparkles',
                'dismissible' => 'session',
                'link'        => '#',
                'linkText'    => Text::_('COM_J2COMMERCE_SETUP_GUIDE_VIEW_TASKS'),
                'priority'    => 50,
            ];
        }

        $user = $this->getApplication()->getIdentity();

        if ($user !== null && $user->authorise('core.edit.state', 'com_menus')) {
            try {
                foreach ($this->getOrphanedJ2CommerceMenuItems() as $orphan) {
                    $result[] = [
                        'id'   => 'j2commerce_orphan_menu_' . (int) $orphan->id,
                        'text' => Text::sprintf(
                            'COM_J2COMMERCE_ORPHAN_MENU_DESC',
                            htmlspecialchars((string) $orphan->title, ENT_QUOTES, 'UTF-8'),
                            htmlspecialchars((string) $orphan->view, ENT_QUOTES, 'UTF-8')
                        ),
                        'type'        => 'danger',
                        'icon'        => 'fa-solid fa-link-slash',
                        'dismissible' => 'none',
                        'link'        => Route::_(
                            'index.php?option=com_ajax&group=system&plugin=j2commerce&format=json'
                            . '&j2c_task=unpublishmenu&id=' . (int) $orphan->id . '&' . Session::getFormToken() . '=1'
                        ),
                        'linkText' => Text::_('COM_J2COMMERCE_ORPHAN_MENU_UNPUBLISH'),
                        'priority' => 100,
                    ];
                }
            } catch (\Throwable $e) {
                // Fail silently — an orphan-menu detection error must never break the dashboard.
            }

            try {
                foreach ($this->getMissingRequiredMenuViews() as $required) {
                    $result[] = [
                        'id'   => 'j2commerce_required_menu_' . preg_replace('/[^a-z0-9_]/', '', strtolower($required->view)),
                        'text' => Text::sprintf(
                            'COM_J2COMMERCE_REQUIRED_MENU_DESC',
                            htmlspecialchars($required->label, ENT_QUOTES, 'UTF-8'),
                            htmlspecialchars($required->view, ENT_QUOTES, 'UTF-8')
                        ),
                        'type'        => 'warning',
                        'icon'        => 'fa-solid fa-link',
                        'dismissible' => 'session',
                        'link'        => Route::_('index.php?option=com_menus&view=items&client_id=0'),
                        'linkText'    => Text::_('COM_J2COMMERCE_REQUIRED_MENU_MANAGE'),
                        'priority'    => 90,
                    ];
                }
            } catch (\Throwable $e) {
                // Fail silently — required-menu detection must never break the dashboard.
            }

            try {
                foreach ($this->getProductArticleMenuItems() as $menuItem) {
                    $result[] = [
                        'id'   => 'j2commerce_product_article_menu_' . (int) $menuItem->id,
                        'text' => Text::sprintf(
                            'COM_J2COMMERCE_PRODUCT_SURFACE_MENU_DESC',
                            htmlspecialchars((string) $menuItem->title, ENT_QUOTES, 'UTF-8')
                        ),
                        'type'        => 'warning',
                        'icon'        => 'fa-solid fa-signs-post',
                        'dismissible' => 'session',
                        'link'        => Route::_('index.php?option=com_menus&view=item&layout=edit&id=' . (int) $menuItem->id),
                        'linkText'    => Text::_('COM_J2COMMERCE_PRODUCT_SURFACE_MENU_EDIT'),
                        'priority'    => 80,
                    ];
                }
            } catch (\Throwable $e) {
                // Fail silently — surface detection must never break the dashboard.
            }
        }

        $event->setArgument('result', $result);
    }

    /**
     * Returns published site menu items pointing at com_j2commerce views that no
     * currently-enabled extension can serve (disabled or uninstalled plugin).
     *
     * @return  array<int, object{id: int, title: string, view: string}>
     *
     * @since   6.0.4
     */
    private function getOrphanedJ2CommerceMenuItems(): array
    {
        $links = $this->getPublishedJ2CommerceMenuLinks();

        if ($links === []) {
            return [];
        }

        $servableViews = $this->getServableJ2CommerceViews();

        return array_values(array_filter(
            $links,
            static fn (object $link): bool => !\in_array($link->view, $servableViews, true)
        ));
    }

    /**
     * Published site component menu items pointing at a com_j2commerce view.
     *
     * @return  array<int, object{id: int, title: string, view: string}>
     */
    private function getPublishedJ2CommerceMenuLinks(): array
    {
        $db        = $this->getDatabase();
        $like      = '%option=com_j2commerce%';
        $clientId  = 0;
        $published = 1;
        $type      = 'component';

        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'title', 'link']))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('client_id') . ' = :clientId')
            ->where($db->quoteName('published') . ' = :published')
            ->where($db->quoteName('type') . ' = :type')
            ->where($db->quoteName('link') . ' LIKE :link')
            ->bind(':clientId', $clientId, ParameterType::INTEGER)
            ->bind(':published', $published, ParameterType::INTEGER)
            ->bind(':type', $type)
            ->bind(':link', $like);

        $db->setQuery($query);
        $links = [];

        foreach ($db->loadObjectList() ?: [] as $row) {
            parse_str((string) parse_url((string) $row->link, PHP_URL_QUERY), $queryParams);

            if (($queryParams['option'] ?? '') !== 'com_j2commerce' || empty($queryParams['view'])) {
                continue;
            }

            $links[] = (object) [
                'id'    => (int) $row->id,
                'title' => (string) $row->title,
                'view'  => (string) $queryParams['view'],
            ];
        }

        return $links;
    }

    /**
     * Published article menu items whose article backs a product, while the store
     * publishes products on the product surface — the redirect leaves these alone,
     * so the owner has to convert them deliberately.
     *
     * @return  array<int, object{id: int, title: string}>
     */
    private function getProductArticleMenuItems(): array
    {
        if ($this->getProductSurfaceMode() !== 'product') {
            return [];
        }

        $db        = $this->getDatabase();
        $like      = '%option=com_content%';
        $clientId  = 0;
        $published = 1;
        $type      = 'component';

        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'title', 'link']))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('client_id') . ' = :clientId')
            ->where($db->quoteName('published') . ' = :published')
            ->where($db->quoteName('type') . ' = :type')
            ->where($db->quoteName('link') . ' LIKE :link')
            ->bind(':clientId', $clientId, ParameterType::INTEGER)
            ->bind(':published', $published, ParameterType::INTEGER)
            ->bind(':type', $type)
            ->bind(':link', $like);

        $db->setQuery($query);

        $candidates = [];

        foreach ($db->loadObjectList() ?: [] as $row) {
            parse_str((string) parse_url((string) $row->link, PHP_URL_QUERY), $queryParams);

            if (($queryParams['option'] ?? '') !== 'com_content' || ($queryParams['view'] ?? '') !== 'article') {
                continue;
            }

            $articleId = (int) ($queryParams['id'] ?? 0);

            if ($articleId > 0) {
                $candidates[$articleId][] = (object) [
                    'id'    => (int) $row->id,
                    'title' => (string) $row->title,
                ];
            }
        }

        if ($candidates === []) {
            return [];
        }

        $source = 'com_content';
        $query  = $db->getQuery(true)
            ->select($db->quoteName('product_source_id'))
            ->from($db->quoteName('#__j2commerce_products'))
            ->where($db->quoteName('product_source') . ' = :source')
            ->whereIn($db->quoteName('product_source_id'), array_keys($candidates))
            ->bind(':source', $source);

        $db->setQuery($query);

        $items = [];

        foreach ($db->loadColumn() ?: [] as $articleId) {
            foreach ($candidates[(int) $articleId] ?? [] as $menuItem) {
                $items[] = $menuItem;
            }
        }

        return $items;
    }

    /**
     * Views an enabled plugin declared as needing a published site menu item (via
     * onJ2CommerceGetRequiredMenuViews — entries of ['view' => ..., 'label' => ...])
     * that no published com_j2commerce menu item currently links to.
     *
     * @return  array<int, object{view: string, label: string}>
     */
    private function getMissingRequiredMenuViews(): array
    {
        PluginHelper::importPlugin('j2commerce');

        $event = new Event('onJ2CommerceGetRequiredMenuViews', ['result' => []]);
        Factory::getContainer()->get(DispatcherInterface::class)->dispatch('onJ2CommerceGetRequiredMenuViews', $event);

        $required = [];

        foreach ((array) $event->getArgument('result', []) as $entry) {
            $entry = (object) $entry;
            $view  = trim((string) ($entry->view ?? ''));

            if ($view === '' || isset($required[$view])) {
                continue;
            }

            $required[$view] = (object) [
                'view'  => $view,
                'label' => trim((string) ($entry->label ?? '')) ?: $view,
            ];
        }

        if ($required === []) {
            return [];
        }

        $linkedViews = array_column($this->getPublishedJ2CommerceMenuLinks(), 'view');

        return array_values(array_filter(
            $required,
            static fn (object $r): bool => !\in_array($r->view, $linkedViews, true)
        ));
    }

    /**
     * Builds the set of com_j2commerce view names an enabled extension can serve —
     * the static core views (mirrored from components/com_j2commerce/src/Service/Router.php)
     * plus any view registered by enabled j2commerce plugins via onJ2CommerceRegisterRouterViews.
     *
     * @return  array<int, string>
     *
     * @since   6.0.4
     */
    private function getServableJ2CommerceViews(): array
    {
        $views = [
            'categories', 'category', 'products', 'product', 'producttags',
            'dashboard', 'carts', 'checkout', 'myprofile', 'confirmation',
            'paymentupdate', 'categoryalias',
        ];

        try {
            PluginHelper::importPlugin('j2commerce');

            $collector = new class () {
                public array $names = [];

                public function registerView(RouterViewConfiguration $cfg): void
                {
                    $this->names[] = $cfg->name;
                }
            };

            $registerEvent = new Event('onJ2CommerceRegisterRouterViews', ['router' => $collector]);
            Factory::getContainer()->get(DispatcherInterface::class)->dispatch('onJ2CommerceRegisterRouterViews', $registerEvent);

            $views = array_merge($views, $collector->names);
        } catch (\Throwable $e) {
            // A misbehaving plugin must not break dashboard orphan detection.
        }

        return $views;
    }

    /**
     * Keeps a shopper's own cart contents out of the shared page cache.
     *
     * Joomla's page cache only stores guest responses, but every guest shares one cache entry per
     * URL — so a basket rendered into the body by any cart layout would be replayed verbatim to
     * the next visitor. URL and menu-item exclusions cannot cover this: a cart module can be
     * published on the home page or a product listing, which are legitimately cacheable.
     *
     * Excluding only non-empty carts keeps the common case cacheable. An empty-cart page may still
     * be replayed to a shopper who has items, which is a stale render, not a disclosure — the
     * minicart placeholders hydrate it back to the correct count.
     *
     * Store-path only: core calls isExcluded() from onAfterRender, never when serving. Entries
     * cached before this shipped keep being served, so the cache must be purged after upgrading.
     */
    private const UNCACHEABLE_VIEWS = ['carts', 'checkout', 'confirmation', 'myprofile', 'orders', 'paymentupdate'];

    /**
     * Adds the active currency to the page-cache key.
     *
     * Prices are rendered in the currency held in the visitor's session, so a single entry
     * per URL would serve one visitor's currency to the next. Contributing the code gives
     * each currency its own entry instead of excluding the page from caching altogether.
     */
    public function onPageCacheGetKey(Event $event): void
    {
        if (!($event instanceof ResultAwareInterface) || !ComponentHelper::isEnabled(self::COMPONENT_NAME)) {
            return;
        }

        try {
            // The event is string-typed: a non-string result throws.
            $event->addResult('j2c.currency=' . (string) CurrencyHelper::getCode());
        } catch (\Throwable $e) {
            Log::add('Page cache key currency failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
        }
    }

    public function onPageCacheIsExcluded(Event $event): void
    {
        if (!ComponentHelper::isEnabled(self::COMPONENT_NAME)) {
            return;
        }

        if (!$this->requestCarriesShopperState()) {
            return;
        }

        // Core reads this with in_array($results, true, true) — a strict boolean is required.
        if ($event instanceof ResultAwareInterface) {
            $event->addResult(true);
        }
    }

    private function requestCarriesShopperState(): bool
    {
        $app = $this->getApplication();

        // View first: the account surface renders a named customer's addresses, phone and order
        // history even when the cart is empty, so a cart-only test would miss it entirely. The
        // views' own sendNoCacheHeaders() does not help — the page cache stores the body without
        // ever inspecting response headers.
        // Lowercased before comparison: the view name reaches the dispatcher through
        // ucfirst(), so a request for Myprofile resolves the same class as myprofile and
        // must be treated the same here.
        $view = strtolower((string) $app->getInput()->getCmd('view', ''));

        if (\in_array($view, self::UNCACHEABLE_VIEWS, true)
            && $app->getInput()->getCmd('option', '') === self::COMPONENT_NAME) {
            return true;
        }

        // A guest holding a looked-up order token can reach that same order detail from elsewhere.
        if ((string) $app->getSession()->get('guest_order_token', '', self::SESSION_NAMESPACE) !== '') {
            return true;
        }

        try {
            return CartHelper::getCartItemCount() > 0;
        } catch (\Throwable $e) {
            Log::add('Page cache exclusion check failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');

            // Fail closed — never cache a page whose shopper state could not be determined.
            return true;
        }
    }

    /** Site: the read-only customerState probe. Administrator: unpublishes one orphaned menu item. */
    public function onAjaxJ2commerce(Event $event): void
    {
        $app = $this->getApplication();

        if ($app->isClient('site')) {
            if ($app->getInput()->getCmd('j2c_task', '') === 'customerState'
                && $app->getInput()->getMethod() === 'GET'
                && ComponentHelper::isEnabled(self::COMPONENT_NAME)) {
                $this->handleCustomerStateAjax();
            }

            return;
        }

        if (!$app->isClient('administrator')) {
            return;
        }

        $dashboardUrl = Route::_('index.php?option=com_j2commerce&view=dashboard', false);

        if (!Session::checkToken('get')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'warning');
            $app->redirect($dashboardUrl);

            return;
        }

        $user = $app->getIdentity();

        if ($user === null || !$user->authorise('core.edit.state', 'com_menus')) {
            $app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'warning');
            $app->redirect($dashboardUrl);

            return;
        }

        $id = $app->getInput()->getInt('id', 0);

        try {
            $isOrphan = false;

            foreach ($this->getOrphanedJ2CommerceMenuItems() as $orphan) {
                if ($orphan->id === $id) {
                    $isOrphan = true;

                    break;
                }
            }

            if ($id <= 0 || !$isOrphan) {
                $app->enqueueMessage(Text::_('COM_J2COMMERCE_ORPHAN_MENU_NOT_FOUND'), 'warning');
                $app->redirect($dashboardUrl);

                return;
            }

            $table = $app->bootComponent('com_menus')->getMVCFactory()->createTable('Menu', 'Administrator');
            $table->load($id);
            $table->published = 0;

            if (!$table->store()) {
                throw new \RuntimeException($table->getError());
            }

            $app->enqueueMessage(Text::_('COM_J2COMMERCE_ORPHAN_MENU_UNPUBLISHED'));
        } catch (\Throwable $e) {
            Log::add('Orphan menu unpublish failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
            $app->enqueueMessage(Text::_('COM_J2COMMERCE_ERR_GENERIC'), 'warning');
        }

        $app->redirect($dashboardUrl);
    }

    private function handleCustomerStateAjax(): void
    {
        // Nothing may escape to com_ajax: its handler returns $e->getMessage() to an anonymous
        // caller AND lets the request complete normally, so the error response would be cached
        // under this URL and replayed to every guest, leaving hydration dead until expiry.
        // Reaching sendJsonAndClose() is what guarantees the exit() that keeps this uncacheable.
        $data = [];

        // The cart key is omitted rather than reported as zero when the read fails: the
        // hydrator treats a returned count as authoritative and would blank a real basket
        // and announce it as empty. An absent key leaves the served markup alone.
        try {
            $data['cart'] = ['count' => CartHelper::getCartItemCount()];
        } catch (\Throwable $e) {
            Log::add('Customer state cart count failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
        }

        try {
            $data['currency'] = [
                'code'      => CurrencyHelper::getCode(),
                'isUserSet' => (bool) $this->getApplication()->getSession()->get('j2commerce_currency_user_set', false),
            ];
        } catch (\Throwable $e) {
            Log::add('Customer state currency failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
        }

        // A4: the token read is the last unguarded statement before the response, and an
        // escape here would be handed to the caller and cached under this URL.
        try {
            $data['token'] = Session::getFormToken();
        } catch (\Throwable $e) {
            Log::add('Customer state token failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
            $data['token'] = '';
        }


        try {
            $collector = J2CommerceHelper::plugin()->event('CustomerState', ['context' => 'site.customer_state']);
            $slices    = $collector->getArgument('result', []);

            foreach ($slices as $slice) {
                if (
                    !\is_array($slice)
                    || !isset($slice['key'])
                    || !\is_string($slice['key'])
                    || !\array_key_exists('data', $slice)
                    || !\is_array($slice['data'])
                    || isset($data[$slice['key']])
                    // Denied in PHP because the payload is consumed as a JS object: a slice keyed
                    // __proto__/constructor/prototype would pollute Object.prototype in the first
                    // consumer that merges it rather than assigning it.
                    || \in_array($slice['key'], ['__proto__', 'constructor', 'prototype'], true)
                ) {
                    continue;
                }

                $data[$slice['key']] = $slice['data'];
            }
        } catch (\Throwable $e) {
            Log::add('Customer state plugin collector failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
        }

        $this->sendJsonAndClose(['success' => true, 'data' => $data]);
    }

    private function sendJsonAndClose(array $payload): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            header('Vary: Cookie');
            header('X-Content-Type-Options: nosniff');
            header('X-Robots-Tag: noindex');
        }

        $json = json_encode($payload);

        if ($json === false) {
            Log::add('Customer state encode failed: ' . json_last_error_msg(), Log::ERROR, 'com_j2commerce');

            $json = '{"success":false}';
        }

        echo $json;

        // Deliberate exit(): close() skips onAfterRespond, the only point plg_system_cache stores a
        // page. Its appStateSupportsCaching() gates solely on isSite && isGET && guest && empty
        // message queue with no option/format/com_ajax exclusion — a plain return here would let a
        // guest's token and cart count be cached and replayed to every other guest.
        $this->getApplication()->close();
    }

    private function getExtensionId(): int
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote($this->_name))
            ->where($db->quoteName('folder') . ' = ' . $db->quote($this->_type))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'));

        return (int) $db->setQuery($query)->loadResult();
    }

    private function registerCustomerStateAssets(HtmlDocument $document): void
    {
        // onBeforeCompileHead has no component check of its own. Without this the hydrator would
        // register on a site whose component is disabled, where onAfterRoute never publishes
        // window.j2commerceURL and the endpoint does not answer.
        if (!ComponentHelper::isEnabled(self::COMPONENT_NAME)) {
            return;
        }

        // Two paths on purpose: the site root's language/ mirror may not carry com_j2commerce.ini,
        // and without the component-dir fallback Text::script() registers raw keys — which makes
        // the announcement resolve to '' and never fire. Mirrors mod_j2commerce_cart's dispatcher.
        $lang = $this->getApplication()->getLanguage();
        $lang->load('com_j2commerce', JPATH_SITE)
            || $lang->load('com_j2commerce', JPATH_SITE . '/components/com_j2commerce');

        $document->getWebAssetManager()->registerAndUseScript(
            'plg_system_j2commerce.customer-state',
            'media/com_j2commerce/js/site/customer-state.js',
            [],
            ['defer' => true]
        );

        Text::script('COM_J2COMMERCE_CART_NO_ITEMS');
        Text::script('COM_J2COMMERCE_N_CART_ITEMS_1');
        Text::script('COM_J2COMMERCE_N_CART_ITEMS_MORE');
    }

    /**
     * Handle schema injection for J2Commerce pages.
     *
     * @param   Event  $event  The before the compile head event
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function onBeforeCompileHead(Event $event): void
    {
        $app = $this->getApplication();

        // Only run on the frontend site
        if (!$app->isClient('site')) {
            return;
        }

        // Check if we have an HTML document
        $document = $app->getDocument();

        if (!($document instanceof HtmlDocument)) {
            return;
        }

        $this->registerCustomerStateAssets($document);

        $debugMode = (bool) $this->params->get('debug_mode', 0);
        $debugInfo = ['System EcommerceSchema: onBeforeCompileHead'];

        $context     = $this->getCurrentProductContext();
        $debugInfo[] = 'Context: type=' . ($context['type'] ?? 'null') . ', id=' . ($context['id'] ?? 'null');

        if ($context['type'] === null || $context['id'] === null) {
            if ($debugMode) {
                $document->addCustomTag('<!-- ' . implode(' | ', $debugInfo) . ' | Skipped: not a product or article page -->');
            }

            return;
        }


        if (!$this->isJ2CommerceAvailable()) {
            if ($debugMode) {
                $document->addCustomTag('<!-- ' . implode(' | ', $debugInfo) . ' | J2Commerce not available -->');
            }

            return;
        }

        $schemaExists = $this->hasExistingProductSchema($document->getHeadData());

        // A schema already on the page ends this handler, unless the surface mode
        // below still has a canonical and a robots directive to contribute.
        if ($schemaExists && $this->getProductSurfaceMode() === 'both') {
            if ($debugMode) {
                $document->addCustomTag('<!-- ' . implode(' | ', $debugInfo) . ' | Schema already exists, skipping -->');
            }

            return;
        }

        $product = null;
        if ($context['type'] === 'product') {
            if ($context['id'] > 0) {
                $product = $this->getProductById($context['id']);
            }

            $debugInfo[] = 'Product lookup by ID ' . $context['id'] . ': ' . ($product ? 'found' : 'not found');
        } elseif ($context['type'] === 'article') {
            $product     = $this->getProductByArticleId($context['id']);
            $debugInfo[] = 'Product lookup by article ID ' . $context['id'] . ': ' . ($product ? 'found product ' . $product->j2commerce_product_id : 'not found');
        }

        if ($product === null) {
            if ($debugMode) {
                $document->addCustomTag('<!-- ' . implode(' | ', $debugInfo) . ' -->');
            }

            return;
        }

        // The context comes from request input, so it also resolves on the error
        // document a rejected product renders — describe only what the visitor
        // is allowed to see.
        if (!ProductVisibilityHelper::isViewable((int) ($product->j2commerce_product_id ?? 0))) {
            if ($debugMode) {
                $document->addCustomTag('<!-- ' . implode(' | ', $debugInfo) . ' | Not viewable, skipping -->');
            }

            return;
        }

        $this->applyProductSurfaceMode($document, (string) $context['type'], $product);

        if ($schemaExists) {
            if ($debugMode) {
                $document->addCustomTag('<!-- ' . implode(' | ', $debugInfo) . ' | Schema already exists, skipping -->');
            }

            return;
        }

        // Build the product schema
        $isVariable  = $this->isVariableProduct($product);
        $variants    = $this->getProductVariants($product);
        $debugInfo[] = 'Product type: ' . ($product->product_type ?? 'null') . ', isVariable: ' . ($isVariable ? 'true' : 'false') . ', variants: ' . \count($variants);

        $productSchema = $this->buildProductSchema($product);
        $debugInfo[]   = 'Built schema: ' . ($productSchema['@type'] ?? 'none');

        if (empty($productSchema) || !isset($productSchema['@type'])) {
            if ($debugMode) {
                $document->addCustomTag('<!-- ' . implode(' | ', $debugInfo) . ' -->');
            }

            return;
        }

        // Build the full JSON-LD structure without @graph wrapper
        $jsonLd = array_merge(
            ['@context' => 'https://schema.org'],
            $productSchema
        );

        // Inject the JSON-LD script — HEX flags ensure no literal </script> (or
        // quote/ampersand) can survive byte-for-byte into the script block.
        $script = '<script type="application/ld+json">' . json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
        $document->addCustomTag($script);

        $debugInfo[] = 'Schema INJECTED';

        if ($debugMode) {
            $document->addCustomTag('<!-- ' . implode(' | ', $debugInfo) . ' -->');
        }
    }

    /**
     * The surface a product is published on: both, product or article.
     */
    private function getProductSurfaceMode(): string
    {
        $mode = (string) ComponentHelper::getParams(self::COMPONENT_NAME)->get('product_surface_mode', 'both');

        return \in_array($mode, ['product', 'article'], true) ? $mode : 'both';
    }

    /**
     * Point the secondary surface at the published one and keep it out of the index.
     *
     * @param   string  $surface  The surface being rendered: product or article
     */
    private function applyProductSurfaceMode(HtmlDocument $document, string $surface, object $product): void
    {
        $mode = $this->getProductSurfaceMode();

        if ($mode === 'both' || $mode === $surface) {
            return;
        }

        $url = $this->getProductSurfaceUrl($mode, $product);

        if ($url === '') {
            return;
        }

        $document->setMetaData('robots', 'noindex, follow');
        $this->setCanonicalUrl($document, $url);
    }

    /**
     * Absolute URL of one of the two surfaces a product is reachable on.
     *
     * @param   string  $surface  The surface to build for: product or article
     * @param   bool    $xhtml    Escape the ampersands — right for an href, wrong for a Location header
     */
    private function getProductSurfaceUrl(string $surface, object $product, bool $xhtml = true): string
    {
        $article = $product->source ?? null;

        if ($surface === 'article') {
            $articleId = (int) ($article->id ?? 0);

            if ($articleId <= 0) {
                return '';
            }

            $alias = (string) ($article->alias ?? '');
            $link  = ContentRouteHelper::getArticleRoute(
                $alias !== '' ? $articleId . ':' . $alias : (string) $articleId,
                (int) ($article->catid ?? 0),
                $article->language ?? null
            );
        } else {
            $productId = (int) ($product->j2commerce_product_id ?? 0);

            if ($productId <= 0) {
                return '';
            }

            $link = RouteHelper::getProductRoute(
                $productId,
                $product->alias ?? null,
                (int) ($product->catid ?? 0) ?: null,
                $article->language ?? null
            );
        }

        return (string) Route::_($link, $xhtml, Route::TLS_IGNORE, true);
    }

    /**
     * Replaces any canonical the component or a template already set — a second one would cancel both out.
     */
    private function setCanonicalUrl(HtmlDocument $document, string $url): void
    {
        $links = $document->getHeadData()['links'] ?? [];

        foreach ($links as $href => $link) {
            if (($link['relation'] ?? '') === 'canonical') {
                unset($links[$href]);
            }
        }

        $document->setHeadData(['links' => $links]);
        $document->addHeadLink($url, 'canonical');
    }

    /**
     * Sends a request for the secondary surface to the published one.
     */
    private function redirectToPreferredProductSurface(): void
    {
        $mode = $this->getProductSurfaceMode();

        if ($mode === 'both') {
            return;
        }

        $app = $this->getApplication();

        if (!$app instanceof SiteApplication) {
            return;
        }

        $input = $app->getInput();

        if (!\in_array($input->getMethod(), ['GET', 'HEAD'], true) || $input->getCmd('format', 'html') !== 'html') {
            return;
        }

        // A task or an edit layout means the request is doing something with the
        // article, not reading it — frontend submission included.
        if ($input->getCmd('task', '') !== '' || $input->getCmd('layout', '') === 'edit') {
            return;
        }

        $id = $input->getInt('id', 0);

        if ($id <= 0) {
            return;
        }

        $option  = $input->getCmd('option');
        $view    = $input->getCmd('view');
        $surface = match (true) {
            $option === 'com_content' && $view === 'article'        => 'article',
            $option === self::COMPONENT_NAME && $view === 'product' => 'product',
            default                                                 => '',
        };

        if ($surface === '' || $surface === $mode) {
            return;
        }

        // Cheap enough to answer before loading a product: the homepage and every
        // dedicated menu item leave here without touching the database.
        if ($this->isActiveMenuItemFor($app, $surface, $id)) {
            return;
        }

        $product = $surface === 'article'
            ? $this->getProductByArticleId($id)
            : $this->getProductById($id);

        if ($product === null) {
            return;
        }

        if (!ProductVisibilityHelper::isViewable((int) ($product->j2commerce_product_id ?? 0))) {
            return;
        }

        // Leave the author their own preview of an article that is not public yet.
        if ($this->canEditProductArticle($product)) {
            return;
        }

        $url = $this->getProductSurfaceUrl($mode, $product, false);

        if ($url === '') {
            return;
        }

        $app->redirect($url, 301);
    }

    /**
     * Whether the current user may edit the article backing a product.
     */
    private function canEditProductArticle(object $product): bool
    {
        $user = $this->getApplication()->getIdentity();

        if ($user === null || (int) $user->id === 0) {
            return false;
        }

        $article   = $product->source ?? null;
        $articleId = (int) ($article->id ?? 0);

        if ($articleId <= 0) {
            return false;
        }

        $asset = 'com_content.article.' . $articleId;

        if ($user->authorise('core.edit', $asset)) {
            return true;
        }

        return $user->authorise('core.edit.own', $asset)
            && (int) ($article->created_by ?? 0) === (int) $user->id;
    }

    /**
     * Whether the active menu item is the surface being redirected away from.
     */
    private function isActiveMenuItemFor(SiteApplication $app, string $surface, int $id): bool
    {
        $active = $app->getMenu()->getActive();

        if ($active === null) {
            return false;
        }

        parse_str((string) parse_url((string) $active->link, PHP_URL_QUERY), $query);

        $option = $surface === 'article' ? 'com_content' : self::COMPONENT_NAME;
        $view   = $surface === 'article' ? 'article' : 'product';

        return ($query['option'] ?? '') === $option
            && ($query['view'] ?? '') === $view
            && (int) ($query['id'] ?? 0) === $id;
    }

    /**
     * Get the current product context from the request
     *
     * @return  array{type: string|null, id: int|null}
     *
     * @since   6.0.0
     */
    private function getCurrentProductContext(): array
    {
        $app   = $this->getApplication();
        $input = $app->getInput();

        $option = $input->getCmd('option');
        $view   = $input->getCmd('view');
        $task   = $input->getCmd('task');
        $id     = $input->getInt('id', 0);

        if ($option === 'com_j2commerce') {
            if ($view === 'product' && $id > 0) {
                return ['type' => 'product', 'id' => $id];
            }

            $productId = $input->getInt('product_id', 0);

            if ($productId > 0) {
                return ['type' => 'product', 'id' => $productId];
            }
        }

        // Check for Joomla article view (com_content) - articles can be linked to J2Commerce products
        if ($option === 'com_content' && $view === 'article' && $id > 0) {
            return ['type' => 'article', 'id' => $id];
        }

        return ['type' => null, 'id' => null];
    }

    /**
     * Check if J2Commerce is available
     *
     * @return  bool
     *
     * @since   6.0.0
     */
    private function isJ2CommerceAvailable(): bool
    {

        if (file_exists(JPATH_ADMINISTRATOR . '/components/com_j2commerce/version.php')) {
            return true;
        }

        return false;
    }

    /**
     * Check if the product schema already exists in head data
     *
     * @param   array  $headData  The document head data
     *
     * @return  bool
     *
     * @since   6.0.0
     */
    private function hasExistingProductSchema(array $headData): bool
    {
        // Check custom tags for existing JSON-LD with Product
        if (!empty($headData['custom'])) {
            foreach ($headData['custom'] as $tag) {
                if (\is_string($tag) && strpos($tag, 'application/ld+json') !== false) {
                    if (strpos($tag, '"@type":"Product"') !== false || strpos($tag, '"@type": "Product"') !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get product by J2Commerce product ID
     *
     * @param   int  $productId  The product ID
     *
     * @return  object|null
     *
     * @since   6.0.0
     */
    private function getProductById(int $productId): ?object
    {
        if (!class_exists(J2CommerceHelper::class)) {
            return null;
        }

        try {
            $productHelper = J2CommerceHelper::product();
            if ($productId > 0) {
                $product = $productHelper->getFullProduct($productId);

                if ($product && isset($product->j2commerce_product_id) && $product->j2commerce_product_id > 0) {
                    return $product;
                }
            }

        } catch (\Exception $e) {
            // Product not found or error
        }

        return null;
    }

    /**
     * Get product by article ID
     *
     *
     * @param   int  $articleId  The Joomla article ID
     *
     * @return  object|null
     *
     * @since   6.0.0
     */
    private function getProductByArticleId(int $articleId): ?object
    {
        if (!class_exists(J2CommerceHelper::class)) {
            return null;
        }

        try {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select($db->quoteName('j2commerce_product_id'))
                ->from($db->quoteName('#__j2commerce_products'))
                ->where($db->quoteName('product_source') . ' = ' . $db->quote('com_content'))
                ->where($db->quoteName('product_source_id') . ' = ' . (int) $articleId);

            $db->setQuery($query);
            $productId = (int) $db->loadResult();

            if ($productId > 0) {
                return $this->getProductById($productId);
            }
        } catch (\Exception $e) {
            // Product not found or error
        }

        return null;
    }

    /**
     * Build Product schema from J2Commerce product
     *
     * @param   object  $product  The J2Commerce product
     *
     * @return  array
     *
     * @since   6.0.0
     */
    private function buildProductSchema(object $product): array
    {
        // Check if this is a variable product with multiple variants
        $isVariable   = $this->isVariableProduct($product);
        $variants     = $this->getProductVariants($product);
        $variantCount = \count($variants);

        // Use Product with hasVariant for variable products with multiple variants
        if ($isVariable && $variantCount > 1) {
            return $this->buildVariableProductSchema($product, $variants);
        }

        return $this->buildSimpleProductSchema($product);
    }

    /**
     * Build a simple Product schema
     *
     * @param   object  $product  The J2Commerce product
     *
     * @return  array
     *
     * @since   6.0.0
     */
    private function buildSimpleProductSchema(object $product): array
    {
        $productUrl = $this->getProductUrl($product);

        $schema = [
            '@type' => 'Product',
            '@id'   => $productUrl . '#product',
        ];

        // Name
        $schema['name'] = $product->product_name ?? ($product->title ?? '');

        // Description
        $description = $this->getProductDescription($product);

        if (!empty($description)) {
            $schema['description'] = $description;
        }

        // SKU
        if (isset($product->variant->sku) && !empty($product->variant->sku)) {
            $schema['sku'] = $product->variant->sku;
        }

        // MPN
        if (isset($product->variant->params)) {
            $variantParams = \is_string($product->variant->params)
                ? json_decode($product->variant->params, true)
                : (array) $product->variant->params;

            if (!empty($variantParams['mpn'])) {
                $schema['mpn'] = $variantParams['mpn'];
            }
        }

        // GTIN/UPC
        if (isset($product->variant->upc) && !empty($product->variant->upc)) {
            $schema['gtin'] = $product->variant->upc;
        }

        // Images
        $images = $this->getProductImages($product);

        if (!empty($images)) {
            $schema['image'] = \count($images) === 1 ? $images[0] : $images;
        }

        // Brand
        $brand = $this->buildBrandSchema($product);

        if ($brand) {
            $schema['brand'] = $brand;
        }

        // Category
        $category = $this->getProductCategoryPath($product);

        if (!empty($category)) {
            $schema['category'] = $category;
        }

        // URL
        $schema['url'] = $productUrl;

        // Offers
        $schema['offers'] = $this->buildOfferSchema($product);

        // Trigger reviews event to allow app_reviews to inject ratings
        $schema = $this->triggerReviewsEvent($schema, (int) $product->j2commerce_product_id);

        return $this->cleanSchema($schema);
    }

    /**
     * Build Product schema for variable products (with hasVariant)
     *
     * @param   object  $product   The J2Commerce product
     * @param   array   $variants  Array of variant objects
     *
     * @return  array
     *
     * @since   6.0.0
     */
    private function buildVariableProductSchema(object $product, array $variants): array
    {
        $productUrl    = $this->getProductUrl($product);
        $masterVariant = $this->getMasterVariant($variants);

        $schema = [
            '@type' => 'Product',
            '@id'   => $productUrl . '#product',
        ];

        // Name
        $schema['name'] = $product->product_name ?? ($product->title ?? '');

        // Description
        $description = $this->getProductDescription($product);

        if (!empty($description)) {
            $schema['description'] = $description;
        }

        // SKU from master variant
        if ($masterVariant && !empty($masterVariant->sku)) {
            $schema['sku'] = $masterVariant->sku;
        }

        // MPN from master variant
        if ($masterVariant && isset($masterVariant->params)) {
            $variantParams = \is_string($masterVariant->params)
                ? json_decode($masterVariant->params, true)
                : (array) $masterVariant->params;

            if (!empty($variantParams['mpn'])) {
                $schema['mpn'] = $variantParams['mpn'];
            }
        }

        // Images
        $images = $this->getProductImages($product);

        if (!empty($images)) {
            $schema['image'] = \count($images) === 1 ? $images[0] : $images;
        }

        // Brand
        $brand = $this->buildBrandSchema($product);

        if ($brand) {
            $schema['brand'] = $brand;
        }

        // Category
        $category = $this->getProductCategoryPath($product);

        if (!empty($category)) {
            $schema['category'] = $category;
        }

        // URL
        $schema['url'] = $productUrl;

        // Build variant schemas
        $variantSchemas = [];

        foreach ($variants as $index => $variant) {
            // Skip master variant
            if ((int) ($variant->is_master ?? 0) === 1) {
                continue;
            }

            $variantSchema = $this->buildVariantSchema($product, $variant, $index);

            if (!empty($variantSchema)) {
                $variantSchemas[] = $variantSchema;
            }
        }

        if (!empty($variantSchemas)) {
            // Add AggregateOffer for the parent product (shows price range across all variants)
            $schema['offers'] = $this->buildAggregateOfferSchema($variants);

            $schema['hasVariant'] = $variantSchemas;
        }

        // Trigger reviews event to allow app_reviews to inject ratings
        $schema = $this->triggerReviewsEvent($schema, (int) $product->j2commerce_product_id);

        return $this->cleanSchema($schema);
    }

    /**
     * Get the master variant from variants array
     *
     * @param   array  $variants  Array of variant objects
     *
     * @return  object|null
     *
     * @since   6.0.0
     */
    private function getMasterVariant(array $variants): ?object
    {
        foreach ($variants as $variant) {
            if ((int) ($variant->is_master ?? 0) === 1) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * Build schema for a single variant
     *
     * @param   object  $product  The parent product
     * @param   object  $variant  The variant object
     * @param   int     $index    The variant index
     *
     * @return  array
     *
     * @since   6.0.0
     */
    private function buildVariantSchema(object $product, object $variant, int $index): array
    {
        $productUrl        = $this->getProductUrl($product);
        $variantName       = $this->getVariantDisplayName($variant);
        $variantProperties = $this->getVariantProperties($variant);

        // Build variant identifier for @id
        $variantIdentifier = !empty($variant->sku) ? $variant->sku : 'variant-' . $index;

        $schema = [
            '@type' => 'Product',
            '@id'   => $productUrl . '?variant=' . urlencode($variantIdentifier) . '#variant',
        ];

        // Name - use variant name or product name
        $schema['name'] = $product->product_name . ($variantName ? ' - ' . $variantName : '');

        // SKU
        if (!empty($variant->sku)) {
            $schema['sku'] = $variant->sku;
        }

        // GTIN/UPC
        if (!empty($variant->upc)) {
            $schema['gtin'] = $variant->upc;
        }

        // Image - check for variant-specific image first
        $variantImage = $this->getVariantImage($variant);

        if (!empty($variantImage)) {
            $schema['image'] = $variantImage;
        } else {
            // Fall back to main product image
            $images = $this->getProductImages($product);

            if (!empty($images)) {
                $schema['image'] = $images[0];
            }
        }

        // Add variant-specific properties (size, color, etc.)
        foreach ($variantProperties as $propName => $propValue) {
            $schema[$propName] = $propValue;
        }

        // Offer for this variant
        $schema['offers'] = $this->buildVariantOfferSchema($variant);

        return $schema;
    }

    /**
     * Get variant-specific image
     *
     * @param   object  $variant  The variant object
     *
     * @return  string|null
     *
     * @since   6.0.0
     */
    private function getVariantImage(object $variant): ?string
    {
        if (isset($variant->params)) {
            $variantParams = \is_string($variant->params)
                ? json_decode($variant->params, true)
                : (array) $variant->params;

            if (!empty($variantParams['variant_main_image'])) {
                return $this->cleanImageUrl($variantParams['variant_main_image']);
            }
        }

        return null;
    }

    /**
     * Get variant properties (size, color, etc.)
     *
     * @param   object  $variant  The variant object
     *
     * @return  array
     *
     * @since   6.0.0
     */
    private function getVariantProperties(object $variant): array
    {
        $properties = [];

        if (!class_exists(J2CommerceHelper::class) || empty($variant->variant_name)) {
            return $properties;
        }

        try {
            // getProductVariants() aliases #__j2commerce_product_variant_optionvalues
            // .product_optionvalue_ids to variant_name, so these are PRODUCT option
            // value ids and have to be resolved through #__j2commerce_product_optionvalues
            // — the same route ProductHelper::getVariantNamesByCSV() takes for the
            // variant's display name. Reading them straight out of
            // #__j2commerce_optionvalues is a different key space, so any row that
            // happens to share the number answers instead.
            $productOptionValueIds = array_values(array_unique(array_filter(
                array_map('intval', explode(',', (string) $variant->variant_name)),
                static fn (int $id): bool => $id > 0
            )));

            if (empty($productOptionValueIds)) {
                return $properties;
            }

            $db    = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('pov.j2commerce_product_optionvalue_id'),
                    $db->quoteName('o.option_unique_name'),
                    $db->quoteName('ov.optionvalue_name'),
                ])
                ->from($db->quoteName('#__j2commerce_product_optionvalues', 'pov'))
                ->join(
                    'INNER',
                    $db->quoteName('#__j2commerce_optionvalues', 'ov')
                    . ' ON ' . $db->quoteName('pov.optionvalue_id') . ' = ' . $db->quoteName('ov.j2commerce_optionvalue_id')
                )
                ->join(
                    'INNER',
                    $db->quoteName('#__j2commerce_options', 'o')
                    . ' ON ' . $db->quoteName('ov.option_id') . ' = ' . $db->quoteName('o.j2commerce_option_id')
                )
                ->whereIn($db->quoteName('pov.j2commerce_product_optionvalue_id'), $productOptionValueIds);

            $db->setQuery($query);

            $rows = $db->loadObjectList('j2commerce_product_optionvalue_id') ?: [];

            // Walk the CSV, not the result set: where two of a variant's options map
            // to the same schema property, the id listed last still wins, exactly as
            // it did when this ran a query per id. An unordered result set would
            // otherwise decide that.
            foreach ($productOptionValueIds as $productOptionValueId) {
                $result = $rows[$productOptionValueId] ?? null;

                if ($result === null || empty($result->option_unique_name) || empty($result->optionvalue_name)) {
                    continue;
                }

                // Map common option names to schema.org properties
                $optionName = strtolower($result->option_unique_name);

                if (strpos($optionName, 'size') !== false) {
                    $properties['size'] = $result->optionvalue_name;
                } elseif (strpos($optionName, 'color') !== false || strpos($optionName, 'colour') !== false) {
                    $properties['color'] = $result->optionvalue_name;
                } elseif (strpos($optionName, 'material') !== false) {
                    $properties['material'] = $result->optionvalue_name;
                } elseif (strpos($optionName, 'pattern') !== false) {
                    $properties['pattern'] = $result->optionvalue_name;
                }
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return $properties;
    }

    /**
     * Get the display name for a variant
     *
     * @param   object  $variant  The variant object
     *
     * @return  string
     *
     * @since   6.0.0
     */
    private function getVariantDisplayName(object $variant): string
    {
        // Try to get variant name from option values
        if (class_exists(J2CommerceHelper::class) && !empty($variant->variant_name)) {
            try {

                $productHelper = J2CommerceHelper::product();
                if ($productHelper && method_exists($productHelper, 'getVariantNamesByCSV')) {
                    $name = $productHelper->getVariantNamesByCSV($variant->variant_name);

                    if (!empty($name)) {
                        return $name;
                    }
                }
            } catch (\Exception $e) {
                // Fall through
            }
        }

        return '';
    }

    /**
     * Build offer schema for a variant
     *
     * @param   object  $variant  The variant object
     *
     * @return  array
     *
     * @since   6.0.0
     */
    private function buildVariantOfferSchema(object $variant): array
    {
        $offer = [
            '@type' => 'Offer',
        ];

        // Price
        $price = $variant->price ?? 0;

        // Check for special price
        if (isset($variant->special_price) && (float) $variant->special_price > 0) {
            $price = $variant->special_price;
        }

        $offer['price']         = number_format((float) $price, 2, '.', '');
        $offer['priceCurrency'] = $this->getCurrencyCode();

        // Item Condition
        $offer['itemCondition'] = $this->getItemConditionUrl();

        // Price Valid Until
        $priceValidUntil = $this->getPriceValidUntil();

        if (!empty($priceValidUntil)) {
            $offer['priceValidUntil'] = $priceValidUntil;
        }

        // Availability
        $offer['availability'] = $this->mapVariantAvailability($variant);

        // URL
        $offer['url'] = Uri::current();

        return $offer;
    }

    /**
     * Build AggregateOffer schema for variable products
     *
     * @param   array  $variants  Array of variant objects
     *
     * @return  array
     *
     * @since   6.0.0
     */
    private function buildAggregateOfferSchema(array $variants): array
    {
        $prices         = [];
        $availabilities = [];
        $offerCount     = 0;

        foreach ($variants as $variant) {
            // Skip master variant
            if ((int) ($variant->is_master ?? 0) === 1) {
                continue;
            }

            $offerCount++;

            // Get price (use special price if available)
            $price = (float) ($variant->price ?? 0);

            if (isset($variant->special_price) && (float) $variant->special_price > 0) {
                $price = (float) $variant->special_price;
            }

            if ($price > 0) {
                $prices[] = $price;
            }

            // Collect availability
            $availabilities[] = $this->mapVariantAvailability($variant);
        }

        $offer = [
            '@type' => 'AggregateOffer',
        ];

        // Price range
        if (!empty($prices)) {
            $offer['lowPrice']  = number_format(min($prices), 2, '.', '');
            $offer['highPrice'] = number_format(max($prices), 2, '.', '');
        }

        $offer['priceCurrency'] = $this->getCurrencyCode();
        $offer['offerCount']    = $offerCount;

        // Item Condition
        $offer['itemCondition'] = $this->getItemConditionUrl();

        // Price Valid Until
        $priceValidUntil = $this->getPriceValidUntil();

        if (!empty($priceValidUntil)) {
            $offer['priceValidUntil'] = $priceValidUntil;
        }

        if (\in_array('https://schema.org/InStock', $availabilities, true)) {
            $offer['availability'] = 'https://schema.org/InStock';
        } elseif (\in_array('https://schema.org/BackOrder', $availabilities, true)) {
            $offer['availability'] = 'https://schema.org/BackOrder';
        } else {
            $offer['availability'] = 'https://schema.org/OutOfStock';
        }

        // URL
        $offer['url'] = Uri::current();

        return $offer;
    }

    /**
     * Map variant availability to schema.org value
     *
     * @param   object  $variant  The variant
     *
     * @return  string
     *
     * @since   6.0.0
     */
    private function mapVariantAvailability(object $variant): string
    {
        // Note: In J2Commerce, availability field: 1 = in stock, 0 = out of stock
        if (isset($variant->availability) && (int) $variant->availability === 0) {
            return 'https://schema.org/OutOfStock';
        }

        $manageStock = (int) ($variant->manage_stock ?? 0);

        if ($manageStock === 1) {
            $quantity = (int) ($variant->stock_quantity ?? 0);

            if ($quantity <= 0) {
                $allowBackorder = (int) ($variant->allow_backorder ?? 0);

                if ($allowBackorder === 1) {
                    return 'https://schema.org/BackOrder';
                }

                return 'https://schema.org/OutOfStock';
            }
        }

        return 'https://schema.org/InStock';
    }

    /**
     * Check if the product is a variable product
     *
     * @param   object  $product  The product
     *
     * @return  bool
     *
     * @since   6.0.0
     */
    private function isVariableProduct(object $product): bool
    {
        $variableTypes = ['variable', 'advancedvariable', 'flexivariable', 'variablesubscriptionproduct'];

        return \in_array($product->product_type ?? '', $variableTypes, true);
    }

    /**
     * Get all variants for a product
     *
     * @param   object  $product  The product
     *
     * @return  array
     *
     * @since   6.0.0
     */
    private function getProductVariants(object $product): array
    {
        $productId = (int) $product->j2commerce_product_id;

        try {
            $db = $this->getDatabase();

            $query = $db->getQuery(true)
                ->select('v.*')
                ->select($db->quoteName('pvo.product_optionvalue_ids', 'variant_name'))
                ->select($db->quoteName('pq.quantity', 'stock_quantity'))
                ->from($db->quoteName('#__j2commerce_variants', 'v'))
                ->join(
                    'LEFT',
                    $db->quoteName('#__j2commerce_product_variant_optionvalues', 'pvo')
                    . ' ON ' . $db->quoteName('v.j2commerce_variant_id') . ' = ' . $db->quoteName('pvo.variant_id')
                )
                ->join(
                    'LEFT',
                    $db->quoteName('#__j2commerce_productquantities', 'pq')
                    . ' ON ' . $db->quoteName('v.j2commerce_variant_id') . ' = ' . $db->quoteName('pq.variant_id')
                )
                ->where($db->quoteName('v.product_id') . ' = ' . $productId);

            $db->setQuery($query);

            return $db->loadObjectList() ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Trigger reviews event to allow review plugins to inject data
     *
     * @param   array  $schema     The product schema
     * @param   int    $productId  The product ID
     *
     * @return  array  The modified schema
     *
     * @since   6.0.0
     */
    private function triggerReviewsEvent(array $schema, int $productId): array
    {
        try {
            PluginHelper::importPlugin('j2commerce');

            $event = new Event('onJ2CommerceSchemaReviewsPrepare', [
                'schema'    => $schema,
                'productId' => $productId,
            ]);
            Factory::getContainer()->get(DispatcherInterface::class)->dispatch('onJ2CommerceSchemaReviewsPrepare', $event);
            $schema = $event->getArgument('schema');

        } catch (\Exception $e) {
            // Silently fail - reviews are optional
        }

        return $schema;
    }

    /**
     * Get product description
     *
     * @param   object  $product  The product
     *
     * @return  string
     *
     * @since   6.0.0
     */
    private function getProductDescription(object $product): string
    {
        $description = '';

        if (!empty($product->product_short_desc)) {
            $description = $product->product_short_desc;
        } elseif (!empty($product->product_long_desc)) {
            $description = $product->product_long_desc;
        } elseif (isset($product->source)) {
            if (!empty($product->source->introtext)) {
                $description = $product->source->introtext;
            } elseif (!empty($product->source->fulltext)) {
                $description = $product->source->fulltext;
            }
        }

        // Clean and truncate — decode entities FIRST so entity-encoded markup
        // becomes real tags that strip_tags() can actually remove.
        $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
        $description = strip_tags($description);
        $description = preg_replace('/\s+/', ' ', $description);
        $description = trim($description);

        if (\strlen($description) > 5000) {
            $description = substr($description, 0, 4997) . '...';
        }

        return $description;
    }

    /**
     * Get product images (cleaned URLs)
     *
     * @param   object  $product  The product
     *
     * @return  array
     *
     * @since   6.0.0
     */
    private function getProductImages(object $product): array
    {
        $images = [];

        // Main image
        if (!empty($product->main_image)) {
            $cleanedUrl = $this->cleanImageUrl($product->main_image);

            if (!empty($cleanedUrl)) {
                $images[] = $cleanedUrl;
            }
        } elseif (!empty($product->thumb_image)) {
            $cleanedUrl = $this->cleanImageUrl($product->thumb_image);

            if (!empty($cleanedUrl)) {
                $images[] = $cleanedUrl;
            }
        }

        // Additional images
        if (!empty($product->additional_images)) {
            $additionalImages = json_decode($product->additional_images, true);

            if (\is_array($additionalImages)) {
                foreach ($additionalImages as $img) {
                    $imgPath = '';

                    if (\is_string($img) && !empty($img)) {
                        $imgPath = $img;
                    } elseif (\is_array($img) && !empty($img['image'])) {
                        $imgPath = $img['image'];
                    }

                    if (!empty($imgPath)) {
                        $cleanedUrl = $this->cleanImageUrl($imgPath);

                        if (!empty($cleanedUrl)) {
                            $images[] = $cleanedUrl;
                        }
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($images)));
    }

    /**
     * Clean and make image URL absolute using HTMLHelper::cleanImageURL
     *
     * @param   string  $imagePath  The image path
     *
     * @return  string
     *
     * @since   6.0.0
     */
    private function cleanImageUrl(string $imagePath): string
    {
        if (empty($imagePath)) {
            return '';
        }

        $cleanedImage = HTMLHelper::_('cleanImageURL', $imagePath);

        // cleanImageURL returns an object with 'url' property
        if (\is_object($cleanedImage) && isset($cleanedImage->url)) {
            $url = $cleanedImage->url;
        } else {
            $url = $imagePath;
        }

        return $this->makeAbsoluteUrl($url);
    }

    /**
     * Make URL absolute
     *
     * @param   string  $url  The URL
     *
     * @return  string
     *
     * @since   6.0.0
     */
    private function makeAbsoluteUrl(string $url): string
    {
        if (empty($url)) {
            return '';
        }

        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
            return $url;
        }

        $url = ltrim($url, '/');

        return rtrim(Uri::root(), '/') . '/' . $url;
    }

    /**
     * Build brand schema
     *
     * @param   object  $product  The product
     *
     * @return  array|null
     *
     * @since   6.0.0
     */
    private function buildBrandSchema(object $product): ?array
    {
        // Check product manufacturer
        if (isset($product->manufacturer) && !empty($product->manufacturer->company)) {
            return [
                '@type' => 'Brand',
                'name'  => $product->manufacturer->company,
            ];
        }

        // Use default from params
        $defaultBrand = $this->params->get('default_brand', '');

        if (!empty($defaultBrand)) {
            return [
                '@type' => 'Brand',
                'name'  => $defaultBrand,
            ];
        }

        return null;
    }

    /**
     * Get the product category path
     *
     * @param   object  $product  The product
     *
     * @return  string
     *
     * @since   6.0.0
     */
    private function getProductCategoryPath(object $product): string
    {
        try {
            $db = $this->getDatabase();

            $categoryId = $this->getProductCategoryId($product);

            if ($categoryId === null) {
                return '';
            }

            return $this->buildCategoryPath($categoryId);
        } catch (\Exception $e) {

        }

        return '';
    }

    /**
     * Get product category ID from various sources
     *
     * @param   object  $product  The product
     *
     * @return  int|null
     *
     * @since   6.0.0
     */
    private function getProductCategoryId(object $product): ?int
    {
        $db = $this->getDatabase();

        if (($product->product_source ?? '') === 'com_content') {
            $sourceId = (int) ($product->product_source_id ?? 0);

            if ($sourceId > 0) {
                $query = $db->getQuery(true)
                    ->select($db->quoteName('catid'))
                    ->from($db->quoteName('#__content'))
                    ->where($db->quoteName('id') . ' = :sourceId')
                    ->bind(':sourceId', $sourceId, ParameterType::INTEGER);

                $db->setQuery($query);
                $catid = (int) $db->loadResult();

                if ($catid > 0) {
                    return $catid;
                }
            }
        }

        // Fallback: Try to get from product source object (article catid)
        if (isset($product->source, $product->source->catid)) {
            $catid = (int) $product->source->catid;

            if ($catid > 0) {
                return $catid;
            }
        }

        return null;
    }

    /**
     * Find category ID by title
     *
     * @param   string  $title  The category title
     *
     * @return  int|null
     *
     * @since   6.0.0
     */
    private function findCategoryIdByTitle(string $title): ?int
    {
        if (empty($title)) {
            return null;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('title') . ' = :title')
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
            ->bind(':title', $title);

        $db->setQuery($query);

        return (int) $db->loadResult() ?: null;
    }

    /**
     * Build category path string from category ID
     *
     * Uses the categories table path column to build a breadcrumb-style path.
     *
     * @param   int  $categoryId  The category ID
     *
     * @return  string
     *
     * @since   6.0.0
     */
    private function buildCategoryPath(int $categoryId): string
    {
        if ($categoryId <= 0) {
            return '';
        }

        $db = $this->getDatabase();

        // Get the category path (e.g., "electronics/phones/smartphones")
        $query = $db->getQuery(true)
            ->select($db->quoteName('path'))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('id') . ' = :categoryId')
            ->bind(':categoryId', $categoryId, ParameterType::INTEGER);

        $db->setQuery($query);
        $path = $db->loadResult();

        if (empty($path)) {
            return '';
        }

        // Get all categories in the path to build titles
        $pathParts = explode('/', $path);

        if (empty($pathParts)) {
            return '';
        }

        // Convert path parts to integers
        $categoryIds = array_map('intval', $pathParts);

        // Get titles for all categories in the path
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'title']))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('id') . ' IN (' . implode(',', $categoryIds) . ')');

        $db->setQuery($query);
        $categories = $db->loadObjectList('id');

        if (empty($categories)) {
            return '';
        }

        // Build the title path in order
        $pathTitles = [];

        foreach ($pathParts as $catId) {
            $catId = (int) $catId;

            if (isset($categories[$catId])) {
                $pathTitles[] = $categories[$catId]->title;
            }
        }

        if (empty($pathTitles)) {
            return '';
        }

        return implode(' > ', $pathTitles);
    }

    /**
     * Get product URL
     *
     * @param   object  $product  The product
     *
     * @return  string
     *
     * @since   6.0.0
     */
    private function getProductUrl(object $product): string
    {
        return Uri::current();
    }

    /**
     * Build offer schema
     *
     * @param   object  $product  The product
     *
     * @return  array
     *
     * @since   6.0.0
     */
    private function buildOfferSchema(object $product): array
    {
        $offer = [
            '@type' => 'Offer',
        ];

        // Price
        if (isset($product->variant)) {
            $price = $product->variant->price ?? 0;

            // Check for special price
            if (isset($product->variant->special_price) && (float) $product->variant->special_price > 0) {
                $price = $product->variant->special_price;
            }

            $offer['price'] = number_format((float) $price, 2, '.', '');
        }

        // Currency
        $offer['priceCurrency'] = $this->getCurrencyCode();

        // Item Condition
        $offer['itemCondition'] = $this->getItemConditionUrl();

        // Price Valid Until
        $priceValidUntil = $this->getPriceValidUntil();

        if (!empty($priceValidUntil)) {
            $offer['priceValidUntil'] = $priceValidUntil;
        }

        // Availability
        $offer['availability'] = $this->mapAvailability($product);

        // URL
        $offer['url'] = $this->getProductUrl($product);

        return $offer;
    }

    /**
     * Get the item condition URL
     *
     * @return  string
     *
     * @since   6.0.0
     */
    private function getItemConditionUrl(): string
    {
        $condition = $this->params->get('item_condition', 'NewCondition');

        $conditionMap = [
            'NewCondition'         => 'https://schema.org/NewCondition',
            'UsedCondition'        => 'https://schema.org/UsedCondition',
            'RefurbishedCondition' => 'https://schema.org/RefurbishedCondition',
            'DamagedCondition'     => 'https://schema.org/DamagedCondition',
        ];

        return $conditionMap[$condition] ?? 'https://schema.org/NewCondition';
    }

    /**
     * Get the price valid until date
     *
     * @return  string
     *
     * @since   6.0.0
     */
    private function getPriceValidUntil(): string
    {
        $priceValidOption = $this->params->get('price_valid_until', '1year');
        $customDate       = $this->params->get('price_valid_custom_date', '');

        $now = new \DateTime();

        switch ($priceValidOption) {
            case '1month':
                $now->modify('+1 month');
                break;
            case '3months':
                $now->modify('+3 months');
                break;
            case '6months':
                $now->modify('+6 months');
                break;
            case '1year':
                $now->modify('+1 year');
                break;
            case 'custom':
                if (!empty($customDate)) {
                    return $customDate;
                }
                $now->modify('+1 year');
                break;
            default:
                $now->modify('+1 year');
        }

        return $now->format('Y-m-d');
    }

    /**
     * Get currency code
     *
     * @return  string
     *
     * @since   6.0.0
     */
    private function getCurrencyCode(): string
    {
        $params          = ComponentHelper::getParams('com_j2commerce');
        $defaultCurrency = $params->get('config_currency', 'USD');

        return $defaultCurrency;
    }

    /**
     * Map product availability to schema.org value
     *
     * @param   object  $product  The product
     *
     * @return  string
     *
     * @since   6.0.0
     */
    private function mapAvailability(object $product): string
    {
        if (!isset($product->variant)) {
            return 'https://schema.org/InStock';
        }

        $variant = $product->variant;

        // Check if product is available
        if (isset($variant->availability) && (int) $variant->availability === 0) {
            return 'https://schema.org/OutOfStock';
        }

        // Check stock
        $manageStock = (int) ($variant->manage_stock ?? 0);

        if ($manageStock === 1) {
            $quantity = (int) ($variant->quantity ?? 0);

            if ($quantity <= 0) {
                // Check backorder setting
                $allowBackorder = (int) ($variant->allow_backorder ?? 0);

                if ($allowBackorder === 1) {
                    return 'https://schema.org/BackOrder';
                }

                return 'https://schema.org/OutOfStock';
            }
        }

        return 'https://schema.org/InStock';
    }

    /**
     * Clean empty values from schema
     *
     * @param   array  $schema  The schema data
     *
     * @return  array
     *
     * @since   6.0.0
     */
    private function cleanSchema(array $schema): array
    {
        $cleaned = [];

        foreach ($schema as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            if (\is_array($value)) {
                $value = $this->cleanSchema($value);

                if (empty($value)) {
                    continue;
                }
            }

            $cleaned[$key] = $value;
        }

        return $cleaned;
    }
}
