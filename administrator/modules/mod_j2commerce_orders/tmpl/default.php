<?php

/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_orders
 *
 * @copyright   (C) 2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;


if (empty($orders)) {
    return;
}
?>
<div class="j2commerce_latest_orders">
    <table class="table itemList align-middle mb-0" id="j2commerce-orders-<?php echo $module->id; ?>">
        <caption class="visually-hidden"><?php echo Text::_('MOD_J2COMMERCE_ORDERS_LATEST_ORDERS'); ?></caption>
        <thead>
        <tr>
            <th scope="col"><?php echo Text::_('COM_J2COMMERCE_HEADING_ORDER'); ?></th>
            <th scope="col" class="d-none d-lg-table-cell"><?php echo Text::_('COM_J2COMMERCE_HEADING_DATE'); ?></th>
            <th scope="col"><?php echo Text::_('COM_J2COMMERCE_HEADING_CUSTOMER'); ?></th>
            <th scope="col" class="d-none d-lg-table-cell text-center"><?php echo Text::_('COM_J2COMMERCE_HEADING_STATUS'); ?></th>
            <th scope="col" class="text-end"><?php echo Text::_('COM_J2COMMERCE_HEADING_TOTAL'); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $i => $order) :
            $customerName = trim(($order->billing_first_name ?? '') . ' ' . ($order->billing_last_name ?? ''));
            if (empty($customerName)) {
                $customerName = $order->user_email ?? Text::_('COM_J2COMMERCE_GUEST');
            }
            $orderViewUrl = Route::_('index.php?option=com_j2commerce&view=order&layout=view&id=' . (int) $order->j2commerce_order_id);
            ?>
            <tr class="row<?php echo $i % 2; ?>">
                <th scope="row">
                    <a href="<?php echo $orderViewUrl; ?>" class="small">
                        <?php echo htmlspecialchars((string) $order->order_id, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </th>
                <td class="d-none d-lg-table-cell">
                    <small><?php echo HTMLHelper::_('date', $order->created_on, $date_format); ?></small>
                </td>
                <td>
                    <small><?php echo htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'); ?></small>
                </td>
                <td class="d-none d-lg-table-cell text-center">
                    <span class="order-status-badge <?php echo htmlspecialchars($order->orderstatus_cssclass ?? 'badge text-bg-secondary', ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo Text::_($order->orderstatus_name ?? 'COM_J2COMMERCE_UNKNOWN'); ?>
                    </span>
                </td>
                <td class="text-end">
                    <strong class="small"><?php echo CurrencyHelper::format((float) $order->order_total, $order->currency_code ?? '', (float) ($order->currency_value ?? 1)); ?></strong>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
