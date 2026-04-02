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

/** @var \J2Commerce\Component\J2commerce\Site\View\Confirmation\HtmlView $this */
?>
<div class="j2commerce-confirmation-noorder">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 text-center">

                <div class="mb-4">
                    <i class="fa-solid fa-magnifying-glass fa-3x text-muted"></i>
                </div>

                <h2><?php echo Text::_('COM_J2COMMERCE_CONFIRMATION_ENTER_TOKEN'); ?></h2>
                <p class="text-muted mb-4"><?php echo Text::_('COM_J2COMMERCE_CONFIRMATION_TOKEN_HELP'); ?></p>

                <form action="<?php echo Route::_('index.php?option=com_j2commerce&view=confirmation'); ?>" method="get" class="mb-4">
                    <input type="hidden" name="option" value="com_j2commerce">
                    <input type="hidden" name="view" value="confirmation">

                    <div class="mb-3">
                        <input type="text"
                               name="token"
                               id="j2c-order-token"
                               class="form-control form-control-lg text-center"
                               placeholder="<?php echo Text::_('COM_J2COMMERCE_CONFIRMATION_TOKEN_PLACEHOLDER'); ?>"
                               required
                               autocomplete="off">
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="fa-solid fa-eye me-2"></i><?php echo Text::_('COM_J2COMMERCE_CONFIRMATION_TOKEN_SUBMIT'); ?>
                    </button>
                </form>

                <div class="d-flex justify-content-center gap-3">
                    <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=myprofile'); ?>" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i><?php echo Text::_('COM_J2COMMERCE_CONFIRMATION_VIEW_ALL_ORDERS'); ?>
                    </a>
                    <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=products'); ?>" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-bag-shopping me-1"></i><?php echo Text::_('COM_J2COMMERCE_CONFIRMATION_CONTINUE_SHOPPING'); ?>
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>
