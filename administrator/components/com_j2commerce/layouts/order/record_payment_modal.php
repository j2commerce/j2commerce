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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var array{payload: array<string, mixed>, methods: array<string, string>, currency: string, today: string, placed: string} $displayData Record Refund dialog. */
$payload  = $displayData['payload'];
$methods  = $displayData['methods'];
$currency = (string) $displayData['currency'];
$today    = (string) $displayData['today'];
$placed   = (string) $displayData['placed'];
$esc      = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$amountLb = Text::_('COM_J2COMMERCE_AMOUNT') . ' (' . $currency . ')';

// Core's libraries.html.bootstrap.modal.main layout (via bootstrap.renderModal) emits role="dialog" with no accessible name and a title without an id; patch both in its output.
// DELETE WHEN: that core layout accepts aria attributes or a title id.
$named = static fn (string $html, string $selector): string => str_replace(
    ['role="dialog"', 'class="modal-title"'],
    ['role="dialog" aria-modal="true" aria-labelledby="' . $selector . 'Title"', 'class="modal-title" id="' . $selector . 'Title"'],
    $html
);

$methodSelect = static function (string $id) use ($methods, $esc): string {
    $html = '<select class="form-select" id="' . $id . '" name="payment_method">';

    foreach ($methods as $value => $label) {
        $html .= '<option value="' . $esc((string) $value) . '">' . $esc($label) . '</option>';
    }

    return $html . '</select>';
};

$footer = static fn (string $formId, string $submitLabel): string =>
    '<button type="button" class="btn btn-danger" data-bs-dismiss="modal">' . $esc(Text::_('JCLOSE')) . '</button>'
    . '<button type="submit" class="btn btn-primary" form="' . $formId . '" data-j2c-record-submit>'
    . '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true" hidden data-j2c-record-spinner></span>'
    . '<span data-j2c-record-submit-label>' . $esc($submitLabel) . '</span> <span class="fa-solid fa-chevron-right" aria-hidden="true"></span></button>';

ob_start();
?>
<div class="p-3">
    <div class="alert alert-info py-2 small" data-j2c-refund-info><span class="fa-solid fa-circle-info me-1" aria-hidden="true"></span><span class="text-body-emphasis"><?php echo $esc((string) $payload['refundInfo']); ?></span></div>
    <form id="j2cRecordRefundForm" class="j2c-record-form" data-j2c-record-kind="refund" novalidate>
        <div class="alert alert-danger py-2" role="alert" hidden data-j2c-record-error></div>
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_J2COMMERCE_RECORD_REFUND_DETAILS'); ?></legend>
            <div class="form-grid">
                <div class="control-group">
                    <label class="form-label" for="j2cRrMethod"><?php echo Text::_('COM_J2COMMERCE_FIELD_REFUND_METHOD'); ?></label>
                    <?php echo $methodSelect('j2cRrMethod'); ?>
                </div>
                <div class="control-group">
                    <label class="form-label" for="j2cRrAmount"><?php echo $esc($amountLb); ?></label>
                    <input class="form-control" type="text" inputmode="decimal" autocomplete="off" id="j2cRrAmount" name="amount"
                           value="<?php echo $esc((string) $payload['refundDefault']); ?>" required>
                </div>
                <div class="control-group">
                    <label class="form-label" for="j2cRrReference"><?php echo Text::_('COM_J2COMMERCE_FIELD_REFERENCE'); ?></label>
                    <input class="form-control" type="text" id="j2cRrReference" name="reference" maxlength="255" autocomplete="off">
                </div>
                <div class="control-group">
                    <label class="form-label" for="j2cRrDate"><?php echo Text::_('COM_J2COMMERCE_FIELD_DATE_REFUNDED'); ?></label>
                    <input class="form-control" type="date" id="j2cRrDate" name="refunded_on" value="<?php echo $esc($today); ?>"
                           min="<?php echo $esc($placed); ?>" max="<?php echo $esc($today); ?>">
                </div>
                <div class="control-group">
                    <label class="form-label" for="j2cRrNote"><?php echo Text::_('COM_J2COMMERCE_NOTE'); ?></label>
                    <textarea class="form-control" id="j2cRrNote" name="note" rows="2" maxlength="1000"></textarea>
                </div>
            </div>
        </fieldset>
    </form>
</div>
<?php
$refundBody = (string) ob_get_clean();

echo $named(HTMLHelper::_('bootstrap.renderModal', 'j2cRecordRefundModal', [
    'title'     => $esc(Text::_('COM_J2COMMERCE_RECORD_REFUND')),
    'footer'    => $footer('j2cRecordRefundForm', Text::_('COM_J2COMMERCE_RECORD_REFUND')),
    'backdrop'  => 'static',
    'modalCss'  => 'modal-dialog-centered modal-dialog-scrollable',
], $refundBody), 'j2cRecordRefundModal');
