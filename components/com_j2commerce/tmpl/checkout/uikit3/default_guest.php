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

use J2Commerce\Component\J2commerce\Administrator\Helper\CustomFieldHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Language\Text;

/** @var \J2Commerce\Component\J2commerce\Site\View\Checkout\HtmlView $this */

$showShipping = $this->showShipping ?? false;
$fields = $this->fields ?? [];
$guestData = $this->guestData ?? [];
?>
<div class="j2commerce-guest-form">

    <div class="row g-3">
        <?php foreach ($fields as $field) : ?>
            <?php echo CustomFieldHelper::renderField($field, $guestData[$field->field_namekey] ?? ''); ?>
        <?php endforeach; ?>
    </div>

    <?php if ($showShipping) : ?>
    <div class="mt-3 mb-3">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="shipping_address" value="1" id="shipping-same-as-billing" checked>
            <label class="form-check-label" for="shipping-same-as-billing">
                <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_SHIPPING_SAME_AS_BILLING'); ?>
            </label>
        </div>
    </div>
    <?php endif; ?>

    <?php echo J2CommerceHelper::plugin()->eventWithHtml('CheckoutGuest', [$this]); ?>

    <div class="mt-3">
        <button type="button" id="button-guest" class="btn btn-primary">
            <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_CONTINUE'); ?>
        </button>
    </div>
</div>
