<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Button\PublishedButton;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Apps\HtmlView $this */



$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')
    ->useScript('multiselect')
    ->useScript('core');

$style = '#appList .j2commerce-app-image {width: 140px;}';
$wa->addInlineStyle($style, [], []);

$user      = $this->getCurrentUser();
$userId    = $user->id;
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$saveOrder = $listOrder == 'a.ordering';

if ($saveOrder && !empty($this->items)) {
    $saveOrderingUrl = 'index.php?option=com_j2commerce&task=apps.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
    HTMLHelper::_('draggablelist.draggable');
}

HTMLHelper::_('bootstrap.tooltip', '[data-bs-toggle="tooltip"]', ['placement' => 'top']);
$rawReturn = 'index.php?option=com_j2commerce&view=apps';
$encodedReturn = base64_encode($rawReturn);
$return = rawurlencode($encodedReturn);

?>

<?php echo $this->navbar; ?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=apps'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="fa fa-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table align-middle" id="appList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_J2COMMERCE_APPS_TABLE_CAPTION'); ?>,
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
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_APP_NAME', 'a.name', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" style="width:10%" class="d-none d-md-table-cell">
                                    <?php echo Text::_('COM_J2COMMERCE_APP_VERSION'); ?>
                                </th>
                                <th scope="col" style="width:5%" class="d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.extension_id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody <?php if ($saveOrder) : ?> class="js-draggable" data-url="<?php echo $saveOrderingUrl; ?>" data-direction="<?php echo strtolower($listDirn); ?>" data-nested="true"<?php endif; ?>>
                        <?php foreach ($this->items as $i => $item) :
                            $ordering   = ($listOrder == 'a.ordering');
                            $canEdit    = $user->authorise('core.edit', 'com_j2commerce.app.' . $item->extension_id);
                            $canCheckin = $user->authorise('core.manage', 'com_checkin') || $item->checked_out == $userId || is_null($item->checked_out);
                            $canChange  = $user->authorise('core.edit.state', 'com_j2commerce.app.' . $item->extension_id) && $canCheckin;

                            $imageExtensions = ['jpg', 'png', 'webp'];
                            $imagePath = '';

                            $mediaPrefix = 'plg_' . $item->folder . '_' . $item->element;

                            foreach ($imageExtensions as $extension) {
                                $j2c_path = JPATH_SITE . '/media/' . $mediaPrefix . '/images/' . $item->element . '.' . $extension;
                                if (file_exists($j2c_path)) {
                                    $imagePath = Uri::root(true) . '/media/' . $mediaPrefix . '/images/' . $item->element . '.' . $extension;
                                    break;
                                }
                            }
                            $desc = !empty($item->description) ? Text::_($item->description) : '';
                            ?>
                            <tr class="row<?php echo $i % 2; ?><?php echo !$item->files_exist ? ' table-warning' : ''; ?>" data-draggable-group="none">
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->extension_id, false, 'cid', 'cb', $item->name); ?>
                                </td>
                                <td class="order text-center d-none d-md-table-cell">
                                    <?php
                                    $iconClass = '';
                                    if (!$canChange) {
                                        $iconClass = ' inactive';
                                    } elseif (!$saveOrder) {
                                        $iconClass = ' inactive" title="' . Text::_('JORDERINGDISABLED');
                                    }
                                    ?>
                                    <span class="sortable-handler<?php echo $iconClass; ?>">
                                        <span class="icon-ellipsis-v" aria-hidden="true"></span>
                                    </span>
                                    <?php if ($canChange && $saveOrder) : ?>
                                        <input type="text" name="order[]" size="5" value="<?php echo $item->ordering; ?>" class="width-20 text-area-order hidden" />
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $options = [
                                        'task_prefix' => 'apps.',
                                        'disabled' => !$canChange,
                                        'id' => 'state-' . $item->extension_id
                                    ];
                                    echo (new PublishedButton())->render((int) $item->enabled, $i, $options);
                                    ?>
                                </td>
                                <th scope="row">
                                    <div class="d-block d-lg-flex">
                                        <div class="flex-shrink-0">
                                            <?php if ($item->enabled): ?>
                                            <a href="<?php echo Route::_($item->edit_link . '&return=' . $return); ?>" class="d-none d-lg-inline-block d-md-block">
                                                    <?php else: ?>
                                                    <span class="d-none d-lg-inline-block d-md-block">
                                            <?php endif;?>
                                            <?php if($imagePath):?>
                                                <img src="<?php echo $imagePath; ?>" class="img-fluid j2commerce-app-image" alt="<?php echo $item->display_name; ?>"/>
                                            <?php elseif (file_exists(JPATH_SITE . '/plugins/' . $item->folder . '/' . $item->element . '/images/' . $item->element . '.png')): ?>
                                                <img src="<?php echo Uri::root(true) . '/plugins/' . $item->folder . '/' . $item->element . '/images/' . $item->element . '.png'; ?>" class="img-fluid j2commerce-app-image" alt="<?php echo $item->display_name; ?>"/>
                                            <?php elseif (file_exists(JPATH_SITE . '/media/j2commerce/images/' . $item->element . '.png')): ?>
                                                <img src="<?php echo Uri::root(true) . '/media/j2commerce/images/' . $item->element . '.png'; ?>" class="img-fluid j2commerce-app-image" alt="<?php echo $item->display_name; ?>"/>

                                            <?php else: ?>
                                                <img src="<?php echo Uri::root(true) . '/media/com_j2commerce/images/default_app_j2commerce.webp'; ?>" class="img-fluid j2commerce-app-image" alt="<?php echo $item->display_name; ?>"/>
                                            <?php endif; ?>
                                        <?php if ($item->enabled): ?>
                                            </a>
                                        <?php else: ?>
                                            </span>
                                        <?php endif;?>
                                        </div>
                                        <div class="flex-grow-1 ms-lg-3 mt-0 mt-lg-0">
                                            <div>
                                                <?php if ($item->enabled): ?>
                                                    <a href="<?php echo Route::_($item->edit_link . '&return=' . $return); ?>"><?php echo $item->display_name; ?></a>
                                                <?php else: ?>
                                                    <span class="text-dark"><?php echo $item->display_name; ?></span>
                                                <?php endif; ?>
                                                <?php if ($item->folder !== 'j2commerce'): ?>
                                                    <span class="badge text-bg-info ms-1"><?php echo $this->escape($item->folder); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="small d-none d-md-block"><?php echo $desc; ?></div>
                                            <div class="small d-block d-md-none"><b><?php echo Text::_('COM_J2COMMERCE_APP_VERSION');?>:</b> <?php echo $this->escape($item->version); ?></div>
                                        </div>
                                    </div>

                                </th>
                                <td class="d-none d-md-table-cell">
                                    <?php echo !empty($item->version) ? $this->escape($item->version) : '-'; ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo $item->extension_id; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php // Load the pagination. ?>
                    <?php echo $this->pagination->getListFooter(); ?>

                <?php endif; ?>

                <input type="hidden" name="task" value="" />
                <input type="hidden" name="boxchecked" value="0" />
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>

<?php echo $this->appCards; ?>

<?php echo $this->footer ?? ''; ?>
