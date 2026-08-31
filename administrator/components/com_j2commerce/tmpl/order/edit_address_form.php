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

use J2Commerce\Component\J2commerce\Administrator\Helper\CustomFieldHelper;
use Joomla\CMS\Language\Text;

/** Shared billing/shipping address editor; expects $this->addressFormType (billing|shipping). */
$type = $this->addressFormType ?? 'billing';
$orderInfo = $this->item->orderinfo ?? null;
$value = static fn (string $field): string => (string) ($orderInfo->{$type . '_' . $field} ?? '');
$countryId = (int) ($orderInfo->{$type . '_country_id'} ?? 0);
$zoneId = (int) ($orderInfo->{$type . '_zone_id'} ?? 0);
$zoneName = (string) ($orderInfo->{$type . '_zone_name'} ?? '');

?>
<div class="card mt-3 d-none" id="<?php echo $type; ?>AddressForm" data-address-type="<?php echo $type; ?>">
    <div class="card-header"><strong><?php echo Text::_('COM_J2COMMERCE_EDIT_ADDRESS'); ?></strong></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="<?php echo $type; ?>_first_name"><?php echo Text::_('COM_J2COMMERCE_FIELD_FIRST_NAME'); ?></label>
                <input type="text" class="form-control" id="<?php echo $type; ?>_first_name" data-address-field="first_name" value="<?php echo $this->escape($value('first_name')); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="<?php echo $type; ?>_last_name"><?php echo Text::_('COM_J2COMMERCE_FIELD_LAST_NAME'); ?></label>
                <input type="text" class="form-control" id="<?php echo $type; ?>_last_name" data-address-field="last_name" value="<?php echo $this->escape($value('last_name')); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="<?php echo $type; ?>_company"><?php echo Text::_('COM_J2COMMERCE_FIELD_COMPANY'); ?></label>
                <input type="text" class="form-control" id="<?php echo $type; ?>_company" data-address-field="company" value="<?php echo $this->escape($value('company')); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="<?php echo $type; ?>_tax_number"><?php echo Text::_('COM_J2COMMERCE_FIELD_ADDRESS_TAX_NUMBER'); ?></label>
                <input type="text" class="form-control" id="<?php echo $type; ?>_tax_number" data-address-field="tax_number" value="<?php echo $this->escape($value('tax_number')); ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="<?php echo $type; ?>_address_1"><?php echo Text::_('COM_J2COMMERCE_FIELD_ADDRESS_1'); ?></label>
                <input type="text" class="form-control" id="<?php echo $type; ?>_address_1" data-address-field="address_1" value="<?php echo $this->escape($value('address_1')); ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="<?php echo $type; ?>_address_2"><?php echo Text::_('COM_J2COMMERCE_FIELD_ADDRESS_2'); ?></label>
                <input type="text" class="form-control" id="<?php echo $type; ?>_address_2" data-address-field="address_2" value="<?php echo $this->escape($value('address_2')); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="<?php echo $type; ?>_city"><?php echo Text::_('COM_J2COMMERCE_FIELD_CITY'); ?></label>
                <input type="text" class="form-control" id="<?php echo $type; ?>_city" data-address-field="city" value="<?php echo $this->escape($value('city')); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="<?php echo $type; ?>_zip"><?php echo Text::_('COM_J2COMMERCE_FIELD_ADDRESS_ZIP'); ?></label>
                <input type="text" class="form-control" id="<?php echo $type; ?>_zip" data-address-field="zip" value="<?php echo $this->escape($value('zip')); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="<?php echo $type; ?>_country_id"><?php echo Text::_('COM_J2COMMERCE_FIELD_ADDRESS_COUNTRY'); ?></label>
                <select class="form-select" id="<?php echo $type; ?>_country_id" data-address-field="country_id">
                    <option value="0"><?php echo Text::_('JGLOBAL_SELECT_AN_OPTION'); ?></option>
                    <?php foreach ($this->countries as $country) : ?>
                        <option value="<?php echo (int) $country->j2commerce_country_id; ?>" <?php echo $countryId === (int) $country->j2commerce_country_id ? 'selected' : ''; ?>>
                            <?php echo $this->escape($country->country_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="<?php echo $type; ?>_zone_id"><?php echo Text::_('COM_J2COMMERCE_FIELD_ADDRESS_ZONE'); ?></label>
                <select class="form-select" id="<?php echo $type; ?>_zone_id" data-address-field="zone_id">
                    <?php if ($zoneId > 0) : ?>
                        <option value="<?php echo $zoneId; ?>" selected><?php echo $this->escape($zoneName); ?></option>
                    <?php else : ?>
                        <option value="0"><?php echo Text::_('JGLOBAL_SELECT_AN_OPTION'); ?></option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="<?php echo $type; ?>_phone_1"><?php echo Text::_('COM_J2COMMERCE_FIELD_ADDRESS_PHONE_1'); ?></label>
                <input type="text" class="form-control" id="<?php echo $type; ?>_phone_1" data-address-field="phone_1" value="<?php echo $this->escape($value('phone_1')); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="<?php echo $type; ?>_phone_2"><?php echo Text::_('COM_J2COMMERCE_FIELD_ADDRESS_PHONE_2'); ?></label>
                <input type="text" class="form-control" id="<?php echo $type; ?>_phone_2" data-address-field="phone_2" value="<?php echo $this->escape($value('phone_2')); ?>">
            </div>
            <?php
            // Checkout custom fields. `data-address-field` is the same hook the fixed
            // inputs use, so admin-order-edit.js posts these without knowing about them
            // and OrderModel::saveOrderAddress() merges them into the order's snapshot.
            //
            // Types whose value is not a plain answer are read-only here: multiuploader
            // files are served from their own upload rows, and a zone needs the
            // country-dependent lookup the fixed zone select already owns.
            $snapshot     = CustomFieldHelper::decodeOrderSnapshot($orderInfo->{'all_' . $type} ?? null);
            $skippedTypes = ['multiuploader', 'zone', 'customtext'];

            foreach (CustomFieldHelper::getOrderFields($type) as $namekey => $field) :
                if (\in_array($field->field_type ?? '', $skippedTypes, true)) {
                    continue;
                }

                $fieldValue = (string) ($snapshot[$namekey] ?? '');
                $fieldId    = $type . '_cf_' . $namekey;
                $fieldLabel = Text::_((string) ($field->field_name ?: $namekey));
                $options    = \in_array($field->field_type, ['select', 'singledropdown', 'radio'], true)
                    ? CustomFieldHelper::getFieldOptions($field)
                    : [];
                ?>
                <div class="col-md-6">
                    <label class="form-label" for="<?php echo $this->escape($fieldId); ?>"><?php echo $this->escape($fieldLabel); ?></label>
                    <?php if ($options !== []) : ?>
                        <select class="form-select" id="<?php echo $this->escape($fieldId); ?>" data-address-field="<?php echo $this->escape($namekey); ?>">
                            <option value=""><?php echo Text::_('JGLOBAL_SELECT_AN_OPTION'); ?></option>
                            <?php foreach ($options as $option) : ?>
                                <option value="<?php echo $this->escape($option['value']); ?>" <?php echo $fieldValue === $option['value'] ? 'selected' : ''; ?>>
                                    <?php echo $this->escape(Text::_($option['name'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($field->field_type === 'checkbox') : ?>
                        <select class="form-select" id="<?php echo $this->escape($fieldId); ?>" data-address-field="<?php echo $this->escape($namekey); ?>">
                            <option value="0" <?php echo $fieldValue === '' || $fieldValue === '0' ? 'selected' : ''; ?>><?php echo Text::_('JNO'); ?></option>
                            <option value="1" <?php echo $fieldValue !== '' && $fieldValue !== '0' ? 'selected' : ''; ?>><?php echo Text::_('JYES'); ?></option>
                        </select>
                    <?php elseif (\in_array($field->field_type, ['textarea', 'wysiwyg'], true)) : ?>
                        <textarea class="form-control" rows="3" id="<?php echo $this->escape($fieldId); ?>" data-address-field="<?php echo $this->escape($namekey); ?>"><?php echo $this->escape($fieldValue); ?></textarea>
                    <?php else : ?>
                        <input type="text" class="form-control" id="<?php echo $this->escape($fieldId); ?>" data-address-field="<?php echo $this->escape($namekey); ?>" value="<?php echo $this->escape($fieldValue); ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="button" class="btn btn-primary" data-j2c-address-save="<?php echo $type; ?>">
                <span class="icon-save" aria-hidden="true"></span> <?php echo Text::_('JSAVE'); ?>
            </button>
            <button type="button" class="btn btn-outline-secondary" data-j2c-address-cancel="<?php echo $type; ?>">
                <?php echo Text::_('JCANCEL'); ?>
            </button>
        </div>
    </div>
</div>
