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
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Voucher\HtmlView $this */

J2CommerceHelper::strapper()->addCSS();

// TODO call getInvoiceNumber() on order to also trigger AfterGetInvoiceNumber
?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title fs-3">
                    <?php echo Text::_('COM_J2COMMERCE_VOUCHER_HISTORY'); ?>:
                    <span class="badge bg-primary"><?php echo htmlspecialchars($this->item->voucher_code, ENT_QUOTES, 'UTF-8'); ?></span>
                </h2>
            </div>
            <div class="card-body">
                <?php if (!empty($this->orders) && count($this->orders) > 0) : ?>
                    <div class="table-responsive">
                        <table class="table">
                            <caption class="visually-hidden">
                                <?php echo Text::_('COM_J2COMMERCE_VOUCHER_HISTORY_TABLE_CAPTION'); ?>
                            </caption>
                            <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCE_VOUCHER_HISTORY_INVOICE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCE_VOUCHER_HISTORY_ORDER_ID'); ?></th>
                                <th scope="col" class="d-none d-md-table-cell"><?php echo Text::_('COM_J2COMMERCE_VOUCHER_HISTORY_CUSTOMER'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCE_VOUCHER_HISTORY_AMOUNT'); ?></th>
                                <th scope="col" class="d-none d-sm-table-cell"><?php echo Text::_('COM_J2COMMERCE_VOUCHER_HISTORY_DATE'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($this->orders as $item) : ?>
                                <?php
                                $orderLink = Route::_('index.php?option=com_j2commerce&view=order&j2commerce_order_id=' . $item->j2commerce_order_id);
                                ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo $orderLink; ?>" target="_blank" class="text-decoration-none">
                                            <span class="icon-external-link" aria-hidden="true"></span>
                                            <?php if (!empty($item->invoice_number) && count($item->invoice_number) > 0) : ?>
                                                <?php echo htmlspecialchars($item->invoice_prefix . $item->invoice_number, ENT_QUOTES, 'UTF-8'); ?>
                                            <?php else : ?>
                                                <?php echo $item->j2commerce_order_id; ?>
                                            <?php endif; ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="<?php echo $orderLink; ?>" target="_blank" class="text-decoration-none">
                                            <?php echo htmlspecialchars($item->order_id, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <?php echo htmlspecialchars($item->user_email, ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td>
                                            <span class="badge bg-success">
                                                <?php echo htmlspecialchars($item->total, ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                    </td>
                                    <td class="d-none d-sm-table-cell">
                                        <time datetime="<?php echo HTMLHelper::_('date', $item->created_on, 'c'); ?>">
                                            <?php echo HTMLHelper::_('date', $item->created_on, Text::_('DATE_FORMAT_LC1')); ?>
                                        </time>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?>
                    <div class="alert alert-info d-flex align-items-center" role="alert">
                        <span class="icon-info-circle me-2" aria-hidden="true"></span>
                        <div>
                            <?php echo Text::_('COM_J2COMMERCE_VOUCHER_HISTORY_NO_HISTORY'); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
