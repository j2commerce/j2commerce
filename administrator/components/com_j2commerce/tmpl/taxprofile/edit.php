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

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Taxprofile\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

// Get existing tax rules with rate/geozone info
$db = Factory::getContainer()->get('DatabaseDriver');
$taxrules = [];

if (!empty($this->item->j2commerce_taxprofile_id)) {
    $query = $db->getQuery(true);
    $query->select([
            $db->quoteName('tr.j2commerce_taxrule_id'),
            $db->quoteName('tr.taxrate_id'),
            $db->quoteName('tr.address'),
            $db->quoteName('tr.ordering'),
            $db->quoteName('txr.taxrate_name'),
            $db->quoteName('txr.tax_percent'),
            $db->quoteName('txr.geozone_id'),
            $db->quoteName('gz.geozone_name'),
        ])
        ->from($db->quoteName('#__j2commerce_taxrules', 'tr'))
        ->join('LEFT', $db->quoteName('#__j2commerce_taxrates', 'txr') . ' ON ' . $db->quoteName('txr.j2commerce_taxrate_id') . ' = ' . $db->quoteName('tr.taxrate_id'))
        ->join('LEFT', $db->quoteName('#__j2commerce_geozones', 'gz') . ' ON ' . $db->quoteName('gz.j2commerce_geozone_id') . ' = ' . $db->quoteName('txr.geozone_id'))
        ->where($db->quoteName('tr.taxprofile_id') . ' = :taxprofile_id')
        ->bind(':taxprofile_id', $this->item->j2commerce_taxprofile_id, ParameterType::INTEGER)
        ->order($db->quoteName('tr.ordering') . ' ASC');
    $db->setQuery($query);
    $taxrules = $db->loadObjectList();
}

