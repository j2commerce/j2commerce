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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$item = $this->item;
$orderHistory = $item->orderhistory ?? [];
$dateFormat = $this->dateFormat;
$timeFormat = $this->timeFormat;

$listLimit = (int) Factory::getApplication()->get('list_limit', 20);
$totalItems = count($orderHistory);
$totalPages = $totalItems > 0 ? (int) ceil($totalItems / $listLimit) : 1;
$currentPage = array_slice($orderHistory, 0, $listLimit);

$firstKey = array_key_first($currentPage);
$lastKey = array_key_last($currentPage);
$currentUserId = (int) (Factory::getApplication()->getIdentity()?->id ?? 0);
?>
<div class="card order-history-card mb-4 border-0 shadow-none bg-transparent" id="j2c-order-history-card">
    <div class="card-header d-flex justify-content-between align-items-center px-0">
        <h2 class="card-title mb-0 fs-4"><?php echo Text::_('COM_J2COMMERCE_ORDER_HISTORY'); ?></h2>
        <?php if ($totalItems > 0) : ?>
            <span class="<?php echo J2htmlHelper::badgeClass('badge text-bg-secondary'); ?>"><?php echo $totalItems; ?></span>
        <?php endif; ?>
    </div>

    <div class="admin-note-card card mb-3 mx-0 mt-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0 fs-5"><?php echo Text::_('COM_J2COMMERCE_ORDER_NOTE'); ?></h3>
            <button type="button" class="btn btn-sm btn-primary" id="addAdminNoteBtn">
                <span class="icon-plus" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_ADD_NOTE'); ?>
            </button>
        </div>
        <div class="card-body">
            <textarea class="form-control" id="adminOrderNote" rows="2" placeholder="<?php echo Text::_('COM_J2COMMERCE_ORDER_NOTE_PLACEHOLDER'); ?>" aria-label="<?php echo Text::_('COM_J2COMMERCE_ORDER_NOTE'); ?>"></textarea>
            <small class="text-muted d-block mt-1"><?php echo Text::_('COM_J2COMMERCE_ORDER_NOTE_INTERNAL_ONLY'); ?></small>
        </div>
    </div>
    <div class="py-3">
        <?php if (!empty($orderHistory)) : ?>
        <div id="j2c-history-container"
             data-order-id="<?php echo $this->escape($item->order_id); ?>"
             data-total-pages="<?php echo $totalPages; ?>"
             data-current-page="1"
             data-limit="<?php echo $listLimit; ?>"
             data-current-user="<?php echo $currentUserId; ?>">
            <div id="j2c-history-items">
                <?php foreach ($currentPage as $i => $history) :
                    $cssClass = $history->orderstatus_cssclass ?? 'badge text-bg-secondary';
                    $keywords = ['success', 'info', 'primary', 'warning', 'danger'];
                    $foundColor = 'secondary';
                    foreach ($keywords as $kw) {
                        if (str_contains($cssClass, $kw)) {
                            $foundColor = $kw;
                            break;
                        }
                    }

                    $comment = $history->comment ?? '';
                    $isFirst = ($i === $firstKey);
                    $isLast = ($i === $lastKey);

                    // Timeline line segments
                    $col1 = $isFirst ? '' : ' border-end';
                    $col2 = $isLast ? '' : ' border-end';

                    // Icon selection
                    if ($isFirst && $totalPages === 1) {
                        $icon = 'fa-solid fa-cart-plus fa-fw text-' . $foundColor;
                    } elseif ($isFirst) {
                        $icon = 'fa-solid fa-cart-plus fa-fw text-' . $foundColor;
                    } else {
                        $icon = 'fa-solid fa-circle fa-fw border border-2 rounded-circle border-white text-' . $foundColor;
                    }

                    // Override icon for admin notes, notifications and removals
                    $params = json_decode($history->params ?? '{}', true) ?: [];
                    $isAdminNote = ($params['type'] ?? '') === 'admin_note';

                    if ($isAdminNote) {
                        $icon = 'fa-solid fa-user fa-fw text-' . $foundColor;
                    } elseif (stripos($comment, 'notified with') !== false) {
                        $icon = 'fa-solid fa-envelope fa-fw text-' . $foundColor;
                    } elseif (stripos($comment, 'item removed') !== false) {
                        $icon = 'fa-solid fa-trash fa-fw text-' . $foundColor;
                    }

                    $iconEvent = J2CommerceHelper::plugin()->event(
                        'RenderOrderHistoryIcon',
                        ['order' => $item, 'historyItem' => $history, 'icon' => $icon]
                    );
                    $icon = J2CommerceHelper::sanitizeHistoryIconClass(
                        (string) $iconEvent->getArgument('icon', $icon)
                    ) ?: $icon;
                ?>
                <div class="row j2c-history-row">
                    <div class="col-auto text-center flex-column d-none d-lg-flex">
                        <div class="row h-50 mb-n1">
                            <div class="col<?php echo $col1; ?>"></div>
                            <div class="col"></div>
                        </div>
                        <div class="m-2 fs-5 j2c-history-indicator">
                            <span class="<?php echo $this->escape($icon); ?>" aria-hidden="true"></span>
                        </div>
                        <div class="row h-50">
                            <div class="col<?php echo $col2; ?>"></div>
                            <div class="col"></div>
                        </div>
                    </div>
                    <div class="col px-2 py-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="float-end text-end text-body-secondary small fw-bold">
                                    <div><?php echo HTMLHelper::_('date', $history->created_on, $dateFormat); ?></div>
                                    <div class="fw-normal"><?php echo HTMLHelper::_('date', $history->created_on, $timeFormat); ?></div>
                                    <?php if ($isAdminNote && (int) ($history->created_by ?? 0) === $currentUserId) : ?>
                                        <button type="button" class="btn btn-sm btn-link text-danger p-0 mt-0 j2c-delete-note" data-history-id="<?php echo (int) $history->j2commerce_orderhistory_id; ?>" title="<?php echo Text::_('JACTION_DELETE'); ?>">
                                            <span class="icon-trash small" aria-hidden="true"></span>
                                            <span class="visually-hidden"><?php echo Text::_('JACTION_DELETE'); ?></span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($history->order_state_id)) : ?>
                                    <h3 class="card-title small mb-1 fs-4">
                                        <span class="<?php echo J2htmlHelper::badgeClass('badge rounded-2 px-2 text-bg-' . $this->escape($foundColor)); ?>">
                                            <?php echo $this->escape(Text::_($history->orderstatus_name ?? 'Unknown')); ?>
                                        </span>
                                    </h3>
                                <?php endif; ?>
                                <?php if ($isAdminNote) : ?>
                                    <strong><?php echo Text::_('COM_J2COMMERCE_ORDER_NOTE'); ?></strong>
                                <?php endif; ?>
                                <?php if (!empty($comment)) : ?>
                                    <p class="card-text text-body-secondary small mb-0"><?php echo $this->escape($comment); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1) : ?>
            <nav class="j2c-history-pagination pagination__wrapper d-flex justify-content-center py-3" aria-label="<?php echo Text::_('COM_J2COMMERCE_ORDER_HISTORY'); ?>">
                <div class="pagination pagination-toolbar text-center mt-0">
                    <ul class="pagination ms-auto me-0">
                        <li class="page-item disabled" data-page="prev">
                            <a class="page-link" href="#" aria-label="<?php echo Text::_('JPREVIOUS'); ?>">
                                <span class="icon-angle-left" aria-hidden="true"></span>
                            </a>
                        </li>
                        <?php for ($p = 1; $p <= $totalPages; $p++) : ?>
                            <li class="page-item<?php echo $p === 1 ? ' active' : ''; ?>" data-page="<?php echo $p; ?>">
                                <a class="page-link" href="#"><?php echo $p; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item<?php echo $totalPages === 1 ? ' disabled' : ''; ?>" data-page="next">
                            <a class="page-link" href="#" aria-label="<?php echo Text::_('JNEXT'); ?>">
                                <span class="icon-angle-right" aria-hidden="true"></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            <?php endif; ?>
        </div>
        <?php else : ?>
            <div class="alert alert-info mb-0"><?php echo Text::_('COM_J2COMMERCE_NO_ORDER_HISTORY'); ?></div>
        <?php endif; ?>
    </div>
</div>
