<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_payment_paypal
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\PaymentPaypal\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

/**
 * Renders the sandbox subscription test panel — four buttons that call the
 * plugin's onAjaxHandler tasks and stream results into a preformatted block
 * underneath. Visible only when sandbox:1.
 *
 * @since  6.1.7
 */
final class PaypaltestbuttonsField extends FormField
{
    protected $type = 'Paypaltestbuttons';

    protected function getInput(): string
    {
        $token = Session::getFormToken();

        // Field renders inside admin plugin-edit form — must hit /administrator/index.php
        // so the admin session + ACL apply. Uri::root() gives the site root which routes
        // to a different com_ajax handler (frontend session, no admin permissions).
        $baseUrl = Uri::base() . 'index.php?option=com_ajax&group=j2commerce&plugin=payment_paypal&format=json';

        $labels = [
            'creds'   => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_SANDBOX_TEST_CREDS'),
            'create'  => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_SANDBOX_TEST_CREATE'),
            'check'   => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_SANDBOX_TEST_CHECK'),
            'cancel'  => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_SANDBOX_TEST_CANCEL'),
            'nvp'     => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_SANDBOX_TEST_NVP'),
            'subId'   => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_SANDBOX_TEST_SUBID_LABEL'),
            'subHelp' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_SANDBOX_TEST_SUBID_HELP'),
        ];

        $html  = '<div class="paypal-sandbox-test-panel">';
        $html .= '<div class="d-flex flex-wrap gap-2 mb-3">';
        $html .= '<button type="button" class="btn btn-outline-primary" data-paypal-test="testCredentials">'
            . htmlspecialchars($labels['creds'], ENT_QUOTES, 'UTF-8') . '</button>';
        $html .= '<button type="button" class="btn btn-outline-success" data-paypal-test="testCreateSubscription">'
            . htmlspecialchars($labels['create'], ENT_QUOTES, 'UTF-8') . '</button>';
        $html .= '<button type="button" class="btn btn-outline-info" data-paypal-test="testCheckStatus">'
            . htmlspecialchars($labels['check'], ENT_QUOTES, 'UTF-8') . '</button>';
        $html .= '<button type="button" class="btn btn-outline-danger" data-paypal-test="testCancel">'
            . htmlspecialchars($labels['cancel'], ENT_QUOTES, 'UTF-8') . '</button>';
        $html .= '<button type="button" class="btn btn-outline-warning" data-paypal-test="testNvpCredentials">'
            . htmlspecialchars($labels['nvp'], ENT_QUOTES, 'UTF-8') . '</button>';
        $html .= '</div>';
        $html .= '<div class="mb-2">';
        $html .= '<label for="paypal-test-sub-id" class="form-label">'
            . htmlspecialchars($labels['subId'], ENT_QUOTES, 'UTF-8') . '</label>';
        $html .= '<input type="text" id="paypal-test-sub-id" class="form-control"'
            . ' placeholder="I-XXXXXXXXXXXX">';
        $html .= '<div class="form-text">'
            . htmlspecialchars($labels['subHelp'], ENT_QUOTES, 'UTF-8') . '</div>';
        $html .= '</div>';
        $html .= '<pre id="paypal-test-output" class="bg-body-tertiary p-3 rounded mt-2" style="max-height:400px; overflow:auto; min-height:60px;"></pre>';
        $html .= '</div>';

        $baseUrlJs = json_encode($baseUrl);
        $tokenJs   = json_encode($token);

        $script = <<<JS
(function(){
    var panel = document.querySelector('.paypal-sandbox-test-panel');
    if (!panel) { return; }
    var output = panel.querySelector('#paypal-test-output');
    var subInput = panel.querySelector('#paypal-test-sub-id');
    var baseUrl = {$baseUrlJs};
    var token   = {$tokenJs};

    function show(label, payload) {
        var ts = new Date().toISOString().replace('T',' ').slice(0,19);
        var pre = '[' + ts + '] ' + label + '\\n' + JSON.stringify(payload, null, 2) + '\\n\\n';
        output.textContent = pre + (output.textContent || '');
        if (payload && payload.paypal_subscription_id && !subInput.value) {
            subInput.value = payload.paypal_subscription_id;
        }
        if (payload && payload.approve_url) {
            var link = document.createElement('a');
            link.href = payload.approve_url;
            link.target = '_blank';
            link.rel = 'noopener';
            link.className = 'btn btn-warning btn-sm mt-2';
            link.textContent = 'Open PayPal approval URL ↗';
            output.parentNode.insertBefore(link, output);
        }
    }

    panel.addEventListener('click', function(e){
        var btn = e.target.closest('[data-paypal-test]');
        if (!btn) return;
        e.preventDefault();
        var task = btn.getAttribute('data-paypal-test');
        var url = baseUrl + '&task=' + encodeURIComponent(task) + '&' + encodeURIComponent(token) + '=1';
        if (task === 'testCheckStatus' || task === 'testCancel') {
            var v = (subInput.value || '').trim();
            if (!v) { show(task, {ok:false, error:'Fill PayPal Subscription ID first'}); return; }
            url += '&paypal_subscription_id=' + encodeURIComponent(v);
        }
        btn.disabled = true;
        fetch(url, {credentials:'same-origin', headers:{'Accept':'application/json'}})
            .then(function(r){ return r.json(); })
            .then(function(json){
                // com_ajax wraps plugin output in {success, message, data}.
                // For our handler, data is a single object; for some Joomla versions it's [object].
                var payload;
                if (json && Object.prototype.hasOwnProperty.call(json, 'data')) {
                    payload = Array.isArray(json.data) ? (json.data[0] || json.data) : json.data;
                } else {
                    payload = json;
                }
                show(task, payload);
            })
            .catch(function(err){ show(task, {ok:false, error:String(err)}); })
            .finally(function(){ btn.disabled = false; });
    });
})();
JS;

        $html .= '<script>' . $script . '</script>';

        return $html;
    }
}
