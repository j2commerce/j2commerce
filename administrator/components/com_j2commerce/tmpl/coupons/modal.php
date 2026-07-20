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

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Coupons\HtmlView $this */

$app       = Factory::getApplication();
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));

/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('core')
    ->useScript('multiselect')
    ->useScript('modal-content-select');
?>
<div class="container-popup">
    <form action="<?php echo Route::_('index.php?option=com_j2commerce&view=coupons&layout=modal&tmpl=component'); ?>" method="post" name="adminForm" id="adminForm">

        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

        <?php if (empty($this->items)) : ?>
            <div class="alert alert-info">
                <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
            </div>
        <?php else : ?>
            <table class="table table-sm itemList align-middle" id="couponsList">
                <caption class="visually-hidden">
                    <?php echo Text::_('COM_J2COMMERCE_COUPONS_TABLE_CAPTION'); ?>,
                    <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                    <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                </caption>
                <thead>
                    <tr>
                        <th scope="col" class="w-1 text-center">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.enabled', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_COUPON_NAME', 'a.coupon_name', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-25 d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_COUPON_CODE', 'a.coupon_code', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-5 d-none d-md-table-cell text-center">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.j2commerce_coupon_id', $listDirn, $listOrder); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($this->items as $i => $item) : ?>
                    <?php
                    $couponName = $item->coupon_name !== '' ? $item->coupon_name : Text::_('COM_J2COMMERCE_COUPON') . ' #' . (int) $item->j2commerce_coupon_id;
                    $title      = $item->coupon_code !== '' ? $couponName . ' (' . $item->coupon_code . ')' : $couponName;
                    $attribs    = 'data-content-select data-content-type="com_j2commerce.coupon"'
                        . ' data-id="' . (int) $item->j2commerce_coupon_id . '"'
                        . ' data-title="' . $this->escape($title) . '"';
                    ?>
                    <tr class="row<?php echo $i % 2; ?>">
                        <td class="text-center tbody-icon">
                            <span class="<?php echo $item->enabled ? 'icon-publish' : 'icon-unpublish'; ?>" aria-hidden="true"
                                title="<?php echo $item->enabled ? Text::_('JENABLED') : Text::_('JDISABLED'); ?>"></span>
                        </td>
                        <th scope="row">
                            <a class="select-link fw-semibold" href="javascript:void(0)" <?php echo $attribs; ?>>
                                <?php echo $this->escape($couponName); ?>
                            </a>
                        </th>
                        <td class="d-none d-md-table-cell">
                            <span class="font-monospace"><?php echo $this->escape($item->coupon_code ?: '-'); ?></span>
                        </td>
                        <td class="text-center d-none d-md-table-cell">
                            <?php echo (int) $item->j2commerce_coupon_id; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php echo $this->pagination->getListFooter(); ?>
        <?php endif; ?>

        <input type="hidden" name="task" value="">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
