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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Site\View\Myprofile\HtmlView $this */

$addresses = $this->addresses;
?>

<div class="j2commerce-address-list">
    <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-bottom">
        <h4 class="uk-margin-remove"><?php echo Text::_('COM_J2COMMERCE_ADDRESS_LIST'); ?></h4>
        <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=myprofile&layout=address&address_id=0'); ?>" class="uk-button uk-button-small uk-button-primary">
            <?php echo Text::_('COM_J2COMMERCE_ADDRESS_ADD'); ?>
        </a>
    </div>

    <?php if (empty($addresses)): ?>
    <div class="uk-alert uk-alert-primary" uk-alert><?php echo Text::_('COM_J2COMMERCE_NO_ADDRESSES'); ?></div>
    <?php else: ?>
    <div class="uk-grid uk-grid-small uk-child-width-1-2@m" uk-grid>
        <?php foreach ($addresses as $addr): ?>
        <div id="j2commerce-address-<?php echo (int) $addr->j2commerce_address_id; ?>">
            <div class="uk-card uk-card-default uk-card-small">
                <div class="uk-card-header uk-flex uk-flex-between uk-flex-middle">
                    <span class="uk-badge"><?php echo $this->escape(ucfirst($addr->type)); ?></span>
                    <div>
                        <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=myprofile&layout=address&address_id=' . (int) $addr->j2commerce_address_id); ?>" class="uk-button uk-button-small uk-button-default uk-margin-small-right" title="<?php echo Text::_('JACTION_EDIT'); ?>">
                            <span class="icon-pencil" aria-hidden="true"></span>
                        </a>
                        <button type="button" class="uk-button uk-button-small uk-button-danger j2commerce-address-delete" data-address-id="<?php echo (int) $addr->j2commerce_address_id; ?>" title="<?php echo Text::_('JACTION_DELETE'); ?>">
                            <span class="icon-trash" aria-hidden="true"></span>
                        </button>
                    </div>
                </div>
                <div class="uk-card-body">
                    <strong><?php echo $this->escape($addr->first_name . ' ' . $addr->last_name); ?></strong><br>
                    <?php if (!empty($addr->company)): ?><?php echo $this->escape($addr->company); ?><br><?php endif; ?>
                    <?php echo $this->escape($addr->address_1); ?><br>
                    <?php if (!empty($addr->address_2)): ?><?php echo $this->escape($addr->address_2); ?><br><?php endif; ?>
                    <?php echo $this->escape($addr->city); ?>
                    <?php if (!empty($addr->zip)): ?>, <?php echo $this->escape($addr->zip); ?><?php endif; ?><br>
                    <?php if (!empty($addr->zone_name)): ?><?php echo $this->escape($addr->zone_name); ?>, <?php endif; ?>
                    <?php echo $this->escape($addr->country_name ?? ''); ?>
                    <?php if (!empty($addr->phone_1)): ?><br><?php echo $this->escape($addr->phone_1); ?><?php endif; ?>
                    <?php if (!empty($addr->email)): ?><br><?php echo $this->escape($addr->email); ?><?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
