<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Shipping Trouble Model (single item model for specific troubleshooting steps)
 *
 * @since  6.0.0
 */
class ShippingtroubleModel extends BaseDatabaseModel
{
    /**
     * Get detailed diagnostic information for a specific aspect
     *
     * @param   string  $aspect  The aspect to diagnose (geozones, methods, rates, products)
     *
     * @return  array  Detailed diagnostic information
     *
     * @since   6.0.0
     */
    public function getDetailedDiagnostic($aspect = 'overview')
    {
        switch ($aspect) {
            case 'geozones':
                return $this->getDiagnosticGeozones();

            case 'methods':
                return $this->getDiagnosticMethods();

            case 'rates':
                return $this->getDiagnosticRates();

            case 'products':
                return $this->getDiagnosticProducts();

            default:
                return $this->getDiagnosticOverview();
        }
    }

    /**
     * Get geozones diagnostic information
     *
     * @return  array  Geozones diagnostic data
     *
     * @since   6.0.0
     */
    protected function getDiagnosticGeozones()
    {
        $db   = $this->getDatabase();
        $data = ['items' => [], 'summary' => []];

        try {
            // Get all geozones with their rule count
            $query = $db->getQuery(true)
                ->select([
                    'g.j2commerce_geozone_id',
                    'g.geozone_name',
                    'g.geozone_code',
                    'g.enabled',
                    'COUNT(gr.j2commerce_geozonerule_id) as rule_count',
                ])
                ->from($db->quoteName('#__j2commerce_geozones', 'g'))
                ->leftJoin(
                    $db->quoteName('#__j2commerce_geozonerules', 'gr') .
                    ' ON ' . $db->quoteName('g.j2commerce_geozone_id') . ' = ' . $db->quoteName('gr.geozone_id')
                )
                ->group($db->quoteName('g.j2commerce_geozone_id'))
                ->order($db->quoteName('g.geozone_name'));

            $db->setQuery($query);
            $data['items'] = $db->loadObjectList() ?: [];

            // Summary statistics
            $enabledCount   = 0;
            $withRulesCount = 0;

            foreach ($data['items'] as $item) {
                if ($item->enabled) {
                    $enabledCount++;
                    if ($item->rule_count > 0) {
                        $withRulesCount++;
                    }
                }
            }

            $data['summary'] = [
                'total'      => \count($data['items']),
                'enabled'    => $enabledCount,
                'with_rules' => $withRulesCount,
                'status'     => $enabledCount > 0 && $withRulesCount > 0 ? 'success' : 'warning',
            ];

        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Failed to get geozones diagnostic: ' . $e->getMessage(), 'error');
        }

        return $data;
    }

    /**
     * Get shipping methods diagnostic information
     *
     * @return  array  Shipping methods diagnostic data
     *
     * @since   6.0.0
     */
    protected function getDiagnosticMethods()
    {
        $db   = $this->getDatabase();
        $data = ['items' => [], 'summary' => []];

        try {
            // Get all shipping methods
            $query = $db->getQuery(true)
                ->select([
                    'sm.j2commerce_shippingmethod_id',
                    'sm.shipping_method_name',
                    'sm.shipping_method_type',
                    'sm.enabled',
                    'sm.params',
                ])
                ->from($db->quoteName('#__j2commerce_shippingmethods', 'sm'))
                ->order($db->quoteName('sm.shipping_method_name'));

            $db->setQuery($query);
            $data['items'] = $db->loadObjectList() ?: [];

            // Summary statistics
            $enabledCount = 0;
            foreach ($data['items'] as $item) {
                if ($item->enabled) {
                    $enabledCount++;
                }
            }

            $data['summary'] = [
                'total'   => \count($data['items']),
                'enabled' => $enabledCount,
                'status'  => $enabledCount > 0 ? 'success' : 'error',
            ];

        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Failed to get shipping methods diagnostic: ' . $e->getMessage(), 'error');
        }

        return $data;
    }

    /**
     * Get shipping rates diagnostic information
     *
     * @return  array  Shipping rates diagnostic data
     *
     * @since   6.0.0
     */
    protected function getDiagnosticRates()
    {
        // This would need to be implemented based on your actual shipping rates structure
        // For now, return basic information
        return [
            'items'   => [],
            'summary' => [
                'total'      => 0,
                'configured' => 0,
                'status'     => 'info',
                'message'    => 'COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_RATES_CHECK_METHODS',
            ],
        ];
    }

    /**
     * Get products diagnostic information
     *
     * @return  array  Products diagnostic data
     *
     * @since   6.0.0
     */
    protected function getDiagnosticProducts()
    {
        $db   = $this->getDatabase();
        $data = ['items' => [], 'summary' => []];

        try {
            // Get products with shipping configuration
            $query = $db->getQuery(true)
                ->select([
                    'p.j2commerce_product_id',
                    'p.product_name',
                    'p.product_sku',
                    'p.enabled',
                    'p.shipping',
                    'p.weight',
                    'p.length',
                    'p.width',
                    'p.height',
                ])
                ->from($db->quoteName('#__j2commerce_products', 'p'))
                ->where($db->quoteName('p.enabled') . ' = 1')
                ->order($db->quoteName('p.product_name'))
                ->setLimit(50); // Limit for performance

            $db->setQuery($query);
            $products = $db->loadObjectList() ?: [];

            // Analyze each product
            $withShipping   = 0;
            $withWeight     = 0;
            $withDimensions = 0;

            foreach ($products as $product) {
                if ($product->shipping) {
                    $withShipping++;
                }
                if (!empty($product->weight) && $product->weight > 0) {
                    $withWeight++;
                }
                if (!empty($product->length) && !empty($product->width) && !empty($product->height)) {
                    $withDimensions++;
                }
            }

            $data['items']   = $products;
            $data['summary'] = [
                'total'           => \count($products),
                'with_shipping'   => $withShipping,
                'with_weight'     => $withWeight,
                'with_dimensions' => $withDimensions,
                'status'          => $withShipping > 0 ? 'success' : 'warning',
            ];

        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Failed to get products diagnostic: ' . $e->getMessage(), 'error');
        }

        return $data;
    }

    /**
     * Get overview diagnostic information
     *
     * @return  array  Overview diagnostic data
     *
     * @since   6.0.0
     */
    protected function getDiagnosticOverview()
    {
        return [
            'geozones' => $this->getDiagnosticGeozones()['summary'],
            'methods'  => $this->getDiagnosticMethods()['summary'],
            'products' => $this->getDiagnosticProducts()['summary'],
        ];
    }
}
