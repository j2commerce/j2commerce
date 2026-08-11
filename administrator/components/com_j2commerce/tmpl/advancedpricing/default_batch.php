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

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Advancedpricing\HtmlView $this */
?>

<div class="modal fade" id="collapseModal" tabindex="-1" aria-labelledby="collapseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="collapseModalLabel"><?php echo Text::_('COM_J2COMMERCE_BATCH_TITLE'); ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>
            <div class="modal-body px-4 pt-4 pb-2">
                <div class="row">
                    <div class="form-group col-md-6 mb-3">
                        <?php echo $this->batchForm->renderField('customer_group_id', 'batch'); ?>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-md-6 mb-3">
                        <?php echo $this->batchForm->renderField('date_from', 'batch'); ?>
                    </div>
                    <div class="form-group col-md-6 mb-3">
                        <?php echo $this->batchForm->renderField('date_to', 'batch'); ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
                <joomla-toolbar-button task="advancedpricing.batch">
                    <button type="button" class="btn btn-primary"><?php echo Text::_('JGLOBAL_BATCH_PROCESS'); ?></button>
                </joomla-toolbar-button>
            </div>
        </div>
    </div>
</div>
