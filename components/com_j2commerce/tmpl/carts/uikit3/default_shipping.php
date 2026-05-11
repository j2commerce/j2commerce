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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Site\View\Carts\HtmlView $this */

// Only show if calculator is enabled
if (!$this->params->get('show_tax_calculator', 1)) {
    return;
}

// If no methods available, render nothing (wrapper is in default.php)
if (empty($this->shipping_methods)) {
    return;
}

$baseUrl = Route::_('index.php');

?>
<div class="card mb-3">
    <div class="card-body">
        <h5 class="card-title"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_SELECT_A_SHIPPING_METHOD'); ?></h5>

        <form action="<?php echo $baseUrl; ?>"
              name="j2commerce-cart-shipping-form"
              id="j2commerce-cart-shipping-form"
              enctype="multipart/form-data">

            <div id="j2commerce-cart-shipping" class="j2commerce-cart-shipping">
                <?php foreach ($this->shipping_methods as $method): ?>
                    <?php
                    $checked = '';
                    if (isset($this->shipping_values['shipping_name']) && $this->shipping_values['shipping_name'] === $method['name']) {
                        $checked = 'checked';
                    }
                    $methodId = 'shipping_' . $method['element'] . '_' . str_replace(' ', '', $method['name']);
                    ?>
                    <div class="form-check mb-2 d-flex align-items-center gap-2">
                        <input type="radio"
                               class="form-check-input flex-shrink-0 mt-0 shipping-method-radio"
                               id="<?php echo $methodId; ?>"
                               name="shipping_method"
                               <?php echo $checked; ?>
                               data-name="<?php echo htmlspecialchars($method['name'], ENT_QUOTES); ?>"
                               data-price="<?php echo $method['price']; ?>"
                               data-tax="<?php echo $method['tax']; ?>"
                               data-extra="<?php echo $method['extra']; ?>"
                               data-element="<?php echo htmlspecialchars($method['element'], ENT_QUOTES); ?>"
                               data-code="<?php echo htmlspecialchars($method['code'], ENT_QUOTES); ?>"
                               data-tax-class-id="<?php echo (int) ($method['tax_class_id'] ?? 0); ?>" />
                        <?php if (!empty($method['image'])) : ?>
                            <img src="<?php echo htmlspecialchars($method['image']); ?>" alt="" class="flex-shrink-0" style="height:20px;">
                        <?php endif; ?>
                        <label class="form-check-label flex-grow-1" for="<?php echo $methodId; ?>">
                            <?php echo stripslashes(Text::_($method['name'])); ?>
                            (<?php echo $this->currency->format($method['price']); ?>)
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <input type="hidden" name="shipping_price" id="shipping_price" value="" />
            <input type="hidden" name="shipping_tax" id="shipping_tax" value="" />
            <input type="hidden" name="shipping_name" id="shipping_name" value="" />
            <input type="hidden" name="shipping_code" id="shipping_code" value="" />
            <input type="hidden" name="shipping_extra" id="shipping_extra" value="" />
            <input type="hidden" name="shipping_tax_class_id" id="shipping_tax_class_id" value="" />
            <input type="hidden" name="shipping_plugin" id="shipping_plugin" value="" />
        </form>
    </div>
</div>
