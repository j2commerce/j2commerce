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

    <table class="cart-footer uk-table uk-table-striped">
        <?php if ($this->order && ($totals = $this->order->get_formatted_order_totals())): ?>
            <?php foreach ($totals as $total): ?>
                <tr>
                    <th scope="row" colspan="2">
                        <?php echo $total['label']; ?>
                        <?php if (isset($total['link'])): ?>
                            <?php echo $total['link']; ?>
                        <?php endif; ?>
                    </th>
                    <td class="uk-text-right"><?php echo $total['value']; ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <div class="uk-margin-top">
        <span class="cart-checkout-button">
            <a class="uk-button uk-button-primary uk-button-large uk-width-1-1 uk-flex uk-flex-middle uk-flex-center" href="<?php echo Route::_($this->checkout_url); ?>">
                <span class="uk-margin-small-right" uk-icon="icon: lock; ratio: 0.8" aria-hidden="true"></span>
                <span class="cart-button-title"><?php echo Text::_('COM_J2COMMERCE_PROCEED_TO_CHECKOUT'); ?></span>
                <span class="uk-margin-small-left" uk-icon="icon: chevron-right; ratio: 0.8" aria-hidden="true"></span>
            </a>
        </span>
        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayCheckoutButton', [$this->order]); ?>
    </div>
</div>
