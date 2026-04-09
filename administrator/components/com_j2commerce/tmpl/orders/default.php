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

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Orders\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')
    ->useScript('multiselect');

$user      = $this->getCurrentUser();
$userId    = $user->id;
$canEdit   = $user->authorise('core.edit', 'com_j2commerce');
$canEditState = $user->authorise('core.edit.state', 'com_j2commerce');
$listOrder  = $this->escape($this->state->get('list.ordering'));
$listDirn   = $this->escape($this->state->get('list.direction'));
$dateFormat = ComponentHelper::getParams('com_j2commerce')->get('date_format', 'Y-m-d H:i:s');

?>

<?php echo $this->navbar; ?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=orders'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table itemList align-middle" id="ordersList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_J2COMMERCE_ORDERS'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?></span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                        </caption>
                        <thead>
                            <tr>
                                <td class="w-1 text-center">
                                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                                </td>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_ORDER', 'a.order_id', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="d-none d-lg-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_DATE', 'a.created_on', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_CUSTOMER', 'oi.billing_last_name', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="text-end">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_TOTAL', 'a.order_total', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="d-none d-md-table-cell">
                                    <?php echo Text::_('COM_J2COMMERCE_HEADING_PAYMENT'); ?>
                                </th>
                                <th scope="col" class="text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_STATUS', 'a.order_state_id', $listDirn, $listOrder); ?>
                                </th>
                                <?php if ($canEditState) : ?>
                                <th scope="col" class="text-center">
                                    <?php echo Text::_('COM_J2COMMERCE_HEADING_ACTIONS'); ?>
                                </th>
                                <?php endif; ?>
                                <th scope="col" class="w-3 d-none d-lg-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.j2commerce_order_id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->items as $i => $item) :
                            $customerName = trim(($item->billing_first_name ?? '') . ' ' . ($item->billing_last_name ?? ''));
                            if (empty($customerName)) {
                                $customerName = $item->user_email ?? Text::_('COM_J2COMMERCE_GUEST');
                            }
                            $paymentDisplay = !empty($item->payment_plugin_name)
                                ? Text::_($item->payment_plugin_name)
                                : $this->escape($item->orderpayment_type ?? '');
                            $orderViewUrl = Route::_('index.php?option=com_j2commerce&view=order&layout=view&id=' . $item->j2commerce_order_id);
                            $orderEditUrl = Route::_('index.php?option=com_j2commerce&view=order&layout=edit&id=' . $item->j2commerce_order_id);
                        ?>
                            <tr class="row<?php echo $i % 2; ?>" data-order-id="<?php echo (int) $item->j2commerce_order_id; ?>">
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->j2commerce_order_id, false, 'cid', 'cb', $item->invoice); ?>
                                </td>
                                <th scope="row">
                                    <?php if ($canEdit) : ?>
                                        <a href="<?php echo $orderViewUrl; ?>" class="small">
                                            <?php echo $this->escape($item->order_id); ?>
                                        </a>
                                    <?php else : ?>
                                        <small><?php echo $this->escape($item->order_id); ?></small>
                                    <?php endif; ?>
                                </th>
                                <td class="d-none d-lg-table-cell">
                                    <small><?php echo HTMLHelper::_('date', $item->created_on, $dateFormat); ?></small>
                                </td>
                                <td>
                                    <strong class="small"><?php echo $this->escape($customerName); ?></strong>
                                    <?php if (!empty($item->user_email) && $customerName !== $item->user_email) : ?>
                                        <div class="small text-break"><?php echo $this->escape($item->user_email); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($item->discount_code)) : ?>
                                        <span class="clickTooltip ms-1" role="button" tabindex="0" style="cursor:pointer;" data-bs-toggle="tooltip" data-bs-trigger="click" data-bs-placement="top" title="<?php echo $this->escape(Text::_('COM_J2COMMERCE_COUPON_CODE') . ': ' . $item->discount_code); ?>">
                                            <span class="fas fa-scissors fa-cut text-warning" aria-hidden="true"></span>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <strong class="small"><?php echo CurrencyHelper::format((float) $item->order_total, $item->currency_code ?? '', (float) ($item->currency_value ?? 1)); ?></strong>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <small><?php echo $this->escape(strip_tags($paymentDisplay)); ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="order-status-badge <?php echo $this->escape($item->orderstatus_cssclass ?? 'badge text-bg-secondary'); ?>">
                                        <?php echo Text::_($item->orderstatus_name ?? 'COM_J2COMMERCE_UNKNOWN'); ?>
                                    </span>
                                </td>
                                <?php if ($canEditState) : ?>
                                <td class="text-center">
                                    <div class="j2commerce-order-actions">
                                        <div class="d-flex align-items-center gap-1 justify-content-center flex-nowrap">
                                            <select class="form-select form-select-sm order-status-select" style="width: auto; min-width: 120px;"
                                                    data-order-id="<?php echo (int) $item->j2commerce_order_id; ?>"
                                                    aria-label="<?php echo Text::_('COM_J2COMMERCE_CHANGE_STATUS'); ?>">
                                                <?php foreach ($this->orderStatuses as $status) : ?>
                                                    <option value="<?php echo (int) $status->j2commerce_orderstatus_id; ?>"
                                                        <?php echo ((int) $item->order_state_id === (int) $status->j2commerce_orderstatus_id) ? 'selected' : ''; ?>>
                                                        <?php echo Text::_($status->orderstatus_name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="btn btn-sm btn-primary order-status-save"
                                                    data-order-id="<?php echo (int) $item->j2commerce_order_id; ?>"
                                                    title="<?php echo Text::_('COM_J2COMMERCE_SAVE_STATUS'); ?>">
                                                <?php echo Text::_('JAPPLY'); ?>
                                            </button>
                                            <a href="<?php echo $orderViewUrl; ?>" class="btn btn-sm btn-link"
                                               aria-label="<?php echo Text::sprintf('COM_J2COMMERCE_VIEW_ORDER_X', $this->escape($item->order_id)); ?>"
                                               title="<?php echo Text::sprintf('COM_J2COMMERCE_VIEW_ORDER_X', $this->escape($item->order_id)); ?>">
                                                <span class="icon-eye" aria-hidden="true"></span>
                                            </a>
                                            <?php // TODO: Re-enable edit link in a future release when order editing is fully implemented ?>
                                            <?php /* <a href="<?php echo $orderEditUrl; ?>" class="btn btn-sm btn-link" title="<?php echo Text::_('JACTION_EDIT'); ?>">
                                                <span class="icon-pencil-alt" aria-hidden="true"></span>
                                            </a> */ ?>
                                        </div>
                                        <div class="form-check form-switch mt-1 d-flex align-items-center justify-content-center gap-1">
                                            <input type="checkbox" class="form-check-input order-notify-check me-2" role="switch" id="notify_<?php echo (int) $item->j2commerce_order_id; ?>" data-order-id="<?php echo (int) $item->j2commerce_order_id; ?>" style="position: relative;top: -3px;" />
                                            <label class="form-check-label small text-muted me-1" for="notify_<?php echo (int) $item->j2commerce_order_id; ?>">
                                                <?php echo Text::_('COM_J2COMMERCE_NOTIFY_CUSTOMER'); ?>
                                            </label>
                                            <span class="clickTooltip" role="button" tabindex="0" style="cursor:pointer;" data-bs-toggle="tooltip" data-bs-trigger="click" data-bs-placement="top" title="<?php echo $this->escape(Text::_('COM_J2COMMERCE_NOTIFY_CUSTOMER_DESC')); ?>">
                                                <span class="icon-info-circle text-muted small" style="top:-1px;position:relative;"></span>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <?php endif; ?>
                                <td class="d-none d-lg-table-cell">
                                    <?php echo (int) $item->j2commerce_order_id; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>

                <input type="hidden" name="task" value="">
                <input type="hidden" name="boxchecked" value="0">
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>

<template id="joomla-dialog-batch"><?php echo $this->loadTemplate('batch_body'); ?></template>

<?php echo $this->footer ?? ''; ?>
