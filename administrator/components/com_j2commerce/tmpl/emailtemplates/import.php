<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&task=emailtemplates.importProcess'); ?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">
    <div class="main-card">
        <div class="card-body p-4">
            <h2><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_IMPORT'); ?></h2>
            <p class="text-body-secondary"><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_IMPORT_DESC'); ?></p>

            <div class="mb-3" style="max-width: 500px;">
                <label for="import_file" class="form-label"><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_IMPORT_FILE'); ?></label>
                <input type="file" class="form-control" name="import_file" id="import_file" accept=".json" required>
                <div class="form-text"><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_IMPORT_FILE_DESC'); ?></div>
            </div>

            <button type="submit" class="btn btn-primary">
                <span class="icon-upload" aria-hidden="true"></span>
                <?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_IMPORT'); ?>
            </button>
            <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=emailtemplates'); ?>" class="btn btn-secondary ms-2">
                <?php echo Text::_('JCANCEL'); ?>
            </a>
        </div>
    </div>

    <input type="hidden" name="task" value="emailtemplates.importProcess">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
