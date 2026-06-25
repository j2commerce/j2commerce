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
use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Site\View\Producttags\HtmlView $this */

$product = $this->product;
$params  = $this->params;

$productUrl = !empty($product->product_view_url)
    ? Route::_($product->product_view_url)
    : Route::_(RouteHelper::getProductRoute(
        (int) $product->j2commerce_product_id,
        $product->alias ?? null,
        (int) ($product->catid ?? 0) ?: null
    ));

$imageSrc = $product->thumb_image ?? $product->main_image ?? '';
?>
<div class="j2commerce-product-item card h-100 product-<?php echo (int) $product->j2commerce_product_id; ?> <?php echo $this->escape($product->product_type ?? 'simple'); ?>">
    <?php if ($params->get('list_show_image', 1) && !empty($imageSrc)) : ?>
        <a href="<?php echo $productUrl; ?>">
            <img src="<?php echo $this->escape($imageSrc); ?>"
                 alt="<?php echo $this->escape($product->main_image_alt ?? $product->product_name ?? ''); ?>"
                 class="card-img-top" loading="lazy" />
        </a>
    <?php endif; ?>

    <div class="card-body">
        <?php if ($params->get('list_show_title', 1) && !empty($product->product_name)) : ?>
            <h5 class="card-title">
                <a href="<?php echo $productUrl; ?>"><?php echo $this->escape($product->product_name); ?></a>
            </h5>
        <?php endif; ?>

        <?php if ($params->get('list_show_product_sku', 1) && !empty($product->sku)) : ?>
            <p class="text-muted small mb-1"><?php echo $this->escape($product->sku); ?></p>
        <?php endif; ?>

        <?php if ($params->get('list_show_short_desc', 0) && !empty($product->product_short_desc)) : ?>
            <p class="card-text"><?php echo strip_tags($product->product_short_desc); ?></p>
        <?php endif; ?>

        <?php if ($params->get('list_show_long_desc', 0) && !empty($product->product_long_desc)) : ?>
            <div class="card-text"><?php echo strip_tags($product->product_long_desc); ?></div>
        <?php endif; ?>

        <?php if ($params->get('list_show_product_base_price', 1)) : ?>
            <div class="j2commerce-price fw-bold">
                <?php echo J2CommerceHelper::product()->displayPrice($product->pricing->base_price ?? $product->price ?? 0); ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($params->get('list_show_cart', 1)) : ?>
        <div class="card-footer bg-transparent border-0">
            <a href="<?php echo $productUrl; ?>" class="btn btn-primary w-100">
                <?php echo Text::_('COM_J2COMMERCE_VIEW_PRODUCT'); ?>
            </a>
        </div>
    <?php endif; ?>
</div>
