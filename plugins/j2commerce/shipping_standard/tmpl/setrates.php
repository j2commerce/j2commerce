<?php

/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.ShippingStandard
 *
 * @copyright   Copyright (C) 2024-2026 J2Commerce, LLC. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

/**
 * Template variables provided by ShippingStandard::renderSetRates():
 *
 * @var  object  $item       The shipping method record
 * @var  array   $geozones   Geozones [id => name]
 * @var  int     $methodId   The method ID
 * @var  int     $methodType The method calculation type (0-6)
 * @var  array   $typeLabels Shipping type labels
 */

$rangeTypes = [1, 2, 4, 5, 6];
$showRange  = \in_array($methodType, $rangeTypes, true);

?>

<div id="setrates-container">

    <!-- Add New Rate Section -->
    <div class="card mb-4">
        <div class="card-body">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th><?php echo Text::_('COM_J2COMMERCE_GEOZONE'); ?></th>
                        <?php if ($showRange) : ?>
                        <th class="rate-range-col"><?php echo Text::_('COM_J2COMMERCE_FIELD_RANGE_START'); ?></th>
                        <th class="rate-range-col"><?php echo Text::_('COM_J2COMMERCE_FIELD_RANGE_END'); ?></th>
                        <?php endif; ?>
                        <th><?php echo Text::_('COM_J2COMMERCE_SHIPPING_COST'); ?></th>
                        <th><?php echo Text::_('COM_J2COMMERCE_HANDLING_COST'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <select class="form-select" id="new_geozone_id">
                                <?php foreach ($geozones as $gzId => $gzName) : ?>
                                    <option value="<?php echo (int) $gzId; ?>"><?php echo htmlspecialchars($gzName, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <?php if ($showRange) : ?>
                        <td>
                            <input type="number" class="form-control" id="new_weight_start" value="0" min="0" step="0.001">
                        </td>
                        <td>
                            <input type="number" class="form-control" id="new_weight_end" value="0" min="0" step="0.001">
                        </td>
                        <?php endif; ?>
                        <td>
                            <input type="number" class="form-control" id="new_rate_price" value="0" min="0" step="0.01">
                        </td>
                        <td>
                            <input type="number" class="form-control" id="new_rate_handling" value="0" min="0" step="0.01">
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="text-end mt-2">
                <button type="button" class="btn btn-primary" id="btn-create-rate">
                    <span class="icon-plus me-1" aria-hidden="true"></span>
                    <?php echo Text::_('JACTION_CREATE'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Existing Rates Section -->
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-end mb-3" id="rates-actions" style="display: none !important;">
                <button type="button" class="btn btn-primary me-2" id="btn-save-rates">
                    <span class="icon-save me-1" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCE_SHIPPING_SAVE_CHANGES'); ?>
                </button>
                <button type="button" class="btn btn-danger" id="btn-delete-rates">
                    <span class="icon-trash me-1" aria-hidden="true"></span>
                    <?php echo Text::_('JACTION_DELETE'); ?>
                </button>
            </div>

            <table class="table" id="rates-table">
                <thead>
                    <tr>
                        <th class="w-1">
                            <input type="checkbox" id="rates-checkall" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>">
                        </th>
                        <th><?php echo Text::_('COM_J2COMMERCE_GEOZONE'); ?></th>
                        <?php if ($showRange) : ?>
                        <th class="rate-range-col"><?php echo Text::_('COM_J2COMMERCE_FIELD_RANGE_START'); ?></th>
                        <th class="rate-range-col"><?php echo Text::_('COM_J2COMMERCE_FIELD_RANGE_END'); ?></th>
                        <?php endif; ?>
                        <th><?php echo Text::_('COM_J2COMMERCE_SHIPPING_COST'); ?></th>
                        <th><?php echo Text::_('COM_J2COMMERCE_HANDLING_COST'); ?></th>
                    </tr>
                </thead>
                <tbody id="rates-body">
                    <tr id="rates-loading">
                        <td colspan="<?php echo $showRange ? 6 : 4; ?>" class="text-center">
                            <span class="icon-spinner icon-spin" aria-hidden="true"></span>
                            <?php echo Text::_('COM_J2COMMERCE_LOADING'); ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div id="rates-pagination" class="text-end text-muted small"></div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const methodId   = <?php echo $methodId; ?>;
    const csrfToken  = '<?php echo Session::getFormToken(); ?>';
    const baseUrl    = 'index.php?option=com_j2commerce';
    const showRange  = <?php echo $showRange ? 'true' : 'false'; ?>;

    const geozoneOptions = <?php
        $opts = [];
        foreach ($geozones as $gzId => $gzName) {
            $opts[] = ['id' => (int) $gzId, 'name' => $gzName];
        }
        echo json_encode($opts);
    ?>;

    // Load rates on page load
    loadRates();

    // Create rate
    document.getElementById('btn-create-rate')?.addEventListener('click', createRate);

    // Save all rates
    document.getElementById('btn-save-rates')?.addEventListener('click', saveAllRates);

    // Delete checked rates
    document.getElementById('btn-delete-rates')?.addEventListener('click', deleteCheckedRates);

    // Check all toggle
    document.getElementById('rates-checkall')?.addEventListener('change', (e) => {
        document.querySelectorAll('#rates-body input[type="checkbox"]').forEach(cb => {
            cb.checked = e.target.checked;
        });
    });

    async function loadRates() {
        try {
            const url = `${baseUrl}&task=shippingplugin.ajax&plugin=shipping_standard&action=loadRates&method_id=${methodId}&${csrfToken}=1&format=json`;
            const response = await fetch(url);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const result = await response.json();

            if (result.success) {
                renderRates(result.data);
            } else {
                showError(result.message || Joomla.Text._('COM_J2COMMERCE_SHIPPING_ERROR_LOADING_RATES'));
            }
        } catch (err) {
            showError(err.message);
        }
    }

    async function createRate() {
        const geozoneId    = parseInt(document.getElementById('new_geozone_id').value, 10);
        const price        = parseFloat(document.getElementById('new_rate_price').value) || 0;
        const handling     = parseFloat(document.getElementById('new_rate_handling').value) || 0;
        const weightStart  = showRange ? (parseFloat(document.getElementById('new_weight_start')?.value) || 0) : 0;
        const weightEnd    = showRange ? (parseFloat(document.getElementById('new_weight_end')?.value) || 0) : 0;

        const formData = new FormData();
        formData.append('method_id', methodId.toString());
        formData.append('geozone_id', geozoneId.toString());
        formData.append('rate_price', price.toString());
        formData.append('rate_handling', handling.toString());
        formData.append('weight_start', weightStart.toString());
        formData.append('weight_end', weightEnd.toString());
        formData.append(csrfToken, '1');

        try {
            const response = await fetch(`${baseUrl}&task=shippingplugin.ajax&plugin=shipping_standard&action=saveRate`, {
                method: 'POST',
                body: formData,
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const result = await response.json();

            if (result.success) {
                // Reset form
                document.getElementById('new_rate_price').value = '0';
                document.getElementById('new_rate_handling').value = '0';
                if (showRange) {
                    document.getElementById('new_weight_start').value = '0';
                    document.getElementById('new_weight_end').value = '0';
                }
                Joomla.renderMessages({ message: [result.data?.message || Joomla.Text._('COM_J2COMMERCE_SHIPPING_RATE_SAVED')] });
                loadRates();
            } else {
                Joomla.renderMessages({ error: [result.message || Joomla.Text._('COM_J2COMMERCE_SHIPPING_ERROR_CREATING_RATE')] });
            }
        } catch (err) {
            Joomla.renderMessages({ error: [err.message] });
        }
    }

    async function saveAllRates() {
        const rows = document.querySelectorAll('#rates-body tr[data-rate-id]');
        if (rows.length === 0) return;

        const rates = [];
        rows.forEach(row => {
            const rateId = parseInt(row.dataset.rateId, 10);
            rates.push({
                rate_id:      rateId,
                method_id:    methodId,
                geozone_id:   parseInt(row.querySelector('.rate-geozone')?.value || '0', 10),
                rate_price:   parseFloat(row.querySelector('.rate-price')?.value || '0'),
                rate_handling: parseFloat(row.querySelector('.rate-handling')?.value || '0'),
                weight_start: showRange ? parseFloat(row.querySelector('.rate-weight-start')?.value || '0') : 0,
                weight_end:   showRange ? parseFloat(row.querySelector('.rate-weight-end')?.value || '0') : 0,
            });
        });

        const formData = new FormData();
        formData.append('rates', JSON.stringify(rates));
        formData.append(csrfToken, '1');

        try {
            const response = await fetch(`${baseUrl}&task=shippingplugin.ajax&plugin=shipping_standard&action=saveRates`, {
                method: 'POST',
                body: formData,
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const result = await response.json();

            if (result.success) {
                Joomla.renderMessages({ message: [result.data?.message || Joomla.Text._('COM_J2COMMERCE_SHIPPING_RATES_SAVED_N')] });
                loadRates();
            } else {
                Joomla.renderMessages({ error: [result.message || Joomla.Text._('COM_J2COMMERCE_SHIPPING_ERROR_SAVING_RATES')] });
            }
        } catch (err) {
            Joomla.renderMessages({ error: [err.message] });
        }
    }

    async function deleteCheckedRates() {
        const checked = document.querySelectorAll('#rates-body input[type="checkbox"]:checked');
        if (checked.length === 0) {
            Joomla.renderMessages({ warning: [Joomla.Text._('COM_J2COMMERCE_SHIPPING_RATE_SELECT_TO_DELETE')] });
            return;
        }

        if (!confirm(Joomla.Text._('COM_J2COMMERCE_CONFIRM_DELETE'))) {
            return;
        }

        const ids = [];
        checked.forEach(cb => {
            const rateId = parseInt(cb.closest('tr')?.dataset.rateId || '0', 10);
            if (rateId > 0) ids.push(rateId);
        });

        const formData = new FormData();
        formData.append('rate_ids', JSON.stringify(ids));
        formData.append(csrfToken, '1');

        try {
            const response = await fetch(`${baseUrl}&task=shippingplugin.ajax&plugin=shipping_standard&action=deleteRates`, {
                method: 'POST',
                body: formData,
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const result = await response.json();

            if (result.success) {
                Joomla.renderMessages({ message: [result.data?.message || Joomla.Text._('COM_J2COMMERCE_SHIPPING_RATES_DELETED_N')] });
                loadRates();
            } else {
                Joomla.renderMessages({ error: [result.message || Joomla.Text._('COM_J2COMMERCE_SHIPPING_ERROR_DELETING_RATES')] });
            }
        } catch (err) {
            Joomla.renderMessages({ error: [err.message] });
        }
    }

    function renderRates(rates) {
        const tbody   = document.getElementById('rates-body');
        const actions = document.getElementById('rates-actions');
        const pag     = document.getElementById('rates-pagination');

        if (!rates || rates.length === 0) {
            const cols = showRange ? 6 : 4;
            tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center text-muted">${Joomla.Text._('JGLOBAL_NO_MATCHING_RESULTS')}</td></tr>`;
            actions.style.display = 'none';
            pag.textContent = '';
            return;
        }

        // Show action buttons
        actions.style.removeProperty('display');
        actions.classList.add('d-flex');

        let html = '';
        for (const rate of rates) {
            const rateId     = rate.j2commerce_shippingrate_id;
            const geozoneId  = parseInt(rate.geozone_id, 10);
            const price      = parseFloat(rate.shipping_rate_price) || 0;
            const handling   = parseFloat(rate.shipping_rate_handling) || 0;
            const wStart     = parseFloat(rate.shipping_rate_weight_start) || 0;
            const wEnd       = parseFloat(rate.shipping_rate_weight_end) || 0;

            // Build geozone dropdown
            let gzOptions = '';
            for (const gz of geozoneOptions) {
                const selected = gz.id === geozoneId ? ' selected' : '';
                gzOptions += `<option value="${gz.id}"${selected}>${escapeHtml(gz.name)}</option>`;
            }

            html += `<tr data-rate-id="${rateId}">
                <td><input type="checkbox" class="rate-checkbox" value="${rateId}"></td>
                <td><select class="form-select rate-geozone">${gzOptions}</select></td>`;

            if (showRange) {
                html += `<td><input type="number" class="form-control rate-weight-start" value="${wStart}" min="0" step="0.001"></td>`;
                html += `<td><input type="number" class="form-control rate-weight-end" value="${wEnd}" min="0" step="0.001"></td>`;
            }

            html += `<td><input type="number" class="form-control rate-price" value="${price}" min="0" step="0.01"></td>
                <td><input type="number" class="form-control rate-handling" value="${handling}" min="0" step="0.01"></td>
            </tr>`;
        }

        tbody.innerHTML = html;
        pag.textContent = Joomla.Text._('COM_J2COMMERCE_SHIPPING_RATES_PAGINATION').replace('%d', rates.length);
    }

    function showError(message) {
        const tbody = document.getElementById('rates-body');
        const cols = showRange ? 6 : 4;
        tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center text-danger">${escapeHtml(message)}</td></tr>`;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
</script>
