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
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * Coupons list model class.
 *
 * @since  6.0.6
 */
class CouponsModel extends ListModel
{
    /**
     * Coupon code
     *
     * @var    string
     * @since  6.0.6
     */
    public string $code = '';

    /**
     * Coupon object
     *
     * @var    mixed
     * @since  6.0.6
     */
    public mixed $coupon = false;

    /**
     * Limit usage to x items
     *
     * @var    string
     * @since  6.0.6
     */
    public string $limit_usage_to_x_items = '';

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   6.0.6
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'j2commerce_coupon_id', 'a.j2commerce_coupon_id',
                'coupon_name', 'a.coupon_name',
                'coupon_code', 'a.coupon_code',
                'value', 'a.value',
                'value_type', 'a.value_type',
                'valid_from', 'a.valid_from',
                'valid_to', 'a.valid_to',
                'enabled', 'a.enabled',
                'ordering', 'a.ordering',
                'max_uses', 'a.max_uses',
                'free_shipping', 'a.free_shipping',
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
     * @since   6.0.6
     */
    protected function populateState($ordering = 'a.ordering', $direction = 'asc'): void
    {
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        $enabled = $this->getUserStateFromRequest($this->context . '.filter.enabled', 'filter_enabled', '', 'string');
        $this->setState('filter.enabled', $enabled);

        $valueType = $this->getUserStateFromRequest($this->context . '.filter.value_type', 'filter_value_type', '', 'string');
        $this->setState('filter.value_type', $valueType);

        $freeShipping = $this->getUserStateFromRequest($this->context . '.filter.free_shipping', 'filter_free_shipping', '', 'string');
        $this->setState('filter.free_shipping', $freeShipping);

        parent::populateState($ordering, $direction);
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     *
     * @since   6.0.6
     */
    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.enabled');
        $id .= ':' . $this->getState('filter.value_type');
        $id .= ':' . $this->getState('filter.free_shipping');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  QueryInterface
     *
     * @since   6.0.6
     */
    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select(
            $this->getState(
                'list.select',
                $db->quoteName([
                    'a.j2commerce_coupon_id',
                    'a.coupon_name',
                    'a.coupon_code',
                    'a.value',
                    'a.value_type',
                    'a.valid_from',
                    'a.valid_to',
                    'a.enabled',
                    'a.ordering',
                    'a.max_uses',
                    'a.free_shipping',
                    'a.max_customer_uses',
                    'a.logged',
                ])
            )
        );

        $query->from($db->quoteName('#__j2commerce_coupons', 'a'));

        // Filter by enabled state
        $enabled = (string) $this->getState('filter.enabled');

        if (is_numeric($enabled)) {
            $enabledInt = (int) $enabled;
            $query->where($db->quoteName('a.enabled') . ' = :enabled')
                ->bind(':enabled', $enabledInt, ParameterType::INTEGER);
        } elseif ($enabled === '') {
            $query->where($db->quoteName('a.enabled') . ' IN (0, 1)');
        }

        // Filter by value type
        $valueType = $this->getState('filter.value_type');
        if (!empty($valueType)) {
            $query->where($db->quoteName('a.value_type') . ' = :value_type')
                ->bind(':value_type', $valueType);
        }

        // Filter by free shipping
        $freeShipping = (string) $this->getState('filter.free_shipping');
        if (is_numeric($freeShipping)) {
            $freeShippingInt = (int) $freeShipping;
            $query->where($db->quoteName('a.free_shipping') . ' = :free_shipping')
                ->bind(':free_shipping', $freeShippingInt, ParameterType::INTEGER);
        }

        // Filter by search
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $searchId = (int) substr($search, 3);
                $query->where($db->quoteName('a.j2commerce_coupon_id') . ' = :searchId')
                    ->bind(':searchId', $searchId, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', trim($search)) . '%';
                $query->where(
                    '(' .
                    $db->quoteName('a.coupon_name') . ' LIKE :search1 OR ' .
                    $db->quoteName('a.coupon_code') . ' LIKE :search2' .
                    ')'
                )
                    ->bind(':search1', $search)
                    ->bind(':search2', $search);
            }
        }

        // Add ordering clause
        $orderCol  = $this->state->get('list.ordering', 'a.ordering');
        $orderDir  = $this->state->get('list.direction', 'ASC');
        $ordering  = $db->escape($orderCol) . ' ' . $db->escape($orderDir);

        $query->order($ordering);

