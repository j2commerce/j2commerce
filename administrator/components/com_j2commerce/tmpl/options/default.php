<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Options\HtmlView $this */

J2CommerceHelper::strapper()->addStyleSheets();

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')
    ->useScript('multiselect');

$user      = $this->getCurrentUser();
$userId    = $user->id;
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$saveOrder = $listOrder == 'a.ordering';

if ($saveOrder && !empty($this->items)) {
    $saveOrderingUrl = 'index.php?option=com_j2commerce&task=options.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
    HTMLHelper::_('draggablelist.draggable');
}
?>

<?php echo $this->navbar;?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=options'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php
                // Search tools bar
                echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]);
                ?>
                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="fa fa-info-circle" aria-hidden="true"></span><span class="sr-only"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table" id="optionList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_J2COMMERCE_OPTIONS_TABLE_CAPTION'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                        </caption>
                        <thead>
                            <tr>
                                <td style="width:1%" class="text-center">
                                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                                </td>
                                <th scope="col" style="width:1%" class="text-center d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', '', 'a.ordering', $listDirn, $listOrder, null, 'asc', 'JGRID_HEADING_ORDERING', 'icon-sort'); ?>
                                </th>
                                <th scope="col" style="width:1%" class="text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.enabled', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_OPTION_NAME', 'a.option_name', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_OPTION_UNIQUE_NAME', 'a.option_unique_name', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_OPTION_TYPE', 'a.type', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" style="width:5%" class="d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.j2commerce_option_id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody <?php if ($saveOrder) : ?> class="js-draggable" data-url="<?php echo $saveOrderingUrl; ?>" data-direction="<?php echo strtolower($listDirn); ?>" data-nested="true"<?php endif; ?>>
                        <?php foreach ($this->items as $i => $item) :
                            $ordering   = ($listOrder == 'a.ordering');
                            $canEdit    = $user->authorise('core.edit', 'com_j2commerce.option.' . $item->j2commerce_option_id);
                            // checked_out columns not yet in options table — treat as always available
                            // $canCheckin = $user->authorise('core.manage', 'com_checkin') || $item->checked_out == $userId || is_null($item->checked_out);
                            $canCheckin = true;
                            $canChange  = $user->authorise('core.edit.state', 'com_j2commerce.option.' . $item->j2commerce_option_id) && $canCheckin;
                            ?>
                            <tr class="row<?php echo $i % 2; ?>" data-draggable-group="none">
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->j2commerce_option_id, false, 'cid', 'cb', $item->option_name); ?>
                                </td>
                                <td class="order text-center d-none d-md-table-cell">
                                    <?php
                                    $iconClass = '';
                                    if (!$canChange) {
                                        $iconClass = ' inactive';
                                    } elseif (!$saveOrder) {
                                        $iconClass = ' inactive tip-top hasTooltip" title="' . HTMLHelper::_('tooltipText', 'JORDERINGDISABLED') . '"';
                                    }
                                    ?>
                                    <span class="sortable-handler<?php echo $iconClass ?>">
                                        <span class="icon-ellipsis-v" aria-hidden="true"></span>
                                    </span>
                                    <?php if ($canChange && $saveOrder) : ?>
                                        <input type="text" style="display:none" name="order[]" size="5" value="<?php echo $item->ordering; ?>" class="width-20 text-area-order">
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('jgrid.published', $item->enabled, $i, 'options.', $canChange, 'cb'); ?>
                                </td>
                                <th scope="row">
                                    <?php /* checked_out columns not yet in options table
                                    <?php if ($item->checked_out) : ?>
                                        <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'options.', $canCheckin); ?>
                                    <?php endif; ?>
                                    */ ?>
                                    <?php if ($canEdit) : ?>
                                        <a href="<?php echo Route::_('index.php?option=com_j2commerce&task=option.edit&id=' . $item->j2commerce_option_id); ?>" title="<?php echo Text::_('JACTION_EDIT'); ?> <?php echo $this->escape($item->option_name); ?>">
                                            <?php echo $this->escape($item->option_name); ?>
                                        </a>
                                    <?php else : ?>
                                        <span title="<?php echo Text::sprintf('JFIELD_ALIAS_LABEL', $this->escape($item->option_name)); ?>"><?php echo $this->escape($item->option_name); ?></span>
                                    <?php endif; ?>
                                </th>
                                <td class="d-none d-md-table-cell">
                                    <span class="small text-muted"><?php echo $this->escape($item->option_unique_name); ?></span>
                                </td>
                                <td>
                                    <?php
                                    $typeLabels = [
                                        'text' => 'COM_J2COMMERCE_OPTION_TYPE_TEXT',
                                        'textarea' => 'COM_J2COMMERCE_OPTION_TYPE_TEXTAREA',
                                        'select' => 'COM_J2COMMERCE_OPTION_TYPE_SELECT',
                                        'radio' => 'COM_J2COMMERCE_OPTION_TYPE_RADIO',
                                        'checkbox' => 'COM_J2COMMERCE_OPTION_TYPE_CHECKBOX',
                                        'date' => 'COM_J2COMMERCE_OPTION_TYPE_DATE',
                                        'datetime' => 'COM_J2COMMERCE_OPTION_TYPE_DATETIME',
                                        'time' => 'COM_J2COMMERCE_OPTION_TYPE_TIME',
                                        'file' => 'COM_J2COMMERCE_OPTION_TYPE_FILE',
                                        'image' => 'COM_J2COMMERCE_OPTION_TYPE_IMAGE',
                                        'color' => 'COM_J2COMMERCE_OPTION_TYPE_COLOR',
                                        'number' => 'COM_J2COMMERCE_OPTION_TYPE_NUMBER',
                                        'email' => 'COM_J2COMMERCE_OPTION_TYPE_EMAIL',
                                        'url' => 'COM_J2COMMERCE_OPTION_TYPE_URL'
                                    ];
                                    $typeLabel = isset($typeLabels[$item->type]) ? Text::_($typeLabels[$item->type]) : ucfirst($item->type);
                                    ?>
                                    <span class="badge bg-secondary"><?php echo $this->escape($typeLabel); ?></span>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo (int) $item->j2commerce_option_id; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php // Load the pagination. ?>
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
