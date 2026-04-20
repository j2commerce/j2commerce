<?php
/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.ReportProducts
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
<div class="j2commerce-report-products">


    <?php if (!empty($chartLabels)) : ?>
    <div class="card mb-4">
        <div class="card-body">
            <canvas id="reportChart" height="300"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table" id="reportProductsList">
            <caption class="visually-hidden">
                <?php echo Text::_('PLG_J2COMMERCE_REPORT_PRODUCTS'); ?>,
                <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?></span>
            </caption>
            <thead>
                <tr>
                    <th scope="col">
                        <?php echo HTMLHelper::_('searchtools.sort', 'PLG_J2COMMERCE_REPORT_PRODUCTS_PRODUCT_NAME', 'orderitem_name', $listDirn, $listOrder); ?>
                    </th>
                    <th scope="col" class="w-10 d-none d-md-table-cell">
                        <?php echo HTMLHelper::_('searchtools.sort', 'PLG_J2COMMERCE_REPORT_PRODUCTS_TOTAL_QUANTITY', 'total_qty', $listDirn, $listOrder); ?>
                    </th>
                    <th scope="col" class="w-10 d-none d-md-table-cell">
                        <?php echo HTMLHelper::_('searchtools.sort', 'PLG_J2COMMERCE_REPORT_PRODUCTS_DISCOUNT', 'total_item_discount', $listDirn, $listOrder); ?>
                    </th>
                    <th scope="col" class="w-10 d-none d-md-table-cell">
                        <?php echo HTMLHelper::_('searchtools.sort', 'PLG_J2COMMERCE_REPORT_PRODUCTS_TAX', 'total_item_tax', $listDirn, $listOrder); ?>
                    </th>
                    <th scope="col" class="w-10 d-none d-lg-table-cell">
                        <?php echo HTMLHelper::_('searchtools.sort', 'PLG_J2COMMERCE_REPORT_PRODUCTS_WITHOUT_TAX', 'total_final_price_without_tax', $listDirn, $listOrder); ?>
                    </th>
                    <th scope="col" class="w-10 text-end">
                        <?php echo HTMLHelper::_('searchtools.sort', 'PLG_J2COMMERCE_REPORT_PRODUCTS_WITH_TAX', 'total_final_price_with_tax', $listDirn, $listOrder); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)) : ?>
                    <?php
                    $qtyTotal        = 0;
                    $discountTotal   = 0.0;
                    $totalWithoutTax = 0.0;
                    $totalWithTax    = 0.0;
                    $totalTax        = 0.0;
                    ?>
                    <?php foreach ($items as $i => $product) : ?>
                        <?php
                        $qtyTotal        += (int) $product->total_qty;
                        $discountTotal   += (float) $product->total_item_discount + (float) $product->total_item_discount_tax;
                        $totalWithoutTax += (float) $product->total_final_price_without_tax;
                        $totalWithTax    += (float) $product->total_final_price_with_tax;
                        $totalTax        += (float) $product->total_item_tax;
                        ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($product->orderitem_name, ENT_QUOTES, 'UTF-8'); ?>
                                <small class="text-muted d-block lh-1"><?php echo Text::_('PLG_J2COMMERCE_REPORT_PRODUCTS_SKU'); ?>: <?php echo htmlspecialchars($product->orderitem_sku, ENT_QUOTES, 'UTF-8'); ?></small>
                            </td>
                            <td class="text-start d-none d-md-table-cell"><?php echo (int) $product->total_qty; ?></td>
                            <td class="text-start d-none d-md-table-cell"><?php echo $currency->format((float) $product->total_item_discount + (float) $product->total_item_discount_tax); ?></td>
                            <td class="text-start d-none d-md-table-cell"><?php echo $currency->format((float) $product->total_item_tax); ?></td>
                            <td class="text-start d-none d-lg-table-cell"><?php echo $currency->format((float) $product->total_final_price_without_tax); ?></td>
                            <td class="text-end"><?php echo $currency->format((float) $product->total_final_price_with_tax); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="fw-bold">
                        <td><?php echo Text::_('PLG_J2COMMERCE_REPORT_PRODUCTS_TOTAL'); ?></td>
                        <td class="text-start d-none d-md-table-cell"><?php echo $qtyTotal; ?></td>
                        <td class="text-start d-none d-md-table-cell"><?php echo $currency->format($discountTotal); ?></td>
                        <td class="text-start d-none d-md-table-cell"><?php echo $currency->format($totalTax); ?></td>
                        <td class="text-start d-none d-lg-table-cell"><?php echo $currency->format($totalWithoutTax); ?></td>
                        <td class="text-end"><?php echo $currency->format($totalWithTax); ?></td>
                    </tr>
                <?php else : ?>
                    <tr>
                        <td colspan="6" class="text-center">
                            <div class="alert alert-info mb-0">
                                <span class="icon-info-circle" aria-hidden="true"></span>
                                <?php echo Text::_('PLG_J2COMMERCE_REPORT_PRODUCTS_NO_ITEMS'); ?>
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
                label: <?php echo json_encode(Text::_('PLG_J2COMMERCE_REPORT_PRODUCTS_WITH_TAX'), JSON_HEX_TAG | JSON_HEX_AMP); ?>,
                data: <?php echo json_encode($chartValues, JSON_HEX_TAG | JSON_HEX_AMP); ?>,
                backgroundColor: 'rgba(25, 118, 210, 0.8)',
                borderColor: 'rgba(25, 118, 210, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: <?php echo json_encode(Text::_('PLG_J2COMMERCE_REPORT_PRODUCTS_CHART_TITLE'), JSON_HEX_TAG | JSON_HEX_AMP); ?>,
                    font: { size: 16 }
                },
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: function(value) { return value.toLocaleString(); } }
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
