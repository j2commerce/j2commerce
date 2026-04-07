<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;

/**
 * Product Filter Table
 *
 * @since  6.0.0
 */
class ProductfilterTable extends Table
{
    /**
     * Constructor
     *
     * @param   DatabaseDriver        $db          Database connector object
     * @param   ?DispatcherInterface  $dispatcher  Event dispatcher for this table
     *
     * @since  6.0.0
     */
    public function __construct(DatabaseDriver $db, ?DispatcherInterface $dispatcher = null)
    {
        parent::__construct('#__j2commerce_product_filters', ['product_id', 'filter_id'], $db, $dispatcher);
    }

    /**
     * Add filter associations to a product
     *
     * Replaces all existing filter associations for the product with the new set.
     *
     * @param   array|string  $filterIds  Array or comma-separated string of filter IDs
     * @param   int|string    $productId  The product ID
     *
     * @return  bool  True on success
     *
     * @since   6.0.0
     */
    public function addFilterToProduct(array|string $filterIds, int|string $productId): bool
    {
        $productId = (int) $productId;

        if (empty($productId)) {
            return false;
        }

        $db = $this->getDbo();

        // Convert string to array if needed
        if (\is_string($filterIds)) {
            $filterIds = array_filter(array_map('intval', explode(',', $filterIds)));
        }

        // Delete existing filter associations for this product
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_product_filters'))
            ->where($db->quoteName('product_id') . ' = :productId')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);
        $db->execute();

        // Insert new filter associations
        if (!empty($filterIds)) {
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__j2commerce_product_filters'))
                ->columns([
                    $db->quoteName('product_id'),
                    $db->quoteName('filter_id'),
                ]);

            foreach ($filterIds as $filterId) {
                $filterId = (int) $filterId;
                if ($filterId > 0) {
                    $query->values($productId . ', ' . $filterId);
                }
            }

            $db->setQuery($query);
            $db->execute();
        }

        return true;
    }
}
