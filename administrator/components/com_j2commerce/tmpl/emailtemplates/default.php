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
use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Emailtemplates\HtmlView $this */

J2CommerceHelper::strapper()->addCSS();

$app = Factory::getApplication();
$user = $app->getIdentity();
$userId = $user->id;
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn = $this->escape($this->state->get('list.direction'));
$saveOrder = $listOrder == 'a.ordering';

if ($saveOrder && !empty($this->items)) {
    $saveOrderingUrl = 'index.php?option=com_j2commerce&task=emailtemplates.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
    HTMLHelper::_('draggablelist.draggable');
}

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')
    ->useScript('multiselect');

HTMLHelper::_('behavior.core');

$isMultilang = Multilanguage::isEnabled();

?>

<?php echo $this->navbar;?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=emailtemplates'); ?>" method="post" name="adminForm" id="adminForm">
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
                    <table class="table" id="emailtemplateList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATES_TABLE_CAPTION'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
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
                                <th scope="col" class="w-10 d-none d-lg-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_EMAILTEMPLATE_RECEIVER_TYPE', 'a.receiver_type', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="title">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_EMAILTEMPLATE_SUBJECT', 'a.subject', $listDirn, $listOrder); ?>
                                </th>
                                <?php if ($isMultilang) : ?>
                                <th scope="col" class="w-10 d-none d-lg-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_LANGUAGE', 'a.language', $listDirn, $listOrder); ?>
                                </th>
                                <?php endif; ?>
                                <th scope="col" class="w-10 d-none d-lg-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_EMAILTEMPLATE_ORDERSTATUS', 'a.orderstatus_id', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-lg-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_EMAILTEMPLATE_GROUP', 'a.group_id', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-lg-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_EMAILTEMPLATE_EMAIL_TYPE', 'a.email_type', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-3 d-none d-lg-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.j2commerce_emailtemplate_id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody <?php if ($saveOrder) : ?> class="js-draggable" data-url="<?php echo $saveOrderingUrl; ?>" data-direction="<?php echo strtolower($listDirn); ?>" data-nested="true"<?php endif; ?>>
                            <?php foreach ($this->items as $i => $item) :
                                $canEdit = $user->authorise('core.edit', 'com_j2commerce.emailtemplate.' . $item->j2commerce_emailtemplate_id);
                                $canCheckin = $user->authorise('core.manage', 'com_checkin') || $item->locked_by == $userId || is_null($item->locked_by);
                                // TODO: Add created_by column to j2commerce_emailtemplates table
                                // $canEditOwn = $user->authorise('core.edit.own', 'com_j2commerce.emailtemplate.' . $item->j2commerce_emailtemplate_id) && $item->created_by == $userId;
                                $canEditOwn = false;
                                $canChange = $user->authorise('core.edit.state', 'com_j2commerce.emailtemplate.' . $item->j2commerce_emailtemplate_id) && $canCheckin;
                            ?>
                                <tr class="row<?php echo $i % 2; ?>" data-draggable-group="0">
                                    <td class="text-center">
                                        <?php echo HTMLHelper::_('grid.id', $i, $item->j2commerce_emailtemplate_id, false, 'cid', 'cb', $item->email_type . ' - ' . $item->subject); ?>
                                    </td>
                                    <td class="text-center d-none d-md-table-cell">
                                        <?php
                                        $iconClass = '';
                                        if (!$canChange) {
                                            $iconClass = ' inactive';
                                        } elseif (!$saveOrder) {
                                            $iconClass = ' inactive" title="' . Text::_('JORDERINGDISABLED');
                                        }
                                        ?>
                                        <span class="sortable-handler<?php echo $iconClass ?>">
                                            <span class="icon-ellipsis-v" aria-hidden="true"></span>
                                        </span>
                                        <?php if ($canChange && $saveOrder) : ?>
                                            <input type="text" name="order[]" size="5" value="<?php echo $item->ordering; ?>" class="width-20 text-area-order hidden">
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($canChange) {
                                            if ($item->enabled == 1) {
                                                // Email template is enabled - show unpublish link
                                                echo '<a href="#" onclick="return Joomla.listItemTask(\'cb' . $i . '\',\'emailtemplates.unpublish\')" class="tbody-icon" title="' . Text::_('COM_J2COMMERCE_EMAILTEMPLATE_ENABLED') . '">';
                                                echo '<span class="icon-publish" aria-hidden="true"></span>';
                                                echo '</a>';
                                            } else {
                                                // Email template is disabled - show publish link
                                                echo '<a href="#" onclick="return Joomla.listItemTask(\'cb' . $i . '\',\'emailtemplates.publish\')" class="tbody-icon" title="' . Text::_('COM_J2COMMERCE_EMAILTEMPLATE_DISABLED') . '">';
                                                echo '<span class="icon-unpublish" aria-hidden="true"></span>';
                                                echo '</a>';
                                            }
                                        } else {
                                            if ($item->enabled == 1) {
                                                echo '<span class="tbody-icon" title="' . Text::_('COM_J2COMMERCE_EMAILTEMPLATE_ENABLED') . '">';
                                                echo '<span class="icon-publish" aria-hidden="true"></span>';
                                                echo '</span>';
                                            } else {
                                                echo '<span class="tbody-icon" title="' . Text::_('COM_J2COMMERCE_EMAILTEMPLATE_DISABLED') . '">';
                                                echo '<span class="icon-unpublish" aria-hidden="true"></span>';
                                                echo '</span>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td class="d-none d-lg-table-cell">
                                        <?php if ($item->receiver_type === '*') : ?>
                                            <?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_RECEIVER_BOTH'); ?>
                                        <?php else : ?>
                                            <?php echo $this->escape(Text::_('COM_J2COMMERCE_EMAILTEMPLATE_RECEIVER_' . strtoupper($item->receiver_type))); ?>
                                        <?php endif; ?>


                                    </td>
                                    <th scope="row" class="has-context">
                                        <div>
                                            <?php if ($canEdit || $canEditOwn) : ?>
                                                <a class="hasTooltip" href="<?php echo Route::_('index.php?option=com_j2commerce&task=emailtemplate.edit&j2commerce_emailtemplate_id=' . (int) $item->j2commerce_emailtemplate_id); ?>" title="<?php echo Text::_('JACTION_EDIT'); ?> <?php echo $this->escape($item->subject); ?>">
                                                    <?php echo $this->escape($item->subject); ?>
                                                </a>
                                            <?php else : ?>
                                                <span title="<?php echo $this->escape($item->subject); ?>">
                                                    <?php echo $this->escape($item->subject); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </th>
                                    <?php if ($isMultilang) : ?>
                                    <td class="d-none d-lg-table-cell">
                                        <?php if ($item->language === '*') : ?>
                                            <?php echo Text::_('JALL'); ?>
                                        <?php else : ?>
                                            <?php echo $item->language; ?>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <td class="d-none d-lg-table-cell">
                                        <?php if ($item->orderstatus_id === '*') : ?>
                                            <?php echo Text::_('JALL'); ?>
                                        <?php else : ?>
                                            <?php echo J2htmlHelper::getOrderStatusHtml($item->orderstatus_id); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="d-none d-lg-table-cell">
                                        <?php if ($item->group_id === '*') : ?>
                                            <?php echo Text::_('JALL'); ?>
                                        <?php else : ?>
                                            <?php echo J2htmlHelper::getUserGroupName($item->group_id); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="d-none d-lg-table-cell">
                                        <?php echo $this->escape($item->email_type); ?>
                                    </td>
                                    <td class="d-none d-lg-table-cell">
                                        <?php echo (int) $item->j2commerce_emailtemplate_id; ?>
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
