<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Dashboard\HtmlView $this */

$doc = Factory::getApplication()->getDocument();
$renderer = $doc->loadRenderer('modules');
$options = ['style' => 'none'];

// Calculate % change for KPIs
$prev = $this->previousPeriod;

$calcChange = function (float $current, float $previous): array {
    if ($previous == 0) {
        return ['pct' => $current > 0 ? 100.0 : 0.0, 'dir' => $current > 0 ? 'up' : 'flat'];
    }
    $pct = (($current - $previous) / $previous) * 100;
    $dir = $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat');
    return ['pct' => round(abs($pct), 1), 'dir' => $dir];
};

$revenueChange    = $calcChange($this->totalRevenue, (float) ($prev['totalRevenue'] ?? 0));
$ordersChange     = $calcChange((float) $this->orderCount, (float) ($prev['orderCount'] ?? 0));
$conversionChange = $calcChange($this->conversionRate, (float) ($prev['conversionRate'] ?? 0));
$sessionsChange   = $calcChange((float) $this->totalSessions, (float) ($prev['totalSessions'] ?? 0));

$changeHtml = function (array $change): string {
    if ($change['dir'] === 'flat') {
        return '';
    }
    $icon = $change['dir'] === 'up' ? 'fa-arrow-up' : 'fa-arrow-down';
    $dirText = $change['dir'] === 'up' ? 'Up' : 'Down';
    return '<span><span class="fa-solid ' . $icon . '" aria-hidden="true"></span><span class="visually-hidden">' . $dirText . '</span> ' . $change['pct'] . '%</span>';
};
if($doc->countModules('j2commerce-dashboard-module-main-tab') && $doc->countModules('j2commerce-dashboard-module-side-tab')){
    $colClass = 'col-lg-6';
} else {
    $colClass = 'col-12';
}
?>

<?php echo $this->navbar; ?>

