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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/** @var \J2Commerce\Component\J2commerce\Site\View\Carts\HtmlView $this */

if (!$this->params->get('show_tax_calculator', 1)) {
    return;
}

$postcodeRequired = $this->params->get('postalcode_required', 1);
$baseUrl = Route::_('index.php');
$loaderImage = Uri::root(true) . '/media/com_j2commerce/images/loader.gif';

// Build country select using native Joomla
$db = Factory::getContainer()->get(DatabaseInterface::class);
$query = $db->getQuery(true)
    ->select([$db->quoteName('j2commerce_country_id', 'value'), $db->quoteName('country_name', 'text')])
    ->from($db->quoteName('#__j2commerce_countries'))
    ->where($db->quoteName('enabled') . ' = 1')
    ->order($db->quoteName('country_name') . ' ASC');
$db->setQuery($query);
$countries = $db->loadObjectList();

// Add placeholder option
array_unshift($countries, (object) ['value' => '', 'text' => Text::_('COM_J2COMMERCE_SELECT_OPTION')]);

// Note: The 7th parameter sets the element ID, not the 'id' key in attribs
$countryList = HTMLHelper::_('select.genericlist', $countries, 'country_id', [
    'class' => 'form-select',
], 'value', 'text', $this->country_id, 'estimate_country_id');

