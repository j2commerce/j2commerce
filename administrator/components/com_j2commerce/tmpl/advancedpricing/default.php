<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns');
$wa->useScript('multiselect');
?>

<?php echo $this->navbar ?? ''; ?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=advancedpricing'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table itemList align-middle" id="advancedPricingList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_J2COMMERCE_ADVANCED_PRICING'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?></span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                        </caption>
                        <thead>
                            <tr>
                                <td class="w-1 text-center">
                                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                                </td>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_PRODUCT_NAME', 'product_name', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-5 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_PRODUCT_ID', 'v.product_id', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10">
                                    <?php echo Text::_('COM_J2COMMERCE_HEADING_VARIANT_SKU'); ?>
                                </th>
                                <th scope="col" class="w-10 text-center">
                                    <?php echo Text::_('COM_J2COMMERCE_HEADING_QUANTITY_RANGE'); ?>
                                </th>
                                <th scope="col" class="w-10">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_DATE_FROM', 'pp.date_from', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_DATE_TO', 'pp.date_to', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_USER_GROUP', 'group_name', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-15">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_PRICE', 'pp.price', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->items as $i => $item) : ?>
                                <tr>
                                    <td class="text-center">
                                        <?php echo HTMLHelper::_('grid.id', $i, $item->j2commerce_productprice_id); ?>
                                    </td>
                                    <th scope="row">
                                        <?php echo $this->escape($item->product_name ?? ''); ?>
                                    </th>
                                    <td class="text-center">
                                        <?php echo (int) ($item->product_id ?? 0); ?>
                                    </td>
                                    <td>
                                        <?php echo (int) $item->variant_id; ?>
                                        <?php if (!empty($item->sku)) : ?>
                                            <small class="text-muted">(<?php echo $this->escape($item->sku); ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $qtyFrom = $item->quantity_from !== null ? rtrim(rtrim(number_format((float) $item->quantity_from, 2, '.', ''), '0'), '.') : '';
                                        $qtyTo   = $item->quantity_to !== null ? rtrim(rtrim(number_format((float) $item->quantity_to, 2, '.', ''), '0'), '.') : '';

                                        if ($qtyFrom !== '' && $qtyTo !== '') {
                                            echo $this->escape($qtyFrom) . ' &ndash; ' . $this->escape($qtyTo);
                                        } elseif ($qtyFrom !== '') {
                                            echo $this->escape($qtyFrom) . '+';
                                        } elseif ($qtyTo !== '') {
                                            echo '&le; ' . $this->escape($qtyTo);
                                        } else {
                                            echo '<span class="text-muted">&mdash;</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo !empty($item->date_from) ? HTMLHelper::_('date', $item->date_from, Text::_('DATE_FORMAT_LC4')) : '<span class="text-muted">&mdash;</span>'; ?>
                                    </td>
                                    <td>
                                        <?php echo !empty($item->date_to) ? HTMLHelper::_('date', $item->date_to, Text::_('DATE_FORMAT_LC4')) : '<span class="text-muted">&mdash;</span>'; ?>
                                    </td>
                                    <td>
                                        <?php echo $this->escape($item->group_name ?? ''); ?>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number"
                                                   class="form-control form-control-sm advancedpricing-price-input"
                                                   value="<?php echo number_format((float) $item->price, 2, '.', ''); ?>"
                                                   data-id="<?php echo (int) $item->j2commerce_productprice_id; ?>"
                                                   data-original="<?php echo number_format((float) $item->price, 2, '.', ''); ?>"
                                                   step="0.01"
                                                   min="0" />
                                            <button type="button" class="btn btn-sm btn-primary price-save-btn" data-id="<?php echo (int) $item->j2commerce_productprice_id; ?>" title="<?php echo Text::_('JAPPLY'); ?>">
                                                <span class="icon-save" aria-hidden="true"></span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>

                <?php echo $this->loadTemplate('batch'); ?>

                <input type="hidden" name="task" value="" />
                <input type="hidden" name="boxchecked" value="0" />
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>

<?php echo $this->footer ?? ''; ?>
