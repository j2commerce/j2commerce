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
<div class="j2commerce">
    <div class="j2commerce-confirmation-noorder">
        <div class="uk-section">
            <div class="uk-container uk-container-small">
                <div class="uk-text-center">

                    <div class="uk-margin-bottom">
                        <span uk-icon="icon: search; ratio: 3" class="uk-text-muted" aria-hidden="true"></span>
                    </div>

                    <h2><?php echo Text::_('COM_J2COMMERCE_CONFIRMATION_ENTER_TOKEN'); ?></h2>
                    <p class="uk-text-meta uk-margin-bottom"><?php echo Text::_('COM_J2COMMERCE_CONFIRMATION_TOKEN_HELP'); ?></p>

                    <form action="<?php echo Route::_('index.php?option=com_j2commerce&view=confirmation'); ?>" method="get" class="uk-margin-bottom">
                        <input type="hidden" name="option" value="com_j2commerce">
                        <input type="hidden" name="view" value="confirmation">

                        <div class="uk-margin-bottom">
                            <input type="text"
                                   name="token"
                                   id="j2c-order-token"
                                   class="uk-input uk-form-large uk-text-center"
                                   placeholder="<?php echo Text::_('COM_J2COMMERCE_CONFIRMATION_TOKEN_PLACEHOLDER'); ?>"
                                   required
                                   autocomplete="off">
                        </div>

                        <button type="submit" class="uk-button uk-button-primary uk-button-large uk-width-1-1">
                            <span uk-icon="icon: search" class="uk-margin-small-right" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_CONFIRMATION_TOKEN_SUBMIT'); ?>
                        </button>
                    </form>

                    <div class="uk-flex uk-flex-center uk-flex-wrap" style="gap: 12px;">
                        <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=myprofile'); ?>" class="uk-button uk-button-default">
                            <span uk-icon="icon: history" class="uk-margin-small-right" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_CONFIRMATION_VIEW_ALL_ORDERS'); ?>
                        </a>
                        <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=products'); ?>" class="uk-button uk-button-default">
                            <span uk-icon="icon: cart" class="uk-margin-small-right" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_CONFIRMATION_CONTINUE_SHOPPING'); ?>
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