        return $query;
    }

    /**
     * Method to get an array of data items.
     *
     * @return  array  An array of data items
     *
     * @since   6.0.6
     */
    public function getItems(): array
    {
        $items = parent::getItems();

        // Ensure we always return an array
        if ($items === false || !\is_array($items)) {
            $app = Factory::getApplication();
            $app->enqueueMessage('Failed to retrieve coupons from database. Please check if the j2commerce_coupons table exists.', 'warning');

            return [];
        }

        // Add computed properties for display
        foreach ($items as $item) {
            // Format dates
            if ($item->valid_from && $item->valid_from !== '0000-00-00 00:00:00') {
                $item->valid_from_formatted = Factory::getDate($item->valid_from)->format('Y-m-d H:i:s');
            } else {
                $item->valid_from_formatted = '';
            }

            if ($item->valid_to && $item->valid_to !== '0000-00-00 00:00:00') {
                $item->valid_to_formatted = Factory::getDate($item->valid_to)->format('Y-m-d H:i:s');
            } else {
                $item->valid_to_formatted = '';
            }

            // Check if coupon is expired
            $now              = Factory::getDate()->toSql();
            $item->is_expired = false;
            if ($item->valid_to && $item->valid_to !== '0000-00-00 00:00:00' && $item->valid_to < $now) {
                $item->is_expired = true;
            }

            // Format value display
            if (str_contains($item->value_type ?? '', 'percentage')) {
                $item->value_display = number_format((float) $item->value, 2) . '%';
            } else {
                $item->value_display = number_format((float) $item->value, 2);
            }
        }

        return $items;
    }

    /**
     * Method to get a table object, load it if necessary.
     *
     * @param   string  $name     The table name. Optional.
     * @param   string  $prefix   The class prefix. Optional.
     * @param   array   $options  Configuration array for model. Optional.
     *
     * @return  \Joomla\CMS\Table\Table  A Table object
     *
     * @since   6.0.6
     * @throws  \Exception
     */
    public function getTable($name = 'Coupon', $prefix = 'Administrator', $options = [])
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get the filter form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  \Joomla\CMS\Form\Form|bool  A Form object on success, false on failure
     *
     * @since   6.0.6
     */
    public function getFilterForm($data = [], $loadData = true)
    {
        return $this->loadForm('com_j2commerce.coupons.filter', 'filter_coupons', ['control' => '', 'load_data' => $loadData]);
    }

    /**
     * Method to get an array of data items for the active filters.
     *
     * @return  array
     *
     * @since   6.0.6
     */
    public function getActiveFilters(): array
    {
        $activeFilters = [];

        if (!empty($this->getState('filter.search'))) {
            $activeFilters['search'] = $this->getState('filter.search');
        }

        if ($this->getState('filter.enabled') !== '') {
            $activeFilters['enabled'] = $this->getState('filter.enabled');
        }

        if (!empty($this->getState('filter.value_type'))) {
            $activeFilters['value_type'] = $this->getState('filter.value_type');
        }

        if ($this->getState('filter.free_shipping') !== '') {
            $activeFilters['free_shipping'] = $this->getState('filter.free_shipping');
        }

        return $activeFilters;
    }

    /**
     * Method to change the enabled state of one or more records.
     *
     * @param   array    $pks    A list of the primary keys to change.
     * @param   integer  $value  The value of the enabled state.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.6
     */
    public function publish(array &$pks, int $value = 1): bool
    {
        $user  = Factory::getApplication()->getIdentity();
        $table = $this->getTable();
        $pks   = (array) $pks;

        // Include the content plugins for the on save events.
        PluginHelper::importPlugin('content');

        // Access checks.
        foreach ($pks as $i => $pk) {
            if ($table->load($pk)) {
                if (!$user->authorise('core.edit.state', 'com_j2commerce.coupon.' . $pk)) {
                    // Prune items that you can't change.
                    unset($pks[$i]);
                    Log::add(Text::_('JERROR_CORE_EDIT_STATE_NOT_PERMITTED'), Log::WARNING, 'jerror');
                }
            }
        }

        // Attempt to change the state of the records.
        if (!$table->publish($pks, $value, $user->get('id'))) {
            throw new \RuntimeException($table->getError());
        }

        $context = $this->option . '.' . $this->name;

        // Trigger the content plugins for the enabled state change.
        $result = Factory::getApplication()->triggerEvent('onContentChangeState', [$context, $pks, $value]);

        if (\in_array(false, $result, true)) {
            throw new \RuntimeException($table->getError());
        }

        // Clear the component's cache
        $this->cleanCache();

        return true;
    }

    /**
     * Method to delete one or more records.
     *
     * @param   array  $pks  A list of the primary keys to delete.
     *
     * @return  boolean  True on success, false on failure.
     *
     * @since   6.0.6
     */
    public function delete(array &$pks): bool
    {
        $user  = Factory::getApplication()->getIdentity();
        $table = $this->getTable();
        $pks   = (array) $pks;

        // Include the content plugins for the on delete events.
        PluginHelper::importPlugin('content');

        // Access checks.
        foreach ($pks as $i => $pk) {
            if ($table->load($pk)) {
                if (!$user->authorise('core.delete', 'com_j2commerce.coupon.' . $pk)) {
                    // Prune items that you can't delete.
                    unset($pks[$i]);
                    Log::add(Text::_('JERROR_CORE_DELETE_NOT_PERMITTED'), Log::WARNING, 'jerror');
                }
            }
        }

        if (empty($pks)) {
            return true;
        }

        $context = $this->option . '.' . $this->name;

        // Trigger the before delete event.
        $result = Factory::getApplication()->triggerEvent('onContentBeforeDelete', [$context, $table]);

        if (\in_array(false, $result, true)) {
            throw new \RuntimeException($table->getError());
        }

        // Attempt to delete the records.
        foreach ($pks as $pk) {
            if (!$table->delete($pk)) {
                throw new \RuntimeException($table->getError());
            }

            // Trigger the after delete event.
            $result = Factory::getApplication()->triggerEvent('onContentAfterDelete', [$context, $table]);

            if (\in_array(false, $result, true)) {
                throw new \RuntimeException($table->getError());
            }
        }

        // Clear the component's cache
        $this->cleanCache();

        return true;
    }

    /**
     * Method to check in one or more records.
     *
     * @param   array  $pks  A list of the primary keys to check in.
     *
     * @return  boolean  True on success, false on failure.
     *
     * @since   6.0.6
     */
    public function checkin(array &$pks = []): bool
    {
        $user  = Factory::getApplication()->getIdentity();
        $table = $this->getTable();
        $pks   = (array) $pks;

        // If there are no primary keys set then use the instance.
        if (empty($pks)) {
            $pks = [$table->getKeyName() => $table->getKeyValue()];
        }

        foreach ($pks as $pk) {
            if ($table->load($pk)) {
                if (!$user->authorise('core.edit.state', 'com_j2commerce.coupon.' . $pk)) {
                    // Prune items that you can't change.
                    continue;
                }

                if (!$table->checkIn($pk)) {
                    throw new \RuntimeException($table->getError());
                }
            }
        }

        return true;
    }

    /**
     * Get the active coupon code from cart or session.
     *
     * Retrieves the coupon code from the cart table if available,
     * otherwise falls back to the session. Also stores the coupon
     * code in session for consistency.
     *
     * @return  string  The coupon code or empty string if not found.
     *
     * @since   6.0.6
     */
    public function get_coupon(): string
    {
        $app        = Factory::getApplication();
        $session    = $app->getSession();
        $couponCode = '';

        try {
            // Try to get cart model and check for cart_coupon
            $cartModel = $app->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createModel('Cart', 'Administrator');

            if ($cartModel) {
                $cart = $cartModel->getCart(0, false);

                if (isset($cart->cart_coupon) && !empty($cart->cart_coupon)) {
                    // Store coupon from cart to session
                    $session->set('coupon', $cart->cart_coupon, 'j2commerce');
                    $couponCode = $cart->cart_coupon;
                } else {
                    // Fall back to session-stored coupon
                    $couponCode = $session->get('coupon', '', 'j2commerce');
                }
            } else {
                // Fall back to session if cart model unavailable
                $couponCode = $session->get('coupon', '', 'j2commerce');
            }
        } catch (\Exception $e) {
            // Fall back to session on any error
            $couponCode = $session->get('coupon', '', 'j2commerce');
        }

        return $couponCode;
    }

    /**
     * Set the coupon code in session.
     *
     * @param   string  $couponCode  The coupon code to set.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    public function set_coupon(string $couponCode): void
    {
        $session = Factory::getApplication()->getSession();
        $session->set('coupon', $couponCode, 'j2commerce');
    }

    /**
     * Clear the coupon code from the session.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    public function clear_coupon(): void
    {
        $session = Factory::getApplication()->getSession();
        $session->clear('coupon', 'j2commerce');
    }
}
