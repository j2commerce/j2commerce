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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Plugin\PluginHelper;

/**
 * Orderhistories Model
 *
 * @since  6.0.0
 */
class OrderhistoriesModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since  6.0.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'j2commerce_orderhistory_id', 'order_id',
                'order_state_id', 'notify_customer',
                'comment', 'created_on',
                'created_by', 'params',
            ];
        }

        parent::__construct($config);
    }

    /**
     * Method to autopopulate the model state.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     *
     * @since  6.0.0
     */
    protected function populateState($ordering = 'a.orderstatus_name', $direction = 'asc')
    {
        // Load the filter state.
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $search);

        $enabled = $this->getUserStateFromRequest($this->context . '.filter.enabled', 'filter_enabled', '');
        $this->setState('filter.enabled', $enabled);

        $core = $this->getUserStateFromRequest($this->context . '.filter.core', 'filter_core', '');
        $this->setState('filter.core', $core);

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
     * @since  6.0.0
     */
    protected function getStoreId($id = '')
    {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  \Joomla\Database\DatabaseQuery
     *
     * @since  6.0.0
     */
    protected function getListQuery()
    {
        // Create a new query object.
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select(
            $this->getState(
                'list.select',
                'a.*'
            )
        );
        $query->from($db->quoteName('#__j2commerce_orderhistories', 'a'));

        // Add the list ordering clause.
        $orderCol  = $this->getState('list.ordering', 'a.created_on');
        $orderDirn = $this->getState('list.direction', 'DESC');

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
     * @since  6.0.0
     */
    public function getItems()
    {
        $items = parent::getItems();

        // Ensure we always return an array
        if ($items === false || !\is_array($items)) {
            // Log the error for debugging
            $app = Factory::getApplication();
            $app->enqueueMessage('Failed to retrieve order histories from database. Please check if the j2commerce_orderhistories table exists.', 'warning');
            return [];
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
     * @since  6.0.0
     * @throws  \Exception
     */
    public function getTable($name = 'Orderhistory', $prefix = 'Administrator', $options = [])
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get the filter form.
     *
     * @param   array    $data      data
     * @param   boolean  $loadData  load current data
     *
     * @return  \Joomla\CMS\Form\Form|false  The form object or false on error
     *
     * @since  6.0.0
     */
    public function getFilterForm($data = [], $loadData = true)
    {
        return $this->loadForm('com_j2commerce.orderhistories.filter', 'filter_orderhistories', ['control' => '', 'load_data' => $loadData]);
    }

    /**
     * Method to change the enabled state of one or more records.
     *
     * @param   array    $pks    A list of the primary keys to change.
     * @param   integer  $value  The value of the enabled state.
     *
     * @return  boolean  True on success.
     *
     * @since  6.0.0
     */
    public function publish(&$pks, $value = 1)
    {
        $user  = Factory::getApplication()->getIdentity();
        $table = $this->getTable();
        $pks   = (array) $pks;

        // Include the content plugins for the on-save events.
        PluginHelper::importPlugin('content');

        // Access checks.
        foreach ($pks as $i => $pk) {
            if ($table->load($pk)) {
                if (!$user->authorise('core.edit.state', 'com_j2commerce.orderhistory.' . $pk)) {
                    // Prune items that you can't change.
                    unset($pks[$i]);
                    Log::add(Text::_('JERROR_CORE_EDIT_STATE_NOT_PERMITTED'), Log::WARNING, 'jerror');
                }
            }
        }

        // Attempt to change the state of the records.
        if (!$table->publish($pks, $value, $user->id)) {
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
     * @since  6.0.0
     */
    public function delete(&$pks)
    {
        $user  = Factory::getApplication()->getIdentity();
        $table = $this->getTable();
        $pks   = (array) $pks;

        // Include the content plugins for the on delete events.
        PluginHelper::importPlugin('content');

        // Access checks.
        foreach ($pks as $i => $pk) {
            if ($table->load($pk)) {
                if (!$user->authorise('core.delete', 'com_j2commerce.orderhistory.' . $pk)) {
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

    public function setOrderHistory($order, $comment = '', $notify=0)
    {

        if (!isset($order->order_id)) {
            return;
        }

        if (empty($comment)) {
            $comment = Text::_('COM_J2COMMERCE_ORDER_UPDATED');
        }

        $history = $this->getTable();
        $history->reset();
        $history->j2commerce_orderhistory_id  = 0;
        $values                               = [];
        $values['j2commerce_orderhistory_id'] = null;
        $values['order_id']                   = $order->order_id;
        $values['order_state_id']             = $order->order_state_id;
        $values['comment']                    = $comment;
        $values['notify_customer']            = $notify;
        $history->save($values);

        return true;
    }

}
