<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Site\View\Carts\HtmlView $this */

?>
<div class="cart-totals-block">
    <h3><?php echo Text::_('COM_J2COMMERCE_CART_TOTALS'); ?></h3>

    <table class="cart-footer table table-bordered">
        <?php if ($this->order && ($totals = $this->order->get_formatted_order_totals())): ?>
            <?php foreach ($totals as $total): ?>
                <tr>
                    <th scope="row" colspan="2">
                        <?php echo $total['label']; ?>
                        <?php if (isset($total['link'])): ?>
                            <?php echo $total['link']; ?>
                        <?php endif; ?>
                    </th>
                    <td class="text-end"><?php echo $total['value']; ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <div class="d-grid gap-2 mt-3">
        <span class="cart-checkout-button">
            <a class="btn btn-lg btn-primary d-flex align-items-center justify-content-center py-3" href="<?php echo Route::_($this->checkout_url); ?>">
                <span class="fa-solid fa-lock me-2 small lh-1 mt-1" aria-hidden="true"></span>
                <span class="cart-button-title lh-1"><?php echo Text::_('COM_J2COMMERCE_PROCEED_TO_CHECKOUT'); ?></span>
                <span class="fa-solid fa-chevron-right ms-2 lh-1 small align-self-center mt-1" aria-hidden="true"></span>
            </a>
        </span>
        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayCheckoutButton', [$this->order]); ?>
    </div>
</div>
