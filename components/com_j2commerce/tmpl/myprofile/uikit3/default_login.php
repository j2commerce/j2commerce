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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;

/** @var \J2Commerce\Component\J2commerce\Site\View\Myprofile\HtmlView $this */

$params    = $this->params;
$returnUrl = base64_encode(Uri::getInstance()->toString());

$input         = Factory::getApplication()->getInput();
$prefillToken  = trim($input->getString('order_token', ''));
$prefillEmail  = trim($input->getString('order_email', ''));
?>

<div class="j2commerce j2commerce-myprofile-login">
    <div class="uk-grid uk-grid-medium" uk-grid>
        <?php if ($params->get('show_login_form', 1)): ?>
        <div class="uk-width-1-2@l">
            <div class="uk-card uk-card-default">
                <div class="uk-card-header"><h4 class="uk-card-title uk-margin-remove"><?php echo Text::_('JLOGIN'); ?></h4></div>
                <div class="uk-card-body">
                    <form action="<?php echo Route::_('index.php'); ?>" method="post" id="j2commerceLoginForm">
                        <div class="uk-margin-bottom">
                            <label for="j2c-username" class="uk-form-label"><?php echo Text::_('COM_J2COMMERCE_USERNAME'); ?></label>
                            <input type="text" name="username" id="j2c-username" class="uk-input" required>
                        </div>
                        <div class="uk-margin-bottom">
                            <label for="j2c-password" class="uk-form-label"><?php echo Text::_('COM_J2COMMERCE_PASSWORD'); ?></label>
                            <input type="password" name="password" id="j2c-password" class="uk-input" required>
                        </div>
                        <?php if (PluginHelper::isEnabled('system', 'remember')): ?>
                        <div class="uk-margin-bottom">
                            <label class="uk-flex uk-flex-middle">
                                <input type="checkbox" name="remember" id="j2c-remember" class="uk-checkbox uk-margin-small-right" value="yes">
                                <span for="j2c-remember"><?php echo Text::_('JGLOBAL_REMEMBER_ME'); ?></span>
                            </label>
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="uk-button uk-button-primary"><?php echo Text::_('JLOGIN'); ?></button>
                        <input type="hidden" name="option" value="com_users">
                        <input type="hidden" name="task" value="user.login">
                        <input type="hidden" name="return" value="<?php echo $returnUrl; ?>">
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>
                    <div class="uk-margin-top">
                        <a href="<?php echo Route::_('index.php?option=com_users&view=reset'); ?>"><?php echo Text::_('COM_J2COMMERCE_FORGOT_PASSWORD'); ?></a>
                        <br>
                        <a href="<?php echo Route::_('index.php?option=com_users&view=remind'); ?>"><?php echo Text::_('COM_J2COMMERCE_FORGOT_USERNAME'); ?></a>
                        <?php if (ComponentHelper::getParams('com_users')->get('allowUserRegistration')): ?>
                        <br>
                        <a href="<?php echo Route::_('index.php?option=com_users&view=registration'); ?>"><?php echo Text::_('COM_J2COMMERCE_REGISTER'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="uk-width-1-2@l">
            <div class="uk-card uk-card-default">
                <div class="uk-card-header"><h4 class="uk-card-title uk-margin-remove"><?php echo Text::_('COM_J2COMMERCE_ORDER_GUEST_VIEW'); ?></h4></div>
                <div class="uk-card-body">
                    <p class="uk-text-meta"><?php echo Text::_('COM_J2COMMERCE_ORDER_GUEST_VIEW_DESC'); ?></p>
                    <form action="<?php echo Route::_('index.php?option=com_j2commerce&task=myprofile.guestEntry'); ?>" method="post" id="j2commerceGuestForm">
                        <div class="uk-margin-bottom">
                            <label for="j2c-guest-email" class="uk-form-label"><?php echo Text::_('COM_J2COMMERCE_ORDER_EMAIL'); ?></label>
                            <input type="email" name="email" id="j2c-guest-email" class="uk-input" value="<?php echo $this->escape($prefillEmail); ?>" required>
                        </div>
                        <div class="uk-margin-bottom">
                            <label for="j2c-guest-token" class="uk-form-label"><?php echo Text::_('COM_J2COMMERCE_ORDER_TOKEN'); ?></label>
                            <input type="text" name="order_token" id="j2c-guest-token" class="uk-input" value="<?php echo $this->escape($prefillToken); ?>" required>
                            <div class="uk-text-meta uk-text-small"><?php echo Text::_('COM_J2COMMERCE_ORDER_TOKEN_HINT'); ?></div>
                        </div>
                        <button type="submit" class="uk-button uk-button-primary"><?php echo Text::_('COM_J2COMMERCE_VIEW'); ?></button>
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