<div id="j2commerce-dashboard">
    <?php if ($this->hasSampleData) : ?>
    <div class="alert alert-purple d-flex align-items-center mb-4">
        <span class="fa-solid fa-circle-info me-2" aria-hidden="true"></span>
        <span class="me-auto"><?php echo Text::_('COM_J2COMMERCE_DASHBOARD_SAMPLEDATA_ACTIVE'); ?></span>
        <button type="button" class="btn btn-sm btn-primary" id="j2c-remove-sampledata">
            <span class="fa-solid fa-trash me-1" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCE_DASHBOARD_REMOVE_SAMPLEDATA'); ?>
        </button>
    </div>
    <?php endif; ?>

    <?php if (!$this->hasProducts && !$this->hasSampleData) : ?>
    <div class="card mb-4">
        <div class="card-body text-center py-5">
            <span class="fa-solid fa-box-open fa-3x text-muted d-block mb-3" aria-hidden="true"></span>
            <h5><?php echo Text::_('COM_J2COMMERCE_DASHBOARD_EMPTY_STORE_TITLE'); ?></h5>
            <p class="text-muted"><?php echo Text::_('COM_J2COMMERCE_DASHBOARD_EMPTY_STORE_DESC'); ?></p>
            <button type="button" class="btn btn-primary" id="j2c-load-sampledata">
                <span class="fa-solid fa-database me-1" aria-hidden="true"></span>
                <?php echo Text::_('COM_J2COMMERCE_DASHBOARD_LOAD_SAMPLEDATA'); ?>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Date Filter Bar -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex flex-wrap align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <label for="dashboard-from" class="form-label mb-0 fw-bold"><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_DATE_FROM'); ?></label>
                        <?php echo HTMLHelper::_('calendar', $this->escape($this->fromDate), 'dashboard-from', 'dashboard-from', '%Y-%m-%d', ['class' => 'form-control-sm', 'style' => 'width:160px']); ?>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label for="dashboard-to" class="form-label mb-0 fw-bold"><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_DATE_TO'); ?></label>
                        <?php echo HTMLHelper::_('calendar', $this->escape($this->toDate), 'dashboard-to', 'dashboard-to', '%Y-%m-%d', ['class' => 'form-control-sm', 'style' => 'width:160px']); ?>
                    </div>
                    <button type="button" id="dashboard-refresh" class="btn btn-primary btn-sm">
                        <span class="icon-loop"></span> <?php echo Text::_('COM_J2COMMERCE_ANALYTICS_REFRESH'); ?>
                    </button>
                    <div class="btn-group btn-group-sm ms-auto" role="group">
                        <button type="button" class="btn btn-outline-secondary dashboard-preset" data-days="1"><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_LAST_1_DAY'); ?></button>
                        <button type="button" class="btn btn-outline-secondary dashboard-preset" data-days="7"><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_LAST_7_DAYS'); ?></button>
                        <button type="button" class="btn btn-outline-secondary dashboard-preset active" data-days="30"><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_LAST_30_DAYS'); ?></button>
                        <button type="button" class="btn btn-outline-secondary dashboard-preset" data-days="90"><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_LAST_90_DAYS'); ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="analytics-dashoard-stats-mini card mb-5 mt-3">
        <div class="card-body">
            <h2 class="fs-3 mb-1"><?php echo Text::_('COM_J2COMMERCE_DASHBOARD_STORE_STATS'); ?></h2>
            <p class="text-body-secondary small mb-3" id="kpi-date-range"><?php echo Text::sprintf('COM_J2COMMERCE_DASHBOARD_DATA_BASED_ON', 30, Text::_('COM_J2COMMERCE_DASHBOARD_DAYS')); ?></p>
            <nav class="quick-icons bg-transparent" aria-label="<?php echo Text::_('COM_J2COMMERCE_DASHBOARD'); ?>">
                <div class="row flex-wrap">
                    <div class="quickicon quickicon-single col-12 col-md-6 col-lg-3 mb-3 mb-lg-0 border-0">
                        <div class="alert alert-success my-0 w-100 border">
                            <div class="quickicon-info d-block w-100">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="quickicon-value display-6 mb-3" id="kpi-revenue"><?php echo $this->escape($this->formattedRevenue); ?></div>
                                    <div class="quickicon-change mb-3" id="kpi-revenue-change"><?php echo $changeHtml($revenueChange); ?></div>
                                </div>
                            </div>
                            <div class="quickicon-name d-flex align-items-center">
                                <span class="j-links-link"><div class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_TOTAL_REVENUE'); ?></div></span>
                            </div>
                        </div>
                    </div>
                    <div class="quickicon quickicon-single col-12 col-md-6 col-lg-3 mb-3 mb-lg-0 border-0">
                        <div class="alert alert-warning my-0 w-100 border">
                            <div class="quickicon-info d-block w-100">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="quickicon-value display-6 mb-3" id="kpi-orders"><?php echo $this->orderCount; ?></div>
                                    <div class="quickicon-change mb-3" id="kpi-orders-change"><?php echo $changeHtml($ordersChange); ?></div>
                                </div>
                            </div>
                            <div class="quickicon-name d-flex align-items-center">
                                <span class="j-links-link"><div class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_ORDER_COUNT'); ?></div></span>
                            </div>
                        </div>
                    </div>
                    <div class="quickicon quickicon-single col-12 col-md-6 col-lg-3 mb-3 mb-lg-0 border-0">
                        <div class="alert alert-info my-0 w-100 border">
                            <div class="quickicon-info d-block w-100">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="quickicon-value display-6 mb-3" id="kpi-conversion"><?php echo $this->escape(number_format($this->conversionRate, 1) . '%'); ?></div>
                                    <div class="quickicon-change mb-3" id="kpi-conversion-change"><?php echo $changeHtml($conversionChange); ?></div>
                                </div>
                            </div>
                            <div class="quickicon-name d-flex align-items-center">
                                <span class="j-links-link"><div class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_CONVERSION_RATE'); ?></div></span>
                            </div>
                        </div>
                    </div>
                    <div class="quickicon quickicon-single col-12 col-md-6 col-lg-3 mb-3 mb-lg-0 border-0">
                        <div class="alert alert-purple my-0 w-100 border">
                            <div class="quickicon-info d-block w-100">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="quickicon-value display-6 mb-3" id="kpi-sessions"><?php echo (int) $this->totalSessions; ?></div>
                                    <div class="quickicon-change mb-3" id="kpi-sessions-change"><?php echo $changeHtml($sessionsChange); ?></div>
                                </div>
                            </div>
                            <div class="quickicon-name d-flex align-items-center">
                                <span class="j-links-link"><div class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_SESSIONS'); ?></div></span>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>
        </div>
    </div>

    <?php if (!empty($this->dashboardMessages)) : ?>
        <div class="mb-5" id="j2commerce-dashboard-messages-wrap">
            <div class="swiper j2commerce-dashboard-messages" id="j2commerce-dashboard-messages">
                <div class="swiper-wrapper">
                    <?php foreach ($this->dashboardMessages as $msg) : ?>
                        <?php echo LayoutHelper::render('dashboard.message', $msg, JPATH_ADMINISTRATOR . '/components/com_j2commerce/layouts'); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Charts (Daily Sales + plugin tabs) + Side Charts (Monthly/Yearly + plugin tabs) -->
    <div class="row mb-4">
        <div class="col-md-8 mb-3">
            <div class="dashboard-chart-tabs h-100">
                <?php echo HTMLHelper::_('uitab.startTabSet', 'dashboardMainTabs', ['active' => 'daily-sales-tab', 'recall' => true, 'breakpoint' => 768]); ?>
                    <?php echo HTMLHelper::_('uitab.addTab', 'dashboardMainTabs', 'daily-sales-tab', Text::_('COM_J2COMMERCE_DASHBOARD_DAILY_SALES')); ?>
                        <div style="min-height:350px"><canvas id="chart-revenue"></canvas></div>
                    <?php echo HTMLHelper::_('uitab.endTab'); ?>

                    <?php if ($doc->countModules('j2commerce-dashboard-main-tab')) :
                        foreach (ModuleHelper::getModules('j2commerce-dashboard-main-tab') as $dashTab) :
                            $dashTabId    = 'dashmod' . (int) $dashTab->id;
                            $dashTabTitle = htmlspecialchars($dashTab->title ?? ('Tab ' . (int) $dashTab->id), ENT_QUOTES, 'UTF-8');
                            echo HTMLHelper::_('uitab.addTab', 'dashboardMainTabs', $dashTabId, $dashTabTitle);
                            echo ModuleHelper::renderModule($dashTab, $options);
                            echo HTMLHelper::_('uitab.endTab');
                        endforeach;
                    endif; ?>

                    <?php echo $this->dashboardMainTabHtml; ?>
                <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="dashboard-chart-tabs h-100">
                <?php echo HTMLHelper::_('uitab.startTabSet', 'dashboardSideTabs', ['active' => 'monthly-tab', 'recall' => true, 'breakpoint' => 768]); ?>
                    <?php echo HTMLHelper::_('uitab.addTab', 'dashboardSideTabs', 'monthly-tab', Text::_('COM_J2COMMERCE_DASHBOARD_MONTHLY_SALES')); ?>
                        <div style="height:350px"><canvas id="chart-monthly"></canvas></div>
                    <?php echo HTMLHelper::_('uitab.endTab'); ?>
                    <?php echo HTMLHelper::_('uitab.addTab', 'dashboardSideTabs', 'yearly-tab', Text::_('COM_J2COMMERCE_DASHBOARD_YEARLY_SALES')); ?>
                        <div style="height:350px"><canvas id="chart-yearly"></canvas></div>
                    <?php echo HTMLHelper::_('uitab.endTab'); ?>

                    <?php $dashSideModules = ModuleHelper::getModules('j2commerce-dashboard-side-tab');
                    foreach ($dashSideModules as $dashSideTab) :
                        $dashSideTabId    = 'dashside' . (int) $dashSideTab->id;
                        $dashSideTabTitle = htmlspecialchars($dashSideTab->title ?? ('Tab ' . (int) $dashSideTab->id), ENT_QUOTES, 'UTF-8');
                        echo HTMLHelper::_('uitab.addTab', 'dashboardSideTabs', $dashSideTabId, $dashSideTabTitle);
                        echo ModuleHelper::renderModule($dashSideTab, $options);
                        echo HTMLHelper::_('uitab.endTab');
                    endforeach; ?>

                    <?php echo $this->dashboardSideTabHtml; ?>
                <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
            </div>
        </div>
    </div>
    <?php if($doc->countModules('j2commerce-dashboard-module-main-tab') || $doc->countModules('j2commerce-dashboard-module-side-tab')):?>
        <div id="dashboardModuleTabs" class="row mb-4 align-items-stretch">
            <?php if($doc->countModules('j2commerce-dashboard-module-main-tab')):?>
                <div class="<?php echo $colClass;?> mb-3">
                    <div class="dashboard-chart-tabs h-100">
                        <?php if ($doc->countModules('j2commerce-dashboard-module-main-tab')) :
                            $modules = ModuleHelper::getModules('j2commerce-dashboard-module-main-tab');
                            echo HTMLHelper::_('uitab.startTabSet', 'dashboardModuleTabs', ['active' => 'module' . (int) $modules[0]->id, 'recall' => true, 'breakpoint' => 768]);

                            foreach ($modules as $module) :
                                $tabId    = 'module' . (int) $module->id;
                                $tabTitle = htmlspecialchars($module->title ?? ('Module ' . (int) $module->id), ENT_QUOTES, 'UTF-8');
                                ?>
                                <?php echo HTMLHelper::_('uitab.addTab', 'dashboardModuleTabs', $tabId, $tabTitle); ?>
                                <?php echo ModuleHelper::renderModule($module, $options); ?>
                                <?php echo HTMLHelper::_('uitab.endTab'); ?>
                            <?php endforeach; ?>
                            <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if($doc->countModules('j2commerce-dashboard-module-side-tab')):?>
                <div class="<?php echo $colClass;?> mb-3">
                    <div class="dashboard-chart-tabs h-100">
                        <?php if ($doc->countModules('j2commerce-dashboard-module-side-tab')) :
                            $modules = ModuleHelper::getModules('j2commerce-dashboard-module-side-tab');
                            echo HTMLHelper::_('uitab.startTabSet', 'dashboardModuleSideTabs', ['active' => 'moduleside' . (int) $modules[0]->id, 'recall' => true, 'breakpoint' => 768]);

                            foreach ($modules as $module) :
                                $tabId    = 'moduleside' . (int) $module->id;
                                $tabTitle = htmlspecialchars($module->title ?? ('ModuleSide ' . (int) $module->id), ENT_QUOTES, 'UTF-8');
                                ?>
                                <?php echo HTMLHelper::_('uitab.addTab', 'dashboardModuleSideTabs', $tabId, $tabTitle); ?>
                                <?php echo ModuleHelper::renderModule($module, $options); ?>
                                <?php echo HTMLHelper::_('uitab.endTab'); ?>
                            <?php endforeach; ?>
                            <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>


    <?php if (!empty($this->pluginQuickIcons)) : ?>
        <div class="card h-100">
            <div class="card-header p-3">
                <h2 class="mb-0 fs-4"><span class="fa-solid fa-bolt me-2 text-warning" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_DASHBOARD_PLUGIN_ICONS'); ?></h2>
            </div>
            <div class="card-body bg-white">
                <nav class="quick-icons px-0 pb-3" aria-label="<?php echo Text::_('COM_J2COMMERCE_DASHBOARD_PLUGIN_ICONS'); ?>">
                    <ul class="nav flex-wrap" id="j2commerce-plugin-quickicons">
                        <?php foreach ($this->pluginQuickIcons as $icon) : ?>
                            <?php echo LayoutHelper::render('dashboard.quickicon', $icon, JPATH_ADMINISTRATOR . '/components/com_j2commerce/layouts'); ?>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($doc->countModules('j2commerce-module-bottom')) : ?>
        <?php echo $renderer->render('j2commerce-module-bottom', $options, null); ?>
    <?php endif; ?>

    <?php if (!J2Commerce\Component\J2commerce\Administrator\SetupGuide\SetupGuideHelper::isComplete()) : ?>
        <?php echo $this->loadTemplate('setup_guide'); ?>
    <?php endif; ?>

</div>

<input type="hidden" id="dashboard-token" value="<?php echo Session::getFormToken(); ?>">

<?php if ($this->showOnboarding) : ?>
    <?php echo $this->loadTemplate('onboarding'); ?>
<?php endif; ?>

<?php echo $this->loadTemplate('categorywizard'); ?>

<?php echo $this->footer ?? ''; ?>
