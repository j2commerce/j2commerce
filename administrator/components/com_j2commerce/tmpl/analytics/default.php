<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Session\Session;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Analytics\HtmlView $this */

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

$revenueChange = $calcChange($this->totalRevenue, (float) ($prev['totalRevenue'] ?? 0));
$ordersChange  = $calcChange((float) $this->orderCount, (float) ($prev['orderCount'] ?? 0));
$aovChange     = $calcChange($this->averageOrderValue, (float) ($prev['averageOrderValue'] ?? 0));
$itemsChange   = $calcChange((float) $this->itemsSold, (float) ($prev['itemsSold'] ?? 0));

$changeHtml = function (array $change): string {
    if ($change['dir'] === 'flat') {
        return '';
    }
    $icon  = $change['dir'] === 'up' ? 'fa-arrow-up' : 'fa-arrow-down';
    $dirText = $change['dir'] === 'up' ? Text::_('COM_J2COMMERCE_DASHBOARD_TREND_UP') : Text::_('COM_J2COMMERCE_DASHBOARD_TREND_DOWN');

    return '<span><span class="fa-solid ' . $icon . '" aria-hidden="true"></span><span class="visually-hidden">' . $dirText . '</span> ' . $change['pct'] . '%</span>';
};
?>

<?php echo $this->navbar; ?>

