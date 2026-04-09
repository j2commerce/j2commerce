<?php
/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_app_boxbuilderproduct
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Plugin\J2Commerce\AppBoxbuilderproduct\Helper\BoxbuilderproductHelper;
use Joomla\Registry\Registry;

$productParams = $this->product->params instanceof Registry
    ? $this->product->params
    : new Registry($this->product->params ?? '{}');

$boxbuilderproducts   = (array) $productParams->get('boxbuilderproduct', []);
$totalBoxBuilderPrice = BoxbuilderproductHelper::getBoxBuilderProductTotal($boxbuilderproducts);
$productHelper        = J2CommerceHelper::product();
?>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeRenderingProductPrice', [$this->product, $this->context]); ?>

<div class="product-price-container fs-2 mb-0 me-3 fw-bold d-flex align-items-center">
    <span class="sale-price">
        <?php if (isset($this->product->pricing->price)): ?>
            <?php echo $productHelper->displayPrice($this->product->pricing->price, $this->product, $this->params); ?>
        <?php endif; ?>
    </span>
    <?php if ($totalBoxBuilderPrice > 0 && ($this->product->pricing->price !== $totalBoxBuilderPrice) && ($this->product->pricing->price < $totalBoxBuilderPrice)): ?>
        <?php $class = isset($this->product->pricing->is_discount_pricing_available) ? 'strike' : ''; ?>
        <del class="fs-6 fw-normal text-body-tertiary ms-2 base-price <?php echo $class; ?>">
            <?php echo $productHelper->displayPrice($totalBoxBuilderPrice, $this->product, $this->params); ?>
        </del>
    <?php endif; ?>
    <?php if ($this->params->get('display_price_with_tax_info', 0)): ?>
        <div class="tax-text">
            <?php echo $productHelper->get_tax_text(); ?>
        </div>
    <?php endif; ?>
</div>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingProductPrice', [$this->product, $this->context]); ?>
