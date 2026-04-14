<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * Layout variables:
 * @var array $displayData Array containing menu items and active page
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\HTML\HTMLHelper;

// Extract display data
$items = $displayData['items'] ?? [];
$active = $displayData['active'] ?? '';
$user = Factory::getApplication()->getIdentity();


$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('bootstrap.dropdown');
$pluginHelper = J2CommerceHelper::plugin();
$versionHelper = J2CommerceHelper::version();
$is_pro = $versionHelper->isPro();


?>


<div class="j2commerce-app-container inline-content my-3">
    <div class="row">
        <div class="col-md-6 align-self-stretch mb-3 mb-lg-0">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-column text-center h-100 pt-3">
                        <div class="mt-auto">
                            <span class="fa-4x mb-2 fa-solid fa-circle-info" aria-hidden="true"></span>
                            <h2 class="fs-1 fw-bold"><?php echo Text::_('COM_J2COMMERCE_APP_LAYOUT_CARDS_HELP_TITLE');?></h2>
                            <p class="text-muted mb-5"><?php echo Text::_('COM_J2COMMERCE_APP_LAYOUT_CARDS_HELP_DESC');?></p>
                        </div>
                        <div class="text-center mt-auto mb-4">
                            <a class="btn btn-outline-primary app-button-open" href="https://www.j2commerce.com/support/technical-support" target="_blank" title="<?php echo Text::_('COM_J2COMMERCE_APP_LAYOUT_CARDS_HELP_BTN2_TITLE');?>"><span class="ps-1"><?php echo Text::_('COM_J2COMMERCE_APP_LAYOUT_CARDS_HELP_BTN2_TITLE');?></span></a>
                            <a class="btn btn-primary app-button-open" href="https://docs.j2commerce.com/v6/apps-and-extensions" target="_blank" title="<?php echo Text::_('COM_J2COMMERCE_APP_LAYOUT_CARDS_HELP_BTN1_TITLE');?>"><span class="ps-1"><?php echo Text::_('COM_J2COMMERCE_APP_LAYOUT_CARDS_HELP_BTN1_TITLE');?></span></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 align-self-stretch">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-column text-center h-100 pt-3">
                        <div class="mt-auto">
                            <span class="fa-4x mb-2 fa-solid fa-cart-arrow-down" aria-hidden="true"></span>
                            <h2 class="fs-1 fw-bold"><?php echo Text::_('COM_J2COMMERCE_APP_LAYOUT_CARDS_ADD_TITLE');?></h2>
                            <p class="text-muted mb-5"><?php echo Text::_('COM_J2COMMERCE_APP_LAYOUT_CARDS_ADD_DESC');?></p>
                        </div>
                        <div class="text-center mt-auto mb-4">
                            <a class="btn btn-primary app-button-open" href="https://www.j2commerce.com/extensions/apps" target="_blank" title="<?php echo Text::_('COM_J2COMMERCE_APP_LAYOUT_CARDS_ADD_BTN1_TITLE');?>"><span class="ps-1"><?php echo Text::_('COM_J2COMMERCE_APP_LAYOUT_CARDS_ADD_BTN1_TITLE');?></span></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
