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

$menuParams = $this->menuItemParams;
?>
<div class="j2commerce">
    <div class="page-header">
        <h1><?php echo $this->escape($menuParams ? $menuParams->get('page_heading', '') : '') ?: Text::_('COM_J2COMMERCE_MYPROFILE'); ?></h1>
    </div>

    <?php if ($params->get('show_logout_myprofile', 0) && $user->id > 0): ?>
    <div class="d-flex justify-content-end mb-3">
        <form action="<?php echo Route::_('index.php'); ?>" method="post">
            <button type="submit" class="btn btn-outline-danger btn-sm text-capitalize"><?php echo Text::_('JLOGOUT'); ?></button>
            <input type="hidden" name="option" value="com_users">
            <input type="hidden" name="task" value="user.logout">
            <input type="hidden" name="return" value="<?php echo base64_encode(Route::_('index.php?option=com_j2commerce&view=myprofile', false)); ?>">
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    </div>
    <?php endif; ?>

    <div class="j2commerce-myprofile">

        <?php if (!empty($this->topMessagesHtml)): ?>
        <div class="j2commerce-myprofile-messages mb-3"><?php echo $this->topMessagesHtml; ?></div>
        <?php endif; ?>

        <ul class="nav nav-tabs d-flex justify-content-center border-0 rounded-0 bg-transparent" id="j2commerceProfileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="orders-tab" data-bs-toggle="tab"
                    data-bs-target="#orders-pane" type="button" role="tab"
                    aria-controls="orders-pane" aria-selected="true">
                    <span class="fa-solid fa-receipt me-1" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCE_MYPROFILE_ORDERS'); ?>
                </button>
            </li>

            <?php if ($params->get('download_area', 1)): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="downloads-tab" data-bs-toggle="tab"
                    data-bs-target="#downloads-pane" type="button" role="tab"
                    aria-controls="downloads-pane" aria-selected="false">
                    <span class="fa-solid fa-download me-1" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCE_MYPROFILE_DOWNLOADS'); ?>
                </button>
            </li>
            <?php endif; ?>

            <?php if ($user->id > 0): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="addresses-tab" data-bs-toggle="tab"
                    data-bs-target="#addresses-pane" type="button" role="tab"
                    aria-controls="addresses-pane" aria-selected="false">
                    <span class="fa-solid fa-map-marker me-1" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCE_MYPROFILE_ADDRESSES'); ?>
                </button>
            </li>
            <?php endif; ?>

            <?php if ($this->useUnifiedPaymentTab) : ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="payment-methods-tab" data-bs-toggle="tab"
                    data-bs-target="#payment-methods-pane" type="button" role="tab"
                    aria-controls="payment-methods-pane" aria-selected="false">
                    <span class="fa-solid fa-credit-card me-1" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHODS_TITLE'); ?>
                </button>
            </li>
            <?php endif; ?>

            <?php echo $this->pluginTabHtml; ?>
        </ul>

        <div class="tab-content pt-4 px-0 border-0 box-shadow-none" id="j2commerceProfileTabContent">
            <div class="tab-pane fade show active" id="orders-pane" role="tabpanel" aria-labelledby="orders-tab">
                <?php echo $this->loadTemplate('orders'); ?>
            </div>

            <?php if ($params->get('download_area', 1)): ?>
            <div class="tab-pane fade" id="downloads-pane" role="tabpanel" aria-labelledby="downloads-tab">
                <?php echo $this->loadTemplate('downloads'); ?>
            </div>
            <?php endif; ?>

            <?php if ($user->id > 0): ?>
            <div class="tab-pane fade" id="addresses-pane" role="tabpanel" aria-labelledby="addresses-tab">
                <?php echo $this->loadTemplate('addresses'); ?>
            </div>
            <?php endif; ?>

            <?php if ($this->useUnifiedPaymentTab) : ?>
            <div class="tab-pane fade" id="payment-methods-pane" role="tabpanel" aria-labelledby="payment-methods-tab">
                <?php echo $this->loadTemplate('payment_methods'); ?>
            </div>
            <?php endif; ?>

            <?php echo $this->pluginContentHtml; ?>
        </div>
    </div>
</div>

<!-- Order Print Modal -->
<div class="modal fade" id="j2commerceOrderModal" tabindex="-1" aria-labelledby="j2commerceOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="j2commerceOrderModalLabel"><?php echo Text::_('COM_J2COMMERCE_ORDER_PRINT'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>
            <div class="modal-body" id="j2commerceOrderModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCLOSE'); ?></button>
                <button type="button" class="btn btn-primary" id="j2commerceOrderPrintBtn">
                    <span class="icon-print" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_ORDER_PRINT'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
