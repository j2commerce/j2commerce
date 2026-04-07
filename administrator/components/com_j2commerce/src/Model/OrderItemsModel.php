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
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Plugin\PluginHelper;

/**
 * Orderhistories Model
 *
 * @since  6.0.0
 */
class OrderItemsModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since  6.0.0
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        parent::__construct($config, $factory);
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
    public function getTable($name = 'Orderitem', $prefix = 'Administrator', $options = [])
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
        return $this->loadForm('com_j2commerce.orderitems.filter', 'filter_orderitems', ['control' => '', 'load_data' => $loadData]);
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
                if (!$user->authorise('core.edit.state', 'com_j2commerce.orderitems.' . $pk)) {
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
                if (!$user->authorise('core.delete', 'com_j2commerce.orderitems.' . $pk)) {
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

    public function getItemsByOrder($order_id)
    {
        if (empty($order_id)) {
            return [];
        }

        $query = $this->_db->getQuery(true);
        $query->select('*')->from('#__j2commerce_orderitems')->where('order_id = '.$this->_db->q($order_id));
        $this->_db->setQuery($query);
        return  $this->_db->loadObjectList();
    }

}
