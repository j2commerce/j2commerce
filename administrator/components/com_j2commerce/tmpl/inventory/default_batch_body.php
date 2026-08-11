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

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Inventory\HtmlView $this */
?>

<div class="p-3">
    <p class="text-body-secondary small">
        <?php echo Text::_('COM_J2COMMERCE_INVENTORY_BATCH_APPLY_HELP'); ?>
        <?php echo Text::_('COM_J2COMMERCE_INVENTORY_BATCH_CASCADE_NOTE'); ?>
    </p>

    <div class="mb-4">
        <?php echo $this->batchForm->renderField('apply_quantity', 'batch'); ?>
        <?php echo $this->batchForm->renderField('quantity', 'batch'); ?>
    </div>

    <div class="mb-4">
        <?php echo $this->batchForm->renderField('apply_manage_stock', 'batch'); ?>
        <?php echo $this->batchForm->renderField('manage_stock', 'batch'); ?>
    </div>

    <div class="mb-4">
        <?php echo $this->batchForm->renderField('apply_availability', 'batch'); ?>
        <?php echo $this->batchForm->renderField('availability', 'batch'); ?>
    </div>

    <button type="button" class="btn btn-primary" id="j2c-batch-apply">
        <span class="icon-checkmark" aria-hidden="true"></span>
        <?php echo Text::_('COM_J2COMMERCE_INVENTORY_BATCH_APPLY_BUTTON'); ?>
    </button>
</div>