?>
<div class="accordion-item">
    <h2 class="accordion-header">
        <button class="accordion-button collapsed"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#shippingEstimatorCollapse"
                aria-expanded="false"
                aria-controls="shippingEstimatorCollapse">
            <?php echo Text::_('COM_J2COMMERCE_CART_TAX_SHIPPING_CALCULATOR_HEADING'); ?>
        </button>
    </h2>
    <div id="shippingEstimatorCollapse"
         class="accordion-collapse collapse"
         data-bs-parent="#cartToolsAccordion">
        <div class="accordion-body">
        <form id="shipping-estimate-form" class="needs-validation" novalidate>
            <div class="mb-3">
                <label for="estimate_country_id" class="form-label">
                    <span class="text-danger">*</span> <?php echo Text::_('COM_J2COMMERCE_SELECT_A_COUNTRY'); ?>
                </label>
                <?php echo $countryList; ?>
            </div>
            <div class="mb-3">
                <label for="estimate_zone_id" class="form-label">
                    <span class="text-danger">*</span> <?php echo Text::_('COM_J2COMMERCE_STATE_PROVINCE'); ?>
                </label>
                <select id="estimate_zone_id" name="zone_id" class="form-select">
                    <option value=""><?php echo Text::_('COM_J2COMMERCE_SELECT_OPTION'); ?></option>
                </select>
            </div>
            <div class="mb-3">
                <label for="estimate_postcode" class="form-label">
                    <?php if ($postcodeRequired): ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                    <?php echo Text::_('COM_J2COMMERCE_POSTCODE'); ?>
                </label>
                <input type="text"
                       id="estimate_postcode"
                       name="postcode"
                       value="<?php echo $this->escape($this->postcode); ?>"
                       class="form-control" />
            </div>

            <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayCalculatorField', [$this->order]); ?>

            <button type="button" id="button-quote" class="btn btn-primary">
                <?php echo Text::_('COM_J2COMMERCE_CART_CALCULATE_TAX_SHIPPING'); ?>
            </button>

            <input type="hidden" name="option" value="com_j2commerce" />
            <input type="hidden" name="view" value="carts" />
            <input type="hidden" name="task" value="estimate" />
        </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('shipping-estimate-form');
    const countrySelect = document.getElementById('estimate_country_id');
    const zoneSelect = document.getElementById('estimate_zone_id');
    const buttonQuote = document.getElementById('button-quote');
    const baseUrl = '<?php echo $baseUrl; ?>';
    const loaderImage = '<?php echo $loaderImage; ?>';
    const currentZoneId = '<?php echo $this->zone_id; ?>';

    // Handle country change to load zones
    if (countrySelect) {
        countrySelect.addEventListener('change', function() {
            const countryId = parseInt(this.value, 10);

            // Reset zone dropdown when no country selected
            if (!countryId || countryId <= 0) {
                zoneSelect.innerHTML = '';
                const defaultOpt = document.createElement('option');
                defaultOpt.value = '';
                defaultOpt.textContent = '<?php echo Text::_('COM_J2COMMERCE_SELECT_OPTION'); ?>';
                zoneSelect.appendChild(defaultOpt);
                return;
            }

            const loader = document.createElement('span');
            loader.className = 'wait ms-2';
            loader.innerHTML = '<img src="' + loaderImage + '" alt="Loading..." style="width: 16px; height: 16px;">';

            this.parentNode.appendChild(loader);

            fetch(baseUrl + '?option=com_j2commerce&task=carts.getCountry&country_id=' + countryId, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                // Build zone options using DOM API (textContent auto-escapes HTML)
                zoneSelect.innerHTML = '';

                const defaultOpt = document.createElement('option');
                defaultOpt.value = '';
                defaultOpt.textContent = '<?php echo Text::_('COM_J2COMMERCE_SELECT_OPTION'); ?>';
                zoneSelect.appendChild(defaultOpt);

                if (data.zone && data.zone.length > 0) {
                    data.zone.forEach(function(zone) {
                        const opt = document.createElement('option');
                        opt.value = zone.j2commerce_zone_id;
                        opt.textContent = zone.zone_name;
                        if (zone.j2commerce_zone_id == currentZoneId) {
                            opt.selected = true;
                        }
                        zoneSelect.appendChild(opt);
                    });
                } else {
                    const noneOpt = document.createElement('option');
                    noneOpt.value = '0';
                    noneOpt.selected = true;
                    noneOpt.textContent = '<?php echo Text::_('COM_J2COMMERCE_CHECKOUT_ZONE_NONE'); ?>';
                    zoneSelect.appendChild(noneOpt);
                }
            })
            .catch(error => {
                console.error('Error loading zones:', error);
            })
            .finally(() => {
                const waitElement = document.querySelector('.wait');
                if (waitElement) {
                    waitElement.remove();
                }
            });
        });

        // Trigger initial load
        countrySelect.dispatchEvent(new Event('change'));
    }

    // Handle estimate button click
    if (buttonQuote) {
        buttonQuote.addEventListener('click', async function() {
            // Remove previous errors
            document.querySelectorAll('.j2error').forEach(el => el.remove());

            const formData = new FormData(form);

            // Remove hidden fields that conflict with URL parameters —
            // POST body task=estimate overrides URL task=carts.estimateAjax in $_REQUEST
            formData.delete('task');
            formData.delete('option');
            formData.delete('view');

            // Disable button and show loading
            buttonQuote.disabled = true;
            buttonQuote.classList.add('disabled');
            const originalText = buttonQuote.innerHTML;
            buttonQuote.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_LOADING'); ?>';

            try {
                // Use POST for the estimate task with AJAX flag
                formData.append('ajax', '1');
                formData.append('<?php echo Factory::getApplication()->getSession()->getFormToken(); ?>', '1');

                const response = await fetch(baseUrl + '?option=com_j2commerce&task=carts.estimateAjax', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Cache-Control': 'no-cache'
                    }
                });

                if (!response.ok) {
                    throw new Error('HTTP error ' + response.status);
                }

                const data = await response.json();

                if (data.error) {
                    // Show validation errors
                    Object.keys(data.error).forEach(function(key) {
                        if (data.error[key]) {
                            const field = document.getElementById('estimate_' + key);
                            if (field) {
                                const errorSpan = document.createElement('span');
                                errorSpan.className = 'j2error text-danger d-block mt-1';
                                errorSpan.textContent = data.error[key];
                                field.parentNode.appendChild(errorSpan);
                            }
                        }
                    });
                }

                if (data.success) {
                    // Show success message if provided
                    if (data.message && typeof Joomla !== 'undefined' && Joomla.renderMessages) {
                        Joomla.renderMessages({ success: [data.message] });
                    }

                    // Refresh the totals section via AJAX
                    refreshCartTotals();
                }
            } catch (error) {
                console.error('Error calculating shipping:', error);
                if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
                    Joomla.renderMessages({ error: ['Error calculating shipping. Please try again.'] });
                }
            } finally {
                buttonQuote.disabled = false;
                buttonQuote.classList.remove('disabled');
                buttonQuote.innerHTML = originalText;
            }
        });
    }

    /**
     * Refresh the cart totals section via AJAX
     */
    async function refreshCartTotals() {
        const csrfToken = '<?php echo Factory::getApplication()->getSession()->getFormToken(); ?>';
        const formData = new FormData();
        formData.append('option', 'com_j2commerce');
        formData.append('task', 'carts.getTotalsAjax');
        formData.append(csrfToken, '1');

        try {
            const response = await fetch(baseUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'Cache-Control': 'no-cache'
                }
            });

            if (!response.ok) {
                throw new Error('HTTP error ' + response.status);
            }

            const data = await response.json();

            if (data.success && data.html) {
                // Find and replace the totals container
                const totalsContainer = document.querySelector('.cart-totals-block');
                if (totalsContainer) {
                    totalsContainer.outerHTML = data.html;
                }

                // Also replace shipping methods section if returned
                if (data.shipping_html !== undefined) {
                    const shippingWrapper = document.getElementById('j2commerce-cart-shipping-wrapper');
                    if (shippingWrapper) {
                        shippingWrapper.innerHTML = data.shipping_html;
                    }
                }
            }
        } catch (error) {
            console.error('Error refreshing totals:', error);
            // Fallback: reload the page if AJAX totals refresh fails
            window.location.reload();
        }
    }

    // Event-delegated shipping method selection handler
    // Survives innerHTML replacement from AJAX updates
    document.addEventListener('change', function(e) {
        const radio = e.target.closest('.shipping-method-radio');
        if (!radio) return;

        const form = document.getElementById('j2commerce-cart-shipping-form');
        if (!form) return;

        // Update hidden fields
        const nameField = document.getElementById('shipping_name');
        const codeField = document.getElementById('shipping_code');
        const priceField = document.getElementById('shipping_price');
        const taxField = document.getElementById('shipping_tax');
        const extraField = document.getElementById('shipping_extra');
        const taxClassIdField = document.getElementById('shipping_tax_class_id');
        const pluginField = document.getElementById('shipping_plugin');

        if (nameField) nameField.value = radio.dataset.name;
        if (codeField) codeField.value = radio.dataset.code;
        if (priceField) priceField.value = radio.dataset.price;
        if (taxField) taxField.value = radio.dataset.tax;
        if (extraField) extraField.value = radio.dataset.extra;
        if (taxClassIdField) taxClassIdField.value = radio.dataset.taxClassId || '0';
        if (pluginField) pluginField.value = radio.dataset.element;

        // Build params from hidden fields + checked radio
        const params = new URLSearchParams();
        form.querySelectorAll('input[type="hidden"], input[type="radio"]:checked').forEach(function(input) {
            params.append(input.name, input.value);
        });

        // Update shipping via AJAX then refresh totals (skip server redirect)
        fetch(baseUrl + '?option=com_j2commerce&task=carts.shippingUpdate&' + params.toString(), {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        })
        .then(response => response.json())
        .then(() => refreshCartTotals())
        .catch(error => {
            console.error('Error updating shipping:', error);
        });
    });
});
</script>
