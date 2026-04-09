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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

/** @var \J2Commerce\Component\J2commerce\Site\View\Product\HtmlView $this */

$totalBundlePrice = (float) ($this->product->bundle_total_price ?? 0.0);
?>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeRenderingProductPrice', [$this->product, $this->context])->getArgument('html', ''); ?>

<?php if ($this->params->get('item_show_product_base_price', 1) || $this->params->get('item_show_product_special_price', 1)) : ?>
    <div class="j2commerce-product-price-container mb-2">
        <div class="d-flex align-items-center">
            <?php if ($this->params->get('item_show_product_special_price', 1)) : ?>
                <div class="sale-price">
                    <?php if (isset($this->product->pricing->price)) {
                        echo J2CommerceHelper::product()->displayPrice($this->product->pricing->price, $this->product, $this->params);
                    } ?>
                </div>
            <?php endif; ?>

            <?php if ($this->params->get('item_show_product_base_price', 1) && $totalBundlePrice > 0 && isset($this->product->pricing->price) && (float) $this->product->pricing->price < $totalBundlePrice) : ?>
                <del class="base-price text-muted ms-2">
                    <?php echo J2CommerceHelper::product()->displayPrice($totalBundlePrice, $this->product, $this->params); ?>
                </del>
            <?php elseif ($this->params->get('item_show_product_base_price', 1)) : ?>
                <del class="base-price text-muted ms-2"></del>
            <?php endif; ?>
        </div>

        <?php if ($this->params->get('display_price_with_tax_info', 0)) : ?>
            <div class="tax-text d-block text-muted small mt-1">
                <?php echo J2CommerceHelper::product()->get_tax_text(); ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingProductPrice', [$this->product, $this->context])->getArgument('html', ''); ?>
