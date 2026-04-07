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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

class AdvancedpricingModel extends ListModel
{
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'j2commerce_productprice_id', 'pp.j2commerce_productprice_id',
                'product_name', 'c.title',
                'product_id', 'v.product_id',
                'variant_id', 'pp.variant_id',
                'date_from', 'pp.date_from',
                'date_to', 'pp.date_to',
                'customer_group_id', 'pp.customer_group_id',
                'price', 'pp.price',
                'group_name', 'ug.title',
                'sku', 'v.sku',
            ];
        }

        parent::__construct($config);
    }

    protected function populateState($ordering = 'v.product_id', $direction = 'ASC'): void
    {
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        $customerGroupId = $this->getUserStateFromRequest($this->context . '.filter.customer_group_id', 'filter_customer_group_id', '', 'int');
        $this->setState('filter.customer_group_id', $customerGroupId);

        parent::populateState($ordering, $direction);
    }

    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.customer_group_id');

        return parent::getStoreId($id);
    }

    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select([
            $db->quoteName('pp') . '.*',
            $db->quoteName('v.product_id'),
            $db->quoteName('v.sku'),
            $db->quoteName('c.title', 'product_name'),
            $db->quoteName('ug.title', 'group_name'),
        ]);

        $query->from($db->quoteName('#__j2commerce_product_prices', 'pp'));

        $query->join(
            'LEFT',
            $db->quoteName('#__j2commerce_variants', 'v'),
            $db->quoteName('pp.variant_id') . ' = ' . $db->quoteName('v.j2commerce_variant_id')
        );

        $query->join(
            'LEFT',
            $db->quoteName('#__j2commerce_products', 'p'),
            $db->quoteName('v.product_id') . ' = ' . $db->quoteName('p.j2commerce_product_id')
        );

        $query->join(
            'LEFT',
            $db->quoteName('#__content', 'c'),
            $db->quoteName('p.product_source_id') . ' = ' . $db->quoteName('c.id')
        );

        $query->join(
            'LEFT',
            $db->quoteName('#__usergroups', 'ug'),
            $db->quoteName('pp.customer_group_id') . ' = ' . $db->quoteName('ug.id')
        );

        // Filter by search (product name or SKU)
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            $search = '%' . str_replace(' ', '%', trim($search)) . '%';
            $query->where(
                '(' .
                $db->quoteName('c.title') . ' LIKE :search1 OR ' .
                $db->quoteName('v.sku') . ' LIKE :search2' .
                ')'
            )
                ->bind(':search1', $search)
                ->bind(':search2', $search);
        }

        // Filter by customer group
        $customerGroupId = (int) $this->getState('filter.customer_group_id');

        if ($customerGroupId > 0) {
            $query->where($db->quoteName('pp.customer_group_id') . ' = :customer_group_id')
                ->bind(':customer_group_id', $customerGroupId, ParameterType::INTEGER);
        }

        // Ordering
        $orderCol  = $this->state->get('list.ordering', 'v.product_id');
        $orderDir  = $this->state->get('list.direction', 'ASC');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }

    public function getItems(): array
    {
        $items = parent::getItems();

        if ($items === false) {
            return [];
        }

        $nullDate = $this->getDatabase()->getNullDate();

        foreach ($items as $item) {
            if ($item->date_from === null || $item->date_from === '0000-00-00 00:00:00' || $item->date_from === $nullDate) {
                $item->date_from = '';
            }

            if ($item->date_to === null || $item->date_to === '0000-00-00 00:00:00' || $item->date_to === $nullDate) {
                $item->date_to = '';
            }
        }

        return $items;
    }
}
