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
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Vendor\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

?>
<form action="<?php echo Route::_('index.php?option=com_j2commerce&layout=edit&id=' . (int) $this->item->j2commerce_vendor_id); ?>" method="post" name="adminForm" id="vendor-form" class="form-validate">

    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'details', Text::_('COM_J2COMMERCE_FIELDSET_BASIC')); ?>
        <div class="row">
            <div class="col-lg-9">
                <div class="row">
                    <div class="col-lg-6">
                        <fieldset id="fieldset-basic" class="options-form">
                            <legend><?php echo Text::_('COM_J2COMMERCE_FIELDSET_ADDRESS_CONTACT'); ?></legend>
                            <div class="form-grid">
                                <?php echo $this->form->renderField('company'); ?>
                                <?php echo $this->form->renderField('first_name'); ?>
                                <?php echo $this->form->renderField('last_name'); ?>
                                <?php echo $this->form->renderField('j2commerce_user_id'); ?>
                                <?php echo $this->form->renderField('email'); ?>
                                <?php echo $this->form->renderField('phone_1'); ?>
                                <?php echo $this->form->renderField('phone_2'); ?>
                                <?php echo $this->form->renderField('fax'); ?>
                                <?php echo $this->form->renderField('tax_number'); ?>
                            </div>
                        </fieldset>
                    </div>
                    <div class="col-lg-6">
                        <fieldset id="fieldset-address-right" class="options-form">
                            <legend><?php echo Text::_('COM_J2COMMERCE_FIELDSET_ADDRESS_LOCATION'); ?></legend>
                            <div class="form-grid">
                                <?php echo $this->form->renderField('address_1'); ?>
                                <?php echo $this->form->renderField('address_2'); ?>
                                <?php echo $this->form->renderField('city'); ?>
                                <?php echo $this->form->renderField('zip'); ?>
                                <?php echo $this->form->renderField('country_id'); ?>
                                <?php echo $this->form->renderField('zone_id'); ?>

                                <?php
                                // Render custom address fields dynamically
                                foreach ($this->addressCustomFields as $field) {
                                    // Only render non-core fields (core fields are already rendered above)
                                    $coreFields = ['first_name', 'last_name', 'email', 'address_1', 'address_2', 'city', 'zip', 'country_id', 'zone_id', 'phone_1', 'phone_2', 'company', 'tax_number'];
                                    if (!\in_array($field->field_namekey, $coreFields, true) && $this->form->getField($field->field_namekey)) {
                                        echo $this->form->renderField($field->field_namekey);
                                    }
                                }
                                ?>
                            </div>
                        </fieldset>
                    </div>
                </div>
            </div>
            <div class="col-lg-3">
                <?php echo LayoutHelper::render('joomla.edit.global', $this); ?>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo $this->form->renderField('j2commerce_vendor_id'); ?>
    <?php echo $this->form->renderField('j2commerce_address_id'); ?>
    <?php echo $this->form->renderField('address_id'); ?>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<script>
/**
 * J2Commerce Vendor - Country/Zone AJAX linking
 * Loads zones dynamically based on the selected country
 */
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    const countrySelect = document.getElementById('jform_country_id');
    const zoneSelect = document.getElementById('jform_zone_id');

    if (!countrySelect || !zoneSelect) {
        return;
    }

    /**
     * Load zones for the selected country via AJAX
     *
     * @param {number} countryId - The selected country ID
     * @param {number} selectedZoneId - The zone ID to pre-select (optional)
     */
    async function loadZones(countryId, selectedZoneId = 0) {
        // Show loading state
        zoneSelect.innerHTML = '<option value=""><?php echo Text::_('COM_J2COMMERCE_LOADING', true); ?></option>';
        zoneSelect.disabled = true;

        if (!countryId || countryId === '0' || countryId === '') {
            zoneSelect.innerHTML = '<option value=""><?php echo Text::_('COM_J2COMMERCE_SELECT_ZONE', true); ?></option>';
            zoneSelect.disabled = false;
            return;
        }

        try {
            const url = 'index.php?option=com_j2commerce&task=vendor.getZones&country_id=' + countryId + '&zone_id=' + selectedZoneId;
            const response = await fetch(url);

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const html = await response.text();
            zoneSelect.innerHTML = html;
            zoneSelect.disabled = false;
        } catch (error) {
            console.error('Error loading zones:', error);
            zoneSelect.innerHTML = '<option value=""><?php echo Text::_('COM_J2COMMERCE_SELECT_ZONE', true); ?></option>';
            zoneSelect.disabled = false;
        }
    }

    // Listen for country changes
    countrySelect.addEventListener('change', function() {
        loadZones(this.value, 0);
    });

    // On page load, if the country is already selected, load zones with the current zone value
    const initialCountryId = countrySelect.value;
    const initialZoneId = zoneSelect.value || 0;

    if (initialCountryId && initialCountryId !== '0' && initialCountryId !== '') {
        loadZones(initialCountryId, initialZoneId);
    }
});
</script>
