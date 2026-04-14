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
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;
use Joomla\CMS\Language\Text;

$columns  = $this->params->get('item_related_product_columns', 3);
$total    = count($this->up_sells);
$counter  = 0;
$itemId   = isset($this->active_menu->id) ? (int) $this->active_menu->id : 0;
?>

<div class="product-upsells">
    <div class="section__title--box text-start mb-5 mb-xl-7">
        <h2 class="text-uppercase ls-1 mb-4 fs-1"><span class="umarex-underline"><?php echo Text::_('COM_J2COMMERCE_RELATED_PRODUCTS_UPSELLS'); ?></span></h2>
    </div>

    <div class="position-relative mx-md-1">
        <button type="button" class="upsells-prev btn btn-prev btn-icon btn-outline-secondary bg-body rounded-circle position-absolute top-50 start-0 z-2 translate-middle-y ms-n1 d-none d-sm-flex justify-content-center align-items-center" aria-label="Prev">
            <span class="si-chevron-left fs-4 text-center"></span>
        </button>
        <button type="button" class="upsells-next btn btn-next btn-icon btn-outline-secondary bg-body rounded-circle position-absolute top-50 end-0 z-2 translate-middle-y me-n1 d-none d-sm-flex justify-content-center align-items-center" aria-label="Next">
            <span class="si-chevron-right fs-4 text-center"></span>
        </button>

        <div class="swiper py-4" data-swiper='{"slidesPerView": 1,"spaceBetween": 24,"loop": true,"navigation": {"prevEl": ".upsells-prev","nextEl": ".upsells-next"},"breakpoints": {"768": {"slidesPerView": 2}}}'>
            <div class="swiper-wrapper">
                <?php foreach ($this->up_sells as $upsell_product) :
                    $upsell_product->product_link = J2CommerceHelper::platform()->getProductUrl(['task' => 'view', 'id' => $upsell_product->j2commerce_product_id]);
                ?>
                    <div class="swiper-slide">
                        <?php echo ProductLayoutService::renderProductItem(
                            $upsell_product,
                            $this->params,
                            ProductLayoutService::CONTEXT_UPSELL,
                            $itemId
                        ); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- External slider prev/next buttons visible on screens < 500px wide (sm breakpoint) -->
        <div class="d-flex justify-content-center gap-2 mt-n2 mb-3 pb-1 d-sm-none">
            <button type="button" class="upsells-prev btn btn-prev btn-icon btn-outline-secondary bg-body rounded-circle me-1" aria-label="Prev">
                <span class="si-chevron-left fs-3"></span>
            </button>
            <button type="button" class="upsells-next btn btn-next btn-icon btn-outline-secondary bg-body rounded-circle" aria-label="Next">
                <span class="si-chevron-right fs-3"></span>
            </button>
        </div>
    </div>
</div>
