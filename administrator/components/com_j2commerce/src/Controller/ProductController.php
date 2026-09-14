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
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;

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
    use WriteAccessTrait;

    protected string $writeAction = 'j2commerce.editproducts';

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
     * Products are edited only in their com_content article, so every task that would open or
     * post the standalone product form sends the user to the article editor instead. The form
     * tasks FormController maps onto these (apply, save2new, save2copy) follow save().
     */
    public function display($cachable = false, $urlparams = []): static
    {
        return $this->redirectToArticle();
    }

    public function add()
    {
        $this->redirectToArticle(0);

        return true;
    }

    public function edit($key = null, $urlVar = 'id')
    {
        $this->redirectToArticle();

        return true;
    }

    public function save($key = null, $urlVar = 'id')
    {
        $this->redirectToArticle();

        return false;
    }

    public function reload($key = null, $urlVar = 'id')
    {
        $this->redirectToArticle();
    }

    public function cancel($key = 'id')
    {
        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=' . $this->view_list, false));

        return true;
    }

    private function redirectToArticle(?int $productId = null): static
    {
        $productId ??= $this->input->getInt('id', 0);

        $this->setRedirect(Route::_(ProductHelper::getArticleEditRoute($productId), false));

        return $this;
    }

    /** JSON exit for the AJAX tasks. close() is exit(), so the headers flush first. */
    private function sendJson(mixed $data): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
        $this->app->setHeader('X-Content-Type-Options', 'nosniff', true);
        $this->app->sendHeaders();

        echo json_encode($data);
        $this->app->close();
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

        // Writing a product record takes the same core.create the product form takes.
        $user = $app->getIdentity();

        if (!$user || $user->guest || !$user->authorise('core.create', 'com_j2commerce')) {
            $this->sendJson([
                'success' => false,
                'message' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'),
            ]);
            return;
        }

        if (empty($articleId)) {
            $this->sendJson([
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
            $this->sendJson([
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
            $this->sendJson([
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

            $this->sendJson([
                'success'    => true,
                'product_id' => $productId,
                'message'    => Text::_('COM_J2COMMERCE_PRODUCT_CREATED_SUCCESS'),
            ]);
        } catch (\Exception $e) {
            $this->sendJson([
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

        // Takes the same core.edit + core.delete pair as the plural twin.
        $user = $app->getIdentity();

        if (
            !$user
            || $user->guest
            || !$user->authorise('core.edit', 'com_j2commerce')
            || !$user->authorise('core.delete', 'com_j2commerce')
        ) {
            $this->sendJson(['success' => false, 'message' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]);
            return;
        }

        if (!$productId || !$newType) {
            $this->sendJson(['success' => false, 'message' => Text::_('COM_J2COMMERCE_INVALID_INPUT_FIELD')]);
            return;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        try {
            // 1. Delete everything the non-master variants own
            $subQuery = $db->getQuery(true)
                ->select($db->quoteName('j2commerce_variant_id'))
                ->from($db->quoteName('#__j2commerce_variants'))
                ->where($db->quoteName('product_id') . ' = :pid1')
                ->where($db->quoteName('is_master') . ' = 0')
                ->bind(':pid1', $productId, ParameterType::INTEGER);

            $db->setQuery($subQuery);
            $variantIds = $db->loadColumn();

            if (!empty($variantIds)) {
                $ids = ArrayHelper::toInteger($variantIds);

                foreach ([
                    '#__j2commerce_product_variant_optionvalues',
                    '#__j2commerce_productquantities',
                    '#__j2commerce_product_prices',
                ] as $childTable) {
                    $query = $db->getQuery(true)
                        ->delete($db->quoteName($childTable))
                        ->whereIn($db->quoteName('variant_id'), $ids);
                    $db->setQuery($query);
                    $db->execute();
                }
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

            $this->sendJson(['success' => true]);
        } catch (\Throwable $e) {
            Log::add('changeProductType failed for product ' . $productId . ': ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
            $this->sendJson(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERR_GENERIC')]);
        }
    }
}
