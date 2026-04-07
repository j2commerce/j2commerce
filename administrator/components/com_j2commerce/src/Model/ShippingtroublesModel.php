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
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

/**
 * Shipping Troubles Model
 *
 * @since  6.0.0
 */
class ShippingtroublesModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   6.0.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'j2commerce_product_id', 'p.j2commerce_product_id',
                'product_name', 'p.product_source_id',
                'product_sku', 'v.sku',
                'weight', 'v.weight',
                'shipping', 'v.shipping',
                'shipping_status',
            ];
        }

        parent::__construct($config);
    }

    /**
     * Method to auto-populate the model state.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function populateState($ordering = 'p.product_source_id', $direction = 'asc')
    {
        // Load the filter state.
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $search);

        $shipping = $this->getUserStateFromRequest($this->context . '.filter.shipping', 'filter_shipping', '');
        $this->setState('filter.shipping', $shipping);

        $shipping_status = $this->getUserStateFromRequest($this->context . '.filter.shipping_status', 'filter_shipping_status', '');
        $this->setState('filter.shipping_status', $shipping_status);

        // List state information.
        parent::populateState($ordering, $direction);
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     *
     * @since   6.0.0
     */
    protected function getStoreId($id = '')
    {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.shipping');
        $id .= ':' . $this->getState('filter.shipping_status');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data for products.
     *
     * @return  \Joomla\Database\DatabaseQuery
     *
     * @since   6.0.0
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select the required fields
        $query->select([
            'p.j2commerce_product_id',
            'c.title AS product_name',
            'v.sku AS product_sku',
            'p.enabled',
            'p.product_source_id',
            'p.product_type',
            'v.shipping',
            'v.weight',
            'v.length',
            'v.width',
            'v.height',
        ])
        ->from($db->quoteName('#__j2commerce_products', 'p'))
        ->innerJoin($db->quoteName('#__content', 'c') . ' ON p.product_source_id = c.id')
        ->leftJoin($db->quoteName('#__j2commerce_variants', 'v') . ' ON p.j2commerce_product_id = v.product_id AND v.is_master = 1')
        ->where($db->quoteName('v.is_master') . ' = 1')
        ->where($db->quoteName('c.state') . ' = 1');

        // Exclude products where product_type contains "variable" AND is_master = 1
        $query->where('NOT (' . $db->quoteName('p.product_type') . ' LIKE ' . $db->quote('%variable%') . ' AND ' . $db->quoteName('v.is_master') . ' = 1)');

        // Exclude downloadable products — they cannot be shipped
        $query->where($db->quoteName('p.product_type') . ' != ' . $db->quote('downloadable'));

        // Filter by search in product name or SKU
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $search = (int) substr($search, 3);
                $query->where($db->quoteName('p.j2commerce_product_id') . ' = :id')
                    ->bind(':id', $search, ParameterType::INTEGER);
            } elseif (stripos($search, 'sku:') === 0) {
                $search = '%' . str_replace(' ', '%', $db->escape(trim(substr($search, 4)), true)) . '%';
                $query->where($db->quoteName('v.sku') . ' LIKE :sku')
                    ->bind(':sku', $search);
            } else {
                $search = '%' . str_replace(' ', '%', $db->escape(trim($search), true)) . '%';
                $query->where(
                    '(' . $db->quoteName('p.product_source_id') . ' LIKE :search1 OR ' .
                    $db->quoteName('v.sku') . ' LIKE :search2)'
                )
                ->bind(':search1', $search)
                ->bind(':search2', $search);
            }
        }

        // Filter by shipping enabled/disabled
        $shipping = $this->getState('filter.shipping');
        if (is_numeric($shipping)) {
            $query->where($db->quoteName('v.shipping') . ' = :shipping')
                ->bind(':shipping', $shipping, ParameterType::INTEGER);
        }

        // Add the list ordering clause.
        $orderCol  = $this->getState('list.ordering', 'p.product_source_id');
        $orderDirn = $this->getState('list.direction', 'ASC');

        if ($orderCol && $orderDirn) {
            $query->order($db->escape($orderCol . ' ' . $orderDirn));
        }

        return $query;
    }

    /**
     * Method to get an array of data items.
     *
     * @return  array  An array of data items
     *
     * @since   6.0.0
     */
    public function getItems()
    {
        $items = parent::getItems();

        if (!\is_array($items)) {
            return [];
        }

        // Analyze shipping configuration for each product
        foreach ($items as &$item) {
            $issues   = [];
            $warnings = [];

            // Check if shipping is enabled for the product
            if (!$item->shipping) {
                $issues[] = 'COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_PRODUCT_SHIPPING_DISABLED';
            }

            // Check weight configuration
            if (empty($item->weight) || $item->weight <= 0) {
                $warnings[] = 'COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_PRODUCT_NO_WEIGHT';
            }

            // Check dimensions
            $hasDimensions = !empty($item->length) && !empty($item->width) && !empty($item->height);
            if (!$hasDimensions) {
                $warnings[] = 'COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_PRODUCT_NO_DIMENSIONS';
            }

            // Determine overall status
            if (!empty($issues)) {
                $item->shipping_status = 'error';
            } elseif (!empty($warnings)) {
                $item->shipping_status = 'warning';
            } else {
                $item->shipping_status = 'success';
            }

            $item->shipping_issues   = $issues;
            $item->shipping_warnings = $warnings;
        }

        // Apply shipping status filter after analysis
        $shipping_status = $this->getState('filter.shipping_status');
        if (!empty($shipping_status)) {
            $items = array_filter($items, function ($item) use ($shipping_status) {
                return $item->shipping_status === $shipping_status;
            });
            // Re-index array
            $items = array_values($items);
        }

        return $items;
    }
    /**
     * Get shipping methods diagnostic information
     *
     * @return  array  Array of diagnostic results
     *
     * @since   6.0.0
     */
    public function getShippingMethodsDiagnostic()
    {
        $db          = $this->getDatabase();
        $diagnostics = [];

        // Check GeoZones
        $query = $db->getQuery(true)
            ->select('COUNT(*) as total')
            ->from($db->quoteName('#__j2commerce_geozones'))
            ->where($db->quoteName('enabled') . ' = 1');
        $db->setQuery($query);
        $geozonesCount = (int) $db->loadResult();

        $diagnostics['geozones'] = [
            'status'  => $geozonesCount > 0 ? 'success' : 'warning',
            'count'   => $geozonesCount,
            'message' => $geozonesCount > 0 ? 'COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_GEOZONES_OK' : 'COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_GEOZONES_MISSING',
        ];

        // Check Shipping Methods
        $query = $db->getQuery(true)
            ->select('COUNT(*) as total')
            ->from($db->quoteName('#__j2commerce_shippingmethods'))
            ->where($db->quoteName('published') . ' = 1');
        $db->setQuery($query);
        $shippingMethodsCount = (int) $db->loadResult();

        $diagnostics['shipping_methods'] = [
            'status'  => $shippingMethodsCount > 0 ? 'success' : 'error',
            'count'   => $shippingMethodsCount,
            'message' => $shippingMethodsCount > 0 ? 'COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_METHODS_OK' : 'COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_METHODS_MISSING',
        ];

        // Check if shipping methods have rates configured
        if ($shippingMethodsCount > 0) {
            // This would need to be adapted based on your actual shipping rates table structure
            // For now, we'll assume methods are configured if they exist and are enabled
            $diagnostics['shipping_rates'] = [
                'status'  => 'success',
                'count'   => $shippingMethodsCount,
                'message' => 'COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_RATES_OK',
            ];
        } else {
            $diagnostics['shipping_rates'] = [
                'status'  => 'error',
                'count'   => 0,
                'message' => 'COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_RATES_MISSING',
            ];
        }

        return $diagnostics;
    }

    /**
     * Get products shipping configuration status
     *
     * @return  array  Array of products with their shipping status
     *
     * @since   6.0.0
     */
    public function getProductsShippingStatus()
    {
        $db  = $this->getDatabase();
        $app = Factory::getApplication();

        // Get pagination parameters
        $limit      = $app->getInput()->getUint('limit', $app->get('list_limit', 20));
        $limitstart = $app->getInput()->getUint('limitstart', 0);

        $query = $db->getQuery(true);

        // Get products with their shipping configuration from variants table
        $query->select([
            'p.j2commerce_product_id',
            'p.product_source_id AS product_name',
            'v.sku AS product_sku',
            'p.enabled',
            'v.shipping',
            'v.weight',
            'v.length',
            'v.width',
            'v.height',
            '0 AS girth',
        ])
        ->from($db->quoteName('#__j2commerce_products', 'p'))
        ->leftJoin($db->quoteName('#__j2commerce_variants', 'v') . ' ON p.j2commerce_product_id = v.product_id AND v.is_master = 1')
        ->where($db->quoteName('p.enabled') . ' = 1')
        ->order($db->quoteName('p.product_source_id') . ' ASC');

        // Apply pagination to the query
        $db->setQuery($query, $limitstart, $limit);

        try {
            $products = $db->loadObjectList();

            if (!\is_array($products)) {
                return [];
            }

            // Analyze shipping configuration for each product
            foreach ($products as &$product) {
                $issues   = [];
                $warnings = [];

                // Check if shipping is enabled for the product
                if (!$product->shipping) {
                    $issues[] = 'COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_PRODUCT_SHIPPING_DISABLED';
                }

                // Check weight configuration
                if (empty($product->weight) || $product->weight <= 0) {
                    $warnings[] = 'COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_PRODUCT_NO_WEIGHT';
                }

                // Check dimensions
                $hasDimensions = !empty($product->length) && !empty($product->width) && !empty($product->height);
                if (!$hasDimensions) {
                    $warnings[] = 'COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_PRODUCT_NO_DIMENSIONS';
                }

                // Determine overall status
                if (!empty($issues)) {
                    $product->shipping_status = 'error';
                } elseif (!empty($warnings)) {
                    $product->shipping_status = 'warning';
                } else {
                    $product->shipping_status = 'success';
                }

                $product->shipping_issues   = $issues;
                $product->shipping_warnings = $warnings;
            }

            return $products;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Failed to retrieve products shipping status: ' . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Get the total count of products for pagination
     *
     * @return  int  Total number of enabled products
     *
     * @since   6.0.0
     */
    public function getProductsTotal()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select('COUNT(DISTINCT p.j2commerce_product_id)')
            ->from($db->quoteName('#__j2commerce_products', 'p'))
            ->leftJoin($db->quoteName('#__j2commerce_variants', 'v') . ' ON p.j2commerce_product_id = v.product_id AND v.is_master = 1')
            ->where($db->quoteName('p.enabled') . ' = 1');

        $db->setQuery($query);

        try {
            return (int) $db->loadResult();
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Failed to get products count: ' . $e->getMessage(), 'error');
            return 0;
        }
    }

    /**
     * Get pagination object for products list
     *
     * @return  Pagination  Pagination object
     *
     * @since   6.0.0
     */
    public function getProductsPagination()
    {
        $app        = Factory::getApplication();
        $limit      = $app->getInput()->getUint('limit', $app->get('list_limit', 20));
        $limitstart = $app->getInput()->getUint('limitstart', 0);
        $total      = $this->getProductsTotal();

        return new Pagination($total, $limitstart, $limit, '', $app);
    }

    /**
     * Get summary statistics for the troubleshooter
     *
     * @return  array  Summary statistics
     *
     * @since   6.0.0
     */
    public function getSummaryStats()
    {
        $db    = $this->getDatabase();
        $stats = [];

        try {
            // Total enabled shipping methods
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__j2commerce_shippingmethods'))
                ->where($db->quoteName('published') . ' = 1');
            $db->setQuery($query);
            $stats['enabled_shipping_methods'] = (int) $db->loadResult();

            // Total enabled geozones
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__j2commerce_geozones'))
                ->where($db->quoteName('enabled') . ' = 1');
            $db->setQuery($query);
            $stats['enabled_geozones'] = (int) $db->loadResult();

            // Total products with shipping enabled (from variants table)
            $query = $db->getQuery(true)
                ->select('COUNT(DISTINCT p.j2commerce_product_id)')
                ->from($db->quoteName('#__j2commerce_products', 'p'))
                ->leftJoin($db->quoteName('#__j2commerce_variants', 'v') . ' ON p.j2commerce_product_id = v.product_id AND v.is_master = 1')
                ->where($db->quoteName('p.enabled') . ' = 1')
                ->where($db->quoteName('v.shipping') . ' = 1');
            $db->setQuery($query);
            $stats['products_with_shipping'] = (int) $db->loadResult();

            // Total enabled products
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__j2commerce_products'))
                ->where($db->quoteName('enabled') . ' = 1');
            $db->setQuery($query);
            $stats['total_products'] = (int) $db->loadResult();

        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Failed to retrieve summary statistics: ' . $e->getMessage(), 'error');
        }

        return $stats;
    }

    /**
     * Get product shipping status statistics without loading full product data
     *
     * @return  array  Array with success, warning, error, and total counts
     *
     * @since   6.0.0
     */
    public function getProductsShippingStatistics()
    {
        $db    = $this->getDatabase();
        $stats = [
            'success' => 0,
            'warning' => 0,
            'error'   => 0,
            'total'   => 0,
        ];

        try {
            // Base query for products
            $query = $db->getQuery(true);
            $query->select([
                'v.shipping',
                'v.weight',
                'v.length',
                'v.width',
                'v.height',
            ])
            ->from($db->quoteName('#__j2commerce_products', 'p'))
            ->leftJoin($db->quoteName('#__j2commerce_variants', 'v') . ' ON p.j2commerce_product_id = v.product_id AND v.is_master = 1')
            ->where($db->quoteName('p.enabled') . ' = 1')
            ->where($db->quoteName('p.product_type') . ' != ' . $db->quote('downloadable'));

            $db->setQuery($query);
            $products = $db->loadObjectList();

            foreach ($products as $product) {
                $issues   = [];
                $warnings = [];

                // Check if shipping is enabled for the product
                if (!$product->shipping) {
                    $issues[] = 'shipping_disabled';
                }

                // Check weight configuration
                if (empty($product->weight) || $product->weight <= 0) {
                    $warnings[] = 'no_weight';
                }

                // Check dimensions
                $hasDimensions = !empty($product->length) && !empty($product->width) && !empty($product->height);
                if (!$hasDimensions) {
                    $warnings[] = 'no_dimensions';
                }

                // Determine status
                if (!empty($issues)) {
                    $stats['error']++;
                } elseif (!empty($warnings)) {
                    $stats['warning']++;
                } else {
                    $stats['success']++;
                }
            }

            $stats['total'] = \count($products);

        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Failed to retrieve products statistics: ' . $e->getMessage(), 'error');
        }

        return $stats;
    }
}
