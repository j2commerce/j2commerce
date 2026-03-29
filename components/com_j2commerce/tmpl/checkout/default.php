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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

/** @var \J2Commerce\Component\J2commerce\Site\View\Checkout\HtmlView $this */

$pageHeading = $this->menuItemParams->get('show_page_heading', 0);
$pageHeadingText = $this->menuItemParams->get('page_heading', '');
$token = Session::getFormToken();

$wa  = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('bootstrap.collapse');
$wa->useScript('bootstrap.dropdown');

$wa->registerAndUseStyle('checkout.style', Uri::root() .'media/com_j2commerce/css/site/checkout.css', [], [], []);
$wa->registerAndUseStyle('com_j2commerce.telephone.css', 'media/com_j2commerce/css/site/telephone-field.css');
$wa->registerAndUseScript('com_j2commerce.telephone', 'media/com_j2commerce/js/site/telephone-field.js', [], ['defer' => true]);

// Grand total for mobile toggle button
$grandTotal = '';
if ($this->order && method_exists($this->order, 'get_formatted_order_totals')) {
    $totals = $this->order->get_formatted_order_totals();
    $grandTotal = $totals['grandtotal']['value'] ?? '';
}

// Pre-compute JS-safe language strings
$selectZoneJs = htmlspecialchars(Text::sprintf('COM_J2COMMERCE_SELECT_PLACEHOLDER', Text::_('COM_J2COMMERCE_ZONE')), ENT_QUOTES, 'UTF-8');
?>
<?php if ($pageHeading) : ?>
    <div class="page-header">
        <h1><?php echo $this->escape($pageHeadingText ?: Text::_('COM_J2COMMERCE_CHECKOUT')); ?></h1>
    </div>
<?php endif; ?>

<?php echo J2CommerceHelper::modules()->loadposition('j2commerce-checkout-top'); ?>

<div id="j2commerce-checkout" class="j2commerce checkout">
    <a href="#j2commerce-checkout-content" class="j2commerce-skip-link visually-hidden-focusable"><?php echo Text::_('COM_J2COMMERCE_SKIP_TO_CHECKOUT'); ?></a>

    <div id="j2commerce-checkout-content">
        <?php
        $showStoreLogo = (int) J2CommerceHelper::config()->get('checkout_show_store_logo', 1);
        if ($showStoreLogo) {
            $storeLogo = J2CommerceHelper::config()->get('store_logo');
            if (\is_string($storeLogo)) {
                $storeLogo = json_decode($storeLogo);
            }
            $logoFile = $storeLogo->imagefile ?? '';
            $logoAlt  = $storeLogo->alt_text ?? '';
            if ($logoFile && $logoAlt !== '') :
        ?>
        <div class="text-center mb-3">
            <img src="<?php echo Uri::root() . htmlspecialchars($logoFile, ENT_QUOTES, 'UTF-8'); ?>"
                 alt="<?php echo htmlspecialchars($logoAlt, ENT_QUOTES, 'UTF-8'); ?>"
                 class="img-fluid">
        </div>
        <?php endif; } ?>
        <div class="j2commerce-checkout-row row justify-content-xl-center">
            <div class="j2commerce-checkout-steps col-lg-8 col-xl-6 order-2 order-lg-1">
                <section id="checkout" role="region" aria-labelledby="checkout-heading-label">
                    <div class="checkout-heading mb-2 d-flex justify-content-between align-items-center">
                        <div>
                            <span id="checkout-heading-label" class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_OPTIONS'); ?></span>
                        </div>
                        <?php if ($this->logged) : ?>
                            <a href="<?php echo Route::_('index.php?option=com_j2commerce&task=checkout.logout&' . $token . '=1'); ?>" class="checkout-logout text-danger">
                                <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_LOGOUT'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="checkout-content" aria-busy="false"></div>
                </section>

                <?php if (!$this->logged) : ?>
                <section id="billing-address" role="region" aria-labelledby="billing-heading-label">
                    <div class="checkout-heading mb-2"><span id="billing-heading-label"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_ACCOUNT'); ?></span></div>
                    <div class="checkout-content" aria-busy="false"></div>
                </section>
                <?php else : ?>
                <section id="billing-address" role="region" aria-labelledby="billing-heading-label">
                    <div class="checkout-heading mb-2"><span id="billing-heading-label"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_BILLING_ADDRESS'); ?></span></div>
                    <div class="checkout-content" aria-busy="false"></div>
                </section>
                <?php endif; ?>

                <section id="custom-steps-after-billing" role="region" aria-labelledby="custom-steps-after-billing-label" style="display:none;">
                    <div class="checkout-heading mb-2"><span id="custom-steps-after-billing-label"></span></div>
                    <div class="checkout-content" aria-busy="false"></div>
                </section>

                <?php if ($this->showShipping) : ?>
                <section id="shipping-address" role="region" aria-labelledby="shipping-heading-label" style="display:none;">
                    <div class="checkout-heading mb-2"><span id="shipping-heading-label"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_SHIPPING_ADDRESS'); ?></span></div>
                    <div class="checkout-content" aria-busy="false"></div>
                </section>
                <?php endif; ?>

                <section id="custom-steps-after-shipping" role="region" aria-labelledby="custom-steps-after-shipping-label" style="display:none;">
                    <div class="checkout-heading mb-2"><span id="custom-steps-after-shipping-label"></span></div>
                    <div class="checkout-content" aria-busy="false"></div>
                </section>

                <section id="custom-steps-before-payment" role="region" aria-labelledby="custom-steps-before-payment-label" style="display:none;">
                    <div class="checkout-heading mb-2"><span id="custom-steps-before-payment-label"></span></div>
                    <div class="checkout-content" aria-busy="false"></div>
                </section>

                <section id="shipping-payment-method" role="region" aria-labelledby="payment-heading-label">
                    <div class="checkout-heading mb-2">
                    <span id="payment-heading-label">
                    <?php if ($this->showShipping) : ?>
                        <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_SHIPPING_PAYMENT_METHOD'); ?>
                    <?php else : ?>
                        <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_PAYMENT_METHOD'); ?>
                    <?php endif; ?>
                    </span>
                    </div>
                    <div class="checkout-content" aria-busy="false"></div>
                </section>

                <section id="custom-steps-before-confirm" role="region" aria-labelledby="custom-steps-before-confirm-label" style="display:none;">
                    <div class="checkout-heading mb-2"><span id="custom-steps-before-confirm-label"></span></div>
                    <div class="checkout-content" aria-busy="false"></div>
                </section>

                <section id="confirm" role="region" aria-labelledby="confirm-heading-label">
                    <div class="checkout-heading"><span id="confirm-heading-label"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_CONFIRM'); ?></span></div>
                    <div class="checkout-content" aria-busy="false"></div>
                </section>
            </div>

            <div class="j2commerce-checkout-sidebar col-lg-4 offset-xl-1 order-1 order-lg-2">
                <?php echo J2CommerceHelper::modules()->loadposition('j2commerce-checkout-sidecart-top'); ?>

                <button class="btn btn-light w-100 d-lg-none d-flex justify-content-between align-items-center border py-3 mb-3"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#checkoutSidecartCollapse"
                        aria-expanded="false"
                        aria-controls="checkoutSidecartCollapse">
                    <span class="d-flex align-items-center gap-2">
                        <span class="icon-cart" aria-hidden="true"></span>
                        <span class="j2commerce-sidecart-toggle-text"><?php echo Text::_('COM_J2COMMERCE_SHOW_ORDER_SUMMARY'); ?></span>
                        <span class="icon-chevron-down small j2commerce-sidecart-chevron" aria-hidden="true"></span>
                    </span>
                    <span class="fw-bold fs-5 j2commerce-sidecart-toggle-total"><?php echo $grandTotal; ?></span>
                </button>

                <div class="bg-light rounded p-3 p-lg-4">
                    <?php echo $this->loadTemplate('sidecart'); ?>
                </div>

                <?php echo J2CommerceHelper::modules()->loadposition('j2commerce-checkout-sidecart-bottom'); ?>
            </div>
        </div>
    </div>
