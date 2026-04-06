<?php
/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_app_boxbuilderproduct
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

$boxbuilderproducts     = (array) $this->product->params->get('boxbuilderproduct', []);
$totalBoxBuilderPrice   = J2CommerceHelper::getBoxBuilderProductTotal($boxbuilderproducts);
?>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeRenderingProductPrice', [$this->product]); ?>

<div class="product-price-container uk-flex uk-flex-middle uk-margin-remove uk-margin-right">
    <span class="sale-price uk-text-large uk-text-bold">
        <?php if (isset($this->product->pricing->price)): ?>
            <?php echo J2CommerceHelper::product()->displayPrice($this->product->pricing->price, $this->product, $this->params); ?>
        <?php endif; ?>
    </span>
    <?php if (($this->product->pricing->price !== $totalBoxBuilderPrice) && ($this->product->pricing->price < $totalBoxBuilderPrice)): ?>
        <?php $class = isset($this->product->pricing->is_discount_pricing_available) ? 'strike' : ''; ?>
        <?php $base_price = J2CommerceHelper::product()->displayPrice($totalBoxBuilderPrice, $this->product, $this->params); ?>
        <del class="uk-text-small uk-text-muted uk-margin-small-left base-price <?php echo $class; ?>"><?php echo $base_price; ?></del>
    <?php endif; ?>
    <?php if ($this->params->get('display_price_with_tax_info', 0)): ?>
        <div class="tax-text">
            <?php echo J2CommerceHelper::product()->get_tax_text(); ?>
        </div>
    <?php endif; ?>
</div>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingProductPrice', [$this->product]); ?>
