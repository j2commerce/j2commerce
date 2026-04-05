<?php

/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_stats
 *
 * @copyright   (C) 2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use Joomla\CMS\Language\Text;

/**
 * @var  \stdClass                $module  The module object
 * @var  \Joomla\Registry\Registry $params  The module params
 * @var  array                    $stats   Order statistics from StatsHelper::getAllStats()
 */
$stats  = $stats ?? [];
$module = $module ?? new \stdClass();

if (empty($stats)) {
    return;
}

$daysInMonth    = max(1, (int) ($stats['daysInMonth'] ?? 1));
$thisMonthCount = (int) ($stats['thisMonth']->count ?? 0);
$thisMonthTotal = (float) ($stats['thisMonth']->total ?? 0.0);
$avgCount       = $thisMonthCount / $daysInMonth;
$avgTotal       = $thisMonthTotal / $daysInMonth;

$leftColumn = [
    ['key' => 'total',     'label' => 'MOD_J2COMMERCE_STATS_TOTAL_ORDERS'],
    ['key' => 'lastYear',  'label' => 'MOD_J2COMMERCE_STATS_LAST_YEAR'],
    ['key' => 'thisYear',  'label' => 'MOD_J2COMMERCE_STATS_THIS_YEAR'],
    ['key' => 'lastMonth', 'label' => 'MOD_J2COMMERCE_STATS_LAST_MONTH'],
];

$rightColumn = [
    ['key' => 'thisMonth', 'label' => 'MOD_J2COMMERCE_STATS_THIS_MONTH'],
    ['key' => 'last7Days', 'label' => 'MOD_J2COMMERCE_STATS_LAST_7_DAYS'],
    ['key' => 'yesterday', 'label' => 'MOD_J2COMMERCE_STATS_YESTERDAY'],
    ['key' => 'today',     'label' => 'MOD_J2COMMERCE_STATS_TODAY', 'bold' => true],
];
?>
<div class="j2commerce_statistics" id="j2commerce-stats-<?php echo $module->id; ?>" style="min-height:350px">
    <div class="row">
        <?php foreach ([$leftColumn, $rightColumn] as $colIndex => $rows) : ?>
            <div class="col-md-6<?php echo $colIndex === 0 ? ' border-end' : ''; ?>">
                    <table class="table mb-0">
                        <caption class="visually-hidden"><?php echo Text::_('MOD_J2COMMERCE_STATS_ORDER_STATISTICS'); ?></caption>
                        <thead>
                        <tr>
                            <th scope="col"><span class="visually-hidden"><?php echo Text::_('MOD_J2COMMERCE_STATS_PERIOD'); ?></span></th>
                            <th scope="col" class="text-center"><?php echo Text::_('MOD_J2COMMERCE_STATS_TOTAL'); ?></th>
                            <th scope="col" class="text-end"><?php echo Text::_('MOD_J2COMMERCE_STATS_AMOUNT'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row) :
                            $count = (int) ($stats[$row['key']]->count ?? 0);
                            $total = (float) ($stats[$row['key']]->total ?? 0.0);
                            $bold  = !empty($row['bold']);
                        ?>
                            <tr>
                                <td><?php echo $bold ? '<strong>' : ''; ?><?php echo Text::_($row['label']); ?><?php echo $bold ? '</strong>' : ''; ?></td>
                                <td class="text-center"><?php echo $bold ? '<strong>' : ''; ?><?php echo $count; ?><?php echo $bold ? '</strong>' : ''; ?></td>
                                <td class="text-end"><?php echo $bold ? '<strong>' : ''; ?><?php echo CurrencyHelper::format($total); ?><?php echo $bold ? '</strong>' : ''; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="mt-3 px-3 py-2 bg-light">
        <div class="row">
            <div class="col text-center">
                <strong><?php echo Text::_('MOD_J2COMMERCE_STATS_DAILY_AVERAGE'); ?>:</strong>
                <span class="ms-2"><?php echo sprintf('%01.1f', $avgCount); ?> <?php echo Text::_('MOD_J2COMMERCE_STATS_TOTAL'); ?></span>
                <span class="ms-3"><?php echo CurrencyHelper::format($avgTotal); ?></span>
            </div>
        </div>
    </div>
</div>
