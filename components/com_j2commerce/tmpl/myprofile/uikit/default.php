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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Site\View\Myprofile\HtmlView $this */

$params = $this->params;
$user   = $this->user;
?>
<div class="j2commerce">
    <?php if ($this->params->get('show_page_heading')) : ?>
    <div class="page-header">
        <h1><?php echo $this->escape($this->params->get('page_heading')); ?></h1>
    </div>
    <?php endif; ?>

    <?php if ($params->get('show_logout_myprofile', 0) && $user->id > 0): ?>
    <div class="uk-flex uk-flex-right uk-margin-bottom">
        <form action="<?php echo Route::_('index.php'); ?>" method="post">
            <button type="submit" class="uk-button uk-button-danger uk-button-small"><?php echo Text::_('JLOGOUT'); ?></button>
            <input type="hidden" name="option" value="com_users">
            <input type="hidden" name="task" value="user.logout">
            <input type="hidden" name="return" value="<?php echo base64_encode(Route::_('index.php?option=com_j2commerce&view=myprofile', false)); ?>">
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    </div>
    <?php endif; ?>

    <div class="j2commerce-myprofile">

        <?php if (!empty($this->topMessagesHtml)): ?>
        <div class="j2commerce-myprofile-messages uk-margin-bottom"><?php echo $this->topMessagesHtml; ?></div>
        <?php endif; ?>

        <ul class="uk-tab uk-flex-center" id="j2commerceProfileTabs" uk-tab>
            <li>
                <a href="#">
                    <span uk-icon="icon: file-text" class="uk-margin-small-right" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCE_MYPROFILE_ORDERS'); ?>
                </a>
            </li>

            <?php if ($params->get('download_area', 1)): ?>
            <li>
                <a href="#">
                    <span uk-icon="icon: download" class="uk-margin-small-right" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCE_MYPROFILE_DOWNLOADS'); ?>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($user->id > 0): ?>
            <li>
                <a href="#">
                    <span uk-icon="icon: location" class="uk-margin-small-right" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCE_MYPROFILE_ADDRESSES'); ?>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($this->useUnifiedPaymentTab) : ?>
            <li>
                <a href="#">
                    <span uk-icon="icon: credit-card" class="uk-margin-small-right" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHODS_TITLE'); ?>
                </a>
            </li>
            <?php endif; ?>

            <?php echo $this->pluginTabHtml; ?>
        </ul>

        <ul class="uk-switcher uk-margin-top" id="j2commerceProfileTabContent">
            <li>
                <?php echo $this->loadTemplate('orders'); ?>
            </li>

            <?php if ($params->get('download_area', 1)): ?>
            <li>
                <?php echo $this->loadTemplate('downloads'); ?>
            </li>
            <?php endif; ?>

            <?php if ($user->id > 0): ?>
            <li>
                <?php echo $this->loadTemplate('addresses'); ?>
            </li>
            <?php endif; ?>

            <?php if ($this->useUnifiedPaymentTab) : ?>
            <li>
                <?php echo $this->loadTemplate('payment_methods'); ?>
            </li>
            <?php endif; ?>

            <?php echo $this->pluginContentHtml; ?>
        </ul>
    </div>
</div>

<!-- Order Print Modal -->
<div id="j2commerceOrderModal" uk-modal>
    <div class="uk-modal-dialog uk-modal-body">
        <div class="uk-modal-header">
            <h2 class="uk-modal-title" id="j2commerceOrderModalLabel"><?php echo Text::_('COM_J2COMMERCE_ORDER_PRINT'); ?></h2>
            <button class="uk-modal-close-default" type="button" uk-close aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
        </div>
        <div class="uk-modal-body" id="j2commerceOrderModalBody">
            <div class="uk-text-center uk-padding">
                <span uk-spinner="ratio: 2" role="status" aria-label="<?php echo Text::_('COM_J2COMMERCE_LOADING'); ?>"></span>
            </div>
        </div>
        <div class="uk-modal-footer uk-text-right">
            <button type="button" class="uk-button uk-button-default uk-modal-close"><?php echo Text::_('JCLOSE'); ?></button>
            <button type="button" class="uk-button uk-button-primary" id="j2commerceOrderPrintBtn">
                <span class="icon-print" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_ORDER_PRINT'); ?>
            </button>
        </div>
    </div>
</div>
