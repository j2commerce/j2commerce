<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Controller;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Product item controller class.
 *
 * Handles single-item operations: edit, save, apply, cancel.
 * For bulk operations (publish, unpublish, delete, batch), see ProductsController.
 *
 * @since  6.0.3
 */
class ProductController extends FormController
{
    /**
     * The URL option for the component.
     *
     * @var    string
     * @since  6.0.3
     */
    protected $option = 'com_j2commerce';

    /**
     * The URL view item variable.
     *
     * @var    string
     * @since  6.0.3
     */
    protected $view_item = 'product';

    /**
     * The URL view list variable.
     *
     * @var    string
     * @since  6.0.3
     */
    protected $view_list = 'products';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.3
     */
    protected $text_prefix = 'COM_J2COMMERCE_PRODUCT';

    /**
     * Intercept display to redirect product view to article editor.
     *
     * J2Commerce products are Joomla articles — there is no standalone product edit form.
     * This redirects view=product&layout=edit to the appropriate com_content article editor.
     */
    public function display($cachable = false, $urlparams = []): static
    {
        $app    = Factory::getApplication();
        $input  = $app->getInput();
        $layout = $input->get('layout', '');
        $id     = $input->getInt('id', 0);

        if ($layout === 'edit') {
            if ($id > 0) {
                // Existing product — find linked article and redirect to its editor
                $db    = Factory::getContainer()->get(DatabaseInterface::class);
                $query = $db->getQuery(true)
                    ->select($db->quoteName('product_source_id'))
                    ->from($db->quoteName('#__j2commerce_products'))
                    ->where($db->quoteName('j2commerce_product_id') . ' = :pid')
                    ->bind(':pid', $id, ParameterType::INTEGER);
                $db->setQuery($query);
                $articleId = (int) $db->loadResult();

                if ($articleId > 0) {
                    $this->setRedirect(Route::_('index.php?option=com_content&task=article.edit&id=' . $articleId, false));
                    return $this;
                }
            }

            // New product or no linked article — redirect to new article
            $this->setRedirect(Route::_('index.php?option=com_content&task=article.add', false));
            return $this;
        }

        return parent::display($cachable, $urlparams);
    }

    /**
     * Method to edit an existing record.
     *
     * CRITICAL: We must explicitly set $urlVar to 'id' because Joomla's FormController
     * defaults to using the Table's primary key name (j2commerce_product_id) as the URL
     * parameter. Since our URLs use 'id' (standard Joomla convention), we override here.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable for the id.
     *
     * @return  boolean  True if access level check passes, false otherwise.
     *
     * @since   6.0.3
     */
    public function edit($key = null, $urlVar = 'id')
    {
        return parent::edit($key, $urlVar);
    }

    /**
     * Method to save a record.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable for the id.
     *
     * @return  boolean  True if successful, false otherwise.
     *
     * @since   6.0.3
     */
    public function save($key = null, $urlVar = 'id')
    {
        return parent::save($key, $urlVar);
    }

    /**
     * Method to cancel an edit.
     *
     * @param   string  $key  The name of the primary key of the URL variable.
     *
     * @return  boolean  True if access level checks pass, false otherwise.
     *
     * @since   6.0.3
     */
    public function cancel($key = 'id')
    {
        return parent::cancel($key);
    }

