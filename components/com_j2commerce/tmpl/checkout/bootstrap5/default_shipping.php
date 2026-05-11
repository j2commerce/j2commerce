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
$shippingAddressId = $this->shippingAddressId ?? '';
$fields = $this->fields ?? [];
$hasAddresses = !empty($addresses);
$isGuest = $this->isGuest ?? false;
$guestShippingData = $this->guestShippingData ?? [];
?>
<div class="j2commerce-shipping-address">

<?php if ($hasAddresses) : ?>
    <div class="mb-3">
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="shipping_address" value="existing" id="shipping-existing" checked>
            <label class="form-check-label fw-bold" for="shipping-existing">
                <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_USE_EXISTING_ADDRESS'); ?>
            </label>
        </div>

        <select name="address_id" id="shipping-address-id" class="form-select mb-3">
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

                $selected = ($addressId == $shippingAddressId) ? 'selected' : '';
                ?>
                <option value="<?php echo (int) $addressId; ?>" <?php echo $selected; ?>>
                    <?php echo htmlspecialchars($label); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div class="form-check mb-3">
            <input class="form-check-input" type="radio" name="shipping_address" value="new" id="shipping-new">
            <label class="form-check-label fw-bold" for="shipping-new">
                <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_NEW_ADDRESS'); ?>
            </label>
        </div>
    </div>

    <div id="shipping-new-address-form" style="display: none;">
<?php else : ?>
    <input type="hidden" name="shipping_address" value="new">
    <div id="shipping-new-address-form">
<?php endif; ?>

        <div class="row g-3">
            <?php foreach ($fields as $field) : ?>
                <?php echo CustomFieldHelper::renderField($field, $guestShippingData[$field->field_namekey] ?? ''); ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="mt-3">
        <?php if ($isGuest) : ?>
        <button type="button" id="button-guest-shipping" class="btn btn-primary btn-checkout-step">
            <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_CONTINUE'); ?>
        </button>
        <?php else : ?>
        <button type="button" id="button-shipping-address" class="btn btn-primary btn-checkout-step">
            <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_CONTINUE'); ?>
        </button>
        <?php endif; ?>
    </div>
</div>
