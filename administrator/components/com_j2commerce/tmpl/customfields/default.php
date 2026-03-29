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
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Customfields\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')
    ->useScript('multiselect');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$saveOrder = $listOrder === 'a.ordering';

if ($saveOrder && !empty($this->items)) {
    $saveOrderingUrl = 'index.php?option=com_j2commerce&task=customfields.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
    HTMLHelper::_('draggablelist.draggable');
}

?>
<?php echo $this->navbar; ?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=customfields'); ?>" method="post" name="adminForm" id="adminForm">
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
                    <table class="table itemList" id="customfieldsList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_J2COMMERCE_CUSTOMFIELDS'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?></span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                        </caption>
                        <thead>
                            <tr>
                                <td class="w-1 text-center">
                                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                                </td>
                                <th scope="col" class="w-1 text-center d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', '', 'a.ordering', $listDirn, $listOrder, null, 'asc', 'JGRID_HEADING_ORDERING', 'icon-sort'); ?>
                                </th>
                                <th scope="col" class="w-1 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.enabled', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_FIELD_NAME', 'a.field_name', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_FIELD_TYPE', 'a.field_type', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 text-center d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_FIELD_REQUIRED', 'a.field_required', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_FIELD_NAMEKEY', 'a.field_namekey', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 text-center d-none d-md-table-cell">
                                    <?php echo Text::_('COM_J2COMMERCE_HEADING_FIELD_CORE'); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.j2commerce_customfield_id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody <?php if ($saveOrder) : ?> class="js-draggable" data-url="<?php echo $saveOrderingUrl; ?>" data-direction="<?php echo strtolower($listDirn); ?>" data-nested="true"<?php endif; ?>>
                        <?php foreach ($this->items as $i => $item) : ?>
                            <tr class="row<?php echo $i % 2; ?>" data-draggable-group="none">
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->j2commerce_customfield_id, false, 'cid', 'cb', $item->field_namekey); ?>
                                </td>
                                <td class="text-center d-none d-md-table-cell">
                                    <?php
                                    $iconClass = '';
                                    if (!$saveOrder) {
                                        $iconClass = ' inactive" title="' . Text::_('JORDERINGDISABLED');
                                    }
                                    ?>
                                    <span class="sortable-handler<?php echo $iconClass; ?>">
                                        <span class="icon-ellipsis-v" aria-hidden="true"></span>
                                    </span>
                                    <?php if ($saveOrder) : ?>
                                        <input type="text" name="order[]" size="5" value="<?php echo $item->ordering; ?>" class="width-20 text-area-order hidden" />
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('jgrid.published', $item->enabled, $i, 'customfields.', true, 'cb'); ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <a href="<?php echo Route::_('index.php?option=com_j2commerce&task=customfield.edit&id=' . $item->j2commerce_customfield_id); ?>">
                                        <?php echo Text::_($this->escape($item->field_name)); ?>
                                    </a>
                                </td>

                                <td class="d-none d-md-table-cell">
                                    <?php echo $this->escape($item->field_type); ?>
                                </td>
                                <td class="text-center d-none d-md-table-cell">
                                    <?php if ($item->field_required == 1) : ?>
                                        <span class="badge text-bg-success"><?php echo Text::_('COM_J2COMMERCE_REQUIRED'); ?></span>
                                    <?php else : ?>
                                        <span class="badge text-bg-danger"><?php echo Text::_('COM_J2COMMERCE_NOT_REQUIRED'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->field_namekey); ?>
                                </td>
                                <td class="text-center d-none d-md-table-cell">
                                    <?php if ($item->field_core == 1) : ?>
                                        <span class="badge text-bg-success">
                                            <?php echo Text::_('COM_J2COMMERCE_CORE_FIELD_YES'); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="badge text-bg-secondary">
                                            <?php echo Text::_('COM_J2COMMERCE_CORE_FIELD_NO'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo (int) $item->j2commerce_customfield_id; ?>
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

<?php echo $this->loadTemplate('batch'); ?>
<?php echo $this->footer ?? ''; ?>
