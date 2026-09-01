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

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * Lists products with no master variant row — backs the Database Health card's "Review" modal
 * for products_without_master_variant. The predicate mirrors
 * DatabaseHealthHelper::countProductsWithoutMasterVariant() exactly, so the modal's total
 * always matches the card's count.
 */
class DatabasehealthproductsModel extends ListModel
{
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'j2commerce_product_id', 'a.j2commerce_product_id',
                'product_name', 'c.title',
            ];
        }

        parent::__construct($config);
    }

    protected function populateState($ordering = 'a.j2commerce_product_id', $direction = 'asc'): void
    {
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        parent::populateState($ordering, $direction);
    }

    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.search');

        return parent::getStoreId($id);
    }

    protected function getListQuery(): QueryInterface
    {
        $db       = $this->getDatabase();
        $isMaster = 1;

        $query = $db->getQuery(true)
            ->select($db->quoteName([
                'a.j2commerce_product_id',
                'a.product_type',
            ]))
            ->select($db->quoteName('c.title', 'product_name'))
            ->from($db->quoteName('#__j2commerce_products', 'a'))
            ->join(
                'LEFT',
                $db->quoteName('#__content', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('a.product_source_id')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_variants', 'v')
                    . ' ON ' . $db->quoteName('v.product_id') . ' = ' . $db->quoteName('a.j2commerce_product_id')
                    . ' AND ' . $db->quoteName('v.is_master') . ' = :isMaster'
            )
            ->where($db->quoteName('v.j2commerce_variant_id') . ' IS NULL')
            ->bind(':isMaster', $isMaster, ParameterType::INTEGER);

        $search = (string) $this->getState('filter.search', '');

        if ($search !== '') {
            if (stripos($search, 'id:') === 0) {
                $searchId = (int) substr($search, 3);
                $query->where($db->quoteName('a.j2commerce_product_id') . ' = :searchId')
                    ->bind(':searchId', $searchId, ParameterType::INTEGER);
            } else {
                $searchLike = '%' . str_replace(' ', '%', trim($search)) . '%';
                $query->where($db->quoteName('c.title') . ' LIKE :search')
                    ->bind(':search', $searchLike);
            }
        }

        $orderCol = $this->state->get('list.ordering', 'a.j2commerce_product_id');
        $orderDir = $this->state->get('list.direction', 'ASC');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }
}
