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

$couponCode   = $displayData['couponCode'] ?? '';
$formId       = $displayData['formId'] ?? 'j2c-coupon';
$variant      = $displayData['variant'] ?? 'inline';
$expanded     = !empty($displayData['expanded']);
$discountLabel = $displayData['discountLabel'] ?? '';
$hasCoupon    = !empty($couponCode);

?>
<?php if ($variant === 'accordion') : ?>
<li<?php echo $expanded ? ' class="uk-open"' : ''; ?>>
    <a class="uk-accordion-title" href="#">
        <?php echo Text::_('COM_J2COMMERCE_COUPON_CODE'); ?>
        <?php if ($hasCoupon) : ?>
            <span class="uk-label uk-label-success uk-margin-small-left j2c-coupon-badge"><?php echo htmlspecialchars($couponCode, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php if ($discountLabel) : ?>
                <small class="uk-text-muted uk-margin-small-left"><?php echo htmlspecialchars($discountLabel, ENT_QUOTES, 'UTF-8'); ?></small>
            <?php endif; ?>
        <?php endif; ?>
    </a>
    <div class="uk-accordion-content">
<?php endif; ?>

<div class="j2c-coupon-form" id="<?php echo $formId; ?>" data-type="coupon">
    <?php if ($hasCoupon) : ?>
        <div class="uk-flex uk-flex-middle uk-flex-between uk-padding-small">
            <span>
                <span class="uk-label uk-label-success">
                    <span class="icon-tag uk-margin-small-right" aria-hidden="true"></span><?php echo htmlspecialchars($couponCode, ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <?php if ($discountLabel) : ?>
                    <small class="uk-text-muted uk-margin-small-left"><?php echo htmlspecialchars($discountLabel, ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
            </span>
            <button type="button" class="uk-button uk-button-link uk-text-danger j2c-remove-coupon"
                    title="<?php echo Text::_('COM_J2COMMERCE_REMOVE_COUPON'); ?>">
                <span class="icon-times" aria-hidden="true"></span>
                <?php echo Text::_('COM_J2COMMERCE_REMOVE'); ?>
            </button>
        </div>
    <?php else : ?>
        <div class="j2c-coupon-input-wrap">
            <div class="uk-flex uk-flex-stretch">
                <div class="uk-width-expand">
                    <input type="text" name="coupon" class="uk-input" placeholder="<?php echo Text::_('COM_J2COMMERCE_ENTER_COUPON_CODE'); ?>" aria-label="<?php echo Text::_('COM_J2COMMERCE_COUPON_CODE'); ?>" />
                </div>
                <button type="button" class="uk-button uk-button-default j2c-apply-coupon">
                    <?php echo Text::_('COM_J2COMMERCE_APPLY_COUPON'); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($variant === 'accordion') : ?>
    </div>
</li>
<?php endif; ?>
