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
$expanded     = !empty($displayData['expanded']);
$discountLabel = $displayData['discountLabel'] ?? '';
$hasVoucher   = !empty($voucherCode);

?>
<?php if ($variant === 'accordion') : ?>
<li<?php echo $expanded ? ' class="uk-open"' : ''; ?>>
    <a class="uk-accordion-title" href="#">
        <?php echo Text::_('COM_J2COMMERCE_VOUCHER_CODE'); ?>
        <?php if ($hasVoucher) : ?>
            <span class="uk-label uk-label-success uk-margin-small-left j2c-voucher-badge"><?php echo htmlspecialchars($voucherCode, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php if ($discountLabel) : ?>
                <small class="uk-text-muted uk-margin-small-left"><?php echo htmlspecialchars($discountLabel, ENT_QUOTES, 'UTF-8'); ?></small>
            <?php endif; ?>
        <?php endif; ?>
    </a>
    <div class="uk-accordion-content">
<?php endif; ?>

<div class="j2c-voucher-form" id="<?php echo $formId; ?>" data-type="voucher">
    <?php if ($hasVoucher) : ?>
        <div class="uk-flex uk-flex-middle uk-flex-between uk-padding-small">
            <span>
                <span class="uk-label uk-label-success">
                    <span class="icon-tag uk-margin-small-right" aria-hidden="true"></span><?php echo htmlspecialchars($voucherCode, ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <?php if ($discountLabel) : ?>
                    <small class="uk-text-muted uk-margin-small-left"><?php echo htmlspecialchars($discountLabel, ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
            </span>
            <button type="button" class="uk-button uk-button-link uk-text-danger j2c-remove-voucher"
                    title="<?php echo Text::_('COM_J2COMMERCE_REMOVE_VOUCHER'); ?>">
                <span class="icon-times" aria-hidden="true"></span>
                <?php echo Text::_('COM_J2COMMERCE_REMOVE'); ?>
            </button>
        </div>
    <?php else : ?>
        <div class="j2c-voucher-input-wrap">
            <div class="uk-flex uk-flex-stretch">
                <div class="uk-width-expand">
                    <input type="text" name="voucher" class="uk-input" placeholder="<?php echo Text::_('COM_J2COMMERCE_ENTER_VOUCHER_CODE'); ?>" aria-label="<?php echo Text::_('COM_J2COMMERCE_VOUCHER_CODE'); ?>" />
                </div>
                <button type="button" class="uk-button uk-button-default j2c-apply-voucher">
                    <?php echo Text::_('COM_J2COMMERCE_APPLY_VOUCHER'); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($variant === 'accordion') : ?>
    </div>
</li>
<?php endif; ?>
