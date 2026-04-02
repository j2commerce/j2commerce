<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Analytics;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\MenuHelper;
use J2Commerce\Component\J2commerce\Administrator\Model\AnalyticsModel;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Toolbar\ToolbarHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    public string $navbar = '';
    public float $totalRevenue = 0.0;
    public int $orderCount = 0;
    public float $averageOrderValue = 0.0;
    public int $itemsSold = 0;
    public array $revenueByDay = [];
    public array $ordersByDay = [];
    public array $topProducts = [];
    public array $statusDistribution = [];
    public array $checkoutFunnel = [];
    public array $pluginWidgets = [];
    public array $liveUsers = [];
    public string $fromDate = '';
    public string $toDate = '';
    public array $previousPeriod = [];
    public string $currencySymbol = '';
    public string $currencyPosition = 'pre';
    public string $formattedRevenue = '';
    public string $formattedAOV = '';
    public array $sessionsAvgByHour = [];
    public int $sessionsTotal = 0;
    public array $conversionsAvgByHour = [];
    public int $conversionsTotal = 0;
    public array $conversionBreakdown = [];
    public array $deviceTypes = [];

    public function display($tpl = null): void
    {
        if (!J2CommerceHelper::canAccess('j2commerce.viewreports')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();
        $this->navbar = $this->getNavbar();

        // Register Chart.js (local bundle) and analytics.js via WAM
        $wa = $this->getDocument()->getWebAssetManager();
        $wa->registerAndUseScript(
            'chartjs',
            'media/com_j2commerce/js/administrator/chart.umd.min.js',
            [],
            ['defer' => true]
        );
        $wa->registerAndUseScript(
            'com_j2commerce.analytics',
            'media/com_j2commerce/js/administrator/analytics.js',
            [],
            ['defer' => true],
            ['chartjs']
        );

        // Default date range: last 30 days
        $this->toDate   = date('Y-m-d');
        $this->fromDate = date('Y-m-d', strtotime('-30 days'));

        $fromDateTime = $this->fromDate . ' 00:00:00';
        $toDateTime   = $this->toDate . ' 23:59:59';

        /** @var AnalyticsModel $model */
        $model = $this->getModel();

        $this->totalRevenue       = $model->getTotalRevenue($fromDateTime, $toDateTime);
        $this->orderCount         = $model->getOrderCount($fromDateTime, $toDateTime);
        $this->averageOrderValue  = $model->getAverageOrderValue($fromDateTime, $toDateTime);
        $this->itemsSold          = $model->getItemsSold($fromDateTime, $toDateTime);
        $this->revenueByDay       = $model->getRevenueByDay($fromDateTime, $toDateTime);
        $this->ordersByDay        = $model->getOrdersByDay($fromDateTime, $toDateTime);
        $this->topProducts        = $model->getTopProducts($fromDateTime, $toDateTime);
        $this->statusDistribution = $model->getOrderStatusDistribution($fromDateTime, $toDateTime);
        $this->checkoutFunnel     = $model->getCheckoutFunnel($fromDateTime, $toDateTime);
        $this->previousPeriod     = $model->getPreviousPeriodData($fromDateTime, $toDateTime);

        // Averaged hourly sessions/conversions across the date range
        $sessionsData              = $model->getSessionsByHourAvg($fromDateTime, $toDateTime);
        $this->sessionsAvgByHour   = $sessionsData['hourly'];
        $this->sessionsTotal       = $sessionsData['total'];

        $conversionsData              = $model->getConversionsByHourAvg($fromDateTime, $toDateTime);
        $this->conversionsAvgByHour   = $conversionsData['hourly'];
        $this->conversionsTotal       = $conversionsData['total'];

        $this->conversionBreakdown = $model->getConversionBreakdown($fromDateTime, $toDateTime);
        $this->deviceTypes         = $model->getDeviceTypes($fromDateTime, $toDateTime);

        // Currency formatting
        $this->currencySymbol   = CurrencyHelper::getSymbol();
        $this->currencyPosition = CurrencyHelper::getSymbolPosition();
        $this->formattedRevenue = CurrencyHelper::format($this->totalRevenue);
        $this->formattedAOV     = CurrencyHelper::format($this->averageOrderValue);

        // Format top product revenue
        foreach ($this->topProducts as $product) {
            $product->formatted_revenue = CurrencyHelper::format((float) $product->total_revenue);
        }

        // Get date format from config
        $params     = ComponentHelper::getParams('com_j2commerce');
        $dateFormat = $params->get('date_format', 'Y-m-d H:i:s');

        // Pass data to JavaScript
        $this->getDocument()->addScriptOptions('com_j2commerce.analytics', [
            'totalRevenue'       => $this->totalRevenue,
            'orderCount'         => $this->orderCount,
            'averageOrderValue'  => $this->averageOrderValue,
            'itemsSold'          => $this->itemsSold,
            'revenueByDay'       => $this->revenueByDay,
            'ordersByDay'        => $this->ordersByDay,
            'topProducts'        => $this->topProducts,
            'statusDistribution' => $this->statusDistribution,
            'checkoutFunnel'     => $this->checkoutFunnel,
            'previousPeriod'     => $this->previousPeriod,
            'from'               => $this->fromDate,
            'to'                 => $this->toDate,
            'ajaxUrl'            => 'index.php?option=com_j2commerce&task=analytics.getData&format=json',
            'currencySymbol'     => $this->currencySymbol,
            'currencyPosition'   => $this->currencyPosition,
            'dateFormat'         => $dateFormat,
            'formattedRevenue'   => $this->formattedRevenue,
            'formattedAOV'       => $this->formattedAOV,
            'sessionsAvgByHour'    => $this->sessionsAvgByHour,
            'sessionsTotal'        => $this->sessionsTotal,
            'conversionsAvgByHour' => $this->conversionsAvgByHour,
            'conversionsTotal'     => $this->conversionsTotal,
            'conversionBreakdown'  => $this->conversionBreakdown,
            'deviceTypes'          => $this->deviceTypes,
        ]);

        // Live users widget (from DashboardModel)
        $dashModel = Factory::getApplication()->bootComponent('com_j2commerce')
            ->getMVCFactory()->createModel('Dashboard', 'Administrator', ['ignore_request' => true]);
        $this->liveUsers = $dashModel ? $dashModel->getLiveUsers() : [];

        $wa->registerAndUseScript(
            'com_j2commerce.liveusers',
            'media/com_j2commerce/js/administrator/liveusers.js',
            [],
            ['defer' => true]
        );

        // CSRF token is handled by Joomla core (HTMLHelper::_('form.csrf')) as a plain string
        Text::script('COM_J2COMMERCE_LIVE_USERS_JUST_NOW');
        Text::script('COM_J2COMMERCE_LIVE_USERS_MINUTES_AGO');
        Text::script('COM_J2COMMERCE_ANALYTICS_NO_DATA');

        // Dispatch plugin widget hook
        PluginHelper::importPlugin('j2commerce');
        $event = new \Joomla\Event\Event('onJ2CommerceGetAnalyticsWidgets', [$this->fromDate, $this->toDate]);
        Factory::getApplication()->getDispatcher()->dispatch('onJ2CommerceGetAnalyticsWidgets', $event);
        $this->pluginWidgets = $event->getArgument('result', []);

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
        ToolbarHelper::title(Text::_('COM_J2COMMERCE_STATISTICS_DASHBOARD'), 'fa-solid fa-chart-pie');

        if ($this->getCurrentUser()->authorise('core.admin', 'com_j2commerce')) {
            ToolbarHelper::preferences('com_j2commerce');
        }

        ToolbarHelper::help('J2Commerce', true, 'https://docs.j2commerce.com/');
    }
}
