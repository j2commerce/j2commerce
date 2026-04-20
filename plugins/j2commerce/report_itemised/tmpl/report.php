<?php
/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.ReportItemised
 *
 * @copyright   Copyright (C) 2024-2026 J2Commerce, LLC. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \stdClass $displayData */
$vars = $displayData;

$currency    = $vars->currency;
$items       = $vars->items;
$chartLabels = $vars->chartLabels;
$chartValues = $vars->chartValues;
$listOrder   = $vars->listOrder;
$listDirn    = $vars->listDirn;

?>
<div class="j2commerce-report-itemised">

    <?php if (!empty($chartLabels)) : ?>
    <div class="card mb-4">
        <div class="card-body">
            <canvas id="reportChart" height="300"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table" id="reportItemisedList">
            <caption class="visually-hidden">
                <?php echo Text::_('PLG_J2COMMERCE_REPORT_ITEMISED'); ?>,
                <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?></span>
            </caption>
            <thead>
                <tr>
                    <th scope="col" class="w-1 text-center">#</th>
                    <th scope="col" class="w-5 d-none d-md-table-cell">
                        <?php echo HTMLHelper::_('searchtools.sort',
                            'PLG_J2COMMERCE_REPORT_ITEMISED_COL_PRODUCT_ID',
                            'oi.product_id', $listDirn, $listOrder); ?>
                    </th>
                    <th scope="col">
                        <?php echo HTMLHelper::_('searchtools.sort',
                            'PLG_J2COMMERCE_REPORT_ITEMISED_COL_PRODUCT_NAME',
                            'oi.orderitem_name', $listDirn, $listOrder); ?>
                    </th>
                    <th scope="col" class="d-none d-md-table-cell">
                        <?php echo Text::_('PLG_J2COMMERCE_REPORT_ITEMISED_COL_OPTIONS'); ?>
                    </th>
                    <th scope="col" class="d-none d-md-table-cell">
                        <?php echo Text::_('PLG_J2COMMERCE_REPORT_ITEMISED_COL_CATEGORY'); ?>
                    </th>
                    <th scope="col" class="w-10 text-end">
                        <?php echo HTMLHelper::_('searchtools.sort',
                            'PLG_J2COMMERCE_REPORT_ITEMISED_COL_QUANTITY',
                            'total_qty', $listDirn, $listOrder); ?>
                    </th>
                    <th scope="col" class="w-10 text-end">
                        <?php echo HTMLHelper::_('searchtools.sort',
                            'PLG_J2COMMERCE_REPORT_ITEMISED_COL_PURCHASES',
                            'order_count', $listDirn, $listOrder); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)) : ?>
                    <?php
                    $qtyTotal   = 0;
                    $orderTotal = 0;
                    ?>
                    <?php foreach ($items as $i => $item) : ?>
                        <?php
                        $qtyTotal   += (int) $item->total_qty;
                        $orderTotal += (int) $item->order_count;
                        ?>
                        <tr>
                            <td class="text-center"><?php echo $i + 1; ?></td>
                            <td class="d-none d-md-table-cell"><?php echo (int) $item->product_id; ?></td>
                            <td>
                                <?php echo htmlspecialchars($item->orderitem_name, ENT_QUOTES, 'UTF-8'); ?>
                                <?php if (!empty($item->orderitem_sku)) : ?>
                                    <small class="text-muted d-block lh-1">
                                        <?php echo Text::_('PLG_J2COMMERCE_REPORT_ITEMISED_COL_SKU'); ?>: <?php echo htmlspecialchars($item->orderitem_sku, ENT_QUOTES, 'UTF-8'); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <?php if (!empty($item->attributes)) : ?>
                                    <?php foreach ($item->attributes as $attr) : ?>
                                        <small>
                                            <strong><?php echo htmlspecialchars($attr->orderitemattribute_name, ENT_QUOTES, 'UTF-8'); ?>:</strong>
                                            <?php echo htmlspecialchars($attr->orderitemattribute_value, ENT_QUOTES, 'UTF-8'); ?>
                                        </small><br>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <?php echo htmlspecialchars($item->category_name ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td class="text-end"><?php echo (int) $item->total_qty; ?></td>
                            <td class="text-end"><?php echo (int) $item->order_count; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="fw-bold">
                        <td colspan="3"><?php echo Text::_('PLG_J2COMMERCE_REPORT_ITEMISED_TOTAL'); ?></td>
                        <td class="d-none d-md-table-cell"></td>
                        <td class="d-none d-md-table-cell"></td>
                        <td class="text-end"><?php echo $qtyTotal; ?></td>
                        <td class="text-end"><?php echo $orderTotal; ?></td>
                    </tr>
                <?php else : ?>
                    <tr>
                        <td colspan="7" class="text-center">
                            <div class="alert alert-info mb-0">
                                <span class="icon-info-circle" aria-hidden="true"></span>
                                <?php echo Text::_('PLG_J2COMMERCE_REPORT_ITEMISED_NO_ITEMS'); ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($chartLabels)) : ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('reportChart');
    if (!ctx || typeof Chart === 'undefined') return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chartLabels, JSON_HEX_TAG | JSON_HEX_AMP); ?>,
            datasets: [{
                label: <?php echo json_encode(
                    Text::_('PLG_J2COMMERCE_REPORT_ITEMISED_COL_QUANTITY'),
                    JSON_HEX_TAG | JSON_HEX_AMP
                ); ?>,
                data: <?php echo json_encode($chartValues, JSON_HEX_TAG | JSON_HEX_AMP); ?>,
                backgroundColor: 'rgba(56, 142, 60, 0.8)',
                borderColor: 'rgba(56, 142, 60, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: <?php echo json_encode(
                        Text::_('PLG_J2COMMERCE_REPORT_ITEMISED_CHART_TITLE'),
                        JSON_HEX_TAG | JSON_HEX_AMP
                    ); ?>,
                    font: { size: 16 }
                },
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        callback: function(value) { return Number.isInteger(value) ? value : ''; }
                    }
                },
                x: {
                    ticks: {
                        maxRotation: 45,
                        minRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 20
                    }
                }
            }
        }
    });
});
</script>
<?php endif; ?>
