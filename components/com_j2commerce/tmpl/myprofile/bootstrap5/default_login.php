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
$prefillToken  = trim($input->getString('order_token', '')) ?: (string) ($this->guestFormData['order_token'] ?? '');
$prefillEmail  = trim($input->getString('order_email', '')) ?: (string) ($this->guestFormData['email'] ?? '');
$captcha       = $this->loginCaptcha;
?>

<div class="j2commerce j2commerce-myprofile-login">
    <div class="row g-4">
        <?php if ($params->get('show_login_form', 1)): ?>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h4 class="mb-0"><?php echo Text::_('JLOGIN'); ?></h4></div>
                <div class="card-body">
                    <form action="<?php echo Route::_('index.php'); ?>" method="post" id="j2commerceLoginForm">
                        <div class="mb-3">
                            <label for="j2c-username" class="form-label"><?php echo Text::_('COM_J2COMMERCE_USERNAME'); ?></label>
                            <input type="text" name="username" id="j2c-username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="j2c-password" class="form-label"><?php echo Text::_('COM_J2COMMERCE_PASSWORD'); ?></label>
                            <input type="password" name="password" id="j2c-password" class="form-control" required>
                        </div>
                        <?php if (PluginHelper::isEnabled('system', 'remember')): ?>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="remember" id="j2c-remember" class="form-check-input" value="yes">
                            <label class="form-check-label" for="j2c-remember"><?php echo Text::_('JGLOBAL_REMEMBER_ME'); ?></label>
                        </div>
                        <?php endif; ?>
                        <?php if ($captcha): ?>
                        <div class="mb-3" role="group" aria-labelledby="j2c-login-captcha-lbl">
                            <div id="j2c-login-captcha-lbl" class="form-label"><?php echo Text::_('COM_J2COMMERCE_MYPROFILE_CAPTCHA_LABEL'); ?></div>
                            <?php echo $captcha->display('captcha', 'j2c-login-captcha'); ?>
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary"><?php echo Text::_('JLOGIN'); ?></button>
                        <?php if ($captcha): ?>
                        <input type="hidden" name="option" value="com_j2commerce">
                        <input type="hidden" name="task" value="myprofile.login">
                        <?php else: ?>
                        <input type="hidden" name="option" value="com_users">
                        <input type="hidden" name="task" value="user.login">
                        <?php endif; ?>
                        <input type="hidden" name="return" value="<?php echo $returnUrl; ?>">
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>
                    <div class="mt-3">
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

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h4 class="mb-0"><?php echo Text::_('COM_J2COMMERCE_ORDER_GUEST_VIEW'); ?></h4></div>
                <div class="card-body">
                    <p class="text-body-secondary"><?php echo Text::_('COM_J2COMMERCE_ORDER_GUEST_VIEW_DESC'); ?></p>
                    <form action="<?php echo Route::_('index.php?option=com_j2commerce&task=myprofile.guestEntry'); ?>" method="post" id="j2commerceGuestForm">
                        <div class="mb-3">
                            <label for="j2c-guest-email" class="form-label"><?php echo Text::_('COM_J2COMMERCE_ORDER_EMAIL'); ?></label>
                            <input type="email" name="email" id="j2c-guest-email" class="form-control" value="<?php echo $this->escape($prefillEmail); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="j2c-guest-token" class="form-label"><?php echo Text::_('COM_J2COMMERCE_ORDER_TOKEN'); ?></label>
                            <input type="text" name="order_token" id="j2c-guest-token" class="form-control" value="<?php echo $this->escape($prefillToken); ?>" required>
                            <div class="form-text"><?php echo Text::_('COM_J2COMMERCE_ORDER_TOKEN_HINT'); ?></div>
                        </div>
                        <?php if ($captcha): ?>
                        <div class="mb-3" role="group" aria-labelledby="j2c-guest-captcha-lbl">
                            <div id="j2c-guest-captcha-lbl" class="form-label"><?php echo Text::_('COM_J2COMMERCE_MYPROFILE_CAPTCHA_LABEL'); ?></div>
                            <?php echo $captcha->display('captcha', 'j2c-guest-captcha'); ?>
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary"><?php echo Text::_('COM_J2COMMERCE_VIEW'); ?></button>
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
