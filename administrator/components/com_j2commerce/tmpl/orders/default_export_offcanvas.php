<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Orders\HtmlView $this */

/**
 * String entries render as regular vertical fields. Array entries are
 * [group label key, "from" field name, "to" field name] and render both
 * fields inline inside a single Bootstrap input group.
 */
$exportLayout = [
    'search',
    ['COM_J2COMMERCE_EXPORT_ORDER_ID_RANGE_LABEL', 'from_j2commerce_order_id', 'to_j2commerce_order_id'],
    ['COM_J2COMMERCE_EXPORT_DATE_RANGE_LABEL', 'since', 'until'],
    'users',
    ['COM_J2COMMERCE_EXPORT_AMOUNT_RANGE_LABEL', 'amount_from', 'amount_to'],
    'payment_type',
    'order_state_id',
    'coupon_code',
];

$countText = $this->exportCount === 1
    ? Text::_('COM_J2COMMERCE_N_ORDERS_WILL_BE_EXPORTED_1')
    : Text::sprintf('COM_J2COMMERCE_N_ORDERS_WILL_BE_EXPORTED', $this->exportCount);
?>
<div class="offcanvas offcanvas-end" tabindex="-1" id="orderExportOffcanvas" aria-labelledby="orderExportOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="orderExportOffcanvasLabel"><?php echo Text::_('COM_J2COMMERCE_EXPORT_ORDERS'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?php echo $this->escape(Text::_('JCANCEL')); ?>"></button>
    </div>
    <div class="offcanvas-body">
        <p class="text-body-secondary"><?php echo Text::_('COM_J2COMMERCE_EXPORT_ORDERS_DESC'); ?></p>

        <form action="<?php echo Route::_('index.php?option=com_j2commerce&task=orders.export'); ?>" method="post" id="orderExportForm">
            <div class="form-vertical">
                <?php foreach ($exportLayout as $exportEntry) : ?>
                    <?php if (\is_array($exportEntry)) : ?>
                        <?php [$rangeLabel, $fromName, $toName] = $exportEntry; ?>
                        <?php $fromField = $this->exportForm->getField($fromName); ?>
                        <?php $toField   = $this->exportForm->getField($toName); ?>
                        <div class="control-group">
                            <div class="control-label">
                                <label for="<?php echo $this->escape($fromField->id); ?>">
                                    <?php echo Text::_($rangeLabel); ?>
                                </label>
                            </div>
                            <div class="controls">
                                <?php // The visible range label is bound to the "from" input only,
                                      // so the "to" input needs its own screen-reader-only name. ?>
                                <label for="<?php echo $this->escape($toField->id); ?>" class="visually-hidden">
                                    <?php echo $this->escape($toField->title); ?>
                                </label>
                                <div class="input-group flex-nowrap">
                                    <?php echo $this->exportForm->getInput($fromName); ?>
                                    <span class="input-group-text" aria-hidden="true">&ndash;</span>
                                    <?php echo $this->exportForm->getInput($toName); ?>
                                </div>
                            </div>
                        </div>
                    <?php else : ?>
                        <?php echo $this->exportForm->renderField($exportEntry); ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <p class="fw-bold mt-3" id="orderExportCount" data-default="<?php echo $this->escape($countText); ?>">
                <?php echo $countText; ?>
            </p>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">
                    <span class="icon-download" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCE_EXPORT_CSV'); ?>
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="offcanvas"><?php echo Text::_('JCANCEL'); ?></button>
            </div>

            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    </div>
</div>
