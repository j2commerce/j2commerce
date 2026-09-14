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

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Ordervariants\HtmlView $this */

// Each Select button posts its data-* attributes to the order editor as a joomla:content-select message.
$this->getDocument()->getWebAssetManager()
    ->useScript('core')
    ->useScript('modal-content-select');

$line      = $this->line;
$currency  = (string) $line->currency_code;
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
?>
<div class="container-popup">
    <form action="<?php echo Route::_('index.php?option=com_j2commerce&view=ordervariants&layout=modal&tmpl=component&orderitem_id=' . (int) $line->j2commerce_orderitem_id); ?>" method="post" name="adminForm" id="adminForm">

        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

        <?php if (empty($this->items)) : ?>
            <div class="alert alert-info">
                <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
            </div>
        <?php else : ?>
            <table class="table itemList align-middle" id="ordervariantsList">
                <caption class="visually-hidden">
                    <?php echo $this->escape(Text::sprintf('COM_J2COMMERCE_ORDERITEM_VARIANTS_CAPTION', $line->orderitem_name)); ?>,
                    <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                    <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                </caption>
                <thead>
                <tr>
                    <th scope="col">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_VARIANT_OPTIONS', 'variant_options', $listDirn, $listOrder); ?>
                    </th>
                    <th scope="col" class="w-15">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_SKU', 'v.sku', $listDirn, $listOrder); ?>
                    </th>
                    <th scope="col" class="w-10 text-end">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_PRICE', 'v.price', $listDirn, $listOrder); ?>
                    </th>
                    <th scope="col" class="w-15">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_STOCK', 'stock_quantity', $listDirn, $listOrder); ?>
                    </th>
                    <th scope="col" class="w-15 text-end">
                        <?php echo Text::_('COM_J2COMMERCE_SELECT'); ?>
                    </th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($this->items as $i => $item) :
                    $options = $item->options_label !== '' ? $item->options_label : Text::_('JNONE');
                    $sku     = (string) ($item->sku ?? '');
                    ?>
                    <tr class="row<?php echo $i % 2; ?>">
                        <th scope="row" class="fw-normal">
                            <?php echo $this->escape($options); ?>
                            <?php if ($item->is_current) : ?>
                                <span class="badge text-bg-info ms-1"><?php echo Text::_('COM_J2COMMERCE_ORDERITEM_VARIANT_CURRENT'); ?></span>
                            <?php endif; ?>
                        </th>
                        <td class="font-monospace"><?php echo $this->escape($sku); ?></td>
                        <td class="text-end"><?php echo $this->escape(CurrencyHelper::format((float) $item->price, $currency)); ?></td>
                        <td>
                            <?php if (!$item->manages_stock) : ?>
                                <?php echo Text::_('COM_J2COMMERCE_ORDERITEM_VARIANT_NOT_TRACKED'); ?>
                            <?php elseif ($item->available > 0) : ?>
                                <?php echo Text::sprintf('COM_J2COMMERCE_IN_STOCK_WITH_QUANTITY', $item->available); ?>
                            <?php else : ?>
                                <?php echo Text::_('COM_J2COMMERCE_OUT_OF_STOCK'); ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($item->is_current) : ?>
                                <span class="small"><?php echo Text::_('COM_J2COMMERCE_ORDERITEM_VARIANT_IN_USE'); ?></span>
                            <?php elseif (!$item->can_fulfil) : ?>
                                <span class="small"><?php echo Text::_('COM_J2COMMERCE_ORDERITEM_VARIANT_NOT_ENOUGH_STOCK'); ?></span>
                            <?php else : ?>
                                <button type="button" class="btn btn-sm btn-primary"
                                        data-content-select
                                        data-content-type="com_j2commerce.ordervariant"
                                        data-id="<?php echo (int) $item->j2commerce_variant_id; ?>">
                                    <?php echo Text::_('COM_J2COMMERCE_SELECT'); ?>
                                    <span class="visually-hidden"><?php echo $this->escape($sku !== '' ? $sku . ' (' . $options . ')' : $options); ?></span>
                                </button>
                            <?php endif; ?>
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
