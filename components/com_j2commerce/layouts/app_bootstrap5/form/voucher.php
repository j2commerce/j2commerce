<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;

$voucherCode  = $displayData['voucherCode'] ?? '';
$formId       = $displayData['formId'] ?? 'j2c-voucher';
$variant      = $displayData['variant'] ?? 'inline';
$accordionId  = $displayData['accordionId'] ?? '';
$expanded     = !empty($displayData['expanded']);
$discountLabel = $displayData['discountLabel'] ?? '';
$hasVoucher   = !empty($voucherCode);

?>
<?php if ($variant === 'accordion') : ?>
<div class="accordion-item">
    <h2 class="accordion-header">
        <button class="accordion-button <?php echo $expanded ? '' : 'collapsed'; ?>"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#<?php echo $formId; ?>-collapse"
                aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>"
                aria-controls="<?php echo $formId; ?>-collapse">
            <?php echo Text::_('COM_J2COMMERCE_VOUCHER_CODE'); ?>
            <?php if ($hasVoucher) : ?>
                <span class="badge bg-success ms-2 j2c-voucher-badge"><?php echo htmlspecialchars($voucherCode, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php if ($discountLabel) : ?>
                    <small class="text-body-tertiary ms-1"><?php echo htmlspecialchars($discountLabel, ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
            <?php endif; ?>
        </button>
    </h2>
    <div id="<?php echo $formId; ?>-collapse"
         class="accordion-collapse collapse <?php echo $expanded ? 'show' : ''; ?>"
         <?php if ($accordionId) : ?>data-bs-parent="#<?php echo $accordionId; ?>"<?php endif; ?>>
        <div class="accordion-body">
<?php endif; ?>

<div class="j2c-voucher-form" id="<?php echo $formId; ?>" data-type="voucher">
    <?php if ($hasVoucher) : ?>
        <div class="d-flex align-items-center justify-content-between py-1">
            <span>
                <span class="badge bg-success">
                    <span class="icon-tag me-1" aria-hidden="true"></span><?php echo htmlspecialchars($voucherCode, ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <?php if ($discountLabel) : ?>
                    <small class="text-body-tertiary ms-1"><?php echo htmlspecialchars($discountLabel, ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
            </span>
            <button type="button" class="btn btn-sm btn-link text-danger p-0 j2c-remove-voucher"
                    title="<?php echo Text::_('COM_J2COMMERCE_REMOVE_VOUCHER'); ?>">
                <span class="icon-times" aria-hidden="true"></span>
                <?php echo Text::_('COM_J2COMMERCE_REMOVE'); ?>
            </button>
        </div>
    <?php else : ?>
        <div class="j2c-voucher-input-wrap">
            <div class="input-group">
                <div class="input-group_inner">
                    <input type="text" name="voucher" class="form-control" placeholder="<?php echo Text::_('COM_J2COMMERCE_ENTER_VOUCHER_CODE'); ?>" aria-label="<?php echo Text::_('COM_J2COMMERCE_VOUCHER_CODE'); ?>" />
                </div>
                <button type="button" class="btn btn-outline-secondary j2c-apply-voucher">
                    <?php echo Text::_('COM_J2COMMERCE_APPLY_VOUCHER'); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($variant === 'accordion') : ?>
        </div>
    </div>
</div>
<?php endif; ?>
