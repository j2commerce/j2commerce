<?php

/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.AppFlexivariable
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\AppFlexivariable\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Controller for Flexivariable AJAX operations
 *
 * @since  6.0.0
 */
class FlexivariableController extends BaseController
{
    protected string $_element = 'app_flexivariable';

    public function __construct($config = [])
    {
        parent::__construct($config);

        $lang = Factory::getApplication()->getLanguage();
        $lang->load('plg_j2commerce_' . $this->_element, JPATH_ADMINISTRATOR);
    }

    public function addFlexiVariant(): void
    {
        $app = Factory::getApplication();

        // CSRF token validation
        if (!Session::checkToken('post')) {
            $json = ['success' => false, 'message' => Text::_('JINVALID_TOKEN')];
            echo json_encode($json);
            $app->close();
            return;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $input = $app->getInput();

        $variantCombin  = $input->get('variant_combin', [], 'array');
        $flexiProductId = $input->getInt('flexi_product_id', 0);

        $json = ['success' => false, 'message' => ''];

        if (empty($variantCombin) || empty($flexiProductId)) {
            $json['message'] = Text::_('PLG_J2COMMERCE_APP_FLEXIVARIABLE_INVALID_DATA');
            echo json_encode($json);
            $app->close();
            return;
        }

        try {
            $productOptionvalueIds = [];

            foreach ($variantCombin as $variantKey => $variantValue) {
                $productOptionvalue                                    = new \stdClass();
                $productOptionvalue->productoption_id                  = (int) $variantKey;
                $productOptionvalue->optionvalue_id                    = (int) $variantValue;
                $productOptionvalue->parent_optionvalue                = '';
                $productOptionvalue->product_optionvalue_price         = 0;
                $productOptionvalue->product_optionvalue_prefix        = '+';
                $productOptionvalue->product_optionvalue_weight        = 0;
                $productOptionvalue->product_optionvalue_weight_prefix = '+';
                $productOptionvalue->product_optionvalue_sku           = '';
                $productOptionvalue->product_optionvalue_default       = 0;
                $productOptionvalue->ordering                          = 0;
                $productOptionvalue->product_optionvalue_attribs       = '{}';

                $db->insertObject('#__j2commerce_product_optionvalues', $productOptionvalue, 'j2commerce_product_optionvalue_id');
                $productOptionvalueIds[] = $productOptionvalue->j2commerce_product_optionvalue_id;
            }

            if (!empty($productOptionvalueIds)) {
                // Create variant
                $variant                       = new \stdClass();
                $variant->product_id           = $flexiProductId;
                $variant->is_master            = 0;
                $variant->shipping             = 0;
                $variant->pricing_calculator   = 'standard';
                $variant->quantity_restriction = 0;
                $variant->allow_backorder      = 0;
                $variant->isdefault_variant    = 0;
                $variant->sku                  = '';

                $db->insertObject('#__j2commerce_variants', $variant, 'j2commerce_variant_id');

                // Update SKU with variant ID
                $variantId = $variant->j2commerce_variant_id;
                $newSku    = 'FLEXI_' . $variantId;

                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_variants'))
                    ->set($db->quoteName('sku') . ' = :sku')
                    ->where($db->quoteName('j2commerce_variant_id') . ' = :variantId')
                    ->bind(':sku', $newSku)
                    ->bind(':variantId', $variantId, ParameterType::INTEGER);

                $db->setQuery($query);
                $db->execute();

                // Create product quantity record
                $productQuantity                     = new \stdClass();
                $productQuantity->variant_id         = $variantId;
                $productQuantity->quantity           = 0;
                $productQuantity->on_hold            = 0;
                $productQuantity->sold               = 0;
                $productQuantity->product_attributes = '';

                $db->insertObject('#__j2commerce_productquantities', $productQuantity, 'j2commerce_productquantity_id');

                // Create product variant optionvalues record
                $productVariantOptionValue                          = new \stdClass();
                $productVariantOptionValue->variant_id              = $variantId;
                $productVariantOptionValue->product_optionvalue_ids = implode(',', $productOptionvalueIds);

                $db->insertObject('#__j2commerce_product_variant_optionvalues', $productVariantOptionValue);

                $json['success']    = true;
                $json['html']       = Text::_('PLG_J2COMMERCE_APP_FLEXIVARIABLE_VARIANT_ADDED');
                $json['variant_id'] = $variantId;
            }
        } catch (\Exception $e) {
            $json['message'] = $e->getMessage();
        }

        echo json_encode($json);
        $app->close();
    }

    public function deleteVariant(): void
    {
        $app = Factory::getApplication();

        // CSRF token validation
        if (!Session::checkToken('post')) {
            $json = ['success' => false, 'message' => Text::_('JINVALID_TOKEN')];
            echo json_encode($json);
            $app->close();
            return;
        }

        $variantId = $app->getInput()->getInt('variant_id', 0);

        $json = ['success' => false];

        if ($variantId > 0 && $this->deleteSingleVariant($variantId)) {
            $json['success'] = true;
        }

        echo json_encode($json);
        $app->close();
    }

    public function deleteAllVariant(): void
    {
        $app = Factory::getApplication();

        // CSRF token validation
        if (!Session::checkToken('post')) {
            $json = ['success' => false, 'message' => Text::_('JINVALID_TOKEN')];
            echo json_encode($json);
            $app->close();
            return;
        }

        $db        = Factory::getContainer()->get(DatabaseInterface::class);
        $productId = $app->getInput()->getInt('product_id', 0);

        $json = ['success' => false];

        if ($productId > 0) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('j2commerce_variant_id'))
                ->from($db->quoteName('#__j2commerce_variants'))
                ->where($db->quoteName('product_id') . ' = :productId')
                ->where($db->quoteName('is_master') . ' = 0')
                ->bind(':productId', $productId, ParameterType::INTEGER);

            $db->setQuery($query);
            $variants = $db->loadColumn();

            foreach ($variants as $variantId) {
                $this->deleteSingleVariant((int) $variantId);
            }

            $json['success'] = true;
        }

