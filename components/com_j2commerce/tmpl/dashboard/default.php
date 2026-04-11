<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Dashboard\HtmlView $this */

?>
<div class="j2commerce j2commerce-dashboard">
    <div class="row">
        <div class="col-lg-12">
            <h1><?php echo Text::_('COM_J2COMMERCE_DASHBOARD'); ?></h1>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h2 class="card-title fs-5"><?php echo Text::_('COM_J2COMMERCE_DASHBOARD_PRODUCTS'); ?></h2>
                    <p class="card-text">
                        <span class="badge bg-primary fs-3"><?php echo (int) $this->productsCount; ?></span>
                    </p>
                    <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=products'); ?>" class="btn btn-primary">
                        <?php echo Text::_('COM_J2COMMERCE_VIEW_PRODUCTS'); ?>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h2 class="card-title fs-5"><?php echo Text::_('COM_J2COMMERCE_DASHBOARD_ORDERS'); ?></h2>
                    <p class="card-text">
                        <span class="badge bg-success fs-3"><?php echo (int) $this->ordersCount; ?></span>
                    </p>
                    <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=orders'); ?>" class="btn btn-success">
                        <?php echo Text::_('COM_J2COMMERCE_VIEW_ORDERS'); ?>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h2 class="card-title fs-5"><?php echo Text::_('COM_J2COMMERCE_DASHBOARD_CUSTOMERS'); ?></h2>
                    <p class="card-text">
                        <span class="badge bg-info fs-3"><?php echo (int) $this->customersCount; ?></span>
                    </p>
                    <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=customers'); ?>" class="btn btn-info">
                        <?php echo Text::_('COM_J2COMMERCE_VIEW_CUSTOMERS'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h2><?php echo Text::_('COM_J2COMMERCE_MIGRATION_STATUS'); ?></h2>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h3><?php echo Text::_('COM_J2COMMERCE_MIGRATION_WELCOME'); ?></h3>
                        <p><?php echo Text::_('COM_J2COMMERCE_MIGRATION_DESC'); ?></p>
                        <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=migration'); ?>" class="btn btn-warning">
                            <?php echo Text::_('COM_J2COMMERCE_START_MIGRATION'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
