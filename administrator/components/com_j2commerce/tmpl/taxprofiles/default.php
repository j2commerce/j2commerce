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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Taxprofiles\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')
    ->useScript('multiselect');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));

?>
<?php echo $this->navbar; ?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=taxprofiles'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table itemList" id="taxprofilesList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_J2COMMERCE_TAXPROFILES'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?></span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                        </caption>
                        <thead>
                            <tr>
                                <td class="w-1 text-center">
                                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                                </td>
                                <th scope="col" class="w-1 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.enabled', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_TAXPROFILE_NAME', 'a.taxprofile_name', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.j2commerce_taxprofile_id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->items as $i => $item) : ?>
                            <?php $isPlugin = !empty($item->is_plugin); ?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td class="text-center">
                                    <?php if (!$isPlugin) : ?>
                                        <?php echo HTMLHelper::_('grid.id', $i, $item->j2commerce_taxprofile_id, false, 'cid', 'cb', $item->taxprofile_name); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($isPlugin) : ?>
                                        <?php
                                        $iconClass = !empty($item->enabled) ? 'icon-publish' : 'icon-unpublish';
                                        $iconTitle = Text::_(!empty($item->enabled) ? 'JLIB_HTML_UNPUBLISH_ITEM' : 'JLIB_HTML_PUBLISH_ITEM');
                                        ?>
                                        <?php // Only follow plugin links that are internal routes — a non-index.php URL would pass through Route::_() unescaped. ?>
                                        <?php if (!empty($item->toggle_link) && str_starts_with((string) $item->toggle_link, 'index.php')) : ?>
                                            <a class="tbody-icon" href="<?php echo Route::_($item->toggle_link); ?>" title="<?php echo $this->escape($iconTitle); ?>">
                                                <span class="<?php echo $iconClass; ?>" aria-hidden="true"></span>
                                            </a>
                                        <?php else : ?>
                                            <span class="tbody-icon">
                                                <span class="<?php echo $iconClass; ?>" aria-hidden="true"></span>
                                            </span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <?php echo HTMLHelper::_('jgrid.published', $item->enabled, $i, 'taxprofiles.', true, 'cb'); ?>
                                    <?php endif; ?>
                                </td>
                                <th scope="row">
                                    <?php if ($isPlugin) : ?>
                                        <?php if (!empty($item->edit_link) && str_starts_with((string) $item->edit_link, 'index.php')) : ?>
                                            <a href="<?php echo Route::_($item->edit_link); ?>">
                                                <?php echo $this->escape($item->taxprofile_name); ?>
                                            </a>
                                        <?php else : ?>
                                            <?php echo $this->escape($item->taxprofile_name); ?>
                                        <?php endif; ?>
                                        <span class="<?php echo $this->escape(J2htmlHelper::badgeClass('badge text-bg-info')); ?> ms-1"><?php echo $this->escape((string) ($item->taxprofile_source ?? 'plugin')); ?></span>
                                    <?php else : ?>
                                        <a href="<?php echo Route::_('index.php?option=com_j2commerce&task=taxprofile.edit&id=' . $item->j2commerce_taxprofile_id); ?>">
                                            <?php echo $this->escape($item->taxprofile_name); ?>
                                        </a>
                                    <?php endif; ?>
                                </th>
                                <td class="d-none d-md-table-cell">
                                    <?php echo (int) $item->j2commerce_taxprofile_id; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>

                <input type="hidden" name="task" value="">
                <input type="hidden" name="boxchecked" value="0">
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>

<?php echo $this->footer ?? ''; ?>