    /**
     * Generate variant records for a configurable product.
     *
     * Creates all possible variant combinations based on the product's traits (options).
     * Uses Cartesian product algorithm to generate unique SKUs for each combination.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function generateVariants(): void
    {
        $this->checkToken();

        $app       = Factory::getApplication();
        $productId = $app->getInput()->getInt('id', 0);

        if (empty($productId)) {
            $app->enqueueMessage(Text::_('COM_J2COMMERCE_ERROR_NO_PRODUCT_SELECTED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=products', false));
            return;
        }

        // Get traits (variant options) for this product
        $traits = ProductHelper::getTraits($productId);

        if (empty($traits)) {
            $app->enqueueMessage(Text::_('COM_J2COMMERCE_ERROR_NO_TRAITS_DEFINED'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&task=product.edit&id=' . $productId, false));
            return;
        }

        // Build arrays for combination generation
        $optionArrays = [];
        foreach ($traits as $trait) {
            if (!empty($trait->values)) {
                $values = [];
                foreach ($trait->values as $value) {
                    $values[] = [
                        'option_id'              => $trait->option_id,
                        'product_optionvalue_id' => $value->j2commerce_product_optionvalue_id,
                        'optionvalue_name'       => $value->optionvalue_name ?? '',
                        'sku_suffix'             => $value->product_optionvalue_sku ?? '',
                        'price_prefix'           => $value->product_optionvalue_prefix ?? '+',
                        'price'                  => $value->product_optionvalue_price ?? 0,
                    ];
                }
                $optionArrays[] = $values;
            }
        }

        if (empty($optionArrays)) {
            $app->enqueueMessage(Text::_('COM_J2COMMERCE_ERROR_NO_OPTION_VALUES'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&task=product.edit&id=' . $productId, false));
            return;
        }

        // Generate all combinations (Cartesian product)
        $combinations = ProductHelper::getCombinations($optionArrays);

        // Get master variant for base SKU and price
        $masterVariant = ProductHelper::getMasterVariant($productId);
        $baseSku       = $masterVariant->sku ?? 'PROD-' . $productId;
        $basePrice     = (float) ($masterVariant->price ?? 0);

        // Create variant records
        $db           = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $date         = Factory::getDate()->toSql();
        $createdCount = 0;
        $skippedCount = 0;

        // Check if any existing variant is already set as default
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_variants'))
            ->where($db->quoteName('product_id') . ' = :piddef')
            ->where($db->quoteName('is_master') . ' = 0')
            ->where($db->quoteName('isdefault_variant') . ' = 1')
            ->bind(':piddef', $productId, ParameterType::INTEGER);
        $db->setQuery($query);
        $hasDefault = (int) $db->loadResult() > 0;

        // Load existing mapping CSVs for duplicate detection
        $query = $db->getQuery(true)
            ->select($db->quoteName('product_optionvalue_ids'))
            ->from($db->quoteName('#__j2commerce_product_variant_optionvalues', 'pvo'))
            ->join('INNER', $db->quoteName('#__j2commerce_variants', 'v')
                . ' ON ' . $db->quoteName('v.j2commerce_variant_id') . ' = ' . $db->quoteName('pvo.variant_id'))
            ->where($db->quoteName('v.product_id') . ' = :pidex')
            ->where($db->quoteName('v.is_master') . ' = 0')
            ->bind(':pidex', $productId, ParameterType::INTEGER);
        $db->setQuery($query);
        $existingCsvs = $db->loadColumn() ?: [];

        $existingNormalized = [];
        foreach ($existingCsvs as $csv) {
            $ids = explode(',', $csv);
            sort($ids);
            $existingNormalized[] = implode(',', $ids);
        }

        foreach ($combinations as $combination) {
            $skuParts        = [];
            $priceAdjustment = 0.0;
            $povIds          = [];

            foreach ($combination as $optionValue) {
                if (!empty($optionValue['sku_suffix'])) {
                    $skuParts[] = $optionValue['sku_suffix'];
                }

                $price = (float) $optionValue['price'];
                $priceAdjustment += ($optionValue['price_prefix'] === '-') ? -$price : $price;

                $povIds[] = (int) $optionValue['product_optionvalue_id'];
            }

            // Skip combinations with missing/invalid option value IDs
            $povIds = array_filter($povIds, static fn (int $id): bool => $id > 0);

            if (empty($povIds)) {
                continue;
            }

            sort($povIds);
            $povIdsCsv = implode(',', $povIds);

            // Check for existing variant with same option combination
            if (\in_array($povIdsCsv, $existingNormalized, true)) {
                $skippedCount++;
                continue;
            }

            $variantSku = $baseSku . (!empty($skuParts) ? '-' . implode('-', $skuParts) : '-V' . ($createdCount + 1));

            $variantData = (object) [
                'product_id'                    => $productId,
                'sku'                           => $variantSku,
                'upc'                           => '',
                'price'                         => $basePrice + $priceAdjustment,
                'pricing_calculator'            => 'standard',
                'shipping'                      => 0,
                'length'                        => 0,
                'width'                         => 0,
                'height'                        => 0,
                'length_class_id'               => 0,
                'weight'                        => 0,
                'weight_class_id'               => 0,
                'manage_stock'                  => 0,
                'quantity_restriction'          => 0,
                'min_out_qty'                   => 0,
                'use_store_config_min_out_qty'  => 1,
                'min_sale_qty'                  => 0,
                'use_store_config_min_sale_qty' => 1,
                'max_sale_qty'                  => 0,
                'use_store_config_max_sale_qty' => 1,
                'notify_qty'                    => 0,
                'use_store_config_notify_qty'   => 1,
                'availability'                  => 1,
                'sold'                          => 0,
                'allow_backorder'               => 0,
                'isdefault_variant'             => (!$hasDefault && $createdCount === 0) ? 1 : 0,
                'is_master'                     => 0,
                'created_on'                    => $date,
                'modified_on'                   => $date,
            ];

            try {
                $db->insertObject('#__j2commerce_variants', $variantData, 'j2commerce_variant_id');
                $variantId = (int) $variantData->j2commerce_variant_id;

                $mapping = (object) [
                    'variant_id'              => $variantId,
                    'product_optionvalue_ids' => $povIdsCsv,
                ];
                $db->insertObject('#__j2commerce_product_variant_optionvalues', $mapping);

                $existingNormalized[] = $povIdsCsv;
                $createdCount++;
            } catch (\Exception $e) {
                $app->enqueueMessage(Text::sprintf('COM_J2COMMERCE_ERROR_CREATING_VARIANT', $variantSku, $e->getMessage()), 'error');
            }
        }

        // Report results
        if ($createdCount > 0) {
            $app->enqueueMessage(Text::sprintf('COM_J2COMMERCE_VARIANTS_CREATED', $createdCount), 'success');
        }
        if ($skippedCount > 0) {
            $app->enqueueMessage(Text::sprintf('COM_J2COMMERCE_VARIANTS_SKIPPED', $skippedCount), 'notice');
        }

        $this->setRedirect(Route::_('index.php?option=com_j2commerce&task=product.edit&id=' . $productId, false));
    }

    /**
     * Create a J2Commerce product from a Joomla article.
     *
     * This AJAX endpoint allows creating a product directly from an article ID.
     * Used when the article hasn't been linked to a product yet.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function createFromArticle(): void
    {
        $this->checkToken();

        $app       = Factory::getApplication();
        $articleId = $app->getInput()->getInt('article_id', 0);

        if (empty($articleId)) {
            echo json_encode([
                'success' => false,
                'message' => Text::_('COM_J2COMMERCE_ERROR_NO_ARTICLE_SELECTED'),
            ]);
            return;
        }

        // Check if product already exists for this article
        $db    = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_product_id'))
            ->from($db->quoteName('#__j2commerce_products'))
            ->where($db->quoteName('product_source') . ' = :source')
            ->where($db->quoteName('product_source_id') . ' = :sourceId')
            ->bind(':source', 'com_content')
            ->bind(':sourceId', $articleId, ParameterType::INTEGER);
        $db->setQuery($query);
        $existingProductId = (int) $db->loadResult();

        if ($existingProductId > 0) {
            echo json_encode([
                'success'    => true,
                'product_id' => $existingProductId,
                'message'    => Text::_('COM_J2COMMERCE_PRODUCT_ALREADY_EXISTS'),
            ]);
            return;
        }

        // Get article data
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'title', 'alias']))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $articleId, ParameterType::INTEGER);
        $db->setQuery($query);
        $article = $db->loadObject();

        if (!$article) {
            echo json_encode([
                'success' => false,
                'message' => Text::_('COM_J2COMMERCE_ERROR_ARTICLE_NOT_FOUND'),
            ]);
            return;
        }

        // Create new product
        $date        = Factory::getDate()->toSql();
        $productData = (object) [
            'visibility'        => 1,
            'product_source'    => 'com_content',
            'product_source_id' => $articleId,
            'product_type'      => 'simple',
            'main_tag'          => '',
            'taxprofile_id'     => 0,
            'manufacturer_id'   => 0,
            'vendor_id'         => 0,
            'has_options'       => 0,
            'addtocart_text'    => 'COM_J2COMMERCE_ADD_TO_CART',
            'enabled'           => 1,
            'plugins'           => '',
            'params'            => '',
            'created_on'        => $date,
            'created_by'        => Factory::getApplication()->getIdentity()->id,
            'modified_on'       => $date,
            'modified_by'       => Factory::getApplication()->getIdentity()->id,
            'up_sells'          => '',
            'cross_sells'       => '',
            'productfilter_ids' => '',
            'hits'              => 0,
        ];

        try {
            $db->insertObject('#__j2commerce_products', $productData, 'j2commerce_product_id');
            $productId = (int) $productData->j2commerce_product_id;

            // Create master variant for the product
            $variantData = (object) [
                'product_id'                    => $productId,
                'sku'                           => 'PROD-' . $productId,
                'upc'                           => '',
                'price'                         => 0,
                'pricing_calculator'            => 'standard',
                'shipping'                      => 0,
                'params'                        => '',
                'length'                        => 0,
                'width'                         => 0,
                'height'                        => 0,
                'length_class_id'               => 0,
                'weight'                        => 0,
                'weight_class_id'               => 0,
                'manage_stock'                  => 0,
                'quantity_restriction'          => 0,
                'min_out_qty'                   => 0,
                'use_store_config_min_out_qty'  => 1,
                'min_sale_qty'                  => 1,
                'use_store_config_min_sale_qty' => 1,
                'max_sale_qty'                  => 0,
                'use_store_config_max_sale_qty' => 1,
                'notify_qty'                    => 0,
                'use_store_config_notify_qty'   => 1,
                'availability'                  => 1,
                'sold'                          => 0,
                'allow_backorder'               => 0,
                'isdefault_variant'             => 1,
                'is_master'                     => 1,
                'created_on'                    => $date,
                'created_by'                    => Factory::getApplication()->getIdentity()->id,
                'modified_on'                   => $date,
                'modified_by'                   => Factory::getApplication()->getIdentity()->id,
            ];

            $db->insertObject('#__j2commerce_variants', $variantData, 'j2commerce_variant_id');

            echo json_encode([
                'success'    => true,
                'product_id' => $productId,
                'message'    => Text::_('COM_J2COMMERCE_PRODUCT_CREATED_SUCCESS'),
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => Text::sprintf('COM_J2COMMERCE_ERROR_CREATING_PRODUCT', $e->getMessage()),
            ]);
        }
    }

    /**
     * AJAX endpoint to change a product's type.
     *
     * Deletes type-specific child data (options, option values, non-master variants)
     * and updates the product_type field to the new value.
     */
    public function changeProductType(): void
    {
        $this->checkToken();

        $app       = Factory::getApplication();
        $productId = $app->getInput()->getInt('product_id', 0);
        $newType   = $app->getInput()->getCmd('new_product_type', '');

        header('Content-Type: application/json; charset=utf-8');

        if (!$productId || !$newType) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_J2COMMERCE_INVALID_INPUT_FIELD')]);
            $app->close();
            return;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        try {
            // 1. Delete product_variant_optionvalues for non-master variants
            $subQuery = $db->getQuery(true)
                ->select($db->quoteName('j2commerce_variant_id'))
                ->from($db->quoteName('#__j2commerce_variants'))
                ->where($db->quoteName('product_id') . ' = :pid1')
                ->where($db->quoteName('is_master') . ' = 0')
                ->bind(':pid1', $productId, ParameterType::INTEGER);

            $db->setQuery($subQuery);
            $variantIds = $db->loadColumn();

            if (!empty($variantIds)) {
                $query = $db->getQuery(true)
                    ->delete($db->quoteName('#__j2commerce_product_variant_optionvalues'))
                    ->whereIn($db->quoteName('variant_id'), $variantIds);
                $db->setQuery($query);
                $db->execute();
            }

            // 2. Delete product_optionvalues via product_options
            $subQuery2 = $db->getQuery(true)
                ->select($db->quoteName('j2commerce_productoption_id'))
                ->from($db->quoteName('#__j2commerce_product_options'))
                ->where($db->quoteName('product_id') . ' = :pid2')
                ->bind(':pid2', $productId, ParameterType::INTEGER);

            $db->setQuery($subQuery2);
            $optionIds = $db->loadColumn();

            if (!empty($optionIds)) {
                $query = $db->getQuery(true)
                    ->delete($db->quoteName('#__j2commerce_product_optionvalues'))
                    ->whereIn($db->quoteName('productoption_id'), $optionIds);
                $db->setQuery($query);
                $db->execute();
            }

            // 3. Delete product_options
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_product_options'))
                ->where($db->quoteName('product_id') . ' = :pid3')
                ->bind(':pid3', $productId, ParameterType::INTEGER);
            $db->setQuery($query);
            $db->execute();

            // 4. Delete non-master variants
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_variants'))
                ->where($db->quoteName('product_id') . ' = :pid4')
                ->where($db->quoteName('is_master') . ' = 0')
                ->bind(':pid4', $productId, ParameterType::INTEGER);
            $db->setQuery($query);
            $db->execute();

            // 5. Update product type
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_products'))
                ->set($db->quoteName('product_type') . ' = :newType')
                ->set($db->quoteName('has_options') . ' = 0')
                ->where($db->quoteName('j2commerce_product_id') . ' = :pid5')
                ->bind(':newType', $newType)
                ->bind(':pid5', $productId, ParameterType::INTEGER);
            $db->setQuery($query);
            $db->execute();

            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $app->close();
    }
}
