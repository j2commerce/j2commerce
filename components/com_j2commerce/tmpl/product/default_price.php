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

dump($this->item->pricing);
?>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeRenderingProductPrice', [$this->item])->getArgument('html'); ?>

<?php if ($this->params->get('item_show_product_base_price', 1) || $this->params->get('item_show_product_special_price', 1)) : ?>
    <div class="product-price-container fs-3 mb-0 me-3 fw-bold d-flex align-items-center">
        <?php if ($this->params->get('item_show_product_special_price', 1)) : ?>
            <div class="sale-price">
                <?php if (isset($this->item->pricing->price)) : ?>
                    <?php echo J2CommerceHelper::product()->displayPrice($this->item->pricing->price, $this->item, $this->params); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

		<?php if($this->params->get('item_show_product_base_price', 1) && isset($this->item->pricing->base_price) && isset($this->item->pricing->price) && ($this->item->pricing->base_price != $this->item->pricing->price)): ?>
            <del class="fs-6 fw-normal text-body-tertiary ms-2 base-price<?php echo isset($this->item->pricing->is_discount_pricing_available) ? ' strike' : ''; ?>"><?php echo J2CommerceHelper::product()->displayPrice($this->item->pricing->base_price, $this->item, $this->params);?></del>
		<?php endif; ?>



        <?php if ($this->params->get('display_price_with_tax_info', 0)) : ?>
            <div class="tax-text">
                <?php echo J2CommerceHelper::product()->get_tax_text(); ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingProductPrice', [$this->item])->getArgument('html'); ?>

<?php if ($this->params->get('item_show_discount_percentage', 1)) : ?>
    <div class="discount-percentage">
        <?php if (isset($this->item->pricing->is_discount_pricing_available) && isset($this->item->pricing->base_price) && !empty($this->item->pricing->base_price) && $this->item->pricing->base_price > 0) : ?>
            <?php $discount = (1 - ($this->item->pricing->price / $this->item->pricing->base_price)) * 100; ?>
            <?php if ($discount > 0) : ?>
                <?php echo Text::sprintf('J2COMMERCE_PRODUCT_OFFER', round($discount) . '%'); ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
