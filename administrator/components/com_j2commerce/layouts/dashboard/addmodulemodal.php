<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

// Ensure Bootstrap modal JS is loaded
HTMLHelper::_('bootstrap.modal');

// URL to Joomla's module type picker (layout=modal loads a compact variant)
$selectUrl = Uri::root(true)
    . '/administrator/index.php?option=com_modules&view=select&client_id=1&tmpl=component&layout=modal';
?>
<div class="modal fade" id="j2cAddModuleModal" tabindex="-1"
     aria-labelledby="j2cAddModuleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="j2cAddModuleModalLabel">
                    <?php echo Text::_('COM_J2COMMERCE_DASHBOARD_ADD_MODULE_MODAL_TITLE'); ?>
                </h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="j2cAddModuleIframe" src="about:blank"
                        style="width:100%;height:70vh;border:0;"
                        title="<?php echo Text::_('COM_J2COMMERCE_DASHBOARD_ADD_MODULE_MODAL_TITLE'); ?>"></iframe>
            </div>
        </div>
    </div>
</div>
<?php
// Emit config once per request via script options
Factory::getApplication()->getDocument()->addScriptOptions('com_j2commerce.addModuleModal', [
    'selectUrl' => $selectUrl,
]);
?>
