<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Schemaorg.ecommerce
 *
 * @copyright   (C) 2024-2026 J2Commerce, LLC All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Schemaorg\Ecommerce\Field;

use J2Commerce\Component\J2commerce\Administrator\Helper\ImageHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\Plugin\Schemaorg\Ecommerce\Helper\J2CommerceSchemaHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Custom form field to display existing J2Commerce product data.
 *
 * This field displays a read-only preview of the auto-detected product data
 * from J2Commerce, showing administrators what schema data will be generated
 * automatically before they decide on any overrides.
 *
 * @since  6.0.0
 */
class EcommerceProductPreviewField extends FormField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $type = 'EcommerceProductPreview';

    /**
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     *
     * @since   6.0.0
     */
    protected function getInput(): string
    {
        $helper = J2CommerceSchemaHelper::getInstance();

        // Check if J2Commerce is available
        if (!$helper->isJ2CommerceAvailable()) {
            return $this->renderAlert(
                'warning',
                Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_J2COMMERCE_NOT_AVAILABLE')
            );
        }

        // Get the current article ID from the form data
        $articleId = $this->getArticleIdFromForm();

        if (!$articleId) {
            return $this->renderAlert(
                'info',
                Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_SAVE_FIRST')
            );
        }

        // Try to get the product linked to this article
        $product = $helper->getProductByArticleId($articleId);

        if (!$product) {
            return $this->renderAlert(
                'info',
                Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_NO_PRODUCT')
            );
        }

        // Render the product preview
        return $this->renderProductPreview($product, $helper);
    }

    /**
     * Get the article ID from the form data
     *
     * @return  int|null  The article ID or null
     *
     * @since   6.0.0
     */
    private function getArticleIdFromForm(): ?int
    {
        // Try to get from form data
        $formData = $this->form->getData();

        if ($formData) {
            $id = $formData->get('id');

            if ($id) {
                return (int) $id;
            }
        }

        // Try to get from input
        $app = Factory::getApplication();
        $id  = $app->getInput()->getInt('id', 0);

        return $id > 0 ? $id : null;
    }

    /**
     * Render an alert message
     *
     * @param   string  $type     The alert type (info, warning, danger, success)
     * @param   string  $message  The message to display
     *
     * @return  string  The HTML alert
     *
     * @since   6.0.0
     */
    private function renderAlert(string $type, string $message): string
    {
        return '<div class="alert alert-' . $type . '" role="alert">'
            . '<span class="visually-hidden">' . ucfirst($type) . ':</span>'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</div>';
    }

    /**
     * Render the product preview card
     *
     * @param   object               $product  The J2Commerce product object
     * @param   J2CommerceSchemaHelper  $helper   The helper instance
     *
     * @return  string  The HTML preview
     *
     * @since   6.0.0
     */
    private function renderProductPreview(object $product, J2CommerceSchemaHelper $helper): string
    {

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('com_j2commerce.editview', 'media/com_j2commerce/css/administrator/editview.css');


        // Get product data
        $productName           = $helper->getProductName($product);
        $images                = $helper->getAllProductImages($product);
        $imageUrl              = !empty($images) ? $images[0] : '';
        $additionalImagesCount = \count($images) > 1 ? \count($images) - 1 : 0;

        // Check if this is a variable product
        $isVariable     = $helper->isVariableProduct($product);
        $variantCount   = 0;
        $defaultVariant = null;

        if ($isVariable && isset($product->variants) && \is_array($product->variants)) {
            // Count non-master variants and find default variant
            foreach ($product->variants as $v) {
                if ((int) ($v->is_master ?? 0) !== 1) {
                    $variantCount++;
                    // Check if this is the default variant (field is isdefault_variant in database)
                    if ((int) ($v->isdefault_variant ?? 0) === 1) {
                        $defaultVariant = $v;
                    }
                }
            }
        }

        // Use default variant if set, otherwise fall back to master variant
        $displayVariant = $defaultVariant ?? ($product->variant ?? null);

        // Price and currency (from default or master variant)
        $price          = $displayVariant ? $helper->getProductPrice($displayVariant) : 0;
        $currency       = $helper->getCurrencyCode();
        $formattedPrice = '$' . number_format($price, 2);

        // Availability
        $availability      = $displayVariant ? $helper->mapAvailability($displayVariant) : 'https://schema.org/InStock';
        $availabilityLabel = $this->getAvailabilityLabel($availability);
        $stockClass        = $this->getStockClass($availability);

        // SKU
        $sku = $displayVariant->sku ?? '';

        // Product type - show ProductGroup for variable products
        $productType = $isVariable && $variantCount > 0 ? 'ProductGroup' : 'Product';
        $displayType = ucfirst($product->product_type ?? 'simple');

        // GTIN/UPC
        $gtin = $displayVariant->upc ?? '';

        // Brand
        $brand = '';
        if (isset($product->manufacturer) && !empty($product->manufacturer->company)) {
            $brand = $product->manufacturer->company;
        }

        // Description
        $description = $helper->getProductDescription($product, 300);

        $html = [];

        // Start card
        $html[] = '<div class="product-card-v3 mb-5">';

        // Card header
        $html[] = '    <div class="card-header-v3">';
        $html[] = '        <div class="header-content">';
        $html[] = '            <div class="header-left">';
        $html[] = '                <h4>' . Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_TITLE') . '</h4>';
        $html[] = '                <div class="product-name-v3">' . htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') . '</div>';
        $html[] = '            </div>';
        $html[] = '            <div class="stock-indicator ' . $stockClass . '">';
        $html[] = '                <span class="stock-dot"></span>';
        $html[] = '                <span class="stock-text">' . $availabilityLabel . '</span>';
        $html[] = '            </div>';
        $html[] = '        </div>';
        $html[] = '    </div>';

        // Card body
        $html[] = '    <div class="card-body-v3">';
        $html[] = '        <div class="main-content">';

        // Image section
        $html[] = '            <div class="image-section">';
        $html[] = '                <div class="image-frame">';
        if (!empty($imageUrl)) {
            $html[] = ImageHelper::getInstance()->getProductImage($imageUrl, height: 160, width: 160, class: 'object-fit-cover img-fluid', alt: htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'));
        } else {
            $html[] = '                    <div class="no-image"><span class="icon-image" aria-hidden="true"></span></div>';
        }
        $html[] = '                </div>';
        if ($additionalImagesCount > 0) {
            $html[] = '                <div class="image-meta">';
            $html[] = '                    <span class="icon-images" aria-hidden="true"></span>';
            $html[] = '                    ' . Text::sprintf('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_ADDITIONAL_IMAGES', $additionalImagesCount);
            $html[] = '                </div>';
        }
        $html[] = '            </div>';

        // Data section
        $html[] = '            <div class="data-section">';

        // Price block
        $html[] = '                <div class="price-block">';
        $html[] = '                    <div class="price-label-v3">' . Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_PRICE') . '</div>';
        $html[] = '                    <div class="price-amount">' . $formattedPrice . '<span class="price-currency">' . $currency . '</span></div>';
        $html[] = '                </div>';

        // Data list
        $html[] = '                <div class="data-list">';

        // SKU
        $html[] = '                    <div class="data-item">';
        $html[] = '                        <span class="data-key">' . Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_SKU') . '</span>';
        $html[] = '                        <span class="data-val' . (empty($sku) ? ' empty' : '') . '">' . ($sku ?: Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_NOT_SET')) . '</span>';
        $html[] = '                    </div>';

        // Schema Type
        $html[] = '                    <div class="data-item">';
        $html[] = '                        <span class="data-key">' . Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_SCHEMA_TYPE') . '</span>';
        $html[] = '                        <span class="data-val">' . $productType . '</span>';
        $html[] = '                    </div>';

        // Product Type
        $html[] = '                    <div class="data-item">';
        $html[] = '                        <span class="data-key">' . Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_TYPE') . '</span>';
        $html[] = '                        <span class="data-val">' . $displayType . '</span>';
        $html[] = '                    </div>';

        // Variant Count (for variable products)
        if ($isVariable && $variantCount > 0) {
            $html[] = '                    <div class="data-item">';
            $html[] = '                        <span class="data-key">' . Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_VARIANTS') . '</span>';
            $html[] = '                        <span class="data-val">' . $variantCount . ' ' . Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_VARIANTS_LABEL') . '</span>';
            $html[] = '                    </div>';
        } else {
            // GTIN/UPC for simple products
            $html[] = '                    <div class="data-item">';
            $html[] = '                        <span class="data-key">' . Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_GTIN') . '</span>';
            $html[] = '                        <span class="data-val' . (empty($gtin) ? ' empty' : '') . '">' . ($gtin ?: Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_NOT_SET')) . '</span>';
            $html[] = '                    </div>';
        }

        // Brand (spans both columns for simple, or fits in grid for variable)
        $html[] = '                    <div class="data-item">';
        $html[] = '                        <span class="data-key">' . Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_BRAND') . '</span>';
        $html[] = '                        <span class="data-val' . (empty($brand) ? ' empty' : '') . '">' . ($brand ?: Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_NOT_SET')) . '</span>';
        $html[] = '                    </div>';

        // GTIN for variable products (show master variant GTIN)
        if ($isVariable && $variantCount > 0) {
            $html[] = '                    <div class="data-item">';
            $html[] = '                        <span class="data-key">' . Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_GTIN') . ' (' . Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_MASTER') . ')</span>';
            $html[] = '                        <span class="data-val' . (empty($gtin) ? ' empty' : '') . '">' . ($gtin ?: Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_NOT_SET')) . '</span>';
            $html[] = '                    </div>';
        }

        $html[] = '                </div>'; // End data-list
        $html[] = '            </div>'; // End data-section

        // Variants table for variable products
        if ($isVariable && $variantCount > 0 && isset($product->variants)) {
            $html[] = $this->renderVariantsTable($product->variants, $helper);
        }

        // Description area
        if (!empty($description)) {
            $html[] = '            <div class="description-area">';
            $html[] = '                <h6>' . Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_DESCRIPTION') . '</h6>';
            $html[] = '                <p>' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p>';
            $html[] = '            </div>';
        }

        // Notice bar
        $html[] = '            <div class="notice-bar">';
        $html[] = '                <span class="icon-info-circle" aria-hidden="true"></span>';
        $html[] = '                <span>' . Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_OVERRIDE_NOTE') . '</span>';
        $html[] = '            </div>';

        $html[] = '        </div>';
        $html[] = '    </div>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Render variants table
     *
     * @param   array                $variants  Array of variant objects
     * @param   J2CommerceSchemaHelper  $helper    The helper instance
     *
     * @return  string  The HTML table
     *
     * @since   6.0.0
     */
    private function renderVariantsTable(array $variants, J2CommerceSchemaHelper $helper): string
    {
        $currency = $helper->getCurrencyCode();
        $html     = [];

        $html[] = '            <div class="variants-section">';
        $html[] = '                <h6>';
        $html[] = '                    ' . Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_VARIANTS_TITLE');

        // Count non-master variants
        $count = 0;
        foreach ($variants as $v) {
            if ((int) ($v->is_master ?? 0) !== 1) {
                $count++;
            }
        }

        $html[] = '                    <span class="badge">' . $count . '</span>';
        $html[] = '                </h6>';
        $html[] = '                <table class="variants-table">';
        $html[] = '                    <thead>';
        $html[] = '                        <tr>';
        $html[] = '                            <th>' . Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_VARIANT_NAME') . '</th>';
        $html[] = '                            <th>' . Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_SKU') . '</th>';
        $html[] = '                            <th>' . Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_PRICE') . '</th>';
        $html[] = '                            <th>' . Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_GTIN') . '</th>';
        $html[] = '                            <th>' . Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_STATUS') . '</th>';
        $html[] = '                        </tr>';
        $html[] = '                    </thead>';
        $html[] = '                    <tbody>';

        foreach ($variants as $variant) {
            // Skip master variant
            if ((int) ($variant->is_master ?? 0) === 1) {
                continue;
            }

            // Format variant name using J2Commerce helper for readable option names
            $variantName         = $this->formatVariantName($variant);
            $variantSku          = $variant->sku ?? '';
            $variantPrice        = '$' . number_format((float) ($variant->price ?? 0), 2);
            $variantGtin         = $variant->upc ?? '';
            $variantAvailability = $helper->mapAvailability($variant);
            $variantStatus       = $this->getAvailabilityLabel($variantAvailability);
            $variantStatusClass  = $this->getStockClass($variantAvailability);

            $html[] = '                        <tr>';
            $html[] = '                            <td>' . $variantName . '</td>';
            $html[] = '                            <td>' . htmlspecialchars($variantSku, ENT_QUOTES, 'UTF-8') . '</td>';
            $html[] = '                            <td>' . $variantPrice . ' ' . $currency . '</td>';
            $html[] = '                            <td class="' . (empty($variantGtin) ? 'empty' : '') . '">' . ($variantGtin ?: '-') . '</td>';
            $html[] = '                            <td><span class="variant-status ' . $variantStatusClass . '">' . $variantStatus . '</span></td>';
            $html[] = '                        </tr>';
        }

        $html[] = '                    </tbody>';
        $html[] = '                </table>';
        $html[] = '            </div>';

        return implode("\n", $html);
    }

    /**
     * Format variant name using J2Commerce's variant name helper
     *
     * The variant_name field contains comma-separated product option value IDs.
     * This method uses J2Commerce's getVariantNamesByCSV to convert those IDs
     * into human-readable option value names, then formats them with bold
     * styling and dash separators.
     *
     * @param   object  $variant  The variant object
     *
     * @return  string  The formatted variant name (HTML)
     *
     * @since   6.0.0
     */
    private function formatVariantName(object $variant): string
    {
        try {
            if (class_exists(J2CommerceHelper::class)) {
                $productHelper = J2CommerceHelper::product();

                if ($productHelper && method_exists($productHelper, 'getVariantNamesByCSV')) {
                    // variant_name contains CSV of product_optionvalue_ids (e.g., "1,5,12")
                    $optionValueIds = $variant->variant_name ?? '';
                    $variantNames   = $productHelper->getVariantNamesByCSV($optionValueIds);

                    if (!empty($variantNames)) {
                        // Split by comma (the method returns comma-separated names)
                        $parts = preg_split('/,(?!\d{3})/', $variantNames);

                        // Bold each part and join with a dash separator
                        $boldParts = array_map(function ($part) {
                            $trimmed = trim($part);

                            return !empty($trimmed)
                                ? '<b>' . htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8') . '</b>'
                                : '';
                        }, $parts);

                        $boldParts = array_filter($boldParts);

                        if (!empty($boldParts)) {
                            return implode(' - ', $boldParts);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Fall back to displaying the raw IDs or unnamed
        }

        // Fallback: show unnamed if we couldn't get proper names
        return '<em>' . Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_UNNAMED') . '</em>';
    }

    /**
     * Get a human-readable label for availability
     *
     * @param   string  $availability  The schema.org availability URL
     *
     * @return  string  The translated label
     *
     * @since   6.0.0
     */
    private function getAvailabilityLabel(string $availability): string
    {
        $labels = [
            'https://schema.org/InStock'      => Text::_('PLG_SCHEMAORG_ECOMMERCE_INSTOCK'),
            'https://schema.org/OutOfStock'   => Text::_('PLG_SCHEMAORG_ECOMMERCE_OUTOFSTOCK'),
            'https://schema.org/PreOrder'     => Text::_('PLG_SCHEMAORG_ECOMMERCE_PREORDER'),
            'https://schema.org/BackOrder'    => Text::_('PLG_SCHEMAORG_ECOMMERCE_BACKORDER'),
            'https://schema.org/Discontinued' => Text::_('PLG_SCHEMAORG_ECOMMERCE_DISCONTINUED'),
        ];

        return $labels[$availability] ?? Text::_('PLG_SCHEMAORG_ECOMMERCE_PREVIEW_UNKNOWN');
    }

    /**
     * Get the CSS class for stock indicator
     *
     * @param   string  $availability  The schema.org availability URL
     *
     * @return  string  The CSS class
     *
     * @since   6.0.0
     */
    private function getStockClass(string $availability): string
    {
        $classes = [
            'https://schema.org/InStock'      => 'in-stock',
            'https://schema.org/OutOfStock'   => 'out-of-stock',
            'https://schema.org/PreOrder'     => 'pre-order',
            'https://schema.org/BackOrder'    => 'back-order',
            'https://schema.org/Discontinued' => 'discontinued',
        ];

        return $classes[$availability] ?? 'unknown';
    }
}