$token = Session::getFormToken();
?>
<form action="<?php echo Route::_('index.php?option=com_j2commerce&layout=edit&id=' . (int) $this->item->j2commerce_taxprofile_id); ?>" method="post" name="adminForm" id="taxprofile-form" class="form-validate">

    <div class="row title-alias form-vertical mb-3">
        <div class="col-12">
            <?php echo $this->form->renderField('taxprofile_name'); ?>
        </div>
    </div>
    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'details', Text::_('COM_J2COMMERCE_FIELDSET_BASIC')); ?>
        <div class="row">
            <div class="col-lg-9">
                <fieldset id="fieldset-basic" class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_FIELDSET_BASIC');?></legend>
                    <div class="form-grid">
                        <div class="alert alert-info">
                            <span class="icon-info-circle" aria-hidden="true"></span>
                            <?php echo Text::_('COM_J2COMMERCE_TAXPROFILE_INFO'); ?>
                        </div>
                    </div>
                </fieldset>
            </div>
            <div class="col-lg-3">
                <?php echo LayoutHelper::render('joomla.edit.global', $this); ?>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'taxrules', Text::_('COM_J2COMMERCE_TAX_RULES')); ?>
        <div class="row">
            <div class="col-lg-12">
                <fieldset id="fieldset-taxrules" class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_TAX_RULES'); ?></legend>

                    <div id="j2commerce-alert-container"></div>

                    <table id="tax-rules-table" class="table table-striped">
                        <thead>
                            <tr>
                                <th style="width: 50%"><?php echo Text::_('COM_J2COMMERCE_FIELD_TAX_RATE'); ?></th>
                                <th style="width: 30%"><?php echo Text::_('COM_J2COMMERCE_FIELD_ADDRESS_TYPE'); ?></th>
                                <th style="width: 20%"><?php echo Text::_('JACTION_DELETE'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="tax-rules-body">
                            <?php $rowIndex = 0; ?>
                            <?php if ($taxrules): ?>
                                <?php foreach ($taxrules as $rule): ?>
                                    <tr id="rule-row-<?php echo $rowIndex; ?>">
                                        <td>
                                            <select name="taxrules[<?php echo $rowIndex; ?>][taxrate_id]" id="taxrate-<?php echo $rowIndex; ?>" class="form-select" required>
                                                <option value=""><?php echo Text::_('COM_J2COMMERCE_SELECT_TAXRATE'); ?></option>
                                            </select>
                                            <input type="hidden" name="taxrules[<?php echo $rowIndex; ?>][j2commerce_taxrule_id]" value="<?php echo (int) $rule->j2commerce_taxrule_id; ?>">
                                        </td>
                                        <td>
                                            <select name="taxrules[<?php echo $rowIndex; ?>][address]" id="address-<?php echo $rowIndex; ?>" class="form-select">
                                                <option value="shipping"<?php echo ($rule->address === 'shipping') ? ' selected' : ''; ?>><?php echo Text::_('COM_J2COMMERCE_ADDRESS_SHIPPING'); ?></option>
                                                <option value="billing"<?php echo ($rule->address === 'billing') ? ' selected' : ''; ?>><?php echo Text::_('COM_J2COMMERCE_ADDRESS_BILLING'); ?></option>
                                            </select>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="J2CommerceTax.removeRule(<?php echo (int) $rule->j2commerce_taxrule_id; ?>, <?php echo $rowIndex; ?>)">
                                                <span class="icon-trash" aria-hidden="true"></span> <?php echo Text::_('JACTION_DELETE'); ?>
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
                                    <button type="button" class="btn btn-primary" onclick="J2CommerceTax.addRule()">
                                        <span class="icon-plus" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_TAX_ADD_RULE'); ?>
                                    </button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo $this->form->renderField('j2commerce_taxprofile_id'); ?>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // CSRF token
    const token = '<?php echo $token; ?>';

    // Current row index
    let rowIndex = <?php echo $rowIndex; ?>;

    // Tax data cache (taxrates and geozones)
    let taxData = null;

    // Store existing rule selections for pre-population
    const existingRules = <?php echo json_encode(array_map(function($r) {
        return ['taxrate_id' => $r->taxrate_id];
    }, $taxrules), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    // J2Commerce Tax namespace
    window.J2CommerceTax = {
        /**
         * Initialize - Load tax data via AJAX
         */
        init: async function() {
            try {
                const response = await fetch('index.php?option=com_j2commerce&task=taxprofile.getTaxData&format=json');
                const result = await response.json();

                if (result.success && result.data) {
                    taxData = result.data;

                    // Populate existing rows
                    existingRules.forEach((rule, index) => {
                        this.populateTaxRateSelect(index, rule.taxrate_id);
                    });
                } else {
                    console.error('Failed to load tax data:', result.message);
                }
            } catch (error) {
                console.error('Error loading tax data:', error);
            }
        },

        /**
         * Populate tax rate dropdown for a specific row
         */
        populateTaxRateSelect: function(index, selectedId = 0) {
            const select = document.getElementById('taxrate-' + index);
            if (!select || !taxData) return;

            const options = [new Option(<?php echo json_encode(Text::_('COM_J2COMMERCE_SELECT_TAXRATE'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, '')];

            taxData.taxrates.forEach(function(rate) {
                const displayText = rate.taxrate_name + ' (' + rate.tax_percent + '%)';
                options.push(new Option(displayText, rate.j2commerce_taxrate_id, false, rate.j2commerce_taxrate_id == selectedId));
            });

            select.replaceChildren(...options);
        },

        /**
         * Add a new rule row
         */
        addRule: function() {
            const tbody = document.getElementById('tax-rules-body');
            const currentRowIndex = rowIndex;

            // Create new row
            const newRow = document.createElement('tr');
            newRow.id = 'rule-row-' + currentRowIndex;

            const taxrateCell = document.createElement('td');
            const taxrateSelect = document.createElement('select');
            taxrateSelect.name = `taxrules[${currentRowIndex}][taxrate_id]`;
            taxrateSelect.id = 'taxrate-' + currentRowIndex;
            taxrateSelect.className = 'form-select';
            taxrateSelect.required = true;
            taxrateSelect.appendChild(new Option(<?php echo json_encode(Text::_('COM_J2COMMERCE_SELECT_TAXRATE'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, ''));
            taxrateCell.appendChild(taxrateSelect);

            const taxruleIdInput = document.createElement('input');
            taxruleIdInput.type = 'hidden';
            taxruleIdInput.name = `taxrules[${currentRowIndex}][j2commerce_taxrule_id]`;
            taxruleIdInput.value = '0';
            taxrateCell.appendChild(taxruleIdInput);
            newRow.appendChild(taxrateCell);

            const addressCell = document.createElement('td');
            const addressSelect = document.createElement('select');
            addressSelect.name = `taxrules[${currentRowIndex}][address]`;
            addressSelect.id = 'address-' + currentRowIndex;
            addressSelect.className = 'form-select';
            addressSelect.appendChild(new Option(<?php echo json_encode(Text::_('COM_J2COMMERCE_ADDRESS_SHIPPING'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'shipping', true, true));
            addressSelect.appendChild(new Option(<?php echo json_encode(Text::_('COM_J2COMMERCE_ADDRESS_BILLING'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'billing'));
            addressCell.appendChild(addressSelect);
            newRow.appendChild(addressCell);

            const actionCell = document.createElement('td');
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'btn btn-sm btn-danger';
            deleteBtn.addEventListener('click', function() {
                J2CommerceTax.removeRule(0, currentRowIndex);
            });
            const deleteIcon = document.createElement('span');
            deleteIcon.className = 'icon-trash';
            deleteIcon.setAttribute('aria-hidden', 'true');
            deleteBtn.appendChild(deleteIcon);
            deleteBtn.appendChild(document.createTextNode(' ' + <?php echo json_encode(Text::_('JACTION_DELETE'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>));
            actionCell.appendChild(deleteBtn);
            newRow.appendChild(actionCell);

            tbody.appendChild(newRow);

            // Populate the new select
            this.populateTaxRateSelect(currentRowIndex);

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
                    const url = 'index.php?option=com_j2commerce&task=taxprofile.removeRule&rule_id=' + ruleId + '&' + token + '=1';
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

            const alertEl = document.createElement('div');
            alertEl.className = `alert ${alertClass} alert-dismissible fade show`;
            alertEl.setAttribute('role', 'alert');
            alertEl.appendChild(document.createTextNode(message));

            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'btn-close';
            closeBtn.setAttribute('data-bs-dismiss', 'alert');
            closeBtn.setAttribute('aria-label', <?php echo json_encode(Text::_('JCLOSE')); ?>);
            alertEl.appendChild(closeBtn);

            container.replaceChildren(alertEl);

            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                const alert = container.querySelector('.alert');
                if (alert) {
                    alert.remove();
                }
            }, 3000);
        }
    };

    // Initialize on page load
    J2CommerceTax.init();
});
</script>
