<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_payment_banktransfer
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var array $displayData */
$vars = $displayData['vars'];
?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=checkout'); ?>"
      method="post"
      name="payment_form"
      id="payment_form"
      enctype="multipart/form-data">

    <div class="j2commerce-payment-banktransfer note note-<?php echo htmlspecialchars($vars->orderpayment_type, ENT_QUOTES, 'UTF-8'); ?>">

        <p class="j2commerce-payment-display-name">
            <strong><?php echo htmlspecialchars(Text::_($vars->display_name), ENT_QUOTES, 'UTF-8'); ?></strong>
        </p>

        <?php if (!empty($vars->bank_information)): ?>
            <div class="alert alert-info mb-3">
                <?php echo nl2br(htmlspecialchars($vars->bank_information, ENT_COMPAT, 'UTF-8')); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($vars->onbeforepayment_text)): ?>
            <p class="j2commerce-on-before-payment-text">
                <?php echo htmlspecialchars($vars->onbeforepayment_text, ENT_QUOTES, 'UTF-8'); ?>
            </p>
        <?php endif; ?>

    </div>

    <button type="button"
            id="banktransfer-submit-button"
            class="j2commerce-cart-button button btn btn-primary">
        <?php echo htmlspecialchars(Text::_($vars->button_text), ENT_QUOTES, 'UTF-8'); ?>
    </button>

    <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($vars->order_id, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="orderpayment_type" value="<?php echo htmlspecialchars($vars->orderpayment_type, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="orderpayment_id" value="<?php echo (int) $vars->orderpayment_id; ?>" />
    <input type="hidden" name="hash" value="<?php echo htmlspecialchars($vars->hash ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
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