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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\SetupGuideHelper;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
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
            'onAfterInitialise'                => 'onAfterInitialise',
            'onAfterRoute'                     => 'onAfterRoute',
            'onAfterRender'                    => 'onAfterRender',
            'onContentPrepare'                 => 'onContentPrepare',
            'onUserLogin'                      => 'onUserLogin',
            'onBeforeCompileHead'              => ['onBeforeCompileHead', Priority::LOW],
            'onJ2CommerceAfterUpdateCart'      => 'onJ2CommerceAfterUpdateCart',
            'onJ2CommerceBeforeGetPrice'       => 'onJ2CommerceBeforeGetPrice',
            'onJ2CommerceCalculateFees'        => 'onJ2CommerceCalculateFees',
            'onJ2CommerceProcessCron'          => 'onJ2CommerceProcessCron',
            'onJ2CommerceGetDashboardMessages' => 'onGetDashboardMessages',
        ];
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

        $registryFile = JPATH_ADMINISTRATOR . '/components/com_j2commerce/language_registry.json';

        if (!is_file($registryFile)) {
            return;
        }

        $extensions = json_decode((string) file_get_contents($registryFile), true);

        if (empty($extensions) || !\is_array($extensions)) {
            return;
        }

        $lang = $app->getLanguage();

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

        $dispatcher = $app->getDispatcher();
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

        $session      = Factory::getApplication()->getSession();
        $oldSessionId = $session->get('old_sessionid', '', self::SESSION_NAMESPACE);
        $userId       = (int) UserHelper::getUserId($user['username']);

        if ($userId === 0) {
            return;
        }

        // Migrate cart to logged-in user
        $this->migrateCartOnLogin($oldSessionId, $userId, $session->getId());
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
     * @param   string  $oldSessionId    The previous session ID
     * @param   int     $userId          The user ID
     * @param   string  $newSessionId    The current session ID
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function migrateCartOnLogin(string $oldSessionId, int $userId, string $newSessionId): void
    {
        // Note: Full cart migration requires CartService to be implemented
        // This is a placeholder that updates cart session references directly

        $db = $this->getDatabase();

        try {
            if (!empty($oldSessionId)) {
                // Update cart items from the old session to user
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_carts'))
                    ->set($db->quoteName('user_id') . ' = :userId')
                    ->set($db->quoteName('session_id') . ' = :newSessionId')
                    ->where($db->quoteName('session_id') . ' = :oldSessionId')
                    ->bind(':userId', $userId, ParameterType::INTEGER)
                    ->bind(':newSessionId', $newSessionId)
                    ->bind(':oldSessionId', $oldSessionId);

                $db->setQuery($query);
                $db->execute();
            } else {
                // Just update session for existing user cart
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_carts'))
                    ->set($db->quoteName('session_id') . ' = :newSessionId')
                    ->where($db->quoteName('user_id') . ' = :userId')
                    ->bind(':newSessionId', $newSessionId)
                    ->bind(':userId', $userId, ParameterType::INTEGER);

                $db->setQuery($query);
                $db->execute();
            }
        } catch (\Exception $e) {
            // Silently fail if cart table doesn't exist yet or other DB error
            // This allows the plugin to work during initial migration
        }
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

        // Update last run timestamp
        $this->updateLastRunTimestamp();
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

        $event->setArgument('result', $result);
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

        $headData = $document->getHeadData();

        if ($this->hasExistingProductSchema($headData)) {
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

        // Inject the JSON-LD script
        $script = '<script type="application/ld+json">' . json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>';
        $document->addCustomTag($script);

        $debugInfo[] = 'Schema INJECTED';

        if ($debugMode) {
            $document->addCustomTag('<!-- ' . implode(' | ', $debugInfo) . ' -->');
        }
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
            // variant_name contains comma-separated option value IDs
            $optionValueIds = explode(',', $variant->variant_name);

            foreach ($optionValueIds as $optionValueId) {
                $optionValueId = (int) trim($optionValueId);

                if ($optionValueId <= 0) {
                    continue;
                }

                // Get option value details
                $db    = $this->getDatabase();
                $query = $db->getQuery(true)
                    ->select([
                        $db->quoteName('o.option_unique_name'),
                        $db->quoteName('ov.optionvalue_name'),
                    ])
                    ->from($db->quoteName('#__j2commerce_optionvalues', 'ov'))
                    ->join(
                        'LEFT',
                        $db->quoteName('#__j2commerce_options', 'o')
                        . ' ON ' . $db->quoteName('ov.option_id') . ' = ' . $db->quoteName('o.j2commerce_option_id')
                    )
                    ->where($db->quoteName('ov.j2commerce_optionvalue_id') . ' = ' . $optionValueId);

                $db->setQuery($query);
                $result = $db->loadObject();

                if ($result && !empty($result->option_unique_name) && !empty($result->optionvalue_name)) {
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
            $app = $this->getApplication();
            PluginHelper::importPlugin('j2commerce');

            $event = new Event('onJ2CommerceSchemaReviewsPrepare', [
                'schema'    => $schema,
                'productId' => $productId,
            ]);
            $app->getDispatcher()->dispatch('onJ2CommerceSchemaReviewsPrepare', $event);
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

        // Clean and truncate
        $description = strip_tags($description);
        $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
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

        return array_unique(array_filter($images));
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
