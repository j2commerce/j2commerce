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

use J2Commerce\Component\J2commerce\Administrator\Helper\ConfigHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Voucher\HtmlView $this */

J2CommerceHelper::strapper()->addCSS();

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('table.columns');

$initialValue = (float) $this->item->voucher_value;

$typeBadgeClass = [
    'redemption' => 'text-bg-primary',
    'credit'     => 'text-bg-success',
    'debit'      => 'text-bg-danger',
    'correction' => 'text-bg-secondary',
];
$typeLabelKey = [
    'redemption' => 'COM_J2COMMERCE_VOUCHER_LEDGER_TYPE_REDEMPTION',
    'credit'     => 'COM_J2COMMERCE_VOUCHER_LEDGER_TYPE_CREDIT',
    'debit'      => 'COM_J2COMMERCE_VOUCHER_LEDGER_TYPE_DEBIT',
    'correction' => 'COM_J2COMMERCE_VOUCHER_LEDGER_TYPE_CORRECTION',
];

$dateFormat = ConfigHelper::getDateFormat();
$listOrder  = $this->escape($this->ledgerState->get('list.ordering'));
$listDirn   = $this->escape($this->ledgerState->get('list.direction'));
?>

<div id="j2commerce-voucher-history">
    <!-- KPI Cards -->
    <div class="analytics-dashoard-stats-mini card mb-4">
        <div class="card-body">
            <h2 class="fs-3 mb-1">
                <?php echo Text::_('COM_J2COMMERCE_VOUCHER_HISTORY'); ?>:
                <span class="badge bg-primary"><?php echo $this->escape($this->item->voucher_code); ?></span>
            </h2>
            <p class="text-body-secondary small mb-3"><?php echo Text::_('COM_J2COMMERCE_VOUCHER_HISTORY_DESC'); ?></p>
            <nav class="quick-icons bg-transparent" aria-label="<?php echo Text::_('COM_J2COMMERCE_VOUCHER_HISTORY'); ?>">
                <div class="row flex-wrap">
                    <div class="quickicon quickicon-single col-12 col-md-6 col-lg-3 mb-3 mb-lg-0 border-0">
                        <div class="alert alert-info my-0 w-100 border-0">
                            <div class="quickicon-info d-block w-100">
                                <div class="quickicon-value mw-100 display-6 mb-3"><?php echo CurrencyHelper::format($initialValue); ?></div>
                            </div>
                            <div class="quickicon-name d-flex align-items-center">
                                <span class="j-links-link"><div class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_VOUCHER_SUMMARY_INITIAL'); ?></div></span>
                            </div>
                        </div>
                    </div>
                    <div class="quickicon quickicon-single col-12 col-md-6 col-lg-3 mb-3 mb-lg-0 border-0">
                        <div class="alert alert-warning my-0 w-100 border-0">
                            <div class="quickicon-info d-block w-100">
                                <div class="quickicon-value mw-100 display-6 mb-3"><?php echo CurrencyHelper::format($this->redeemedTotal); ?></div>
                            </div>
                            <div class="quickicon-name d-flex align-items-center">
                                <span class="j-links-link"><div class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_VOUCHER_SUMMARY_REDEEMED'); ?></div></span>
                            </div>
                        </div>
                    </div>
                    <div class="quickicon quickicon-single col-12 col-md-6 col-lg-3 mb-3 mb-lg-0 border-0">
                        <div class="alert alert-purple my-0 w-100 border-0">
                            <div class="quickicon-info d-block w-100">
                                <div class="quickicon-value mw-100 display-6 mb-3"><?php echo CurrencyHelper::format($this->adjustmentsNet); ?></div>
                            </div>
                            <div class="quickicon-name d-flex align-items-center">
                                <span class="j-links-link"><div class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_VOUCHER_SUMMARY_ADJUSTMENTS'); ?></div></span>
                            </div>
                        </div>
                    </div>
                    <div class="quickicon quickicon-single col-12 col-md-6 col-lg-3 mb-3 mb-lg-0 border-0">
                        <div class="alert alert-success my-0 w-100 border-0">
                            <div class="quickicon-info d-block w-100">
                                <div class="quickicon-value mw-100 display-6 mb-3"><?php echo CurrencyHelper::format($this->remainingBalance); ?></div>
                            </div>
                            <div class="quickicon-name d-flex align-items-center">
                                <span class="j-links-link"><div class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_VOUCHER_SUMMARY_REMAINING'); ?></div></span>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>
        </div>
    </div>

    <?php if ($this->ledgerIsEmpty) : ?>
        <?php echo $this->loadTemplate('emptystate'); ?>
    <?php else : ?>
        <form action="<?php echo Route::_('index.php?option=com_j2commerce&view=voucher&layout=history&id=' . (int) $this->item->j2commerce_voucher_id); ?>" method="post" name="adminForm" id="adminForm">
            <div class="row">
                <div class="col-md-12">
                    <div id="j-main-container" class="j-main-container">
                        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                        <?php if (empty($this->ledger)) : ?>
                            <div class="alert alert-info">
                                <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                                <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                            </div>
                        <?php else : ?>
                            <table class="table itemList" id="voucherHistoryList">
                                <caption class="visually-hidden">
                                    <?php echo Text::_('COM_J2COMMERCE_VOUCHER_HISTORY_TABLE_CAPTION'); ?>,
                                    <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?></span>,
                                    <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                                </caption>
                                <thead>
                                    <tr>
                                        <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_VOUCHER_HISTORY_DATE', 'created_on', $listDirn, $listOrder); ?></th>
                                        <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_VOUCHER_LEDGER_TYPE', 'type', $listDirn, $listOrder); ?></th>
                                        <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_VOUCHER_LEDGER_AMOUNT', 'amount', $listDirn, $listOrder); ?></th>
                                        <th scope="col" class="d-none d-md-table-cell"><?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_VOUCHER_LEDGER_RUNNING_BALANCE', 'running_balance', $listDirn, $listOrder); ?></th>
                                        <th scope="col" class="d-none d-lg-table-cell"><?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_VOUCHER_LEDGER_REFERENCE', 'reference', $listDirn, $listOrder); ?></th>
                                        <th scope="col" class="d-none d-md-table-cell"><?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_VOUCHER_LEDGER_ACTOR', 'actor', $listDirn, $listOrder); ?></th>
                                        <th scope="col" class="d-none d-lg-table-cell"><?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_VOUCHER_LEDGER_REASON', 'reason', $listDirn, $listOrder); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($this->ledger as $i => $row) : ?>
                                        <tr class="row<?php echo $i % 2; ?>">
                                            <td>
                                                <time datetime="<?php echo HTMLHelper::_('date', $row->created_on, 'c'); ?>">
                                                    <?php echo HTMLHelper::_('date', $row->created_on, $dateFormat); ?>
                                                </time>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $typeBadgeClass[$row->type] ?? 'text-bg-secondary'; ?>">
                                                    <?php echo Text::_($typeLabelKey[$row->type] ?? $row->type); ?>
                                                </span>
                                            </td>
                                            <td class="<?php echo (float) $row->signed_amount < 0 ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo ((float) $row->signed_amount < 0 ? '-' : '+') . CurrencyHelper::format(abs((float) $row->signed_amount)); ?>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <?php echo CurrencyHelper::format((float) $row->running_balance); ?>
                                            </td>
                                            <td class="d-none d-lg-table-cell">
                                                <?php if ($row->type === 'redemption' && !empty($row->order_pk)) : ?>
                                                    <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=order&j2commerce_order_id=' . (int) $row->order_pk); ?>" target="_blank" class="text-decoration-none">
                                                        <span class="icon-external-link" aria-hidden="true"></span>
                                                        <?php echo $this->escape((string) $row->reference); ?>
                                                    </a>
                                                <?php elseif (!empty($row->reference)) : ?>
                                                    <?php echo $this->escape((string) $row->reference); ?>
                                                <?php else : ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <?php echo $this->escape((string) $row->actor); ?>
                                            </td>
                                            <td class="d-none d-lg-table-cell">
                                                <?php echo $row->reason ? $this->escape((string) $row->reason) : '-'; ?>
                                                <?php if (!empty($row->note)) : ?>
                                                    <div class="small text-body-secondary"><?php echo $this->escape((string) $row->note); ?></div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <?php echo $this->ledgerPagination->getListFooter(); ?>
                        <?php endif; ?>

                        <input type="hidden" name="id" value="<?php echo (int) $this->item->j2commerce_voucher_id; ?>">
                        <input type="hidden" name="task" value="">
                        <input type="hidden" name="boxchecked" value="0">
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<div class="modal fade" id="adjustBalanceModal" tabindex="-1" aria-labelledby="adjustBalanceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="<?php echo Route::_('index.php?option=com_j2commerce&task=voucher.adjust'); ?>" method="post" class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title fs-5" id="adjustBalanceModalLabel"><?php echo Text::_('COM_J2COMMERCE_VOUCHER_ADJUST_BALANCE'); ?></h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-3">
                    <label for="adjustment_type" class="form-label"><?php echo Text::_('COM_J2COMMERCE_FIELD_ADJUSTMENT_TYPE'); ?></label>
                    <select name="adjustment_type" id="adjustment_type" class="form-select" required>
                        <option value="credit"><?php echo Text::_('COM_J2COMMERCE_VOUCHER_LEDGER_TYPE_CREDIT'); ?></option>
                        <option value="debit"><?php echo Text::_('COM_J2COMMERCE_VOUCHER_LEDGER_TYPE_DEBIT'); ?></option>
                        <option value="correction"><?php echo Text::_('COM_J2COMMERCE_VOUCHER_LEDGER_TYPE_CORRECTION'); ?></option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="adjustment_amount" class="form-label"><?php echo Text::_('COM_J2COMMERCE_FIELD_ADJUSTMENT_AMOUNT'); ?></label>
                    <input type="number" step="0.00001" name="amount" id="adjustment_amount" class="form-control" required>
                    <div class="form-text"><?php echo Text::_('COM_J2COMMERCE_VOUCHER_ADJUST_AMOUNT_HELP'); ?></div>
                </div>
                <div class="mb-3">
                    <label for="adjustment_reason" class="form-label"><?php echo Text::_('COM_J2COMMERCE_FIELD_ADJUSTMENT_REASON'); ?></label>
                    <input type="text" name="reason" id="adjustment_reason" class="form-control" maxlength="255" required>
                </div>
                <div class="mb-3">
                    <label for="adjustment_order_id" class="form-label"><?php echo Text::_('COM_J2COMMERCE_FIELD_ADJUSTMENT_ORDER_ID'); ?></label>
                    <input type="text" name="order_id" id="adjustment_order_id" class="form-control" maxlength="255">
                    <div class="form-text"><?php echo Text::_('COM_J2COMMERCE_VOUCHER_ADJUST_ORDER_ID_HELP'); ?></div>
                </div>
                <div class="mb-0">
                    <label for="adjustment_note" class="form-label"><?php echo Text::_('COM_J2COMMERCE_FIELD_ADJUSTMENT_NOTE'); ?></label>
                    <textarea name="note" id="adjustment_note" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo Text::_('COM_J2COMMERCE_VOUCHER_ADJUST_BALANCE'); ?></button>
                <input type="hidden" name="id" value="<?php echo (int) $this->item->j2commerce_voucher_id; ?>">
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </form>
    </div>
</div>