<div id="j2commerce-analytics">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex flex-wrap align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <label for="analytics-from" class="form-label mb-0 fw-bold"><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_DATE_FROM'); ?></label>
                        <?php echo HTMLHelper::_('calendar', $this->escape($this->fromDate), 'analytics-from', 'analytics-from', '%Y-%m-%d', ['class' => 'form-control-sm', 'style' => 'width:160px']); ?>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label for="analytics-to" class="form-label mb-0 fw-bold"><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_DATE_TO'); ?></label>
                        <?php echo HTMLHelper::_('calendar', $this->escape($this->toDate), 'analytics-to', 'analytics-to', '%Y-%m-%d', ['class' => 'form-control-sm', 'style' => 'width:160px']); ?>
                    </div>
                    <button type="button" id="analytics-refresh" class="btn btn-primary btn-sm">
                        <span class="icon-loop"></span> <?php echo Text::_('COM_J2COMMERCE_ANALYTICS_REFRESH'); ?>
                    </button>
                    <div class="btn-group btn-group-sm ms-auto" role="group">
                        <button type="button" class="btn btn-outline-secondary analytics-preset" data-days="7"><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_LAST_7_DAYS'); ?></button>
                        <button type="button" class="btn btn-outline-secondary analytics-preset active" data-days="30"><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_LAST_30_DAYS'); ?></button>
                        <button type="button" class="btn btn-outline-secondary analytics-preset" data-days="90"><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_LAST_90_DAYS'); ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="analytics-dashoard-stats-mini card mb-5 mt-3">
        <div class="card-body">
            <nav class="quick-icons" aria-label="J2Commerce Analytics Dashboard Stats Mini">
                <div class="row flex-wrap">
                    <div class="quickicon quickicon-single col-12 col-md-6 col-lg-3 mb-3 mb-lg-0 border-0">
                        <div class="alert alert-success my-0 w-100 border-0">
                            <div class="quickicon-info d-block w-100">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="quickicon-value display-6 mb-3" id="kpi-revenue"><?php echo $this->escape($this->formattedRevenue); ?></div>
                                    <div class="quickicon-change mb-3" id="kpi-revenue-change"><?php echo $changeHtml($revenueChange); ?></div>
                                </div>
                            </div>
                            <div class="quickicon-name d-flex align-items-center">
                                <span class="j-links-link">
                                    <div class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_TOTAL_REVENUE'); ?></div>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="quickicon quickicon-single col-12 col-md-6 col-lg-3 mb-3 mb-lg-0 border-0">
                        <div class="alert alert-warning my-0 w-100 border-0">
                            <div class="quickicon-info d-block w-100">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="quickicon-value display-6 mb-3" id="kpi-orders"><?php echo $this->orderCount; ?></div>
                                    <div class="quickicon-change mb-3" id="kpi-orders-change"><?php echo $changeHtml($ordersChange); ?></div>
                                </div>
                            </div>
                            <div class="quickicon-name d-flex align-items-center">
                                <span class="j-links-link">
                                    <div class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_ORDER_COUNT'); ?></div>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="quickicon quickicon-single col-12 col-md-6 col-lg-3 mb-3 mb-lg-0 border-0">
                        <div class="alert alert-info my-0 w-100 border-0">
                            <div class="quickicon-info d-block w-100">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="quickicon-value display-6 mb-3" id="kpi-aov"><?php echo $this->escape($this->formattedAOV); ?></div>
                                    <div class="quickicon-change mb-3" id="kpi-aov-change"><?php echo $changeHtml($aovChange); ?></div>
                                </div>
                            </div>
                            <div class="quickicon-name d-flex align-items-center">
                                <span class="j-links-link">
                                    <div class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_AOV'); ?></div>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="quickicon quickicon-single col-12 col-md-6 col-lg-3 mb-3 mb-lg-0 border-0">
                        <div class="alert alert-purple my-0 w-100 border-0">
                            <div class="quickicon-info d-block w-100">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="quickicon-value display-6 mb-3" id="kpi-items"><?php echo $this->itemsSold; ?></div>
                                    <div class="quickicon-change mb-3" id="kpi-items-change"><?php echo $changeHtml($itemsChange); ?></div>
                                </div>
                            </div>
                            <div class="quickicon-name d-flex align-items-center">
                                <span class="j-links-link">
                                    <div class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_ITEMS_SOLD'); ?></div>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>
        </div>
    </div>
    <?php $analyticsModuleOptions = ['style' => 'none']; ?>
    <div id="analyticsMainCharts">
        <div class="row mb-4">
            <div class="col-md-8 mb-3">
                <div id="analyticsMainChart" class="dashboard-chart-tabs h-100">
                    <?php echo HTMLHelper::_('uitab.startTabSet', 'analyticsMainTabs', ['active' => 'analytics-revenue-tab', 'recall' => true, 'breakpoint' => 768]); ?>
                        <?php echo HTMLHelper::_('uitab.addTab', 'analyticsMainTabs', 'analytics-revenue-tab', Text::_('COM_J2COMMERCE_ANALYTICS_REVENUE_TREND')); ?>
                            <div style="min-height:350px"><canvas id="chart-revenue"></canvas></div>
                        <?php echo HTMLHelper::_('uitab.endTab'); ?>

                        <?php $analyticsMainModules = ModuleHelper::getModules('j2commerce-analytics-main-tab');
                        foreach ($analyticsMainModules as $analyticsMainTab) :
                            $analyticsMainTabId    = 'analytmain' . (int) $analyticsMainTab->id;
                            $analyticsMainTabTitle = htmlspecialchars($analyticsMainTab->title ?? ('Tab ' . (int) $analyticsMainTab->id), ENT_QUOTES, 'UTF-8');
                            echo HTMLHelper::_('uitab.addTab', 'analyticsMainTabs', $analyticsMainTabId, $analyticsMainTabTitle);
                            echo ModuleHelper::renderModule($analyticsMainTab, $analyticsModuleOptions);
                            echo HTMLHelper::_('uitab.endTab');
                        endforeach; ?>
                    <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div id="analyticsSideChart" class="dashboard-chart-tabs h-100">
                    <?php echo HTMLHelper::_('uitab.startTabSet', 'analyticsSideTabs', ['active' => 'analytics-status-tab', 'recall' => true, 'breakpoint' => 768]); ?>
                        <?php echo HTMLHelper::_('uitab.addTab', 'analyticsSideTabs', 'analytics-status-tab', Text::_('COM_J2COMMERCE_ANALYTICS_STATUS_DISTRIBUTION')); ?>
                            <div style="height:350px"><canvas id="chart-status"></canvas></div>
                        <?php echo HTMLHelper::_('uitab.endTab'); ?>

                        <?php $analyticsSideModules = ModuleHelper::getModules('j2commerce-analytics-side-tab');
                        foreach ($analyticsSideModules as $analyticsSideTab) :
                            $analyticsSideTabId    = 'analytside' . (int) $analyticsSideTab->id;
                            $analyticsSideTabTitle = htmlspecialchars($analyticsSideTab->title ?? ('Tab ' . (int) $analyticsSideTab->id), ENT_QUOTES, 'UTF-8');
                            echo HTMLHelper::_('uitab.addTab', 'analyticsSideTabs', $analyticsSideTabId, $analyticsSideTabTitle);
                            echo ModuleHelper::renderModule($analyticsSideTab, $analyticsModuleOptions);
                            echo HTMLHelper::_('uitab.endTab');
                        endforeach; ?>
                    <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
                </div>
            </div>
        </div>
    </div>


    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="card h-100" id="j2commerce-breakdown-widget">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="mb-0 fs-4"><span class="fa-solid fa-filter me-2 text-info" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_CONVERSION_RATES'); ?></h2>
                </div>
                <div class="card-body">
                    <?php
                    $stages = [
                        ['label' => Text::_('COM_J2COMMERCE_ANALYTICS_BREAKDOWN_SESSIONS'), 'rate' => $bd['rates']['sessions'] ?? 100, 'count' => $bd['totalSessions'] ?? 0, 'opacity' => '0.3'],
                        ['label' => Text::_('COM_J2COMMERCE_ANALYTICS_BREAKDOWN_ADDED_TO_CART'), 'rate' => $bd['rates']['addedToCart'] ?? 0, 'count' => $bd['addedToCart'] ?? 0, 'opacity' => '0.5'],
                        ['label' => Text::_('COM_J2COMMERCE_ANALYTICS_BREAKDOWN_REACHED_CHECKOUT'), 'rate' => $bd['rates']['reachedCheckout'] ?? 0, 'count' => $bd['reachedCheckout'] ?? 0, 'opacity' => '0.75'],
                        ['label' => Text::_('COM_J2COMMERCE_ANALYTICS_BREAKDOWN_COMPLETED'), 'rate' => $bd['rates']['completedOrder'] ?? 0, 'count' => $bd['completedOrder'] ?? 0, 'opacity' => '1'],
                    ];
                    ?>
                    <div id="breakdown-stats">
                        <?php foreach ($stages as $stage) : ?>
                            <div class="d-flex align-items-center mb-2">
                                <div class="text-body-secondary small text-nowrap" style="width:110px"><?php echo $this->escape($stage['label']); ?></div>
                                <div class="flex-grow-1 mx-2">
                                    <div class="progress rounded-0" style="height:22px">
                                        <div class="progress-bar" role="progressbar" style="width:<?php echo $stage['rate']; ?>%;background-color:rgba(54,162,235,<?php echo $stage['opacity']; ?>)" aria-valuenow="<?php echo $stage['rate']; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $stage['rate']; ?>%</div>
                                    </div>
                                </div>
                                <span class="badge text-bg-info"><?php echo (int) $stage['count']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="mb-0 fs-4"><span class="fa-solid fa-cart-shopping me-2 text-info" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_CHECKOUT_FUNNEL'); ?></h2>
                </div>
                <div class="card-body"><canvas id="chart-funnel"></canvas></div>
            </div>
        </div>
    </div>


    <div class="row mb-4">
        <div class="col-12 col-lg-4 mb-3">
            <div class="card h-100" id="j2commerce-sessions-widget">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="mb-0 fs-4"><span class="fa-solid fa-chart-line me-2 text-info" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_SESSIONS_BY_TIME'); ?></h2>
                    <span class="badge text-bg-<?php echo $this->sessionsTotal > 0 ? 'success' : 'warning'; ?>" id="badge-sessions-total"><?php echo $this->sessionsTotal; ?></span>
                </div>
                <div class="card-body">
                    <div style="height:200px"><canvas id="chart-sessions"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4 mb-3">
            <div class="card h-100" id="j2commerce-conversions-widget">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="mb-0 fs-4"><span class="fa-solid fa-chart-area me-2 text-info" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_CONVERSIONS_BY_TIME'); ?></h2>
                    <span class="badge text-bg-<?php echo $this->conversionsTotal > 0 ? 'success' : 'warning'; ?>" id="badge-conversions-total"><?php echo $this->conversionsTotal; ?></span>
                </div>
                <div class="card-body">
                    <div style="height:200px"><canvas id="chart-conversions"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4 mb-3">

        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12 col-lg-6 mb-3">
            <?php echo LayoutHelper::render('widget.liveusers',$this->liveUsers,JPATH_ADMINISTRATOR . '/components/com_j2commerce/layouts'); ?>
        </div>
        <div class="col-12 col-lg-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="mb-0 fs-4"><span class="fa-solid fa-trophy me-2 text-info" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_TOP_PRODUCTS'); ?></h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="analytics-products">
                            <caption class="visually-hidden">
                                <?php echo Text::_('COM_J2COMMERCE_ANALYTICS_TOP_PRODUCTS'); ?>,
                                <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                                <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="w-5"><?php echo Text::_('JGRID_HEADING_ID');?></th>
                                    <th scope="col"><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_PRODUCT_NAME'); ?></th>
                                    <th scope="col" class="w-10"><?php echo Text::_('COM_J2COMMERCE_HEADING_QTY'); ?></th>
                                    <th scope="col" class="text-end w-15"><?php echo Text::_('COM_J2COMMERCE_SALES'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($this->topProducts)) : ?>
                                    <tr><td colspan="4" class="text-center text-muted"><?php echo Text::_('COM_J2COMMERCE_ANALYTICS_NO_DATA'); ?></td></tr>
                                <?php else : ?>
                                    <?php foreach ($this->topProducts as $i => $product) : ?>
                                        <tr>
                                            <td><?php echo $i + 1; ?></td>
                                            <td><?php echo $this->escape($product->name); ?></td>
                                            <td class="text-start"><?php echo (int) $product->total_qty; ?></td>
                                            <td class="text-end"><?php echo $this->escape($product->formatted_revenue); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Plugin Widgets -->
    <?php if (!empty($this->pluginWidgets)) : ?>
    <div class="row mb-4">
        <?php foreach ($this->pluginWidgets as $widgetHtml) : ?>
            <?php echo $widgetHtml; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<input type="hidden" id="analytics-token" value="<?php echo Session::getFormToken(); ?>">

<?php echo $this->footer ?? ''; ?>
