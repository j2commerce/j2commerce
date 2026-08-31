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
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Site\View\Myprofile\HtmlView $this */

// Rendered for the first paint and again for every AJAX search and page change, so an
// override of this file keeps its markup throughout. myprofile.js only relies on the
// #j2c-orders-table-wrap container it replaces and on .j2c-page-link[data-page].
$orders     = $this->orders ?? [];
$search     = $this->ordersSearch;
$params     = $this->params;
$dateFormat = $params->get('date_format', 'Y-m-d');
$total      = isset($this->pagination) ? $this->pagination->total : \count($orders);
$limit      = isset($this->pagination) ? $this->pagination->limit : 20;
?>
<?php if (empty($orders)): ?>
<div class="uk-alert uk-alert-primary" id="j2c-no-orders" uk-alert><?php echo Text::_($search !== '' ? 'COM_J2COMMERCE_NO_ORDERS_MATCH_SEARCH' : 'COM_J2COMMERCE_NO_ORDERS'); ?></div>
<?php else: ?>
<div class="uk-overflow-auto">
    <table class="uk-table uk-table-striped" id="j2c-orders-table">
        <thead>
            <tr>
                <th scope="col"><?php echo Text::_('COM_J2COMMERCE_ORDER_DATE'); ?></th>
                <th scope="col"><?php echo Text::_('COM_J2COMMERCE_INVOICE_NO'); ?></th>
                <th scope="col"><?php echo Text::_('COM_J2COMMERCE_ORDER_STATUS'); ?></th>
                <th scope="col" class="uk-text-right"><?php echo Text::_('COM_J2COMMERCE_ORDER_AMOUNT'); ?></th>
                <th scope="col" class="uk-text-center" style="width:1%"><span class="uk-hidden-visually"><?php echo Text::_('COM_J2COMMERCE_ACTIONS'); ?></span></th>
            </tr>
        </thead>
        <tbody id="j2c-orders-body">
            <?php foreach ($orders as $item): ?>
            <?php
            $cssClass   = !empty($item->orderstatus_cssclass) ? $item->orderstatus_cssclass : 'uk-badge';
            $statusName = Text::_($item->orderstatus_name ?? '');
            ?>
            <?php $orderViewUrl = Route::_('index.php?option=com_j2commerce&view=myprofile&layout=order&order_id=' . urlencode($item->order_id)); ?>
            <tr>
                <td><a href="<?php echo $orderViewUrl; ?>"><?php echo HTMLHelper::_('date', $item->created_on, $dateFormat); ?></a></td>
                <td><a href="<?php echo $orderViewUrl; ?>"><?php echo $this->escape($item->order_id); ?></a></td>
                <td><span class="uk-badge <?php echo $this->escape($cssClass); ?>"><?php echo $this->escape($statusName); ?></span></td>
                <td class="uk-text-right">
                    <?php echo CurrencyHelper::format((float) $item->order_total,$item->currency_code ?? '',(float) ($item->currency_value ?? 1)); ?>
                </td>
                <td class="uk-text-center uk-text-nowrap">
                    <a href="<?php echo $orderViewUrl; ?>"
                       class="uk-button uk-button-small uk-button-default"
                       aria-label="<?php echo Text::sprintf('COM_J2COMMERCE_ORDER_VIEW_X', $this->escape($item->order_id)); ?>"
                       title="<?php echo Text::sprintf('COM_J2COMMERCE_ORDER_VIEW_X', $this->escape($item->order_id)); ?>">
                        <span uk-icon="icon: eye" aria-hidden="true"></span>
                    </a>
                    <button type="button" class="uk-button uk-button-small uk-button-default j2commerce-order-print" data-url="<?php echo Route::_('index.php?option=com_j2commerce&view=myprofile&layout=order&order_id=' . urlencode($item->order_id) . '&tmpl=component'); ?>" title="<?php echo Text::_('COM_J2COMMERCE_ORDER_PRINT'); ?>">
                        <span uk-icon="icon: print" aria-hidden="true"></span>
                    </button>
                    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayOrder', [$item])->getArgument('html', ''); ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<div class="uk-flex uk-flex-right uk-flex-middle" id="j2c-orders-pagination">
    <?php
    $start = $this->pagination ? $this->pagination->limitstart + 1 : 1;
    $end   = min($start + $limit - 1, $total);
    ?>
    <?php if ($total > $limit): ?>
    <nav aria-label="<?php echo Text::_('JLIB_HTML_PAGINATION'); ?>">
        <ul class="uk-pagination uk-margin-remove" id="j2c-pagination-list">
            <?php
            $pages = (int) ceil($total / $limit);
            $currentPage = $this->pagination ? (int) floor($this->pagination->limitstart / $limit) : 0;

            for ($p = 0; $p < $pages; $p++):
                $active = ($p === $currentPage) ? ' uk-active' : '';
            ?>
            <li class="<?php echo $active; ?>">
                <a class="j2c-page-link" href="#" data-page="<?php echo $p; ?>"><?php echo $p + 1; ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <span class="uk-text-meta uk-text-small uk-margin-small-left" id="j2c-orders-count">
        <?php echo $start . ' - ' . $end . ' / ' . $total . ' ' . Text::_('COM_J2COMMERCE_ITEMS'); ?>
    </span>
</div>
<?php endif; ?>
