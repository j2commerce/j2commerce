<?php
/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.PaymentPaypal
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/** @var array $displayData */
$vars = $displayData['vars'];

$sandbox = $vars->sandbox ?? false;
$clientId = $vars->client_id ?? '';
?>

<div class="j2commerce-payment-paypal note note-<?php echo htmlspecialchars($vars->orderpayment_type, ENT_QUOTES, 'UTF-8'); ?>">

    <?php if (!empty($vars->display_image)): ?>
        <span class="j2commerce-payment-image">
            <img class="payment-plugin-image payment_paypal" src="<?php echo Uri::root() . htmlspecialchars($vars->display_image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($vars->display_name, ENT_QUOTES, 'UTF-8'); ?>" />
        </span>
    <?php endif; ?>

    <p class="j2commerce-payment-display-name">
        <strong><?php echo htmlspecialchars(Text::_($vars->display_name), ENT_QUOTES, 'UTF-8'); ?></strong>
    </p>

    <?php if (!empty($vars->onbeforepayment_text)): ?>
        <p class="j2commerce-on-before-payment-text">
            <?php echo htmlspecialchars(Text::_($vars->onbeforepayment_text), ENT_QUOTES, 'UTF-8'); ?>
        </p>
    <?php endif; ?>

</div>

<!-- PayPal Button Container -->
<div id="paypal-button-container"
     data-order-id="<?php echo htmlspecialchars($vars->order_id, ENT_QUOTES, 'UTF-8'); ?>"
     data-create-order-url="<?php echo htmlspecialchars($vars->create_order_url, ENT_QUOTES, 'UTF-8'); ?>"
     data-capture-order-url="<?php echo htmlspecialchars($vars->capture_order_url, ENT_QUOTES, 'UTF-8'); ?>"
     data-csrf-token="<?php echo htmlspecialchars($vars->csrf_token, ENT_QUOTES, 'UTF-8'); ?>"
     data-currency="<?php echo htmlspecialchars($vars->currency_code, ENT_QUOTES, 'UTF-8'); ?>"
     data-amount="<?php echo number_format($vars->orderpayment_amount, 2, '.', ''); ?>"
     data-sandbox="<?php echo $sandbox ? 'true' : 'false'; ?>"
     data-client-id="<?php echo htmlspecialchars($clientId, ENT_QUOTES, 'UTF-8'); ?>"
     data-is-subscription="<?php echo !empty($vars->is_subscription) ? 'true' : 'false'; ?>"
     data-subscription-mode="<?php echo htmlspecialchars($vars->subscription_mode ?? 'rest', ENT_QUOTES, 'UTF-8'); ?>"
     data-debug="<?php echo ($vars->debug ?? 0) ? 'true' : 'false'; ?>"
></div>

<div id="paypal-error-message" class="alert alert-danger d-none" role="alert"></div>
<div id="paypal-processing-message" class="alert alert-info d-none" role="alert">
    <?php echo Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PROCESSING'); ?>
</div>
