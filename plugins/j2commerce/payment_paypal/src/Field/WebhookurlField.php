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

use J2Commerce\Plugin\J2Commerce\PaymentPaypal\Service\PayPalWebhooks;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/** Shows the endpoint to register at PayPal, since the Webhook ID is only issued once one exists. */
class WebhookurlField extends FormField
{
    protected $type = 'Webhookurl';

    protected function getInput(): string
    {
        $siteUrl = rtrim(Uri::root(), '/');
        $url     = $siteUrl . '/index.php?option=com_ajax&group=j2commerce&plugin=payment_paypal&format=raw&task=webhook';

        $id = htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8');

        // The core renderfield layout prints the description as {id}-desc; a custom getInput() has to link it itself.
        $describedBy = $this->description ? ' aria-describedby="' . $id . '-desc"' : '';

        $input = '<input type="text" class="form-control font-monospace" id="' . $id
            . '" value="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $describedBy . ' readonly>';

        $input .= '<div class="form-text">'
            . '<p class="mb-1" id="' . $id . '-events-label">'
            . htmlspecialchars(Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_WEBHOOK_EVENTS'), ENT_QUOTES, 'UTF-8')
            . '</p><ul class="list-unstyled font-monospace mb-0" aria-labelledby="' . $id . '-events-label">';

        foreach (PayPalWebhooks::HANDLED_EVENTS as $eventType) {
            $input .= '<li>' . htmlspecialchars($eventType, ENT_QUOTES, 'UTF-8') . '</li>';
        }

        $input .= '</ul></div>';

        if (preg_match('/\b(localhost|127\.0\.0\.1|::1|\.local|\.test)\b/i', $siteUrl)) {
            $input .= '<div class="alert alert-warning mt-2 mb-0">'
                . '<span class="icon-warning" aria-hidden="true"></span> '
                . Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_WEBHOOK_URL_LOCAL')
                . '</div>';
        }

        return $input;
    }
}
