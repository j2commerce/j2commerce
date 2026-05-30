<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_content_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\Content\J2Commerce\Field;

use J2Commerce\Component\J2commerce\Administrator\Field\MultiimageuploaderField;
use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Custom form field for J2Commerce product editor within article edit forms.
 *
 * @since  6.0.0
 */
final class J2commerceField extends FormField
{
    protected $type = 'J2Commerce';

    private ?DatabaseInterface $db = null;

    /** @since 6.0.0 */
    protected function getInput(): string
    {
        try {
            $app = Factory::getApplication();
            $db  = $this->getDb();

            $language = $app->getLanguage();
            $language->load('com_j2commerce', JPATH_ADMINISTRATOR . '/components/com_j2commerce');
            $language->load('com_j2commerce.sys', JPATH_ADMINISTRATOR . '/components/com_j2commerce');
            $language->load('plg_content_j2commerce', JPATH_ADMINISTRATOR);

            if ($app->isClient('administrator')) {
                $articleId = $app->getInput()->getInt('id', 0);
            } else {
                $articleId = $app->getInput()->getInt('a_id', 0);
            }

            $product = null;
            if ($articleId > 0) {
                $product = ProductHelper::getFullProductBySource('com_content', $articleId);
            }

            return $this->buildProductForm($product, $articleId);
        } catch (\Throwable $e) {
            // Log the full error for debugging
            Factory::getApplication()->getLogger()->error('J2Commerce Field Error: ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'class' => \get_class($e),
            ]);

            // Show generic error to user
            Factory::getApplication()->enqueueMessage(Text::_('PLG_CONTENT_J2COMMERCE_ERROR_LOADING_FORM'), 'error');

            return '';
        }
    }

    /** @since 6.0.0 */
    protected function getLabel(): string
    {
        return '';
    }

    /** @since 6.0.0 */
    private function buildProductForm(?object $product, int $articleId): string
    {
        $app         = Factory::getApplication();
        $formPrefix  = 'jform[attribs][j2commerce]';
        $productId   = $product->j2commerce_product_id ?? 0;
        $productType = $product->product_type ?? '';

        // Calculate variant statistics
        $variantStats = $this->calculateVariantStats($productId, $productType);
        $imageCount   = $this->getImageCount($productId);
        $filterCount  = $this->getFilterCount($product);
        $hasRelations = !empty($product->up_sells) || !empty($product->cross_sells);

        if ($app->isClient('site')) {
            $this->registerAdminAssetsForSite($app, $product);
        }

        $layout = new FileLayout('form', JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product');

        return $layout->render([
            'product'         => $product,
            'form_prefix'     => $formPrefix,
            'loadSubTemplate' => [$this, 'loadSubTemplate'],
            'variantStats'    => $variantStats,
            'imageCount'      => $imageCount,
            'filterCount'     => $filterCount,
            'hasRelations'    => $hasRelations,
        ]);
    }

    /** @since 6.0.0 */
    private function registerAdminAssetsForSite(object $app, ?object $product): void
    {
        $wa = $app->getDocument()->getWebAssetManager();

        // Styles — mirror admin editview registration (form.php + StrapperHelper)
        $wa->registerAndUseStyle('com_j2commerce.admin.css', 'media/com_j2commerce/css/administrator/j2commerce_admin.css');
        $wa->registerAndUseStyle('com_j2commerce.editview', 'media/com_j2commerce/css/administrator/editview.css');
        $wa->registerAndUseStyle('com_j2commerce.vendor.dual-listbox.css', 'media/com_j2commerce/vendor/dual-listbox/css/dual-listbox.css');

        // Scripts — mirror StrapperHelper::loadAdminScripts()
        $wa->useScript('core')->useScript('form.validate');
        $wa->registerAndUseScript('com_j2commerce.admin', 'media/com_j2commerce/js/administrator/j2commerce.js', [], ['defer' => true]);
        $wa->registerAndUseScript('com_j2commerce.vendor.dual-listbox', 'media/com_j2commerce/vendor/dual-listbox/js/dual-listbox.js', [], ['defer' => true]);
        $wa->registerAndUseScript('com_j2commerce.vendor.chartjs', 'media/com_j2commerce/vendor/chartjs/js/chart.umd.min.js', [], ['defer' => true]);
        $wa->registerAndUseScript('com_j2commerce.admin.modal-products', 'media/com_j2commerce/js/administrator/modal-products.js', [], ['defer' => true]);

        HTMLHelper::_('bootstrap.modal');

        // Uppy + multiimageuploader (T25) — idempotent via static $loaded guard
        MultiimageuploaderField::loadAssetsStatic();

        // productForm scriptOptions (T26) — admin gets this from form.php itself
        $app->getDocument()->addScriptOptions('com_j2commerce.productForm', [
            'productId'   => (int) ($product->j2commerce_product_id ?? 0),
            'productType' => $product->product_type ?? '',
            'enabled'     => (bool) ($product->enabled ?? 0),
            'csrfToken'   => Session::getFormToken(),
        ]);
    }

    /** @since 6.0.0 */
    private function calculateVariantStats(int $productId, string $productType): array
    {
        $stats = [
            'total'                => 0,
            'manage_inventory_yes' => 0,
            'manage_inventory_no'  => 0,
            'shipping_enabled'     => 0,
            'shipping_disabled'    => 0,
            'in_stock_percent'     => 100,
            'out_of_stock_percent' => 0,
        ];

        if ($productId <= 0) {
            return $stats;
        }

        $db = $this->getDb();

        // Get variant data
        $query = $db->getQuery(true)
            ->select('v.j2commerce_variant_id, v.manage_stock, v.shipping')
            ->from($db->quoteName('#__j2commerce_variants', 'v'))
            ->where('v.product_id = ' . (int) $productId);
        $db->setQuery($query);
        $variants = $db->loadObjectList();

        if (empty($variants)) {
            return $stats;
        }

        $stats['total'] = \count($variants);
        $variantIds     = array_map(fn ($v) => $v->j2commerce_variant_id, $variants);

        foreach ($variants as $variant) {
            if ($variant->manage_stock) {
                $stats['manage_inventory_yes']++;
            } else {
                $stats['manage_inventory_no']++;
            }
            if ($variant->shipping) {
                $stats['shipping_enabled']++;
            } else {
                $stats['shipping_disabled']++;
            }
        }

        // Get stock quantities
        if (!empty($variantIds)) {
            $query = $db->getQuery(true)
                ->select('variant_id, quantity')
                ->from($db->quoteName('#__j2commerce_productquantities'))
                ->where('variant_id IN (' . implode(',', array_map('intval', $variantIds)) . ')');
            $db->setQuery($query);
            $quantities = $db->loadObjectList('variant_id');

            $inStock    = 0;
            $outOfStock = 0;
            foreach ($variants as $variant) {
                $qty = $quantities[$variant->j2commerce_variant_id]->quantity ?? 0;
                if ($qty > 0) {
                    $inStock++;
                } else {
                    $outOfStock++;
                }
            }

            $stats['in_stock_percent']     = $stats['total'] > 0 ? round(($inStock / $stats['total']) * 100) : 0;
            $stats['out_of_stock_percent'] = $stats['total'] > 0 ? round(($outOfStock / $stats['total']) * 100) : 0;
        }

        return $stats;
    }

    /** @since 6.0.0 */
    private function getImageCount(int $productId): int
    {
        if ($productId <= 0) {
            return 0;
        }

        $db    = $this->getDb();
        $query = $db->getQuery(true)
            ->select($db->quoteName('main_image') . ', ' . $db->quoteName('additional_images'))
            ->from($db->quoteName('#__j2commerce_productimages'))
            ->where('product_id = ' . (int) $productId);
        $db->setQuery($query);
        $row = $db->loadAssoc();

        if (empty($row)) {
            return 0;
        }

        $count = 0;

        // Count main image if not empty
        if (!empty($row['main_image'])) {
            $count++;
        }

        // Count additional images from JSON field
        if (!empty($row['additional_images'])) {
            $additionalImages = json_decode($row['additional_images'], true);
            if (\is_array($additionalImages)) {
                $count += \count($additionalImages);
            }
        }

        return $count;
    }

    /** @since 6.0.0 */
    private function getFilterCount(?object $product): int
    {
        if (empty($product) || empty($product->productfilter_ids)) {
            return 0;
        }

        // Handle both array and string formats
        if (\is_array($product->productfilter_ids)) {
            return \count(array_filter($product->productfilter_ids));
        }

        $filterIds = explode(',', $product->productfilter_ids);

        return \count(array_filter($filterIds));
    }

    /** @since 6.0.0 */
    private function getDb(): DatabaseInterface
    {
        if ($this->db === null) {
            $this->db = Factory::getContainer()->get(DatabaseInterface::class);
        }

        return $this->db;
    }
}
