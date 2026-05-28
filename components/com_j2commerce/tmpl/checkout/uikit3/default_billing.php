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
use Joomla\CMS\Language\Text;

/** @var \J2Commerce\Component\J2commerce\Site\View\Checkout\HtmlView $this */

$addresses = $this->addresses ?? [];
$billingAddressId = $this->billingAddressId ?? '';
$showShipping = $this->showShipping ?? false;
$fields = $this->fields ?? [];
$hasAddresses = !empty($addresses);
?>
<div class="j2commerce-billing-address">

<?php if ($hasAddresses) : ?>
    <fieldset class="uk-margin-bottom">
        <legend class="uk-hidden-visually"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_ADDRESS_SELECTION'); ?></legend>
        <div class="uk-margin-small-bottom">
            <label class="uk-flex uk-flex-middle">
                <input class="uk-radio uk-margin-small-right" type="radio" name="billing_address" value="existing" id="billing-existing" checked>
                <span class="uk-text-bold" for="billing-existing">
                    <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_USE_EXISTING_ADDRESS'); ?>
                </span>
            </label>
        </div>

        <select name="address_id" id="address_id" class="uk-select uk-margin-bottom" aria-label="<?php echo Text::_('COM_J2COMMERCE_CHECKOUT_SELECT_ADDRESS'); ?>">
            <?php foreach ($addresses as $address) : ?>
                <?php
                $addressId = $address->j2commerce_address_id ?? $address->id ?? '';
                $label = ($address->first_name ?? '') . ' ' . ($address->last_name ?? '');

                if (!empty($address->address_1)) {
                    $label .= ', ' . $address->address_1;
                }

                if (!empty($address->city)) {
                    $label .= ', ' . $address->city;
                }

                $selected = ($addressId == $billingAddressId) ? 'selected' : '';
                ?>
                <option value="<?php echo (int) $addressId; ?>" <?php echo $selected; ?>>
                    <?php echo htmlspecialchars($label); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div class="uk-margin-bottom">
            <label class="uk-flex uk-flex-middle">
                <input class="uk-radio uk-margin-small-right" type="radio" name="billing_address" value="new" id="billing-new">
                <span class="uk-text-bold" for="billing-new">
                    <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_NEW_ADDRESS'); ?>
                </span>
            </label>
        </div>
    </fieldset>

    <div id="billing-new-address-form" style="display: none;">
<?php else : ?>
    <input type="hidden" name="billing_address" value="new">
    <div id="billing-new-address-form">
<?php endif; ?>

        <div class="uk-grid uk-grid-small" uk-grid>
            <?php foreach ($fields as $field) : ?>
                <?php
                $prefill = '';

                if ($field->field_namekey === 'email' && !empty($this->user->email)) {
                    $prefill = $this->user->email;
                }

                echo CustomFieldHelper::renderField($field, $prefill, [], 'uikit');
                ?>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($showShipping) : ?>
    <div class="uk-margin">
        <label class="uk-flex uk-flex-middle">
            <input class="uk-checkbox uk-margin-small-right" type="checkbox" name="shipping_address" value="1" id="shipping-same-as-billing" checked>
            <span for="shipping-same-as-billing">
                <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_SHIPPING_SAME_AS_BILLING'); ?>
            </span>
        </label>
    </div>
    <?php endif; ?>

    <div class="uk-margin-top">
        <button type="button" id="button-billing-address" class="uk-button uk-button-primary btn-checkout-step">
            <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_CONTINUE'); ?>
        </button>
    </div>
</div>
