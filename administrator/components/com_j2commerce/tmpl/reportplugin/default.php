<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Reportplugin\HtmlView $this */

echo $this->navbar;

$pluginName = Factory::getApplication()->getInput()->getCmd('plugin', '');

?>

<?php if ($this->filterForm) : ?>
    <?php // Standard Joomla list view frame with searchtools ?>
    <form action="<?php echo Route::_('index.php?option=com_j2commerce&view=reportplugin&plugin=' . htmlspecialchars($pluginName, ENT_QUOTES, 'UTF-8') . '&pluginview=report'); ?>"
          method="post" name="adminForm" id="adminForm">
        <div class="row">
            <div class="col-md-12">
                <div id="j-main-container" class="j-main-container">
                    <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                    <?php // Plugin content (chart + sortable table) ?>
                    <?php echo $this->pluginHtml; ?>

                    <?php // Pagination ?>
                    <?php if ($this->pagination) : ?>
                        <?php echo $this->pagination->getListFooter(); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <input type="hidden" name="task" value="">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
    <?php // Rename "Filter Options" to "Report Options" for report views ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var btn = document.querySelector('.js-stools-btn-filter');
        if (btn) {
            btn.childNodes[0].textContent = <?php echo json_encode(Text::_('COM_J2COMMERCE_REPORT_OPTIONS'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> + ' ';
        }
    });
    </script>
<?php else : ?>
    <?php // Legacy fallback: plugin manages its own form/filters ?>
    <?php echo $this->pluginHtml; ?>
<?php endif; ?>

<?php echo $this->footer ?? ''; ?>
