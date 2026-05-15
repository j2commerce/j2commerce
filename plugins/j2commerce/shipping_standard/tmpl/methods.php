<?php

/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.ShippingStandard
 *
 * @copyright   Copyright (C) 2024-2026 J2Commerce, LLC. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/**
 * Template variables provided by ShippingStandard::renderMethodsList():
 *
 * @var  array                                       $items         List of shipping method objects
 * @var  \Joomla\CMS\Pagination\Pagination           $pagination    Pagination object
 * @var  \Joomla\CMS\Form\Form                       $filterForm    Filter form
 * @var  array                                       $activeFilters Active filters
 * @var  string                                      $listOrder     Current ordering column
 * @var  string                                      $listDirn      Current ordering direction
 * @var  array                                       $typeLabels    Shipping type labels by type ID
 * @var  object                                      $state         State object with filter/list values
 */

$wa = \Joomla\CMS\Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')
    ->useScript('multiselect');

?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=shippingplugin&plugin=shipping_standard&pluginview=methods'); ?>"
      method="post" name="adminForm" id="adminForm">

    <input type="hidden" name="plugin" value="shipping_standard">

    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => (object) [
                    'filterForm'    => $filterForm,
                    'activeFilters' => $activeFilters,
                ]]); ?>

                <?php if (empty($items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span>
                        <span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table itemList" id="shippingMethodsList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('PLG_J2COMMERCE_SHIPPING_STANDARD_METHODS_TABLE'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?></span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col" class="w-1 text-center">
                                    #
                                </th>
                                <td class="w-1 text-center">
                                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                                </td>
                                <th scope="col" class="w-5 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_SHIPPING_METHOD_ID', 'a.j2commerce_shippingmethod_id', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_SHIPPING_METHOD_NAME', 'a.shipping_method_name', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 text-center d-none d-md-table-cell">
                                    <?php echo Text::_('COM_J2COMMERCE_SHIPPING_SET_RATES'); ?>
                                </th>
                                <th scope="col" class="w-15 d-none d-md-table-cell">
                                    <?php echo Text::_('COM_J2COMMERCE_HEADING_TAX_CLASS_NAME'); ?>
                                </th>
                                <th scope="col" class="w-5 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.published', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $i => $item) : ?>
                            <?php
                            $editUrl = Route::_('index.php?option=com_j2commerce&view=shippingplugin&plugin=shipping_standard&pluginview=method&id=' . $item->j2commerce_shippingmethod_id);
                            $ratesUrl = Route::_('index.php?option=com_j2commerce&view=shippingplugin&plugin=shipping_standard&pluginview=setrates&id=' . $item->j2commerce_shippingmethod_id);
                            $typeLabel = Text::_($typeLabels[(int) $item->shipping_method_type] ?? 'COM_J2COMMERCE_UNKNOWN');
                            $maxSubtotal = (float) $item->subtotal_maximum;
                            ?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td class="text-center">
                                    <?php echo $i + 1; ?>
                                </td>
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->j2commerce_shippingmethod_id, false, 'cid', 'cb', $item->shipping_method_name); ?>
                                </td>
                                <td class="text-center">
                                    <a href="<?php echo $editUrl; ?>">
                                        <?php echo (int) $item->j2commerce_shippingmethod_id; ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="<?php echo $editUrl; ?>">
                                        <?php echo htmlspecialchars($item->shipping_method_name, ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                    <div class="small">
                                        <strong><?php echo Text::_('COM_J2COMMERCE_HEADING_SHIPPING_TYPE'); ?>:</strong>
                                        <?php echo $typeLabel; ?>
                                    </div>
                                    <div class="small">
                                        <strong><?php echo Text::_('COM_J2COMMERCE_SHIPPING_MAX_SUBTOTAL_REQUIRED'); ?>:</strong>
                                        <?php echo $maxSubtotal > 0 ? CurrencyHelper::format($maxSubtotal) : Text::_('COM_J2COMMERCE_UNLIMITED'); ?>
                                    </div>
                                </td>
                                <td class="text-center d-none d-md-table-cell">
                                    <a href="<?php echo $ratesUrl; ?>">
                                        [ <?php echo Text::_('COM_J2COMMERCE_SHIPPING_SET_RATES'); ?> ]
                                    </a>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo !empty($item->taxprofile_name) ? htmlspecialchars($item->taxprofile_name, ENT_QUOTES, 'UTF-8') : '&mdash;'; ?>
                                </td>
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'shippingplugin.', true, 'cb'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php echo $pagination->getListFooter(); ?>
                <?php endif; ?>

                <input type="hidden" name="task" value="">
                <input type="hidden" name="boxchecked" value="0">
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