</div>

<?php echo J2CommerceHelper::modules()->loadposition('j2commerce-checkout-bottom'); ?>



<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    var baseUrl = 'index.php';
    var token = '<?php echo $token; ?>';
    var showShipping = <?php echo $this->showShipping ? 'true' : 'false'; ?>;
    var isLoggedIn = <?php echo $this->logged ? 'true' : 'false'; ?>;
    var modifyText = '<?php echo Text::_('COM_J2COMMERCE_CHECKOUT_MODIFY', true); ?>';
    var billingTask = isLoggedIn ? 'billingAddress' : null;

    // Utility: slide content up (hide)
    function slideUp(el) {
        if (!el) return;
        el.classList.remove('active');
    }

    // Utility: slide content down (show)
    function slideDown(el) {
        if (!el) return;
        el.classList.add('active');
    }

    // Utility: get content div for a step
    function getContent(stepId) {
        return document.querySelector('#' + stepId + ' .checkout-content');
    }

    // Utility: hide all step contents
    function hideAllContents() {
        document.querySelectorAll('.checkout-content').forEach(function(el) {
            slideUp(el);
        });
        // Reset aria-expanded on all edit links
        document.querySelectorAll('.checkout-heading a[aria-expanded]').forEach(function(a) {
            a.setAttribute('aria-expanded', 'false');
        });
    }

    // Utility: add edit link to a step heading
    function addEditLink(stepId) {
        // For logged-in users, #checkout already has a server-rendered logout link
        if (stepId === 'checkout' && isLoggedIn) return;
        var section = document.getElementById(stepId);
        // Skip custom step sections that were never shown (no steps)
        if (section && section.style.display === 'none') return;
        var heading = document.querySelector('#' + stepId + ' .checkout-heading');
        if (!heading) return;
        var existing = heading.querySelector('a:not(.checkout-logout)');
        if (existing) existing.remove();
        // Get step name from the heading label for accessible context
        var labelSpan = heading.querySelector('span[id$="-heading-label"], span');
        var stepName = labelSpan ? labelSpan.textContent.trim() : stepId;
        var link = document.createElement('a');
        link.textContent = modifyText;
        link.setAttribute('role', 'button');
        link.setAttribute('tabindex', '0');
        link.setAttribute('href', '#');
        link.setAttribute('aria-expanded', 'false');
        link.setAttribute('aria-label', modifyText + ' ' + stepName);
        heading.appendChild(link);
    }

    // Utility: remove all edit links (preserves logout link)
    function removeEditLinks() {
        document.querySelectorAll('.checkout-heading a:not(.checkout-logout)').forEach(function(a) { a.remove(); });
    }

    // Utility: show loading spinner on a button
    function showLoading(btn) {
        if (!btn) return;
        btn.disabled = true;
        var spinner = document.createElement('span');
        spinner.className = 'wait';
        spinner.innerHTML = '&nbsp;<span class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></span></span>';
        btn.parentNode.insertBefore(spinner, btn.nextSibling);
    }

    // Utility: hide loading spinner
    function hideLoading(btn) {
        if (!btn) return;
        btn.disabled = false;
        var wait = btn.parentNode.querySelector('.wait');
        if (wait) wait.remove();
    }

    // Utility: clear warnings and errors
    function clearErrors(container) {
        if (!container) return;
        container.querySelectorAll('.warning, .j2error, .alert-danger').forEach(function(el) { el.remove(); });
        container.querySelectorAll('.j2-invalid').forEach(function(el) {
            el.classList.remove('j2-invalid');
            el.removeAttribute('aria-invalid');
            el.removeAttribute('aria-describedby');
        });
    }

    // Utility: show warning in a container, with optional detail shown in <small>
    function showWarning(container, message, detail) {
        if (!container || !message) return;
        var div = document.createElement('div');
        div.className = 'warning alert alert-danger alert-dismissible';
        div.setAttribute('role', 'alert');
        div.setAttribute('aria-live', 'assertive');
        div.textContent = message;
        if (detail) {
            var small = document.createElement('small');
            small.className = 'd-block mt-1 text-danger-emphasis';
            small.textContent = detail;
            div.appendChild(small);
        }
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-close';
        btn.setAttribute('data-bs-dismiss', 'alert');
        btn.setAttribute('aria-label', '<?php echo Text::_('JCLOSE', true); ?>');
        div.appendChild(btn);
        container.prepend(div);
        div.setAttribute('tabindex', '-1');
        div.focus();
        announceToScreenReader(message, 'assertive');
    }

    // Utility: extract a human-readable error detail from a server response body
    function extractErrorDetail(text) {
        if (!text) return '';
        try {
            var json = JSON.parse(text);
            if (json.message) return json.message;
            if (json.error) return typeof json.error === 'string' ? json.error : '';
        } catch (e) { /* not JSON */ }
        // Try to extract from Joomla HTML error page: <title>...</title>
        var m = text.match(/<title[^>]*>([^<]+)<\/title>/i);
        if (m && m[1]) return m[1].trim();
        // Fallback: first 200 chars of non-HTML text
        if (text.indexOf('<') === -1 && text.length > 0) return text.substring(0, 200);
        return '';
    }

    // Utility: show field error
    function showFieldError(container, fieldId, message) {
        if (!container || !message) return;
        var field = container.querySelector('#' + fieldId);
        if (field) {
            field.classList.add('j2-invalid');
            field.setAttribute('aria-invalid', 'true');
            field.setAttribute('aria-describedby', fieldId + '-error');
            var span = document.createElement('span');
            span.className = 'j2error d-block';
            span.id = fieldId + '-error';
            span.setAttribute('role', 'alert');
            span.textContent = message;
            field.parentNode.insertBefore(span, field.nextSibling);
        }
    }

    // Accessibility: move focus to element
    function focusElement(selector) {
        var el = document.querySelector(selector);
        if (!el) return;
        el.setAttribute('tabindex', '-1');
        el.focus();
        el.removeAttribute('tabindex');
    }

    // Accessibility: announce to screen readers
    function announceToScreenReader(message, priority) {
        var announcer = document.getElementById('j2commerce-sr-announcer');
        if (!announcer) {
            announcer = document.createElement('div');
            announcer.id = 'j2commerce-sr-announcer';
            announcer.className = 'visually-hidden';
            announcer.setAttribute('role', 'status');
            announcer.setAttribute('aria-live', 'polite');
            announcer.setAttribute('aria-atomic', 'true');
            document.body.appendChild(announcer);
        }
        announcer.setAttribute('aria-live', priority || 'polite');
        announcer.textContent = message;
        setTimeout(function() { announcer.textContent = ''; }, 1000);
    }

    // Accessibility: trap focus within element
    function trapFocus(element) {
        var focusable = element.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        element.addEventListener('keydown', function(e) {
            if (e.key !== 'Tab') return;
            if (e.shiftKey) {
                if (document.activeElement === first) { e.preventDefault(); last.focus(); }
            } else {
                if (document.activeElement === last) { e.preventDefault(); first.focus(); }
            }
        });
    }

    // Utility: initialize AJAX-linked country/zone fields within a container.
    // Populates the country dropdown, then cascades to zones on change.
    // Preserves any pre-selected values (e.g. from a re-rendered form).
    function initCountryZoneFields(container) {
        if (!container) return;

        var countrySelect = container.querySelector('select[name="country_id"]');
        var zoneSelect = container.querySelector('select[name="zone_id"]');

        if (!countrySelect) return;

        // Capture any server-rendered selections before replacing innerHTML
        var savedCountryId = countrySelect.value || '';
        var savedZoneId = zoneSelect ? (zoneSelect.value || '') : '';

        // Fetch and populate countries, restoring saved selection
        var countryUrl = baseUrl + '?option=com_j2commerce&task=ajax.getCountries';
        if (savedCountryId) countryUrl += '&country_id=' + savedCountryId;

        fetch(countryUrl)
            .then(function(r) { return r.text(); })
            .then(function(html) {
                countrySelect.innerHTML = html;
                // If a country was pre-selected and zones exist, cascade to load zones
                if (countrySelect.value && zoneSelect) {
                    loadZones(countrySelect.value, savedZoneId);
                }
            })
            .catch(function(err) {
                console.error('Error loading countries:', err);
            });

        if (!zoneSelect) return;

        // Load zones for the selected country
        function loadZones(countryId, selectedZoneId) {
            zoneSelect.innerHTML = '<option value=""><?php echo Text::_('COM_J2COMMERCE_LOADING', true); ?></option>';
            zoneSelect.disabled = true;

            if (!countryId || countryId === '0' || countryId === '') {
                zoneSelect.innerHTML = '<option value=""><?php echo $selectZoneJs; ?></option>';
                zoneSelect.disabled = false;
                return;
            }

            var url = baseUrl + '?option=com_j2commerce&task=ajax.getZones&country_id=' + countryId;
            if (selectedZoneId) url += '&zone_id=' + selectedZoneId;

            fetch(url)
                .then(function(r) { return r.text(); })
                .then(function(html) {
                    zoneSelect.innerHTML = html;
                    zoneSelect.disabled = false;
                })
                .catch(function(err) {
                    console.error('Error loading zones:', err);
                    zoneSelect.innerHTML = '<option value=""><?php echo $selectZoneJs; ?></option>';
                    zoneSelect.disabled = false;
                });
        }

        // Country change → reload zones
        countrySelect.addEventListener('change', function() {
            loadZones(this.value, 0);
        });
    }

    // Core: fetch a step's HTML content from the server
    function fetchStep(task, stepId) {
        var content = getContent(stepId);
        if (!content) return Promise.resolve();
        content.setAttribute('aria-busy', 'true');

        var formData = new FormData();
        formData.append('option', 'com_j2commerce');
        formData.append('task', 'checkout.' + task);
        formData.append(token, '1');

        return fetch(baseUrl, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response) {
            if (!response.ok) {
                return response.text().then(function(text) {
                    console.error('Checkout step HTTP ' + response.status + ':', text.substring(0, 500));
                    content.setAttribute('aria-busy', 'false');
                    if (response.status === 403) {
                        content.innerHTML = '<div class="alert alert-warning" role="alert">Your session has expired. Please <a href="' + window.location.href + '">reload the page</a> and try again.</div>';
                    } else {
                        var detail = extractErrorDetail(text);
                        var html = '<div class="alert alert-danger" role="alert">Server error (' + response.status + '). Please try again.';
                        if (detail) {
                            html += '<small class="d-block mt-1 text-danger-emphasis">' + detail.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</small>';
                        }
                        html += '</div>';
                        content.innerHTML = html;
                    }
                });
            }
            return response.text();
        })
        .then(function(html) {
            if (html !== undefined) {
                content.innerHTML = html;
                content.setAttribute('aria-busy', 'false');
                refreshSidecart();
                document.dispatchEvent(new CustomEvent('j2commerce:checkout:stepLoaded', { detail: { stepId: stepId } }));
            }
        })
        .catch(function(error) {
            console.error('Checkout network error:', error);
            content.setAttribute('aria-busy', 'false');
            content.innerHTML = '<div class="alert alert-danger" role="alert">Unable to connect to the server. Please check your connection and try again.</div>';
        });
    }

    // Core: submit form data and get JSON response
    function submitForm(task, formDataOrElement) {
        var formData;
        if (formDataOrElement instanceof FormData) {
            formData = formDataOrElement;
        } else {
            formData = new FormData();
            // Collect form inputs from element
            var el = typeof formDataOrElement === 'string' ? document.querySelector(formDataOrElement) : formDataOrElement;
            if (el) {
                // For shipping/payment groups with radio buttons that have sibling
                // hidden fields (same name across multiple rate containers), we must
                // only collect hidden fields from the SELECTED rate's container —
                // otherwise PHP receives duplicate keys and uses the last value,
                // which is the most expensive rate (sorted cheapest-first).
                var shippingFields = ['shipping_name','shipping_price','shipping_code','shipping_tax','shipping_tax_class_id','shipping_extra'];
                var checkedShippingRadio = el.querySelector('input[type="radio"][name="shipping_plugin"]:checked');
                var selectedShippingContainer = checkedShippingRadio ? checkedShippingRadio.closest('.list-group-item') : null;

                el.querySelectorAll('input, select, textarea').forEach(function(input) {
                    if (!input.name || input.disabled) return;
                    if (input.type === 'checkbox' || input.type === 'radio') {
                        if (input.checked) formData.append(input.name, input.value);
                    } else if (input.type !== 'file' && input.type !== 'submit') {
                        // Skip shipping hidden fields that belong to unselected rates
                        if (shippingFields.indexOf(input.name) !== -1) {
                            if (!selectedShippingContainer || !selectedShippingContainer.contains(input)) return;
                        }
                        formData.append(input.name, input.value);
                    }
                });
            }
        }
        formData.append('option', 'com_j2commerce');
        formData.append('task', 'checkout.' + task);
        formData.append(token, '1');

        return fetch(baseUrl, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response) {
            return response.text().then(function(text) {
                if (!response.ok) {
                    console.error('Checkout HTTP ' + response.status + ':', text.substring(0, 500));
                    var detail = extractErrorDetail(text);
                    if (response.status === 403) {
                        return { error: { warning: 'Your session has expired. Please reload the page and try again.' }, _detail: detail };
                    }
                    return { error: { warning: 'Server error (' + response.status + '). Please try again.' }, _detail: detail };
                }
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Checkout JSON parse error:', text.substring(0, 500));
                    if (text.indexOf('<!DOCTYPE') !== -1 || text.indexOf('<html') !== -1) {
                        return { error: { warning: 'Your session has expired. Please reload the page and try again.' } };
                    }
                    return { error: { warning: 'Unexpected server response. Please reload the page and try again.' } };
                }
            });
        })
        .catch(function(error) {
            console.error('Checkout network error:', error);
            return { error: { warning: 'Unable to connect to the server. Please check your connection and try again.' } };
        });
    }

    // Heading edit link clicks - open that step
    document.addEventListener('click', function(e) {
        var link = e.target.closest('.checkout-heading a');
        if (!link) return;
        e.preventDefault();
        var step = link.closest('[id]');
        if (!step) return;
        hideAllContents();
        slideDown(getContent(step.id));
        link.setAttribute('aria-expanded', 'true');
    });

    // Keyboard accessibility for edit links (Enter/Space)
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var link = e.target.closest('.checkout-heading a');
        if (!link) return;
        e.preventDefault();
        var step = link.closest('[id]');
        if (!step) return;
        hideAllContents();
        slideDown(getContent(step.id));
        link.setAttribute('aria-expanded', 'true');
    });

    // Toggle existing/new address form visibility (billing)
    // Note: The inline script in default_billing.php won't execute via innerHTML,
    // so we handle it here with event delegation.
    document.addEventListener('change', function(e) {
        if (!e.target.matches('input[name="billing_address"]')) return;
        var newForm = document.getElementById('billing-new-address-form');
        if (newForm) {
            newForm.style.display = e.target.value === 'new' ? 'block' : 'none';
        }
    });

    // Toggle existing/new address form visibility (shipping)
    document.addEventListener('change', function(e) {
        if (!e.target.matches('input[name="shipping_address"]')) return;
        var newForm = document.getElementById('shipping-new-address-form');
        if (newForm) {
            newForm.style.display = e.target.value === 'new' ? 'block' : 'none';
        }
    });

    // Account type radio changes (register/guest)
    document.addEventListener('change', function(e) {
        if (!e.target.matches('#checkout .checkout-content input[name="account"]')) return;
        var billingHeading = document.querySelector('#billing-address .checkout-heading span');
        if (!billingHeading) return;
        if (e.target.value === 'register') {
            billingHeading.textContent = '<?php echo Text::_('COM_J2COMMERCE_CHECKOUT_ACCOUNT', true); ?>';
        } else {
            billingHeading.textContent = '<?php echo Text::_('COM_J2COMMERCE_CHECKOUT_BILLING_ADDRESS', true); ?>';
        }
    });

    // Button: Continue from account type selection
    document.addEventListener('click', function(e) {
        if (!e.target.matches('#button-account')) return;
        var selected = document.querySelector('input[name="account"]:checked');
        if (!selected) return;
        var task = selected.value;
        billingTask = task;
        var btn = e.target;
        showLoading(btn);

        fetchStep(task, 'billing-address').then(function() {
            hideLoading(btn);
            clearErrors(getContent('billing-address'));
            hideAllContents();
            slideDown(getContent('billing-address'));
            removeEditLinks();
            addEditLink('checkout');
            initCountryZoneFields(getContent('billing-address'));
            focusElement('#billing-address .checkout-heading');
            announceToScreenReader('<?php echo Text::_('COM_J2COMMERCE_CHECKOUT_BILLING_ADDRESS', true); ?>');
        });
    });

    // Button: Login
    document.addEventListener('click', function(e) {
        if (!e.target.matches('#button-login')) return;
        var btn = e.target;
        showLoading(btn);

        submitForm('loginValidate', '#checkout #login').then(function(json) {
            hideLoading(btn);
            clearErrors(getContent('checkout'));

            if (json.redirect) {
                window.location.href = json.redirect;
            } else if (json.error) {
                if (json.error.warning) {
                    showWarning(getContent('checkout'), json.error.warning, json._detail);
                }
            }
        });
    });

    // Enter key in login form
    document.addEventListener('keypress', function(e) {
        if (e.keyCode !== 13) return;
        var loginForm = e.target.closest('#checkout #login');
        if (!loginForm) return;
        var loginBtn = document.getElementById('button-login');
        if (loginBtn) loginBtn.click();
    });

    // Button: Register
    document.addEventListener('click', function(e) {
        if (!e.target.matches('#button-register')) return;
        var btn = e.target;
        showLoading(btn);

        submitForm('registerValidate', '#billing-address').then(function(json) {
            hideLoading(btn);
            clearErrors(getContent('billing-address'));

            // After register+login, the CSRF token changes — update it
            if (json.token) {
                token = json.token;
            }

            if (json.redirect) {
                window.location.href = json.redirect;
            } else if (json.error) {
                if (json.error.warning) {
                    showWarning(getContent('billing-address'), json.error.warning, json._detail);
                }
                Object.keys(json.error).forEach(function(key) {
                    if (key !== 'warning' && json.error[key]) {
                        showFieldError(getContent('billing-address'), key, json.error[key]);
                    }
                });
            } else {
                advanceFromBilling();
            }
        });
    });

    // Button: Billing Address (logged-in user)
    document.addEventListener('click', function(e) {
        if (!e.target.matches('#button-billing-address')) return;
        var btn = e.target;
        showLoading(btn);

        submitForm('billingAddressValidate', '#billing-address').then(function(json) {
            hideLoading(btn);
            clearErrors(getContent('billing-address'));

            if (json.redirect) {
                window.location.href = json.redirect;
            } else if (json.error) {
                if (json.error.warning) {
                    showWarning(getContent('billing-address'), json.error.warning, json._detail);
                }
                Object.keys(json.error).forEach(function(key) {
                    if (key !== 'warning' && json.error[key]) {
                        showFieldError(getContent('billing-address'), key, json.error[key]);
                    }
                });
            } else {
                advanceFromBilling();
            }
        });
    });

    // Core: check for custom steps at a given position, show them or skip
    function checkCustomSteps(position, onComplete) {
        var formData = new FormData();
        formData.append('option', 'com_j2commerce');
        formData.append('task', 'checkout.getCustomSteps');
        formData.append('position', position);
        formData.append(token, '1');

        fetch(baseUrl, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(json) {
            if (json.hasSteps) {
                var sectionSuffix = position.replace(/_/g, '-');
                var section = document.getElementById('custom-steps-' + sectionSuffix);
                if (section) {
                    section.style.display = '';

                    // Set the heading label dynamically
                    var label = document.getElementById('custom-steps-' + sectionSuffix + '-label');
                    if (label) label.textContent = json.heading || '';

                    var content = getContent(section.id);
                    if (content) {
                        content.innerHTML = json.html
                            + '<div class="mt-3"><button id="button-custom-steps" type="button" class="btn btn-primary btn-checkout-step" data-position="' + position + '">'
                            + '<?php echo Text::_('COM_J2COMMERCE_CHECKOUT_CONTINUE', true); ?></button></div>';
                    }
                    hideAllContents();
                    slideDown(content);
                    removeEditLinks();
                    addEditLink('checkout');
                    addEditLink('billing-address');
                    // Add edit links for earlier steps based on position
                    if (position !== 'after_billing') {
                        addEditLink('custom-steps-after-billing');
                        if (showShipping) addEditLink('shipping-address');
                    }
                    if (position === 'before_payment' || position === 'before_confirm') {
                        addEditLink('custom-steps-after-shipping');
                    }
                    if (position === 'before_confirm') {
                        addEditLink('custom-steps-before-payment');
                        addEditLink('shipping-payment-method');
                    }
                    focusElement('#custom-steps-' + sectionSuffix + ' .checkout-heading');
                    announceToScreenReader(json.heading || '');

                    section._onComplete = onComplete;
                }
            } else {
                onComplete();
            }
        })
        .catch(function(err) {
            console.error('Custom steps error:', err);
            onComplete();
        });
    }

    // Helper: advance from billing to next step
    function advanceFromBilling() {
        // Check for custom steps before proceeding
        checkCustomSteps('after_billing', function() {
            proceedAfterBilling();
        });

        // Refresh the billing display using the same task that loaded it
        var refetchTask = billingTask || 'billingAddress';
        fetchStep(refetchTask, 'billing-address').then(function() {
            initCountryZoneFields(getContent('billing-address'));
        });
    }

    function proceedAfterBilling() {
        var sameAsBilling = document.getElementById('shipping-same-as-billing');
        var skipShippingAddress = sameAsBilling && sameAsBilling.checked;

        if (showShipping && !skipShippingAddress) {
            var shipEl = document.getElementById('shipping-address');
            if (shipEl) shipEl.style.display = '';
            fetchStep('shippingAddress', 'shipping-address').then(function() {
                hideAllContents();
                slideDown(getContent('shipping-address'));
                removeEditLinks();
                addEditLink('checkout');
                addEditLink('billing-address');
                addEditLink('custom-steps-after-billing');
                initCountryZoneFields(getContent('shipping-address'));
                focusElement('#shipping-address .checkout-heading');
                announceToScreenReader('<?php echo Text::_('COM_J2COMMERCE_CHECKOUT_SHIPPING_ADDRESS', true); ?>');
            });
        } else {
            var shipEl = document.getElementById('shipping-address');
            if (shipEl) shipEl.style.display = 'none';
            goToShippingPayment();
        }
    }

    // Button: Guest checkout
    document.addEventListener('click', function(e) {
        if (!e.target.matches('#button-guest')) return;
        var btn = e.target;
        showLoading(btn);

        submitForm('guestValidate', '#billing-address').then(function(json) {
            hideLoading(btn);
            clearErrors(getContent('billing-address'));

            if (json.redirect) {
                window.location.href = json.redirect;
            } else if (json.error) {
                if (json.error.warning) {
                    showWarning(getContent('billing-address'), json.error.warning, json._detail);
                }
                Object.keys(json.error).forEach(function(key) {
                    if (key !== 'warning' && json.error[key]) {
                        showFieldError(getContent('billing-address'), key, json.error[key]);
                    }
                });
            } else {
                advanceFromBilling();
            }
        });
    });

    // Button: Shipping Address
    document.addEventListener('click', function(e) {
        if (!e.target.matches('#button-shipping-address')) return;
        var btn = e.target;
        showLoading(btn);

        submitForm('shippingAddressValidate', '#shipping-address').then(function(json) {
            hideLoading(btn);
            clearErrors(getContent('shipping-address'));

            if (json.redirect) {
                window.location.href = json.redirect;
            } else if (json.error) {
                if (json.error.warning) {
                    showWarning(getContent('shipping-address'), json.error.warning, json._detail);
                }
                Object.keys(json.error).forEach(function(key) {
                    if (key !== 'warning' && json.error[key]) {
                        showFieldError(getContent('shipping-address'), key, json.error[key]);
                    }
                });
            } else {
                advanceFromShipping();
                // Refresh shipping address display
                fetchStep('shippingAddress', 'shipping-address').then(function() {
                    initCountryZoneFields(getContent('shipping-address'));
                });
                var refetchTask2 = billingTask || 'billingAddress';
                fetchStep(refetchTask2, 'billing-address').then(function() {
                    initCountryZoneFields(getContent('billing-address'));
                });
            }
        });
    });

    // Button: Guest Shipping
    document.addEventListener('click', function(e) {
        if (!e.target.matches('#button-guest-shipping')) return;
        var btn = e.target;
        showLoading(btn);

        submitForm('guestShippingValidate', '#shipping-address').then(function(json) {
            hideLoading(btn);
            clearErrors(getContent('shipping-address'));

            if (json.redirect) {
                window.location.href = json.redirect;
            } else if (json.error) {
                if (json.error.warning) {
                    showWarning(getContent('shipping-address'), json.error.warning, json._detail);
                }
                Object.keys(json.error).forEach(function(key) {
                    if (key !== 'warning' && json.error[key]) {
                        showFieldError(getContent('shipping-address'), key, json.error[key]);
                    }
                });
            } else {
                advanceFromShipping();
            }
        });
    });

    // Helper: advance from shipping to shipping/payment (with custom step check)
    function advanceFromShipping() {
        checkCustomSteps('after_shipping', function() {
            goToShippingPayment();
        });
    }

    function goToShippingPayment() {
        checkCustomSteps('before_payment', function() {
            showShippingPayment();
        });
    }

    function showShippingPayment() {
        fetchStep('shippingPaymentMethod', 'shipping-payment-method').then(function() {
            hideAllContents();
            slideDown(getContent('shipping-payment-method'));
            removeEditLinks();
            addEditLink('checkout');
            addEditLink('billing-address');
            addEditLink('custom-steps-after-billing');
            if (showShipping) addEditLink('shipping-address');
            addEditLink('custom-steps-after-shipping');
            addEditLink('custom-steps-before-payment');
            autoSelectFirstShipping();
            focusElement('#shipping-payment-method .checkout-heading');
            announceToScreenReader('<?php echo Text::_('COM_J2COMMERCE_CHECKOUT_SHIPPING_PAYMENT_METHOD', true); ?>');
        });
    }

    // Helper: advance from payment to confirm (with before_confirm custom step check)
    function advanceToConfirm() {
        checkCustomSteps('before_confirm', function() {
            goToConfirm();
        });
    }

    function goToConfirm() {
        fetchStep('confirm', 'confirm').then(function() {
            hideAllContents();
            slideDown(getContent('confirm'));
            removeEditLinks();
            addEditLink('checkout');
            addEditLink('billing-address');
            addEditLink('custom-steps-after-billing');
            if (showShipping) addEditLink('shipping-address');
            addEditLink('custom-steps-after-shipping');
            addEditLink('custom-steps-before-payment');
            addEditLink('shipping-payment-method');
            addEditLink('custom-steps-before-confirm');
            focusElement('#confirm .checkout-heading');
            announceToScreenReader('<?php echo Text::_('COM_J2COMMERCE_CHECKOUT_CONFIRM', true); ?>');
        });
    }

    // Button: Custom Steps Continue
    document.addEventListener('click', function(e) {
        if (!e.target.matches('#button-custom-steps')) return;
        var btn = e.target;
        var position = btn.getAttribute('data-position') || 'after_billing';
        var sectionId = 'custom-steps-' + position.replace(/_/g, '-');
        var section = document.getElementById(sectionId);
        var content = getContent(sectionId);

        showLoading(btn);
        clearErrors(content);

        // Build FormData from the custom step section, adding position
        var fd = new FormData();
        if (content) {
            content.querySelectorAll('input, select, textarea').forEach(function(input) {
                if (!input.name || input.disabled) return;
                if (input.type === 'checkbox' || input.type === 'radio') {
                    if (input.checked) fd.append(input.name, input.value);
                } else if (input.type !== 'file' && input.type !== 'submit') {
                    fd.append(input.name, input.value);
                }
            });
        }
        fd.append('position', position);

        submitForm('saveCustomSteps', fd).then(function(json) {
            hideLoading(btn);

            if (json.error) {
                if (json.error.warning) {
                    showWarning(content, json.error.warning, json._detail);
                }
                Object.keys(json.error).forEach(function(key) {
                    if (key !== 'warning' && json.error[key]) {
                        showFieldError(content, key, json.error[key]);
                    }
                });
            } else {
                refreshSidecart();
                if (section && section._onComplete) {
                    section._onComplete();
                }
            }
        });
    });

    // Button: Payment Method (continue from shipping/payment selection to confirm)
    document.addEventListener('click', function(e) {
        if (!e.target.matches('#button-payment-method')) return;
        var btn = e.target;
        showLoading(btn);

        submitForm('shippingPaymentMethodValidate', '#shipping-payment-method').then(function(json) {
            hideLoading(btn);
            clearErrors(getContent('shipping-payment-method'));

            if (json.redirect) {
                window.location.href = json.redirect;
            } else if (json.error) {
                if (json.error.shipping) {
                    var shippingDiv = document.getElementById('shipping_error_div');
                    if (shippingDiv) {
                        var errSpan = document.createElement('span');
                        errSpan.className = 'j2error';
                        errSpan.textContent = json.error.shipping;
                        shippingDiv.innerHTML = '';
                        shippingDiv.appendChild(errSpan);
                    }
                }
                if (json.error.warning) {
                    showWarning(getContent('shipping-payment-method'), json.error.warning, json._detail);
                }
                Object.keys(json.error).forEach(function(key) {
                    if (key !== 'warning' && key !== 'shipping' && json.error[key]) {
                        showFieldError(getContent('shipping-payment-method'), key, json.error[key]);
                    }
                });
            } else {
                advanceToConfirm();
            }
        });
    });

    // === Payment form submission (event delegation) ===
    // Plugin prepayment templates are loaded via innerHTML which doesn't execute <script> tags.
    // This generic handler catches clicks on any payment submit button in the confirm step.
    function handlePaymentSubmit(form, btn) {
        if (!form || !btn) return;

        var formData = new FormData(form);
        // Ensure correct Joomla task routing (controller.method)
        formData.set('task', 'checkout.confirmPayment');
        // Ensure CSRF token is present
        formData.set(token, '1');

        // Include customer_note from confirm step (textarea may be outside the payment form)
        var noteField = document.getElementById('customer_note');
        if (noteField && !formData.has('customer_note')) {
            formData.set('customer_note', noteField.value);
        }

        showLoading(btn);

        fetch(baseUrl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.text().then(function(text) {
                if (!response.ok) {
                    console.error('Payment HTTP ' + response.status + ':', text.substring(0, 500));
                    return { error: 'Server error (' + response.status + '). Please try again.', _detail: extractErrorDetail(text) };
                }
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Payment JSON parse error:', text.substring(0, 500));
                    if (text.indexOf('<!DOCTYPE') !== -1 || text.indexOf('<html') !== -1) {
                        return { error: 'Your session has expired. Please reload the page and try again.' };
                    }
                    return { error: 'Unexpected server response. Please reload the page and try again.' };
                }
            });
        })
        .then(function(json) {
            hideLoading(btn);

            // Clear previous error/warning messages
            form.querySelectorAll('.j2error, .j2success, .j2warning, .warning, .alert-danger, .alert-success').forEach(function(el) { el.remove(); });

            if (json.error) {
                var msg = typeof json.error === 'string' ? json.error : Object.values(json.error).join(', ');
                showWarning(form, msg, json._detail);
                btn.disabled = false;
            }

            if (json.redirect) {
                window.location.href = json.redirect;
            }

            if (json.success && !json.redirect) {
                var successDiv = document.createElement('div');
                successDiv.className = 'j2success alert alert-success';
                successDiv.textContent = json.success;
                form.prepend(successDiv);
            }
        })
        .catch(function(err) {
            console.error('Payment network error:', err);
            hideLoading(btn);
            btn.disabled = false;
            showWarning(form, 'Unable to connect to the server. Please check your connection and try again.');
        });
    }

    // Catch button clicks in confirm step payment forms
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('#confirm .checkout-content form button');
        if (!btn) return;
        // Skip alert dismiss buttons (close buttons on error/warning alerts)
        if (btn.classList.contains('btn-close') || btn.hasAttribute('data-bs-dismiss')) return;
        var form = btn.closest('form');
        if (!form || !form.querySelector('input[name="orderpayment_type"]')) return;
        e.preventDefault();
        handlePaymentSubmit(form, btn);
    });

    // Catch form submit events (for type="submit" buttons)
    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (!form.closest('#confirm .checkout-content')) return;

        // Sync customer_note textarea to any hidden sync field in this form (e.g., free order form)
        var noteField = document.getElementById('customer_note');
        var syncField = form.querySelector('.j2commerce-customer-note-sync');
        if (noteField && syncField) {
            syncField.value = noteField.value;
        }

        if (!form.querySelector('input[name="orderpayment_type"]')) return;
        e.preventDefault();
        var btn = form.querySelector('button[type="submit"]') || form.querySelector('button:not(.btn-close):not([data-bs-dismiss])');
        handlePaymentSubmit(form, btn);
    });

    // === SHIPPING RADIO CHANGE: save selection to session + refresh sidecart ===
    document.addEventListener('change', function(e) {
        var radio = e.target;
        if (radio.type !== 'radio' || radio.name !== 'shipping_plugin') return;
        var rateDiv = radio.closest('.list-group-item');
        if (!rateDiv) return;

        var formData = new FormData();
        formData.append('option', 'com_j2commerce');
        formData.append('task', 'checkout.saveShippingSelection');
        formData.append(token, '1');
        formData.append('shipping_plugin', radio.value);

        // Collect the hidden field values from the selected rate's container
        var hiddenFields = rateDiv.querySelectorAll('input[type="hidden"]');
        hiddenFields.forEach(function(input) {
            if (input.name) formData.append(input.name, input.value);
        });

        fetch(baseUrl, { method: 'POST', body: formData, headers: {'X-Requested-With': 'XMLHttpRequest'} })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) refreshSidecart();
            })
            .catch(function(e) { console.error('Shipping selection error:', e); });
    });

    // === SIDECART REFRESH ===
    function refreshSidecart() {
        var formData = new FormData();
        formData.append('option', 'com_j2commerce');
        formData.append('task', 'checkout.refreshSidecart');
        formData.append(token, '1');

        fetch(baseUrl, { method: 'POST', body: formData, headers: {'X-Requested-With': 'XMLHttpRequest'} })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.html) {
                    var container = document.getElementById('checkout-sidecart-content');
                    if (container) container.innerHTML = data.html;
                    updateMobileTotal();
                }
            })
            .catch(function(e) { console.error('Sidecart refresh error:', e); });
    }

    function updateMobileTotal() {
        var grandTotal = document.querySelector('#checkout-sidecart-content .j2commerce-sidecart-grandtotal');
        var toggleTotal = document.querySelector('.j2commerce-sidecart-toggle-total');
        if (grandTotal && toggleTotal) {
            toggleTotal.textContent = grandTotal.textContent;
        }
    }

    // Auto-select first shipping radio and save to session, then refresh sidecart
    function autoSelectFirstShipping() {
        // Don't override server-side pre-selection from session
        var alreadyChecked = document.querySelector(
            '#shipping-payment-method .checkout-content input[type="radio"][name="shipping_plugin"]:checked'
        );
        if (alreadyChecked) return;

        var radio = document.querySelector('#shipping-payment-method .checkout-content input[type="radio"][name="shipping_plugin"]');
        if (!radio) return;

        // Ensure it's checked
        radio.checked = true;

        // Find its container and collect hidden fields
        var rateDiv = radio.closest('.list-group-item');
        if (!rateDiv) return;

        var formData = new FormData();
        formData.append('option', 'com_j2commerce');
        formData.append('task', 'checkout.saveShippingSelection');
        formData.append(token, '1');
        formData.append('shipping_plugin', radio.value);

        rateDiv.querySelectorAll('input[type="hidden"]').forEach(function(input) {
            if (input.name) formData.append(input.name, input.value);
        });

        fetch(baseUrl, { method: 'POST', body: formData, headers: {'X-Requested-With': 'XMLHttpRequest'} })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) refreshSidecart();
            })
            .catch(function(e) { console.error('Auto shipping select error:', e); });
    }

    // Toggle text show/hide for mobile sidecart
    document.addEventListener('shown.bs.collapse', function(e) {
        if (e.target.id === 'checkoutSidecartCollapse') {
            var txt = document.querySelector('.j2commerce-sidecart-toggle-text');
            if (txt) txt.textContent = '<?php echo Text::_('COM_J2COMMERCE_HIDE_ORDER_SUMMARY', true); ?>';
            var chevron = document.querySelector('.j2commerce-sidecart-chevron');
            if (chevron) chevron.style.transform = 'rotate(180deg)';
        }
    });
    document.addEventListener('hidden.bs.collapse', function(e) {
        if (e.target.id === 'checkoutSidecartCollapse') {
            var txt = document.querySelector('.j2commerce-sidecart-toggle-text');
            if (txt) txt.textContent = '<?php echo Text::_('COM_J2COMMERCE_SHOW_ORDER_SUMMARY', true); ?>';
            var chevron = document.querySelector('.j2commerce-sidecart-chevron');
            if (chevron) chevron.style.transform = '';
        }
    });

    // === SIDECART REFRESH: Generic event for plugins to trigger sidecart updates ===
    document.addEventListener('j2commerce:checkout:refreshSidecart', function() { refreshSidecart(); });

    // === SIDECART COUPON/VOUCHER: Listen for events from coupon-voucher.js ===
    document.addEventListener('j2commerce:coupon:applied', function() { refreshSidecart(); });
    document.addEventListener('j2commerce:coupon:removed', function() { refreshSidecart(); });
    document.addEventListener('j2commerce:voucher:applied', function() { refreshSidecart(); });
    document.addEventListener('j2commerce:voucher:removed', function() { refreshSidecart(); });

    // === INITIALIZATION: Load first step based on login status ===

    <?php
    // Case 1: Guest-only checkout (no login form, no registration)
    if (!$this->logged && $this->params->get('allow_guest_checkout') && !$this->params->get('show_login_form', 1) && !$this->params->get('allow_registration', 1)) : ?>

    // Guest only - skip checkout options, go straight to guest billing
    (function() {
        var billingHeading = document.querySelector('#billing-address .checkout-heading span');
        if (billingHeading) billingHeading.textContent = '<?php echo Text::_('COM_J2COMMERCE_CHECKOUT_BILLING_ADDRESS', true); ?>';
        document.getElementById('checkout').style.display = 'none';
        fetchStep('guest', 'billing-address').then(function() {
            slideDown(getContent('billing-address'));
            initCountryZoneFields(getContent('billing-address'));
        });
    })();

    <?php
    // Case 2: Not logged in - show login/register/guest options
    elseif (!$this->logged) : ?>

    fetchStep('login', 'checkout').then(function() {
        slideDown(getContent('checkout'));
    });

    <?php
    // Case 3: Logged in - skip to billing address
    else : ?>

    fetchStep('billingAddress', 'billing-address').then(function() {
        slideDown(getContent('billing-address'));
        initCountryZoneFields(getContent('billing-address'));
    });

    <?php endif; ?>

});
</script>