        echo json_encode($json);
        $app->close();
    }

    protected function deleteSingleVariant(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        try {
            // Delete inventory
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_productquantities'))
                ->where($db->quoteName('variant_id') . ' = :variantId')
                ->bind(':variantId', $id, ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();

            // Delete prices
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_product_prices'))
                ->where($db->quoteName('variant_id') . ' = :variantId')
                ->bind(':variantId', $id, ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();

            // Get product optionvalue IDs
            $query = $db->getQuery(true)
                ->select($db->quoteName('product_optionvalue_ids'))
                ->from($db->quoteName('#__j2commerce_product_variant_optionvalues'))
                ->where($db->quoteName('variant_id') . ' = :variantId')
                ->bind(':variantId', $id, ParameterType::INTEGER);

            $db->setQuery($query);
            $productOptionvalueIds = $db->loadResult();

            if (!empty($productOptionvalueIds)) {
                // Delete product optionvalues - use direct IN clause since values are internal
                $ids    = array_map('intval', explode(',', $productOptionvalueIds));
                $idList = implode(',', $ids);

                $query = $db->getQuery(true)
                    ->delete($db->quoteName('#__j2commerce_product_optionvalues'))
                    ->where($db->quoteName('j2commerce_product_optionvalue_id') . ' IN (' . $idList . ')');

                $db->setQuery($query);
                $db->execute();
            }

            // Delete variant optionvalues mapping
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_product_variant_optionvalues'))
                ->where($db->quoteName('variant_id') . ' = :variantId')
                ->bind(':variantId', $id, ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();

            // Delete variant
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_variants'))
                ->where($db->quoteName('j2commerce_variant_id') . ' = :variantId')
                ->bind(':variantId', $id, ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();

            return true;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }
    }
}
