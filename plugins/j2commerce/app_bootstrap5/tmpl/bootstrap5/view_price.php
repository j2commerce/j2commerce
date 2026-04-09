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
use Joomla\CMS\Language\Text;

/** @var \J2Commerce\Component\J2commerce\Site\View\Product\HtmlView $this */

?>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeRenderingProductPrice', [$this->product, $this->context])->getArgument('html', ''); ?>

<?php if ($this->params->get('item_show_product_base_price', 1) || $this->params->get('item_show_product_special_price', 1)) : ?>
    <div class="j2commerce-product-price-container mb-3">
        <div class="d-flex align-items-center">
            <?php if ($this->params->get('item_show_product_special_price', 1)) : ?>
                <div class="sale-price lh-1 fs-3 fw-normal">
                    <?php if (isset($this->product->pricing->price)) {
                        echo J2CommerceHelper::product()->displayPrice($this->product->pricing->price, $this->product, $this->params);
                    } ?>
                </div>
            <?php endif; ?>

            <?php if ($this->params->get('item_show_product_base_price', 1) && isset($this->product->pricing->base_price) && isset($this->product->pricing->price)
                && $this->product->pricing->base_price != $this->product->pricing->price) : ?>

                <?php $base_price = J2CommerceHelper::product()->displayPrice($this->product->pricing->base_price, $this->product, $this->params); ?>
                <del class="base-price fs-5 fw-normal text-body-tertiary lh-1 ms-2">
                    <?php echo $base_price; ?>
                </del>
            <?php endif; ?>
        </div>
        <?php if ($this->params->get('display_price_with_tax_info', 0)) : ?>
            <div class="tax-text d-block text-body-tertiary small mt-1">
                <?php echo J2CommerceHelper::product()->get_tax_text(); ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingProductPrice', [$this->product, $this->context])->getArgument('html', ''); ?>


