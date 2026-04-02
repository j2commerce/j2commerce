<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use RuntimeException;

/**
 * Emailtemplates Model
 *
 * @since  6.0.0
 */
class EmailtemplatesModel extends ListModel
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
                'j2commerce_emailtemplate_id', 'a.j2commerce_emailtemplate_id',
                'email_type', 'a.email_type',
                'receiver_type', 'a.receiver_type',
                'subject', 'a.subject',
                'orderstatus_id', 'a.orderstatus_id',
                'group_id', 'a.group_id',
                'paymentmethod', 'a.paymentmethod',
                'language', 'a.language',
                'enabled', 'a.enabled',
                'ordering', 'a.ordering'
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
    protected function populateState($ordering = 'a.j2commerce_emailtemplate_id', $direction = 'asc')
    {
        // Load the filter state.
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $search);

        $enabled = $this->getUserStateFromRequest($this->context . '.filter.enabled', 'filter_enabled', '');
        $this->setState('filter.enabled', $enabled);

        $emailType = $this->getUserStateFromRequest($this->context . '.filter.email_type', 'filter_email_type', '');
        $this->setState('filter.email_type', $emailType);

        $orderStatus = $this->getUserStateFromRequest($this->context . '.filter.orderstatus_id', 'filter_orderstatus_id', '');
        $this->setState('filter.orderstatus_id', $orderStatus);

        $groupID = $this->getUserStateFromRequest($this->context . '.filter.group_id', 'filter_group_id', '');
        $this->setState('filter.group_id', $groupID);

        $paymentMethod = $this->getUserStateFromRequest($this->context . '.filter.paymentmethod', 'filter_paymentmethod', '');
        $this->setState('filter.paymentmethod', $paymentMethod);

        $language = $this->getUserStateFromRequest($this->context . '.filter.language', 'filter_language', '');
        $this->setState('filter.language', $language);

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
        $id .= ':' . $this->getState('filter.enabled');
        $id .= ':' . $this->getState('filter.email_type');
        $id .= ':' . $this->getState('filter.orderstatus_id');
        $id .= ':' . $this->getState('filter.group_id');
        $id .= ':' . $this->getState('filter.paymentmethod');
        $id .= ':' . $this->getState('filter.language');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  \Joomla\Database\DatabaseQuery
     *
     * @since   6.0.0
     */
    protected function getListQuery()
    {
        // Create a new query object.
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select(
            $this->getState(
                'list.select',
                'a.*'
            )
        );
        $query->from($db->quoteName('#__j2commerce_emailtemplates', 'a'));

        // Filter by enabled state
        $enabled = (string) $this->getState('filter.enabled');

        if (is_numeric($enabled)) {
            $query->where($db->quoteName('a.enabled') . ' = :enabled')
                ->bind(':enabled', $enabled, ParameterType::INTEGER);
        } elseif ($enabled === '') {
            $query->where($db->quoteName('a.enabled') . ' IN (0, 1)');
        }

        // Filter by email type
        $emailType = $this->getState('filter.email_type');
        if (!empty($emailType)) {
            $query->where($db->quoteName('a.email_type') . ' = :email_type')
                ->bind(':email_type', $emailType);
        }

        // Filter by order status
        $orderStatus = $this->getState('filter.orderstatus_id');
        if (!empty($orderStatus)) {
            $query->where($db->quoteName('a.orderstatus_id') . ' = :orderstatus_id')
                ->bind(':orderstatus_id', $orderStatus);
        }

        // Filter by user group
        $groupID = $this->getState('filter.group_id');
        if (!empty($groupID)) {
            $query->where($db->quoteName('a.group_id') . ' = :group_id')
                ->bind(':group_id', $groupID);
        }

        // Filter by payment method
        $paymentMethod = $this->getState('filter.paymentmethod');
        if (!empty($paymentMethod)) {
            $query->where($db->quoteName('a.paymentmethod') . ' = :paymentmethod')
                ->bind(':paymentmethod', $paymentMethod);
        }

        // Filter by language
        $language = $this->getState('filter.language');
        if (!empty($language)) {
            $query->where($db->quoteName('a.language') . ' = :language')
                ->bind(':language', $language);
        }

        // Filter by search in subject and body
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $search = (int) substr($search, 3);
                $query->where($db->quoteName('a.j2commerce_emailtemplate_id') . ' = :id')
                    ->bind(':id', $search, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', $db->escape(trim($search), true) . '%');
                $query->where('(' . $db->quoteName('a.subject') . ' LIKE :search1 OR ' . $db->quoteName('a.body') . ' LIKE :search2)')
                    ->bind(':search1', $search)
                    ->bind(':search2', $search);
            }
        }

        // Add the list ordering clause.
        $orderCol = $this->getState('list.ordering', 'a.j2commerce_emailtemplate_id');
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

        // Ensure we always return an array
        if ($items === false || !is_array($items)) {
            // Log the error for debugging
            $app = Factory::getApplication();
            $app->enqueueMessage('Failed to retrieve email templates from database. Please check if the j2commerce_emailtemplates table exists.', 'warning');
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
     * @since   6.0.0
     * @throws  \Exception
     */
    public function getTable($name = 'Emailtemplate', $prefix = 'Administrator', $options = [])
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
     * @since   6.0.0
     */
    public function getFilterForm($data = [], $loadData = true)
    {
        return $this->loadForm('com_j2commerce.emailtemplates.filter', 'filter_emailtemplates', ['control' => '', 'load_data' => $loadData]);
    }

    /**
     * Method to change the enabled state of one or more records.
     *
     * @param   array    $pks    A list of the primary keys to change.
     * @param   integer  $value  The value of the enabled state.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.0
     */
    public function publish(&$pks, $value = 1)
    {
        $user = Factory::getApplication()->getIdentity();
        $table = $this->getTable();
        $pks = (array) $pks;

        // Include the content plugins for the on save events.
        \Joomla\CMS\Plugin\PluginHelper::importPlugin('content');

        // Access checks.
        foreach ($pks as $i => $pk) {
            if ($table->load($pk)) {
                if (!$user->authorise('core.edit.state', 'com_j2commerce.emailtemplate.' . $pk)) {
                    // Prune items that you can't change.
                    unset($pks[$i]);
                    \Joomla\CMS\Log\Log::add(\Joomla\CMS\Language\Text::_('JERROR_CORE_EDIT_STATE_NOT_PERMITTED'), \Joomla\CMS\Log\Log::WARNING, 'jerror');
                }
            }
        }

        // Attempt to change the state of the records.
        if (!$table->publish($pks, $value, $user->get('id'))) {
            throw new RuntimeException($table->getError());
        }

        $context = $this->option . '.' . $this->name;

        // Trigger the content plugins for the enabled state change.
        $result = Factory::getApplication()->triggerEvent('onContentChangeState', [$context, $pks, $value]);

        if (in_array(false, $result, true)) {
            throw new RuntimeException($table->getError());
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
     * @since   6.0.0
     */
    public function delete(&$pks)
    {
        $user = Factory::getApplication()->getIdentity();
        $table = $this->getTable();
        $pks = (array) $pks;

        // Include the content plugins for the on delete events.
        \Joomla\CMS\Plugin\PluginHelper::importPlugin('content');

        // Access checks.
        foreach ($pks as $i => $pk) {
            if ($table->load($pk)) {
                if (!$user->authorise('core.delete', 'com_j2commerce.emailtemplate.' . $pk)) {
                    // Prune items that you can't delete.
                    unset($pks[$i]);
                    \Joomla\CMS\Log\Log::add(\Joomla\CMS\Language\Text::_('JERROR_CORE_DELETE_NOT_PERMITTED'), \Joomla\CMS\Log\Log::WARNING, 'jerror');
                }
            }
        }

        if (empty($pks)) {
            return true;
        }

        $context = $this->option . '.' . $this->name;

        // Trigger the before delete event.
        $result = Factory::getApplication()->triggerEvent('onContentBeforeDelete', [$context, $table]);

        if (in_array(false, $result, true)) {
            throw new RuntimeException($table->getError());
        }

        // Attempt to delete the records.
        foreach ($pks as $pk) {
            if (!$table->delete($pk)) {
                throw new RuntimeException($table->getError());
            }

            // Trigger the after delete event.
            $result = Factory::getApplication()->triggerEvent('onContentAfterDelete', [$context, $table]);

            if (in_array(false, $result, true)) {
                throw new RuntimeException($table->getError());
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
     * @since   6.0.0
     */
    public function checkin(&$pks = [])
    {
        $user = Factory::getApplication()->getIdentity();
        $table = $this->getTable();
        $pks = (array) $pks;

        // If there are no primary keys set then use the instance.
        if (empty($pks)) {
            $pks = [$table->getKeyName() => $table->getKeyValue()];
        }

        foreach ($pks as $pk) {
            if ($table->load($pk)) {
                if (!$user->authorise('core.edit.state', 'com_j2commerce.emailtemplate.' . $pk)) {
                    // Prune items that you can't change.
                    continue;
                }

                if (!$table->checkIn($pk)) {
                    throw new RuntimeException($table->getError());
                }
            }
        }

        return true;
    }
}
