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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Site\View\Checkout\HtmlView $this */

$account = $this->account ?? 'register';
$allowRegistration = (int) $this->params->get('allow_registration', 1);
$allowGuest = (int) $this->params->get('allow_guest_checkout', 0);
$showLogin = (int) $this->params->get('show_login_form', 1);
?>
<div class="row">
    <?php if ($allowRegistration || $allowGuest) : ?>
    <div class="col-md-6 mb-3">
        <h4><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_NEW_CUSTOMER'); ?></h4>
        <p><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_OPTIONS'); ?></p>

        <?php if ($allowRegistration) : ?>
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="account" value="register" id="register"
                <?php echo $account === 'register' ? 'checked' : ''; ?>>
            <label class="form-check-label fw-bold" for="register">
                <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_REGISTER'); ?>
            </label>
        </div>
        <?php endif; ?>

        <?php if ($allowGuest) : ?>
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="account" value="guest" id="guest"
                <?php echo $account === 'guest' ? 'checked' : ''; ?>>
            <label class="form-check-label fw-bold" for="guest">
                <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_GUEST'); ?>
            </label>
        </div>
        <?php endif; ?>

        <?php if ($allowRegistration) : ?>
        <p class="text-muted mt-2">
            <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_REGISTER_ACCOUNT_HELP_TEXT'); ?>
        </p>
        <?php endif; ?>

        <button type="button" id="button-account" class="btn btn-primary mt-2">
            <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_CONTINUE'); ?>
        </button>
    </div>
    <?php endif; ?>

    <?php if ($showLogin) : ?>
    <div id="login" class="col-md-6 mb-3">
        <h4><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_RETURNING_CUSTOMER'); ?></h4>

        <div class="mb-3">
            <label class="form-label fw-bold"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_USERNAME'); ?></label>
            <input type="text" name="email" value="" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_PASSWORD'); ?></label>
            <input type="password" name="password" value="" class="form-control">
        </div>

        <input type="hidden" name="task" value="checkout.loginValidate">
        <input type="hidden" name="option" value="com_j2commerce">

        <button type="button" id="button-login" class="btn btn-primary">
            <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_LOGIN'); ?>
        </button>

        <div class="mt-2">
            <a href="<?php echo Route::_('index.php?option=com_users&view=reset'); ?>" target="_blank">
                <?php echo Text::_('COM_J2COMMERCE_FORGOT_YOUR_PASSWORD'); ?>
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<input type="hidden" name="option" value="com_j2commerce">
<input type="hidden" name="view" value="checkout">
