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

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\ParameterType;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Geozone\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

Text::script('COM_J2COMMERCE_GEOZONE_ERR_NAME_REQUIRED_FOR_ADD_ALL');

// Get the countries list for dropdown
$db = Factory::getContainer()->get('DatabaseDriver');
$query = $db->getQuery(true);
$query->select($db->quoteName(['j2commerce_country_id', 'country_name']))
    ->from($db->quoteName('#__j2commerce_countries'))
    ->where($db->quoteName('enabled') . ' = 1')
    ->order($db->quoteName('country_name') . ' ASC');
$db->setQuery($query);
$countries = $db->loadObjectList();

// Get existing geozonerules with zone names
$geozonerules = [];
if (!empty($this->item->j2commerce_geozone_id)) {
    $query = $db->getQuery(true);
    $query->select([
            $db->quoteName('gr.j2commerce_geozonerule_id'),
            $db->quoteName('gr.country_id'),
            $db->quoteName('gr.zone_id'),
            $db->quoteName('c.country_name'),
            $db->quoteName('z.zone_name'),
        ])
        ->from($db->quoteName('#__j2commerce_geozonerules', 'gr'))
        ->join('LEFT', $db->quoteName('#__j2commerce_countries', 'c') . ' ON ' . $db->quoteName('c.j2commerce_country_id') . ' = ' . $db->quoteName('gr.country_id'))
        ->join('LEFT', $db->quoteName('#__j2commerce_zones', 'z') . ' ON ' . $db->quoteName('z.j2commerce_zone_id') . ' = ' . $db->quoteName('gr.zone_id'))
        ->where($db->quoteName('gr.geozone_id') . ' = :geozone_id')
        ->bind(':geozone_id', $this->item->j2commerce_geozone_id, ParameterType::INTEGER)
        ->order($db->quoteName('gr.j2commerce_geozonerule_id') . ' ASC');
    $db->setQuery($query);
    $geozonerules = $db->loadObjectList();
}

// Build zones cache for existing rules (to avoid AJAX on page load)
$zonesCache = [];
foreach ($geozonerules as $rule) {
    if ($rule->country_id && !isset($zonesCache[$rule->country_id])) {
        $query = $db->getQuery(true);
        $query->select($db->quoteName(['j2commerce_zone_id', 'zone_name']))
            ->from($db->quoteName('#__j2commerce_zones'))
            ->where($db->quoteName('country_id') . ' = :country_id')
            ->where($db->quoteName('enabled') . ' = 1')
            ->bind(':country_id', $rule->country_id, ParameterType::INTEGER)
            ->order($db->quoteName('zone_name') . ' ASC');
        $db->setQuery($query);
        $zonesCache[$rule->country_id] = $db->loadObjectList();
    }
}

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
                                            <select name="geozonerules[<?php echo $rowIndex; ?>][country_id]" id="country-<?php echo $rowIndex; ?>" class="form-select" onchange="J2CommerceGeozone.loadZones(<?php echo $rowIndex; ?>, this.value)">
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
                                            <button type="button" class="btn btn-danger" onclick="J2CommerceGeozone.removeRule(<?php echo (int) $rule->j2commerce_geozonerule_id; ?>, <?php echo $rowIndex; ?>)">
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
                                    <button type="button" class="btn btn-primary" onclick="J2CommerceGeozone.addRule()">
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

        return coreSubmitButton(task);
    };

    // Countries data for new rows
    const countries = <?php echo json_encode(array_map(function($c) {
        return ['id' => $c->j2commerce_country_id, 'name' => $c->country_name];
    }, $countries)); ?>;

    // CSRF token
    const token = '<?php echo $token; ?>';

    // Current row index
    let rowIndex = <?php echo $rowIndex; ?>;

    // J2Commerce Geozone namespace
    window.J2CommerceGeozone = {
        /**
         * Load zones for a country via AJAX
         */
        loadZones: async function(index, countryId, selectedZoneId = 0) {
            const zoneSelect = document.getElementById('zone-' + index);
            if (!zoneSelect) return;

            // Show loading state
            zoneSelect.innerHTML = '<option value="0"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></option>';
            zoneSelect.disabled = true;

            try {
                const url = 'index.php?option=com_j2commerce&task=geozone.getZones&country_id=' + countryId + '&zone_id=' + selectedZoneId;
                const response = await fetch(url);
                const html = await response.text();

                zoneSelect.innerHTML = html;
                zoneSelect.disabled = false;
            } catch (error) {
                console.error('Error loading zones:', error);
                zoneSelect.innerHTML = '<option value="0"><?php echo Text::_('COM_J2COMMERCE_ALL_ZONES'); ?></option>';
                zoneSelect.disabled = false;
            }
        },

        /**
         * Add a new rule row
         */
        addRule: function() {
            const tbody = document.getElementById('geozone-rules-body');

            // Build country options
            let countryOptions = '<option value=""><?php echo Text::_('COM_J2COMMERCE_SELECT_COUNTRY'); ?></option>';
            countries.forEach(function(country) {
                countryOptions += '<option value="' + country.id + '">' + J2CommerceGeozone.escapeHtml(country.name) + '</option>';
            });

            // Create new row
            const newRow = document.createElement('tr');
            newRow.id = 'rule-row-' + rowIndex;
            newRow.innerHTML = `
                <td>
                    <select name="geozonerules[${rowIndex}][country_id]" id="country-${rowIndex}" class="form-select" onchange="J2CommerceGeozone.loadZones(${rowIndex}, this.value)">
                        ${countryOptions}
                    </select>
                </td>
                <td>
                    <select name="geozonerules[${rowIndex}][zone_id]" id="zone-${rowIndex}" class="form-select">
                        <option value="0"><?php echo Text::_('COM_J2COMMERCE_ALL_ZONES'); ?></option>
                    </select>
                    <input type="hidden" name="geozonerules[${rowIndex}][j2commerce_geozonerule_id]" value="0">
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger" onclick="J2CommerceGeozone.removeRule(0, ${rowIndex})">
                        <span class="icon-trash" aria-hidden="true"></span> <?php echo Text::_('JACTION_DELETE'); ?>
                    </button>
                </td>
            `;

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
                    const url = 'index.php?option=com_j2commerce&task=geozone.removeRule&rule_id=' + ruleId + '&' + token + '=1';
                    const response = await fetch(url);
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

            container.innerHTML = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    ${this.escapeHtml(message)}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;

            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                const alert = container.querySelector('.alert');
                if (alert) {
                    alert.remove();
                }
            }, 3000);
        },

        /**
         * Escape HTML entities
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };
});
</script>
