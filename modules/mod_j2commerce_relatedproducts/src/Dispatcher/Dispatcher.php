<?php

/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_relatedproducts
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Module\RelatedProducts\Site\Dispatcher;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\Registry\Registry;

class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    public function dispatch(): void
    {
        try {
            parent::dispatch();
        } finally {
            ProductLayoutService::clearSubtemplateOverride();
        }
    }

    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();

        $lang = $this->app->getLanguage();
        $lang->load('com_j2commerce', JPATH_SITE);

        /** @var \Joomla\Registry\Registry $moduleParams */
        $moduleParams = $data['params'];

        $data['params']        = $this->buildLayoutParams($moduleParams);
        $data['itemId']        = (int) ($this->app->getMenu()->getActive()?->id ?? 0);
        $data['layoutType']    = $moduleParams->get('layout_type', 'slider');
        $data['moduleId']      = (int) $data['module']->id;
        $data['showHeading']   = (bool) $moduleParams->get('show_heading', 1);
        $data['headingText']   = $moduleParams->get('heading_text', '');
        $data['relationType']  = $moduleParams->get('relation_type', 'cross_sells');
        $data['ajaxRefresh']   = (bool) $moduleParams->get('ajax_refresh', 1);

        try {
            J2CommerceHelper::strapper()->loadProductListAssets();
        } catch (\Throwable) {
        }

        if ($data['layoutType'] === 'slider') {
            $wa = $this->app->getDocument()->getWebAssetManager();
            $wa->registerAndUseScript(
                'com_j2commerce.vendor.swiper',
                'media/com_j2commerce/vendor/swiper/js/swiper-bundle.min.js',
                [],
                ['defer' => true]
            );
            $wa->registerAndUseStyle(
                'com_j2commerce.vendor.swiper.css',
                'media/com_j2commerce/vendor/swiper/css/swiper-bundle.min.css'
            );
        }

        $subtemplate = $moduleParams->get('subtemplate', '');
        if ($subtemplate !== '') {
            ProductLayoutService::setSubtemplateOverride($subtemplate);
        }

        try {
            $helper           = $this->getHelperFactory()->getHelper('RelatedProductsHelper');
            $data['products'] = $helper->getRelatedProducts($moduleParams);
        } catch (\Throwable) {
            $data['products'] = [];
        }

        return $data;
    }

    private function buildLayoutParams(Registry $moduleParams): Registry
    {
        $baseParams = clone ComponentHelper::getParams('com_j2commerce');

        $baseParams->set('list_no_of_columns', (int) $moduleParams->get('list_no_of_columns', 4));
        $imageWidth = (int) $moduleParams->get('module_image_thumbnail_width', 200);
        $baseParams->set('module_image_thumbnail_width', $imageWidth);
        $baseParams->set('list_image_thumbnail_width', $imageWidth);
        $baseParams->set('list_show_image', (bool) $moduleParams->get('list_show_image', 1));
        $baseParams->set('list_show_title', (bool) $moduleParams->get('list_show_title', 1));
        $baseParams->set('list_show_description', (bool) $moduleParams->get('list_show_description', 0));
        $baseParams->set('list_show_product_sku', (bool) $moduleParams->get('list_show_product_sku', 0));
        $baseParams->set('list_show_cart', (int) $moduleParams->get('list_show_cart', 1));
        $baseParams->set('list_show_price', (bool) $moduleParams->get('list_show_price', 1));
        $baseParams->set('list_show_product_stock', (int) $moduleParams->get('list_show_product_stock', 0));
        $baseParams->set('list_show_product_base_price', (int) $moduleParams->get('list_show_product_base_price', 1));
        $baseParams->set('list_show_product_special_price', (int) $moduleParams->get('list_show_product_special_price', 1));
        $baseParams->set('list_show_discount_percentage', (int) $moduleParams->get('list_show_discount_percentage', 1));
        $baseParams->set('list_link_title', (bool) $moduleParams->get('list_link_title', 1));
        $baseParams->set('list_image_link_to_product', (bool) $moduleParams->get('list_image_link_to_product', 1));
        $baseParams->set('list_image_type', $moduleParams->get('list_image_type', 'thumbimage'));
        $baseParams->set('list_description_length', (int) $moduleParams->get('list_description_length', 150));
        $baseParams->set('show_qty_field', (int) $moduleParams->get('show_qty_field', 1));
        $baseParams->set('image_for_product_options', (int) $moduleParams->get('image_for_product_options', 0));
        $baseParams->set('product_option_price', (int) $moduleParams->get('product_option_price', 1));
        $baseParams->set('addtocart_button_class', $moduleParams->get('addtocart_button_class', 'btn btn-primary'));

        $baseParams->set('slides_per_view', (int) $moduleParams->get('slides_per_view', 4));
        $baseParams->set('space_between', (int) $moduleParams->get('space_between', 20));
        $baseParams->set('autoplay', (bool) $moduleParams->get('autoplay', 0));
        $baseParams->set('autoplay_delay', (int) $moduleParams->get('autoplay_delay', 4));
        $baseParams->set('loop', (bool) $moduleParams->get('loop', 0));
        $baseParams->set('navigation', (bool) $moduleParams->get('navigation', 1));
        $baseParams->set('pagination', (bool) $moduleParams->get('pagination', 0));

        return $baseParams;
    }
}
