<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Dashboard;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\ConfigHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\MenuHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OnboardingHelper;
use J2Commerce\Component\J2commerce\Administrator\Model\AnalyticsModel;
use J2Commerce\Component\J2commerce\Administrator\Model\DashboardModel;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\SetupGuideHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    public string $navbar = '';
    public int $productsCount = 0;
    public int $ordersCount = 0;
    public int $customersCount = 0;
    public array $recentOrders = [];
    public array $liveUsers = [];

    // Analytics KPIs (date-filtered)
    public float $totalRevenue = 0.0;
    public int $orderCount = 0;
    public float $conversionRate = 0.0;
    public int $totalSessions = 0;
    public array $revenueByDay = [];
    public array $previousPeriod = [];
    public string $fromDate = '';
    public string $toDate = '';
    public string $currencySymbol = '';
    public string $currencyPosition = 'pre';
    public string $formattedRevenue = '';

    // Sales tabs (all-time)
    public array $monthlySales = [];
    public array $yearlySales = [];

    // Plugin tab injection (uitab format) — main = col-md-8, side = col-md-4
    public string $dashboardMainTabHtml = '';
    public string $dashboardSideTabHtml = '';

    // Plugin quick icons
    public array $pluginQuickIcons = [];

    // Plugin dashboard messages (independent of quick icons)
    public array $dashboardMessages = [];

    // Onboarding wizard
    public bool $showOnboarding = false;

    public function display($tpl = null): void
    {
        $this->loadAdminAssets();
        $this->navbar = $this->getNavbar();

        // Register Chart.js and dashboard JS
        $wa = $this->getDocument()->getWebAssetManager();
        $wa->registerAndUseScript('chartjs', 'media/com_j2commerce/js/administrator/chart.umd.min.js', [], ['defer' => true]);
        $wa->registerAndUseScript('com_j2commerce.dashboard', 'media/com_j2commerce/js/administrator/dashboard.js', [], ['defer' => true], ['chartjs']);
        $wa->registerAndUseScript('com_j2commerce.liveusers', 'media/com_j2commerce/js/administrator/liveusers.js', [], ['defer' => true]);

        // Default date range: last 30 days in store timezone
        $tz      = Factory::getApplication()->getConfig()->get('offset', 'UTC');
        $storeTz = new \DateTimeZone($tz);
        $utcTz   = new \DateTimeZone('UTC');
        $now     = new \DateTimeImmutable('now', $storeTz);

        $this->toDate   = $now->format('Y-m-d');
        $this->fromDate = $now->modify('-29 days')->format('Y-m-d');

        // Convert store-local day boundaries to UTC for SQL queries
        $fromLocal    = new \DateTimeImmutable($this->fromDate . ' 00:00:00', $storeTz);
        $toLocal      = new \DateTimeImmutable($this->toDate . ' 23:59:59', $storeTz);
        $fromDateTime = $fromLocal->setTimezone($utcTz)->format('Y-m-d H:i:s');
        $toDateTime   = $toLocal->setTimezone($utcTz)->format('Y-m-d H:i:s');

        /** @var DashboardModel $model */
        $model = $this->getModel();

        $this->productsCount  = $model->getProductsCount();
        $this->ordersCount    = $model->getOrdersCount();
        $this->customersCount = $model->getCustomersCount();
        $this->recentOrders   = $model->getRecentOrders(5);
        $this->liveUsers      = $model->getLiveUsers();

        // All-time sales data for tabs
        $this->monthlySales = $model->getMonthlySales();
        $this->yearlySales  = $model->getYearlySales();

        // Date-filtered KPIs from AnalyticsModel
        $analyticsModel = Factory::getApplication()->bootComponent('com_j2commerce')
            ->getMVCFactory()->createModel('Analytics', 'Administrator', ['ignore_request' => true]);

        if ($analyticsModel) {
            $this->totalRevenue   = $analyticsModel->getTotalRevenue($fromDateTime, $toDateTime);
            $this->orderCount     = $analyticsModel->getOrderCount($fromDateTime, $toDateTime);
            $this->revenueByDay   = $analyticsModel->getRevenueByDay($fromDateTime, $toDateTime);
            $this->previousPeriod = $analyticsModel->getPreviousPeriodData($fromDateTime, $toDateTime);

            $breakdown = $analyticsModel->getConversionBreakdown($fromDateTime, $toDateTime);
            $this->conversionRate = (float) ($breakdown['overallRate'] ?? 0.0);
            $this->totalSessions  = (int) ($breakdown['totalSessions'] ?? 0);
        }

        // Currency formatting
        $this->currencySymbol   = CurrencyHelper::getSymbol();
        $this->currencyPosition = CurrencyHelper::getSymbolPosition();
        $this->formattedRevenue = CurrencyHelper::format($this->totalRevenue);

        $params     = ComponentHelper::getParams('com_j2commerce');
        $dateFormat = $params->get('date_format', 'Y-m-d H:i:s');

        // Pass data to JavaScript
        $this->getDocument()->addScriptOptions('com_j2commerce.dashboard', [
            'totalRevenue'      => $this->totalRevenue,
            'orderCount'        => $this->orderCount,
            'conversionRate'    => $this->conversionRate,
            'totalSessions'     => $this->totalSessions,
            'revenueByDay'      => $this->revenueByDay,
            'previousPeriod'    => $this->previousPeriod,
            'monthlySales'      => $this->monthlySales,
            'yearlySales'       => $this->yearlySales,
            'from'              => $this->fromDate,
            'to'                => $this->toDate,
            'ajaxUrl'           => 'index.php?option=com_j2commerce&task=dashboard.getData&format=json',
            'currencySymbol'    => $this->currencySymbol,
            'currencyPosition'  => $this->currencyPosition,
            'dateFormat'        => $dateFormat,
            'formattedRevenue'  => $this->formattedRevenue,
            'storeTimezone'     => $tz,
        ]);

        // Plugin tab injection — plugins output uitab.addTab/endTab blocks
        $eventData = [$this->monthlySales, $this->yearlySales, $this->revenueByDay];
        $this->dashboardMainTabHtml = J2CommerceHelper::plugin()->eventWithHtml('DashboardMainTabContent', $eventData)->getArgument('html', '');
        $this->dashboardSideTabHtml = J2CommerceHelper::plugin()->eventWithHtml('DashboardSideTabContent', $eventData)->getArgument('html', '');

        // Collect plugin quick icons
        $quickIconEvent = J2CommerceHelper::plugin()->event('GetQuickIcons', ['context' => 'j2commerce_dashboard']);
        $rawIcons = $quickIconEvent->getArgument('result', []);

        $this->pluginQuickIcons = [];

        foreach ($rawIcons as $entry) {
            if (isset($entry['id'], $entry['link'], $entry['text'])) {
                $this->pluginQuickIcons[] = $entry;
            }
        }

        if (!empty($this->pluginQuickIcons)) {
            $this->getDocument()->addScriptOptions('com_j2commerce.quickicons', [
                'ajaxIcons' => array_filter(
                    array_column($this->pluginQuickIcons, 'ajaxUrl', 'id'),
                    fn($url) => !empty($url)
                ),
            ]);
        }

        // Collect dashboard messages from plugins (independent of quick icons)
        $msgEvent = J2CommerceHelper::plugin()->event('GetDashboardMessages', ['context' => 'j2commerce_dashboard']);
        $rawMessages = $msgEvent->getArgument('result', []);

        $this->dashboardMessages = [];

        foreach ($rawMessages as $msg) {
            if (isset($msg['id'], $msg['text'])) {
                $this->dashboardMessages[] = $msg;
            }
        }

        usort($this->dashboardMessages, fn($a, $b) => ($a['priority'] ?? 500) <=> ($b['priority'] ?? 500));

        if (!empty($this->dashboardMessages)) {
            $wa->registerAndUseScript('com_j2commerce.swiper', 'media/com_j2commerce/js/site/swiper-bundle.min.js', [], ['defer' => true]);
            $wa->registerAndUseStyle('com_j2commerce.swiper.css', 'media/com_j2commerce/css/site/swiper-bundle.min.css');
            $this->getDocument()->addScriptOptions('com_j2commerce.dashboardMessages', [
                'messageIds' => array_column($this->dashboardMessages, 'id'),
            ]);
        }

        Text::script('COM_J2COMMERCE_LIVE_USERS_JUST_NOW');
        Text::script('COM_J2COMMERCE_LIVE_USERS_MINUTES_AGO');
        Text::script('COM_J2COMMERCE_ANALYTICS_NO_DATA');
        Text::script('COM_J2COMMERCE_DASHBOARD_DATA_BASED_ON');
        Text::script('COM_J2COMMERCE_DASHBOARD_DAY');
        Text::script('COM_J2COMMERCE_DASHBOARD_DAYS');
        Text::script('COM_J2COMMERCE_DASHBOARD_MSG_DISMISS_SESSION');
        Text::script('COM_J2COMMERCE_DASHBOARD_MSG_DISMISS_FOREVER');
        Text::script('COM_J2COMMERCE_LOADING');

        if (!SetupGuideHelper::isComplete()) {
            $wa->registerAndUseScript('com_j2commerce.setup-guide', 'media/com_j2commerce/js/administrator/setup-guide.js', [], ['defer' => true]);
            $wa->registerAndUseStyle('com_j2commerce.setup-guide.css', 'media/com_j2commerce/css/administrator/setup-guide.css');
            $this->getDocument()->addScriptOptions('com_j2commerce.setupGuide', [
                'isRtl' => Factory::getApplication()->getLanguage()->isRtl(),
            ]);
            Text::script('COM_J2COMMERCE_SETUP_GUIDE_PROGRESS');
            Text::script('COM_J2COMMERCE_SETUP_GUIDE_ALL_COMPLETE');
            Text::script('COM_J2COMMERCE_SETUP_GUIDE_ALL_COMPLETE_DESC');
            Text::script('COM_J2COMMERCE_SETUP_GUIDE_DISMISS');
            Text::script('COM_J2COMMERCE_SETUP_GUIDE_ACTION_ENABLE');
            Text::script('COM_J2COMMERCE_SETUP_GUIDE_ACTION_CREATE');
            Text::script('COM_J2COMMERCE_SETUP_GUIDE_ACTION_PUBLISH');
            Text::script('COM_J2COMMERCE_SETUP_GUIDE_ACTION_CONFIGURE');
            Text::script('COM_J2COMMERCE_SETUP_GUIDE_START_GUIDED_TOUR');
            Text::script('COM_J2COMMERCE_SETUP_GUIDE_PARAM_SAVED');
            Text::script('COM_J2COMMERCE_SETUP_GUIDE_CHECK_DOWNLOAD_ID_PLACEHOLDER');
            Text::script('COM_J2COMMERCE_SETUP_GUIDE_CHECK_DOWNLOAD_ID_SAVE');
            Text::script('COM_J2COMMERCE_SETUP_GUIDE_CHECK_DOWNLOAD_ID_CLEAR');
        }

        // Onboarding wizard — show on first visit or when re-run requested
        $onboardingComplete = (int) ConfigHelper::get('onboarding_complete', 0);

        if (Factory::getApplication()->getInput()->getInt('rerun_onboarding', 0) === 1) {
            OnboardingHelper::persistConfig(['onboarding_complete' => '0', 'onboarding_last_step' => '0']);
            $onboardingComplete = 0;
        }

        if ($onboardingComplete === 0) {
            $this->showOnboarding = true;

            // Init Bootstrap modal JS — MUST be called before parent::display()
            HTMLHelper::_('bootstrap.modal', '#j2commerceOnboardingModal', [
                'backdrop' => 'static',
                'keyboard' => false,
            ]);

            $wa->registerAndUseScript('com_j2commerce.onboarding', 'media/com_j2commerce/js/administrator/onboarding.js', [], ['defer' => true]);
            $wa->registerAndUseStyle('com_j2commerce.onboarding.css', 'media/com_j2commerce/css/administrator/onboarding.css');

            // Build JS options for onboarding (replaces inline <script> in template)
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

            $currencyQuery = $db->getQuery(true)
                ->select([$db->quoteName('currency_code'), $db->quoteName('currency_symbol'), $db->quoteName('currency_position')])
                ->from($db->quoteName('#__j2commerce_currencies'))
                ->where($db->quoteName('enabled') . ' = 1');
            $currencyRows = $db->setQuery($currencyQuery)->loadObjectList();

            $currencyMeta = [];

            foreach ($currencyRows as $cr) {
                $currencyMeta[$cr->currency_code] = ['symbol' => $cr->currency_symbol, 'position' => $cr->currency_position];
            }

            $weightQuery = $db->getQuery(true)
                ->select([$db->quoteName('j2commerce_weight_id', 'id'), $db->quoteName('weight_title', 'title')])
                ->from($db->quoteName('#__j2commerce_weights'))
                ->where($db->quoteName('enabled') . ' = 1');
            $weightRows = $db->setQuery($weightQuery)->loadObjectList('id');

            $lengthQuery = $db->getQuery(true)
                ->select([$db->quoteName('j2commerce_length_id', 'id'), $db->quoteName('length_title', 'title')])
                ->from($db->quoteName('#__j2commerce_lengths'))
                ->where($db->quoteName('enabled') . ' = 1');
            $lengthRows = $db->setQuery($lengthQuery)->loadObjectList('id');

            $countryDefaults = [];
            $mappedCountries = [223, 222, 38, 13, 101, 14, 21, 33, 53, 55, 56, 57, 67, 72, 73, 81, 84, 97, 103, 105, 117, 123, 124, 132, 150, 170, 171, 175, 189, 190, 195, 203];

            foreach ($mappedCountries as $cid) {
                $def = OnboardingHelper::getCountryDefaults($cid);
                $countryDefaults[$cid] = [
                    'currency'    => $def['currency'],
                    'weight_id'   => $def['weight_id'],
                    'weight_name' => $weightRows[$def['weight_id']]->title ?? '',
                    'length_id'   => $def['length_id'],
                    'length_name' => $lengthRows[$def['length_id']]->title ?? '',
                ];
            }

            $resumeStep = OnboardingHelper::getResumeStep();
            $savedZoneId = (int) ConfigHelper::get('zone_id', 0);

            $this->getDocument()->addScriptOptions('com_j2commerce.onboarding', [
                'currencyMeta'    => $currencyMeta,
                'countryDefaults' => $countryDefaults,
                'zoneAjaxUrl'     => 'index.php?option=com_j2commerce&task=ajax.getZones',
                'resumeStep'      => $resumeStep,
                'savedZoneId'     => $savedZoneId,
            ]);

            Text::script('COM_J2COMMERCE_ONBOARDING_BTN_CONTINUE');
            Text::script('COM_J2COMMERCE_ONBOARDING_BTN_FINISH');
            Text::script('COM_J2COMMERCE_ONBOARDING_DISMISS_CONFIRM');
            Text::script('COM_J2COMMERCE_ONBOARDING_ERR_REQUIRED');
            Text::script('COM_J2COMMERCE_ONBOARDING_ERR_NETWORK');
            Text::script('COM_J2COMMERCE_ONBOARDING_ERR_SAVE');
            Text::script('COM_J2COMMERCE_ONBOARDING_MEASUREMENTS_SYNCED');
            Text::script('COM_J2COMMERCE_ONBOARDING_TAX_CREATED');
            Text::script('COM_J2COMMERCE_ONBOARDING_TAX_SKIPPED');
            Text::script('COM_J2COMMERCE_ONBOARDING_LANG_INSTALLING');
            Text::script('COM_J2COMMERCE_ONBOARDING_LANG_SUCCESS');
            Text::script('COM_J2COMMERCE_ONBOARDING_READY_SUMMARY_STORE');
            Text::script('COM_J2COMMERCE_ONBOARDING_READY_SUMMARY_CURRENCY');
            Text::script('COM_J2COMMERCE_ONBOARDING_READY_SUMMARY_MEASUREMENTS');
            Text::script('COM_J2COMMERCE_ONBOARDING_READY_SUMMARY_TAX');
            Text::script('COM_J2COMMERCE_ONBOARDING_READY_SUMMARY_PRODUCTS');
            Text::script('COM_J2COMMERCE_ONBOARDING_READY_SUMMARY_NOT_CONFIGURED');
            Text::script('COM_J2COMMERCE_ONBOARDING_READY_SUMMARY_NOT_SELECTED');
            Text::script('COM_J2COMMERCE_ONBOARDING_DEFAULTS_PREVIEW');
        }

        $this->addToolbar();
        parent::display($tpl);
    }

    protected function getNavbar(): string
    {
        $displayData = [
            'items'  => MenuHelper::getMenuItems(),
            'active' => MenuHelper::getActiveView(),
        ];

        return LayoutHelper::render('navbar.default', $displayData, JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
    }

    protected function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_J2COMMERCE_DASHBOARD'), 'fa-solid fa-tachometer-alt');

        if (!SetupGuideHelper::isComplete()) {
            $toolbar = $this->getDocument()->getToolbar();
            $toolbar->standardButton('setup-guide', 'COM_J2COMMERCE_SETUP_GUIDE', '')
                ->icon('fa-solid fa-wand-magic-sparkles text-white')
                ->listCheck(false)
                ->attributes(['class' => 'btn btn-purple'])
                ->onclick("document.getElementById('j2commerce-setup-guide') && bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('j2commerce-setup-guide')).show(); return false;");
        }

        if ($this->getCurrentUser()->authorise('core.admin', 'com_j2commerce')) {
            ToolbarHelper::preferences('com_j2commerce');
        }

        ToolbarHelper::help('J2Commerce', true, 'https://docs.j2commerce.com/');
    }
}
