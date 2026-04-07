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
 * Carts list model class.
 *
 * Provides list operations for shopping carts in admin.
 * Used for abandoned cart recovery, customer cart viewing, etc.
 *
 * @since  6.0.0
 */
class CartsModel extends ListModel
{
    /**
     * Cart type filter (cart, wishlist).
     *
     * @var    string
     * @since  6.0.0
     */
    protected string $cart_type = 'cart';

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
                'j2commerce_cart_id', 'tbl.j2commerce_cart_id',
                'user_id', 'tbl.user_id',
                'session_id', 'tbl.session_id',
                'cart_type', 'tbl.cart_type',
                'created_on', 'tbl.created_on',
                'modified_on', 'tbl.modified_on',
                'customer_ip', 'tbl.customer_ip',
                'totalitems',
                'username', 'u.username',
                'name', 'u.name',
                'email', 'u.email',
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
    protected function populateState($ordering = 'tbl.j2commerce_cart_id', $direction = 'desc'): void
    {
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        $userId = $this->getUserStateFromRequest($this->context . '.filter.user_id', 'filter_user_id', '', 'string');
        $this->setState('filter.user_id', $userId);

        $cartType = $this->getUserStateFromRequest($this->context . '.filter.cart_type', 'filter_cart_type', 'cart', 'string');
        $this->setState('filter.cart_type', $cartType);

        $sessionId = $this->getUserStateFromRequest($this->context . '.filter.session_id', 'filter_session_id', '', 'string');
        $this->setState('filter.session_id', $sessionId);

        $dateFrom = $this->getUserStateFromRequest($this->context . '.filter.date_from', 'filter_date_from', '', 'string');
        $this->setState('filter.date_from', $dateFrom);

        $dateTo = $this->getUserStateFromRequest($this->context . '.filter.date_to', 'filter_date_to', '', 'string');
        $this->setState('filter.date_to', $dateTo);

        // Whether to join users table
        $userJoin = $this->getUserStateFromRequest($this->context . '.filter.usertable_join', 'filter_usertable_join', '1', 'int');
        $this->setState('filter.usertable_join', $userJoin);

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
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.user_id');
        $id .= ':' . $this->getState('filter.cart_type');
        $id .= ':' . $this->getState('filter.session_id');
        $id .= ':' . $this->getState('filter.date_from');
        $id .= ':' . $this->getState('filter.date_to');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  QueryInterface
     *
     * @since   6.0.0
     */
    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select required fields
        $query->select($db->quoteName([
            'tbl.j2commerce_cart_id',
            'tbl.user_id',
            'tbl.session_id',
            'tbl.cart_voucher',
            'tbl.cart_coupon',
            'tbl.cart_type',
            'tbl.created_on',
            'tbl.modified_on',
            'tbl.customer_ip',
            'tbl.cart_browser',
        ]));

        $query->from($db->quoteName('#__j2commerce_carts', 'tbl'));

        // Subquery to count cart items
        $subquery = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_cartitems', 'cartitem'))
            ->where($db->quoteName('cartitem.cart_id') . ' = ' . $db->quoteName('tbl.j2commerce_cart_id'));

        $query->select('(' . $subquery . ') AS totalitems');

        // Join users table if requested
        $userJoin = $this->getState('filter.usertable_join', 1);

        if ($userJoin) {
            $query->select($db->quoteName([
                'u.username',
                'u.name',
                'u.email',
            ]));
            $query->join('LEFT', $db->quoteName('#__users', 'u') . ' ON ' .
                $db->quoteName('tbl.user_id') . ' = ' . $db->quoteName('u.id'));
        }

        // Filter by user_id
        $userId = $this->getState('filter.user_id');

        if (is_numeric($userId)) {
            $userId = (int) $userId;
            $query->where($db->quoteName('tbl.user_id') . ' = :userId')
                ->bind(':userId', $userId, ParameterType::INTEGER);
        }

        // Filter by session_id
        $sessionId = $this->getState('filter.session_id');

        if (!empty($sessionId)) {
            $query->where($db->quoteName('tbl.session_id') . ' = :sessionId')
                ->bind(':sessionId', $sessionId);
        }

        // Filter by cart_type
        $cartType = $this->getState('filter.cart_type');

        if (!empty($cartType)) {
            $query->where($db->quoteName('tbl.cart_type') . ' = :cartType')
                ->bind(':cartType', $cartType);
        }

        // Filter by date range
        $dateFrom = $this->getState('filter.date_from');

        if (!empty($dateFrom)) {
            $query->where($db->quoteName('tbl.created_on') . ' >= :dateFrom')
                ->bind(':dateFrom', $dateFrom);
        }

        $dateTo = $this->getState('filter.date_to');

        if (!empty($dateTo)) {
            $query->where($db->quoteName('tbl.created_on') . ' <= :dateTo')
                ->bind(':dateTo', $dateTo);
        }

        // Filter by search
        $search = $this->getState('filter.search');

        if (!empty($search) && $userJoin) {
            if (stripos($search, 'id:') === 0) {
                $searchId = (int) substr($search, 3);
                $query->where($db->quoteName('tbl.j2commerce_cart_id') . ' = :searchId')
                    ->bind(':searchId', $searchId, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', trim($search)) . '%';
                $query->where(
                    '(' .
                    $db->quoteName('u.username') . ' LIKE :search1 OR ' .
                    $db->quoteName('u.email') . ' LIKE :search2 OR ' .
                    $db->quoteName('u.name') . ' LIKE :search3 OR ' .
                    $db->quoteName('tbl.j2commerce_cart_id') . ' LIKE :search4 OR ' .
                    $db->quoteName('tbl.user_id') . ' LIKE :search5' .
                    ')'
                )
                    ->bind(':search1', $search)
                    ->bind(':search2', $search)
                    ->bind(':search3', $search)
                    ->bind(':search4', $search)
                    ->bind(':search5', $search);
            }
        }

        // Ordering
        $orderCol = $this->state->get('list.ordering', 'tbl.j2commerce_cart_id');
        $orderDir = $this->state->get('list.direction', 'DESC');

        // Validate order direction
        if (!\in_array(strtoupper($orderDir), ['ASC', 'DESC'])) {
            $orderDir = 'DESC';
        }

        // Validate order column
        $allowedCols = ['tbl.j2commerce_cart_id', 'tbl.created_on', 'tbl.modified_on', 'tbl.user_id'];

        if (!\in_array($orderCol, $allowedCols)) {
            $orderCol = 'tbl.j2commerce_cart_id';
        }

        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }

    /**
     * Load a single cart record.
     *
     * @param   int  $cartId  Cart ID (0 = load most recent for current user/session).
     *
     * @return  object|null  Cart object or null.
     *
     * @since   6.0.0
     */
    public function loadCart(int $cartId = 0): ?object
    {
        $db    = $this->getDatabase();
        $query = $this->getListQuery();

        if ($cartId > 0) {
            $query->where($db->quoteName('tbl.j2commerce_cart_id') . ' = :cartId')
                ->bind(':cartId', $cartId, ParameterType::INTEGER);
        }

        $query->order($db->quoteName('tbl.modified_on') . ' DESC');

        $db->setQuery($query, 0, 1);

        return $db->loadObject() ?: null;
    }

    /**
     * Set cart type for filtering.
     *
     * @param   string  $type  Cart type (cart, wishlist).
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setCartType(string $type = 'cart'): void
    {
        $this->cart_type = $type;
        $this->setState('filter.cart_type', $type);
    }

    /**
     * Get cart type.
     *
     * @return  string  Cart type.
     *
     * @since   6.0.0
     */
    public function getCartType(): string
    {
        return $this->cart_type;
    }
}
