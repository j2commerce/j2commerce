<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

/**
 * Variants list model class.
 *
 * Provides methods for loading product variants with filtering, pagination,
 * and dimension lookups (lengths/weights).
 *
 * @since  6.0.0
 */
class VariantsModel extends ListModel
{
    /**
     * Flag to ignore request/session state when loading variants programmatically.
     * Set via constructor config or setState('filter.ignore_request', true).
     *
     * @var bool
     * @since 6.0.0
     */
    protected bool $ignoreRequest = false;

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   6.0.0
     */
    public function __construct($config = [])
    {
        // CRITICAL: Set ignore_request flag BEFORE parent constructor
        // Parent constructor may trigger populateState() which reads user session
        // This must be set first to prevent session state from overwriting our filters
        if (!empty($config['ignore_request'])) {
            $this->ignoreRequest = true;
        }

        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'j2commerce_variant_id', 'a.j2commerce_variant_id',
                'product_id', 'a.product_id',
                'is_master', 'a.is_master',
                'sku', 'a.sku',
                'price', 'a.price',
                'availability', 'a.availability',
                'isdefault_variant', 'a.isdefault_variant',
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
    protected function populateState($ordering = 'a.j2commerce_variant_id', $direction = 'asc'): void
    {
        // Check for ignore_request flag to skip user state
        // First check class property (set via constructor), then check state (set via setState)
        $ignoreRequest = $this->ignoreRequest || $this->state->get('filter.ignore_request', false);

        if ($ignoreRequest) {
            // Skip parent::populateState() entirely — it reads list.limit/ordering from user session
            // which overwrites programmatic setState() calls (e.g., list.limit = 0 for "load all")
            $this->setState('list.ordering', $ordering);
            $this->setState('list.direction', strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC');
            return;
        }

        $productId = $this->getUserStateFromRequest($this->context . '.filter.product_id', 'filter_product_id', 0, 'int');
        $this->setState('filter.product_id', $productId);

        $isMaster = $this->getUserStateFromRequest($this->context . '.filter.is_master', 'filter_is_master', '', 'string');
        $this->setState('filter.is_master', $isMaster);

        $productType = $this->getUserStateFromRequest($this->context . '.filter.product_type', 'filter_product_type', '', 'string');
        $this->setState('filter.product_type', $productType);

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
    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.product_id');
        $id .= ':' . $this->getState('filter.is_master');
        $id .= ':' . $this->getState('filter.product_type');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  \Joomla\Database\QueryInterface
     *
     * @since   6.0.0
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select variant fields
        $query->select([
            $db->quoteName('a.j2commerce_variant_id'),
            $db->quoteName('a.product_id'),
            $db->quoteName('a.is_master'),
            $db->quoteName('a.sku'),
            $db->quoteName('a.upc'),
            $db->quoteName('a.price'),
            $db->quoteName('a.pricing_calculator'),
            $db->quoteName('a.shipping'),
            $db->quoteName('a.params'),
            $db->quoteName('a.length'),
            $db->quoteName('a.width'),
            $db->quoteName('a.height'),
            $db->quoteName('a.length_class_id'),
            $db->quoteName('a.weight'),
            $db->quoteName('a.weight_class_id'),
            $db->quoteName('a.manage_stock'),
            $db->quoteName('a.quantity_restriction'),
            $db->quoteName('a.min_out_qty'),
            $db->quoteName('a.use_store_config_min_out_qty'),
            $db->quoteName('a.min_sale_qty'),
            $db->quoteName('a.use_store_config_min_sale_qty'),
            $db->quoteName('a.max_sale_qty'),
            $db->quoteName('a.use_store_config_max_sale_qty'),
            $db->quoteName('a.notify_qty'),
            $db->quoteName('a.use_store_config_notify_qty'),
            $db->quoteName('a.availability'),
            $db->quoteName('a.sold'),
            $db->quoteName('a.allow_backorder'),
            $db->quoteName('a.isdefault_variant'),
            $db->quoteName('a.created_on'),
            $db->quoteName('a.created_by'),
            $db->quoteName('a.modified_on'),
            $db->quoteName('a.modified_by'),
        ])
            ->from($db->quoteName('#__j2commerce_variants', 'a'));

        // Join length class
        $query->select([
            $db->quoteName('l.length_title'),
            $db->quoteName('l.length_unit'),
        ])
            ->join('LEFT', $db->quoteName('#__j2commerce_lengths', 'l'), $db->quoteName('a.length_class_id') . ' = ' . $db->quoteName('l.j2commerce_length_id'));

        // Join weight class
        $query->select([
            $db->quoteName('w.weight_title'),
            $db->quoteName('w.weight_unit'),
        ])
            ->join('LEFT', $db->quoteName('#__j2commerce_weights', 'w'), $db->quoteName('a.weight_class_id') . ' = ' . $db->quoteName('w.j2commerce_weight_id'));

        // Join quantity table
        $query->select([
                $db->quoteName('q.quantity'),
                $db->quoteName('q.j2commerce_productquantity_id'),
            ])
            ->join('LEFT', $db->quoteName('#__j2commerce_productquantities', 'q'), $db->quoteName('a.j2commerce_variant_id') . ' = ' . $db->quoteName('q.variant_id'));

        // Join product_variant_optionvalues for variant_name
        $query->select($db->quoteName('pvo.product_optionvalue_ids', 'variant_name'))
            ->join('LEFT', $db->quoteName('#__j2commerce_product_variant_optionvalues', 'pvo'), $db->quoteName('a.j2commerce_variant_id') . ' = ' . $db->quoteName('pvo.variant_id'));

        // Filter by product_id
        $productId = $this->getState('filter.product_id');
        if (!empty($productId)) {
            $productId = (int) $productId;
            $query->where($db->quoteName('a.product_id') . ' = :productId')
                ->bind(':productId', $productId, ParameterType::INTEGER);
        }

        // Filter by is_master
        $isMaster = $this->getState('filter.is_master');
        if ($isMaster !== '' && $isMaster !== null) {
            $isMaster = (int) $isMaster;
            $query->where($db->quoteName('a.is_master') . ' = :isMaster')
                ->bind(':isMaster', $isMaster, ParameterType::INTEGER);
        }

        // Ordering
        $orderCol = $this->state->get('list.ordering', 'a.j2commerce_variant_id');
        $orderDir = $this->state->get('list.direction', 'ASC');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }

    /**
     * Override getItems to enrich variant data with human-readable variant names.
     *
     * This ensures consistency between AJAX loading and normal page loading.
     * The variant_name field from the query contains CSV IDs (e.g., "7,8,9"),
     * which we convert to human-readable names (e.g., "Red, Large").
     *
     * @return  array  Array of variant objects with enriched data.
     *
     * @since   6.0.0
     */
    public function getItems(): array
    {
        $items = parent::getItems() ?: [];

        if (empty($items)) {
            return $items;
        }

        // Enrich each variant with human-readable variant_name
        // Preserve original CSV IDs in variant_name_ids for behavior processing
        foreach ($items as $item) {
            // Convert CSV of IDs to human-readable names
            if (!empty($item->variant_name)) {
                // Preserve original CSV IDs for Flexivariable behavior processing
                $item->variant_name_ids = $item->variant_name;
                // Convert to human-readable names for display
                $item->variant_name = $this->getVariantNameFromCsv($item->variant_name);
            }
        }

        return $items;
    }

    /**
     * Convert a CSV string of product_optionvalue_ids to human-readable variant names.
     *
     * @param   string  $csv  Comma-separated list of product_optionvalue_ids.
     *
     * @return  string  Human-readable variant name (e.g., "Red, Large").
     *
     * @since   6.0.0
     */
    protected function getVariantNameFromCsv(string $csv): string
    {
        if (empty($csv)) {
            return '';
        }

        $ids = array_map('intval', explode(',', $csv));
        $ids = array_filter($ids);

        if (empty($ids)) {
            return '';
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Get option values with their parent option names for "Any" handling
        $query->select([
                $db->quoteName('pov.optionvalue_id'),
                $db->quoteName('ov.optionvalue_name'),
                $db->quoteName('o.option_name'),
            ])
            ->from($db->quoteName('#__j2commerce_product_optionvalues', 'pov'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_optionvalues', 'ov') .
                ' ON ' . $db->quoteName('pov.optionvalue_id') . ' = ' . $db->quoteName('ov.j2commerce_optionvalue_id')
            )
            ->leftJoin(
                $db->quoteName('#__j2commerce_product_options', 'po') .
                ' ON ' . $db->quoteName('pov.productoption_id') . ' = ' . $db->quoteName('po.j2commerce_productoption_id')
            )
            ->leftJoin(
                $db->quoteName('#__j2commerce_options', 'o') .
                ' ON ' . $db->quoteName('po.option_id') . ' = ' . $db->quoteName('o.j2commerce_option_id')
            )
            ->whereIn($db->quoteName('pov.j2commerce_product_optionvalue_id'), $ids);

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        if (empty($rows)) {
            return $csv; // Return original CSV if lookup fails
        }

        $names = [];
        foreach ($rows as $row) {
            if ((int) $row->optionvalue_id === 0) {
                // "Any" option selected - show "Any [Option Name]"
                $names[] = Text::_('COM_J2COMMERCE_ANY') . ' ' . ($row->option_name ?? '');
            } elseif (!empty($row->optionvalue_name)) {
                $names[] = $row->optionvalue_name;
            }
        }

        return implode(', ', $names);
    }

    /**
     * Get dimension options (lengths or weights) for dropdowns.
     *
     * @param   string  $type      The dimension type ('lengths' or 'weights').
     * @param   string  $keyField  The field to use as the key (e.g., 'j2commerce_length_id').
     * @param   string  $nameField The field to use as the display name (e.g., 'length_title').
     *
     * @return  array  Array of dimension objects with 'value' and 'text' properties.
     *
     * @since   6.0.0
     */
    public function getDimensions(string $type, string $keyField, string $nameField): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $table = '#__j2commerce_' . $type;

        $query->select([
            $db->quoteName($keyField, 'value'),
            $db->quoteName($nameField, 'text'),
        ])
            ->from($db->quoteName($table))
            ->where($db->quoteName('enabled') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Delete a variant by ID.
     *
     * @param   int  $variantId  The variant ID to delete.
     *
     * @return  bool  True on success, false on failure.
     *
     * @since   6.0.0
     */
    public function delete(int $variantId): bool
    {
        $db = $this->getDatabase();

        try {
            // Delete from productquantities first
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_productquantities'))
                ->where($db->quoteName('variant_id') . ' = :variantId')
                ->bind(':variantId', $variantId, ParameterType::INTEGER);
            $db->setQuery($query);
            $db->execute();

            // Delete from product_variant_optionvalues
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_product_variant_optionvalues'))
                ->where($db->quoteName('variant_id') . ' = :variantId')
                ->bind(':variantId', $variantId, ParameterType::INTEGER);
            $db->setQuery($query);
            $db->execute();

            // Delete the variant
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_variants'))
                ->where($db->quoteName('j2commerce_variant_id') . ' = :variantId')
                ->bind(':variantId', $variantId, ParameterType::INTEGER);
            $db->setQuery($query);
            $db->execute();

            return true;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }
    }

    /** Load a single variant by primary key. */
    public function getItem(int $variantId): ?object
    {
        if ($variantId <= 0) {
            return null;
        }

        $db    = $this->getDatabase();
        $query = $this->getListQuery();
        $query->where($db->quoteName('a.j2commerce_variant_id') . ' = :variantPk')
            ->bind(':variantPk', $variantId, ParameterType::INTEGER);

        $db->setQuery($query, 0, 1);

        return $db->loadObject() ?: null;
    }

    /**
     * Get variants for a specific product.
     *
     * This is a convenience method that sets the filter and returns items
     * without affecting user session state.
     *
     * @param   int   $productId  The product ID.
     * @param   bool  $masterOnly Whether to only return the master variant.
     *
     * @return  array  Array of variant objects.
     *
     * @since   6.0.0
     */
    public function getVariantsByProductId(int $productId, bool $masterOnly = false): array
    {
        // Set filters directly without affecting user session
        $this->setState('filter.product_id', $productId);
        $this->setState('filter.is_master', $masterOnly ? 1 : '');
        $this->setState('filter.ignore_request', true);
        $this->setState('list.limit', 0);
        $this->setState('list.start', 0);

        // Clear any cached query
        $this->_db = null;

        return $this->getItems() ?: [];
    }
}
