<?php
/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.PaymentMoneyorder
 *
 * @copyright   Copyright (C) 2024-2026 J2Commerce, LLC. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var array $displayData */
$vars = $displayData['vars'];
?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=checkout'); ?>"
      method="post"
      name="moneyorder_form"
      id="moneyorder_form"
      enctype="multipart/form-data">

    <div class="j2commerce-payment-moneyorder note note-<?php echo htmlspecialchars($vars->orderpayment_type, ENT_QUOTES, 'UTF-8'); ?>">

        <?php if (!empty($vars->display_image)): ?>
            <span class="j2commerce-payment-image">
                <img class="payment-plugin-image payment_moneyorder"
                     src="<?php echo Uri::root() . htmlspecialchars($vars->display_image, ENT_QUOTES, 'UTF-8'); ?>"
                     alt="<?php echo htmlspecialchars($vars->display_name, ENT_QUOTES, 'UTF-8'); ?>" />
            </span>
        <?php endif; ?>

        <p class="j2commerce-payment-display-name">
            <strong><?php echo htmlspecialchars(Text::_($vars->display_name), ENT_QUOTES, 'UTF-8'); ?></strong>
        </p>

        <?php if (!empty($vars->onbeforepayment_text)): ?>
            <div class="j2commerce-on-before-payment-text">
                <?php echo J2htmlHelper::merchantText($vars->onbeforepayment_text); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($vars->moneyorder_information)): ?>
            <div class="j2commerce-moneyorder-information alert alert-info">
                <h4><?php echo Text::_('PLG_J2COMMERCE_PAYMENT_MONEYORDER_INSTRUCTIONS'); ?></h4>
                <?php echo J2htmlHelper::merchantText($vars->moneyorder_information); ?>
            </div>
        <?php endif; ?>

    </div>

    <button type="button"
            id="moneyorder-submit-button"
            class="j2commerce-cart-button button btn btn-primary">
        <?php echo htmlspecialchars(Text::_($vars->button_text), ENT_QUOTES, 'UTF-8'); ?>
    </button>

    <input type="hidden" name="order_id" value="<?php echo (int) $vars->order_id; ?>" />
    <input type="hidden" name="orderpayment_type" value="<?php echo htmlspecialchars($vars->orderpayment_type, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="hash" value="<?php echo htmlspecialchars($vars->hash, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="option" value="com_j2commerce" />
    <input type="hidden" name="view" value="checkout" />
    <input type="hidden" name="task" value="confirmPayment" />
    <input type="hidden" name="paction" value="process" />

    <div class="plugin_error_div">
        <span class="plugin_error"></span>
        <span class="plugin_error_instruction"></span>
    </div>

    <?php echo HTMLHelper::_('form.token'); ?>
</form>
