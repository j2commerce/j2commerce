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

use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Products\HtmlView $this */

?>
<div class="p-3 j2c-batch-body">
    <div class="row">
        <?php if ($this->canBatch && Multilanguage::isEnabled()) : ?>
            <div class="form-group col-md-6">
                <?php echo $this->batchForm->renderField('language_id', 'batch'); ?>
            </div>
        <?php endif; ?>
        <?php if ($this->canBatchState) : ?>
            <div class="form-group col-md-6">
                <?php echo $this->batchForm->renderField('assetgroup_id', 'batch'); ?>
            </div>
        <?php endif; ?>
        <?php if ($this->canBatch) : ?>
            <div class="form-group col-md-6">
                <?php echo $this->batchForm->renderField('category_id', 'batch'); ?>
            </div>
            <div class="form-group col-md-6">
                <?php echo $this->batchForm->renderField('tag', 'batch'); ?>
            </div>
            <div class="form-group col-md-6">
                <?php echo $this->batchForm->renderField('tag_addremove', 'batch'); ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="btn-toolbar p-3">
    <joomla-toolbar-button task="products.batch" class="ms-auto">
        <button type="button" class="btn btn-success"><?php echo Text::_('JGLOBAL_BATCH_PROCESS'); ?></button>
    </joomla-toolbar-button>
</div>
