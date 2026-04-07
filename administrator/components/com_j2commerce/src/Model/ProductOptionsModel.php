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
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\ParameterType;

/**
 * Product Options list model class.
 *
 * Provides methods to retrieve product options with their associated option details.
 * This model links products to options with ordering and required settings.
 *
 * @since  6.0.0
 */
class ProductOptionsModel extends ListModel
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
                'j2commerce_productoption_id', 'a.j2commerce_productoption_id',
                'option_id', 'a.option_id',
                'parent_id', 'a.parent_id',
                'product_id', 'a.product_id',
                'ordering', 'a.ordering',
                'required', 'a.required',
                'is_variant', 'a.is_variant',
                'option_name', 'o.option_name',
                'type', 'o.type',
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
    protected function populateState($ordering = 'a.ordering', $direction = 'asc'): void
    {
        $app = Factory::getApplication();

        // Load the filter state
        $productId = $app->getUserStateFromRequest($this->context . '.filter.product_id', 'filter_product_id', 0, 'int');
        $this->setState('filter.product_id', $productId);

        $parentId = $app->getUserStateFromRequest($this->context . '.filter.parent_id', 'filter_parent_id', null, 'int');
        $this->setState('filter.parent_id', $parentId);

        $search = $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

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
        $id .= ':' . $this->getState('filter.parent_id');
        $id .= ':' . $this->getState('filter.search');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  DatabaseQuery
     *
     * @since   6.0.0
     */
    protected function getListQuery(): DatabaseQuery
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select required fields from product_options
        $query->select(
            $db->quoteName([
                'a.j2commerce_productoption_id',
                'a.option_id',
                'a.parent_id',
                'a.product_id',
                'a.ordering',
                'a.required',
                'a.is_variant',
            ])
        );

        // Select fields from options table
        $query->select(
            $db->quoteName([
                'o.option_unique_name',
                'o.option_name',
                'o.type',
                'o.option_params',
            ])
        );

        $query->from($db->quoteName('#__j2commerce_product_options', 'a'));

        // LEFT JOIN to options table
        $query->join(
            'LEFT',
            $db->quoteName('#__j2commerce_options', 'o') .
            ' ON ' . $db->quoteName('o.j2commerce_option_id') . ' = ' . $db->quoteName('a.option_id')
        );

        // Filter by product_id
        $productId = (int) $this->getState('filter.product_id');
        if ($productId > 0) {
            $query->where($db->quoteName('a.product_id') . ' = :productId')
                ->bind(':productId', $productId, ParameterType::INTEGER);
        }

        // Filter by parent_id (supports null check)
        $parentId = $this->getState('filter.parent_id');
        if ($parentId !== null && $parentId !== '') {
            $parentId = (int) $parentId;
            $query->where($db->quoteName('a.parent_id') . ' = :parentId')
                ->bind(':parentId', $parentId, ParameterType::INTEGER);
        }

        // Filter by search
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $searchId = (int) substr($search, 3);
                $query->where($db->quoteName('a.j2commerce_productoption_id') . ' = :searchId')
                    ->bind(':searchId', $searchId, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', trim($search)) . '%';
                $query->where(
                    '(' . $db->quoteName('o.option_name') . ' LIKE :search1 OR ' .
                    $db->quoteName('o.option_unique_name') . ' LIKE :search2)'
                )
                    ->bind(':search1', $search)
                    ->bind(':search2', $search);
            }
        }

        // Add ordering clause - always by ordering ASC as primary
        $orderCol = $this->state->get('list.ordering', 'a.ordering');
        $orderDir = $this->state->get('list.direction', 'ASC');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }

    /**
     * Get options by product ID.
     *
     * @param   int  $productId  The product ID.
     *
     * @return  array  Array of product option objects.
     *
     * @since   6.0.0
     */
    public function getOptionsByProductId(int $productId): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select(
            $db->quoteName([
                'a.j2commerce_productoption_id',
                'a.option_id',
                'a.parent_id',
                'a.product_id',
                'a.ordering',
                'a.required',
                'a.is_variant',
            ])
        );

        $query->select(
            $db->quoteName([
                'o.option_unique_name',
                'o.option_name',
                'o.type',
                'o.option_params',
            ])
        );

        $query->from($db->quoteName('#__j2commerce_product_options', 'a'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_options', 'o') .
                ' ON ' . $db->quoteName('o.j2commerce_option_id') . ' = ' . $db->quoteName('a.option_id')
            )
            ->where($db->quoteName('a.product_id') . ' = :productId')
            ->bind(':productId', $productId, ParameterType::INTEGER)
            ->order($db->quoteName('a.ordering') . ' ASC');

        $db->setQuery($query);

        try {
            return $db->loadObjectList() ?: [];
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Get parent option values for a given parent option and product.
     *
     * This method retrieves the option values associated with a parent option
     * within the context of a specific product.
     *
     * @param   int  $parentId   The parent option ID.
     * @param   int  $productId  The product ID.
     *
     * @return  array  Array of product option value objects.
     *
     * @since   6.0.0
     */
    public function getParentOptionValues(int $parentId, int $productId): array
    {
        $db = $this->getDatabase();

        // First, get the productoption_id for this parent option and product
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_productoption_id'))
            ->from($db->quoteName('#__j2commerce_product_options'))
            ->where($db->quoteName('option_id') . ' = :parentId')
            ->where($db->quoteName('product_id') . ' = :productId')
            ->bind(':parentId', $parentId, ParameterType::INTEGER)
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);

        try {
            $productoptionId = (int) $db->loadResult();

            if (empty($productoptionId)) {
                return [];
            }

            // Now get the option values for this productoption
            $valuesQuery = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__j2commerce_product_optionvalues'))
                ->where($db->quoteName('productoption_id') . ' = :productoptionId')
                ->bind(':productoptionId', $productoptionId, ParameterType::INTEGER)
                ->order($db->quoteName('ordering') . ' ASC');

            $db->setQuery($valuesQuery);

            return $db->loadObjectList() ?: [];
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Import options from one product to another.
     *
     * Copies all product options and their option values from a source product
     * to a destination product, updating parent option value references.
     *
     * @param   int  $sourceProductId  The source product ID to copy from.
     * @param   int  $destProductId    The destination product ID to copy to.
     *
     * @return  bool  True on success, false on failure.
     *
     * @since   6.0.0
     */
    public function importOptionsFromProduct(int $sourceProductId, int $destProductId): bool
    {
        $db = $this->getDatabase();

        // Get all options from source product
        $sourceOptions = $this->getOptionsByProductId($sourceProductId);

        if (empty($sourceOptions)) {
            return false;
        }

        // Map to track old option value IDs to new ones
        $optionValueMap         = [];
        $importedOptionValueIds = [];

        try {
            $db->transactionStart();

            // Loop through each source option
            foreach ($sourceOptions as $sourceOption) {
                // Insert the new product option
                $newOption = (object) [
                    'option_id'  => $sourceOption->option_id,
                    'parent_id'  => $sourceOption->parent_id,
                    'product_id' => $destProductId,
                    'ordering'   => $sourceOption->ordering,
                    'required'   => $sourceOption->required,
                    'is_variant' => $sourceOption->is_variant,
                ];

                $db->insertObject('#__j2commerce_product_options', $newOption, 'j2commerce_productoption_id');
                $newProductoptionId = $db->insertid();

                // Get option values for this source option
                $valuesQuery = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__j2commerce_product_optionvalues'))
                    ->where($db->quoteName('productoption_id') . ' = :productoptionId')
                    ->bind(':productoptionId', $sourceOption->j2commerce_productoption_id, ParameterType::INTEGER);

                $db->setQuery($valuesQuery);
                $sourceValues = $db->loadObjectList();

                if (!empty($sourceValues)) {
                    foreach ($sourceValues as $sourceValue) {
                        $oldValueId = $sourceValue->j2commerce_product_optionvalue_id;

                        // Insert new option value
                        $newValue = (object) [
                            'productoption_id'                  => $newProductoptionId,
                            'optionvalue_id'                    => $sourceValue->optionvalue_id,
                            'parent_optionvalue'                => $sourceValue->parent_optionvalue,
                            'product_optionvalue_price'         => $sourceValue->product_optionvalue_price,
                            'product_optionvalue_prefix'        => $sourceValue->product_optionvalue_prefix,
                            'product_optionvalue_weight'        => $sourceValue->product_optionvalue_weight,
                            'product_optionvalue_weight_prefix' => $sourceValue->product_optionvalue_weight_prefix,
                            'product_optionvalue_sku'           => $sourceValue->product_optionvalue_sku,
                            'product_optionvalue_default'       => $sourceValue->product_optionvalue_default,
                            'ordering'                          => $sourceValue->ordering,
                            'product_optionvalue_attribs'       => $sourceValue->product_optionvalue_attribs,
                        ];

                        $db->insertObject('#__j2commerce_product_optionvalues', $newValue, 'j2commerce_product_optionvalue_id');
                        $newValueId = $db->insertid();

                        // Track the mapping
                        $optionValueMap[$oldValueId] = $newValueId;
                        $importedOptionValueIds[]    = $newValueId;
                    }
                }
            }

            // Update parent_optionvalue references with new IDs
            $this->migrateParentOptionValueReferences($importedOptionValueIds, $optionValueMap, $db);

            $db->transactionCommit();

            return true;
        } catch (\Exception $e) {
            $db->transactionRollback();
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Migrate parent option value references after import.
     *
     * Updates the parent_optionvalue field in imported option values
     * to reference the newly created option value IDs.
     *
     * @param   array                                     $importedIds  Array of newly imported option value IDs.
     * @param   array                                     $map          Map of old IDs to new IDs.
     * @param   \Joomla\Database\DatabaseInterface|null  $db           Database object.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function migrateParentOptionValueReferences(array $importedIds, array $map, $db = null): void
    {
        if ($db === null) {
            $db = $this->getDatabase();
        }

        foreach ($importedIds as $valueId) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('parent_optionvalue'))
                ->from($db->quoteName('#__j2commerce_product_optionvalues'))
                ->where($db->quoteName('j2commerce_product_optionvalue_id') . ' = :valueId')
                ->bind(':valueId', $valueId, ParameterType::INTEGER);

            $db->setQuery($query);
            $parentOptionvalue = $db->loadResult();

            if (!empty($parentOptionvalue)) {
                $parentValues = explode(',', $parentOptionvalue);
                $newValues    = [];

                foreach ($parentValues as $oldValue) {
                    $oldValue = trim($oldValue);
                    if (isset($map[(int) $oldValue])) {
                        $newValues[] = $map[(int) $oldValue];
                    }
                }

                if (!empty($newValues)) {
                    $newParentOptionvalue = implode(',', $newValues);

                    $updateQuery = $db->getQuery(true)
                        ->update($db->quoteName('#__j2commerce_product_optionvalues'))
                        ->set($db->quoteName('parent_optionvalue') . ' = :newValue')
                        ->where($db->quoteName('j2commerce_product_optionvalue_id') . ' = :valueId')
                        ->bind(':newValue', $newParentOptionvalue)
                        ->bind(':valueId', $valueId, ParameterType::INTEGER);

                    $db->setQuery($updateQuery);
                    $db->execute();
                }
            }
        }
    }
}
