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
use Joomla\CMS\Session\Session;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Geozone\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

Text::script('COM_J2COMMERCE_GEOZONE_ERR_NAME_REQUIRED_FOR_ADD_ALL');
Text::script('JACTION_DELETE');
Text::script('JCLOSE');

// Encodes PHP values for the inline <script>: the hex flags stop a country or zone
// name containing </script> or a quote from breaking out of the block.
$toJs = static fn ($value): string => json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$countries    = $this->countries;
$geozonerules = $this->geozonerules;
$zonesCache   = $this->zonesCache;

$token = Session::getFormToken();
?>
<form action="<?php echo Route::_('index.php?option=com_j2commerce&layout=edit&id=' . (int) $this->item->j2commerce_geozone_id); ?>" method="post" name="adminForm" id="geozone-form" class="form-validate">

    <div class="row title-alias form-vertical mb-3">
        <div class="col-12 col-lg-6">
            <?php echo $this->form->renderField('geozone_name'); ?>
        </div>
    </div>

    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'details', Text::_('COM_J2COMMERCE_GEOZONE_RULES')); ?>
        <div class="row">
            <div class="col-lg-9">
                <fieldset id="fieldset-geozonerules" class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_GEOZONE_RULES'); ?></legend>

                    <div id="j2commerce-alert-container"></div>

                    <table id="geozone-rules-table" class="table table-striped">
                        <thead>
                            <tr>
                                <th style="width: 40%"><?php echo Text::_('COM_J2COMMERCE_FIELD_COUNTRY'); ?></th>
                                <th style="width: 40%"><?php echo Text::_('COM_J2COMMERCE_FIELD_ZONE'); ?></th>
                                <th style="width: 20%" class="text-end"><?php echo Text::_('JACTION_DELETE'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="geozone-rules-body">
                            <?php $rowIndex = 0; ?>
                            <?php if ($geozonerules): ?>
                                <?php foreach ($geozonerules as $rule): ?>
                                    <tr id="rule-row-<?php echo $rowIndex; ?>">
                                        <td>
                                            <select name="geozonerules[<?php echo $rowIndex; ?>][country_id]" id="country-<?php echo $rowIndex; ?>" class="form-select" data-role="country" data-row-index="<?php echo $rowIndex; ?>">
                                                <option value=""><?php echo Text::_('COM_J2COMMERCE_SELECT_COUNTRY'); ?></option>
                                                <?php foreach ($countries as $country): ?>
                                                    <option value="<?php echo (int) $country->j2commerce_country_id; ?>"<?php echo ($country->j2commerce_country_id == $rule->country_id) ? ' selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($country->country_name, ENT_QUOTES, 'UTF-8'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="geozonerules[<?php echo $rowIndex; ?>][zone_id]" id="zone-<?php echo $rowIndex; ?>" class="form-select">
                                                <option value="0"><?php echo Text::_('COM_J2COMMERCE_ALL_ZONES'); ?></option>
                                                <?php if (isset($zonesCache[$rule->country_id])): ?>
                                                    <?php foreach ($zonesCache[$rule->country_id] as $zone): ?>
                                                        <option value="<?php echo (int) $zone->j2commerce_zone_id; ?>"<?php echo ($zone->j2commerce_zone_id == $rule->zone_id) ? ' selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($zone->zone_name, ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                            <input type="hidden" name="geozonerules[<?php echo $rowIndex; ?>][j2commerce_geozonerule_id]" value="<?php echo (int) $rule->j2commerce_geozonerule_id; ?>">
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-danger" data-action="remove-rule" data-rule-id="<?php echo (int) $rule->j2commerce_geozonerule_id; ?>" data-row-index="<?php echo $rowIndex; ?>">
                                                <span class="icon-trash me-1" aria-hidden="true"></span> <?php echo Text::_('JACTION_DELETE'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php $rowIndex++; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3">
                                    <button type="button" class="btn btn-primary" data-action="add-rule">
                                        <span class="icon-plus" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_GEOZONE_ADD_RULE'); ?>
                                    </button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>

                    <?php // Rendered after every rule row on purpose: PHP drops input variables ?>
                    <?php // past max_input_vars in document order, so this arrives only if they all did. ?>
                    <input type="hidden" name="geozonerules_rendered" id="geozonerules_rendered" value="<?php echo $rowIndex; ?>">
                </fieldset>
            </div>
            <div class="col-lg-3">
                <?php echo LayoutHelper::render('joomla.edit.global', $this); ?>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo $this->form->renderField('j2commerce_geozone_id'); ?>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Add All Countries saves the record so the rows it adds come back numbered from the
    // database, and the record cannot save without a name. Block the submit rather than let
    // the round trip fail on the server.
    const coreSubmitButton = Joomla.submitbutton;

    Joomla.submitbutton = function(task) {
        // Tell the server how many rule rows this page meant to send, so a POST cut short by
        // max_input_vars is detected instead of being saved as a smaller set of rules.
        const renderedField = document.getElementById('geozonerules_rendered');

        if (renderedField) {
            renderedField.value = String(
                document.querySelectorAll('#geozone-rules-body tr').length
            );
        }

        if (task === 'geozone.addAllCountries') {
            const nameField = document.getElementById('jform_geozone_name');

            if (!nameField || nameField.value.trim() === '') {
                Joomla.renderMessages({
                    warning: [Joomla.Text._('COM_J2COMMERCE_GEOZONE_ERR_NAME_REQUIRED_FOR_ADD_ALL')]
                });

                if (nameField) {
                    nameField.focus();
                }

                return false;
            }
        }

        // Forwards formSelector/validate, which core accepts but this wrapper does not name.
        return coreSubmitButton.apply(this, arguments);
    };

    // Countries data for new rows
    const countries = <?php echo $toJs(array_map(static fn ($c) => ['id' => (int) $c->j2commerce_country_id, 'name' => $c->country_name], $countries)); ?>;

    const strings = <?php echo $toJs([
        'allZones'      => Text::_('COM_J2COMMERCE_ALL_ZONES'),
        'loading'       => Text::_('COM_J2COMMERCE_LOADING'),
        'selectCountry' => Text::_('COM_J2COMMERCE_SELECT_COUNTRY'),
    ]); ?>;

    // CSRF token
    const token = <?php echo $toJs($token); ?>;

    // Current row index
    let rowIndex = <?php echo $rowIndex; ?>;

    // J2Commerce Geozone namespace
    window.J2CommerceGeozone = {
        /**
         * Load zones for a country via AJAX
         */
        loadZones: async function(index, countryId) {
            const zoneSelect = document.getElementById('zone-' + index);
            if (!zoneSelect) return;

            // Show loading state
            zoneSelect.replaceChildren(new Option(strings.loading, '0'));
            zoneSelect.disabled = true;

            try {
                const url = 'index.php?option=com_j2commerce&task=geozone.getZones'
                    + '&country_id=' + encodeURIComponent(countryId);
                const response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});

                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                const payload = await response.json();

                if (!payload.success) {
                    throw new Error(payload.message || 'Request failed');
                }

                zoneSelect.replaceChildren(
                    new Option(strings.allZones, '0'),
                    ...payload.data.map((zone) => new Option(zone.name, String(zone.id)))
                );
            } catch (error) {
                console.error('Error loading zones:', error);
                zoneSelect.replaceChildren(new Option(strings.allZones, '0'));
                this.showAlert('error', error.message);
            }

            zoneSelect.disabled = false;
        },

        /**
         * Add a new rule row
         */
        addRule: function() {
            const tbody = document.getElementById('geozone-rules-body');
            const index = rowIndex;

            const newRow = document.createElement('tr');
            newRow.id = 'rule-row-' + index;

            const countryCell = document.createElement('td');
            const countrySelect = document.createElement('select');
            countrySelect.name = 'geozonerules[' + index + '][country_id]';
            countrySelect.id = 'country-' + index;
            countrySelect.className = 'form-select';
            countrySelect.dataset.role = 'country';
            countrySelect.dataset.rowIndex = String(index);
            countrySelect.append(
                new Option(strings.selectCountry, ''),
                ...countries.map((country) => new Option(country.name, String(country.id)))
            );
            countryCell.appendChild(countrySelect);

            const zoneCell = document.createElement('td');
            const zoneSelect = document.createElement('select');
            zoneSelect.name = 'geozonerules[' + index + '][zone_id]';
            zoneSelect.id = 'zone-' + index;
            zoneSelect.className = 'form-select';
            zoneSelect.appendChild(new Option(strings.allZones, '0'));

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'geozonerules[' + index + '][j2commerce_geozonerule_id]';
            hidden.value = '0';

            zoneCell.append(zoneSelect, hidden);

            const deleteCell = document.createElement('td');
            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'btn btn-sm btn-danger';
            deleteButton.dataset.action = 'remove-rule';
            deleteButton.dataset.ruleId = '0';
            deleteButton.dataset.rowIndex = String(index);

            const icon = document.createElement('span');
            icon.className = 'icon-trash';
            icon.setAttribute('aria-hidden', 'true');

            deleteButton.append(icon, ' ' + Joomla.Text._('JACTION_DELETE'));
            deleteCell.appendChild(deleteButton);

            newRow.append(countryCell, zoneCell, deleteCell);
            tbody.appendChild(newRow);
            rowIndex++;
        },

        /**
         * Remove a rule row (delete from DB if saved, otherwise just remove from DOM)
         */
        removeRule: async function(ruleId, index) {
            const row = document.getElementById('rule-row-' + index);
            if (!row) return;

            // If rule is saved in DB, delete via AJAX
            if (ruleId > 0) {
                try {
                    const url = 'index.php?option=com_j2commerce&task=geozone.removeRule&rule_id='
                        + encodeURIComponent(ruleId) + '&' + token + '=1';
                    const response = await fetch(url);

                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }

                    const data = await response.json();

                    if (data.data && data.data.success) {
                        this.showAlert('success', data.data.message);
                    }
                } catch (error) {
                    console.error('Error removing rule:', error);
                }
            }

            // Remove row from DOM with fade effect
            row.style.transition = 'opacity 0.3s';
            row.style.opacity = '0';
            setTimeout(function() {
                row.remove();
            }, 300);
        },

        /**
         * Show an alert message
         */
        showAlert: function(type, message) {
            const container = document.getElementById('j2commerce-alert-container');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';

            const alert = document.createElement('div');
            alert.className = 'alert ' + alertClass + ' alert-dismissible fade show';
            alert.setAttribute('role', 'alert');
            alert.textContent = message;

            const closeButton = document.createElement('button');
            closeButton.type = 'button';
            closeButton.className = 'btn-close';
            closeButton.dataset.bsDismiss = 'alert';
            closeButton.setAttribute('aria-label', Joomla.Text._('JCLOSE'));

            alert.appendChild(closeButton);
            container.replaceChildren(alert);

            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                const current = container.querySelector('.alert');
                if (current) {
                    current.remove();
                }
            }, 3000);
        }
    };

    // Delegated on the table so rows added after load are covered without rebinding.
    const rulesTable = document.getElementById('geozone-rules-table');

    rulesTable.addEventListener('change', function(e) {
        const select = e.target.closest('select[data-role="country"]');

        if (!select) {
            return;
        }

        J2CommerceGeozone.loadZones(Number(select.dataset.rowIndex), select.value)
            .catch((error) => console.error('Error loading zones:', error));
    });

    rulesTable.addEventListener('click', function(e) {
        if (e.target.closest('[data-action="add-rule"]')) {
            J2CommerceGeozone.addRule();
            return;
        }

        const removeButton = e.target.closest('[data-action="remove-rule"]');

        if (removeButton) {
            J2CommerceGeozone.removeRule(
                Number(removeButton.dataset.ruleId),
                Number(removeButton.dataset.rowIndex)
            );
        }
    });
});
</script>
