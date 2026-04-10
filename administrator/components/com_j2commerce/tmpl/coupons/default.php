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
use Joomla\CMS\Session\Session;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Coupons\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')
    ->useScript('multiselect');

$user      = $this->getCurrentUser();
$userId    = $user->id;
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$saveOrder = $listOrder === 'a.ordering';

if ($saveOrder && !empty($this->items)) {
    $saveOrderingUrl = 'index.php?option=com_j2commerce&task=coupons.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
    HTMLHelper::_('draggablelist.draggable');
}

?>

<?php echo $this->navbar; ?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=coupons'); ?>" method="post" name="adminForm" id="adminForm">
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
                    <table class="table itemList" id="couponsList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_J2COMMERCE_COUPONS'); ?>,
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
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_COUPON_NAME', 'a.coupon_name', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_COUPON_CODE', 'a.coupon_code', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-7 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_VALUE', 'a.value', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-8 d-none d-lg-table-cell">
                                    <?php echo Text::_('COM_J2COMMERCE_HEADING_VALUE_TYPE'); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-md-table-cell text-center">
                                    <?php echo Text::_('COM_J2COMMERCE_HEADING_EXPIRATION'); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-lg-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_VALID_FROM', 'a.valid_from', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-lg-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_VALID_TO', 'a.valid_to', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.j2commerce_coupon_id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody <?php if ($saveOrder) : ?> class="js-draggable" data-url="<?php echo $saveOrderingUrl; ?>" data-direction="<?php echo strtolower($listDirn); ?>" data-nested="true"<?php endif; ?>>
                        <?php foreach ($this->items as $i => $item) :
                            $canChange = $user->authorise('core.edit.state', 'com_j2commerce');
                            $canEdit   = $user->authorise('core.edit', 'com_j2commerce');
                        ?>
                            <tr class="row<?php echo $i % 2; ?>" data-draggable-group="none">
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->j2commerce_coupon_id, false, 'cid', 'cb', $item->coupon_name); ?>
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
                                    <span class="sortable-handler<?php echo $iconClass; ?>">
                                        <span class="icon-ellipsis-v" aria-hidden="true"></span>
                                    </span>
                                    <?php if ($canChange && $saveOrder) : ?>
                                        <input type="text" name="order[]" size="5" value="<?php echo $item->ordering; ?>" class="width-20 text-area-order hidden" />
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('jgrid.published', $item->enabled, $i, 'coupons.', $canChange, 'cb'); ?>
                                </td>
                                <th scope="row">
                                    <?php if ($canEdit) : ?>
                                        <a href="<?php echo Route::_('index.php?option=com_j2commerce&task=coupon.edit&id=' . $item->j2commerce_coupon_id); ?>">
                                            <?php echo $this->escape($item->coupon_name); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo $this->escape($item->coupon_name); ?>
                                    <?php endif; ?>
                                </th>
                                <td>
                                    <code><?php echo $this->escape($item->coupon_code); ?></code>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <small><?php echo $item->value_display; ?></small>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <small>
                                        <?php if ($item->value_type === 'percentage') : ?>
                                            <?php echo Text::_('COM_J2COMMERCE_VALUE_TYPE_PERCENTAGE'); ?>
                                        <?php else : ?>
                                            <?php echo Text::_('COM_J2COMMERCE_VALUE_TYPE_FIXED'); ?>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td class="d-none d-md-table-cell text-center">
                                    <?php if ($item->is_expired) : ?>
                                        <span class="<?php echo J2htmlHelper::badgeClass('badge text-bg-danger'); ?>"><?php echo Text::_('COM_J2COMMERCE_COUPON_EXPIRED'); ?></span>
                                    <?php else : ?>
                                        <span class="<?php echo J2htmlHelper::badgeClass('badge text-bg-success'); ?>"><?php echo Text::_('COM_J2COMMERCE_COUPON_ACTIVE'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <?php if ($item->valid_from_formatted) : ?>
                                        <small><?php echo HTMLHelper::_('date', $item->valid_from, Text::_('DATE_FORMAT_LC4')); ?></small>
                                    <?php else : ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <?php if ($item->valid_to_formatted) : ?>
                                        <small class="<?php echo $item->is_expired ? 'text-danger' : ''; ?>">
                                            <?php echo HTMLHelper::_('date', $item->valid_to, Text::_('DATE_FORMAT_LC4')); ?>
                                        </small>
                                    <?php else : ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo (int) $item->j2commerce_coupon_id; ?>
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
