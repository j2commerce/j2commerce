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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;
use Joomla\String\StringHelper;

/**
 * Filtergroup Model
 *
 * @since  6.0.0
 */
class FiltergroupModel extends AdminModel
{
    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  6.0.0
     */
    public $typeAlias = 'com_j2commerce.filtergroup';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $text_prefix = 'COM_J2COMMERCE_FILTERGROUP';

    /**
     * Method to auto-populate the model state.
     *
     * @return  void
     *
     * @since  6.0.0
     */
    protected function populateState()
    {
        $app   = Factory::getApplication();
        $input = $app->getInput();

        // Get the primary key from the request data
        $pk = $input->getInt('id');

        // Check if this is explicitly a new record request
        $task   = $input->get('task', '');
        $layout = $input->get('layout', '');

        // If no ID in input, check user state (for post-redirect scenarios)
        // But only if we're not explicitly in a "new" or "add" context
        if (!$pk && !\in_array($task, ['add', 'new']) && $layout !== 'edit') {
            $context = 'com_j2commerce.edit.filtergroup';
            $pk      = (int) $app->getUserState($context . '.id');
        }

        // For new records, ensure pk is 0
        if (\in_array($task, ['add', 'new']) || ($layout === 'edit' && !$pk)) {
            $pk = 0;
            // Clear any lingering user state for new records
            $context = 'com_j2commerce.edit.filtergroup';
            $app->setUserState($context . '.id', 0);
        }

        $this->setState('filtergroup.id', $pk);

        // Load the parameters.
        $params = ComponentHelper::getParams('com_j2commerce');
        $this->setState('params', $params);
    }

    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  Form|boolean  A Form object on success, false on failure
     *
     * @since  6.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm('com_j2commerce.filtergroup', 'filtergroup', ['control' => 'jform', 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
        }

        // Set dynamic fieldset label based on group name
        $item = $this->getItem();
        if (!empty($item->group_name)) {
            $filtersLabel = $item->group_name . ' ' . Text::_('COM_J2COMMERCE_FILTERGROUP_FILTERS');
            $form->setFieldAttribute('filters', 'label', $filtersLabel, 'filters');
        }

        // Modify the form based on access controls.
        if (!$this->canEditState((object) $data)) {
            // Disable fields for display.
            $form->setFieldAttribute('ordering', 'disabled', 'true');
            $form->setFieldAttribute('published', 'disabled', 'true');

            // Disable fields while saving.
            $form->setFieldAttribute('ordering', 'filter', 'unset');
            $form->setFieldAttribute('published', 'filter', 'unset');
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since  6.0.0
     */
    protected function loadFormData()
    {
        // Check the session for previously entered form data.
        $data = Factory::getApplication()->getUserState('com_j2commerce.edit.filtergroup.data', []);

        if (empty($data)) {
            $data = $this->getItem();

            // Prime some default values.
            if ($this->getState('filtergroup.id') == 0) {
                $app             = Factory::getApplication();
                $data->published = $app->input->getInt('published', 1);
            }
        }

        $this->preprocessData('com_j2commerce.filtergroup', $data);

        return $data;
    }

    /**
     * Method to get a single record.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  mixed  Object on success, false on failure.
     *
     * @since  6.0.0
     */
    public function getItem($pk = null)
    {
        // If no primary key is provided, get it from the model state
        if ($pk === null) {
            $pk = $this->getState('filtergroup.id');
        }

        // Ensure we have a valid primary key
        if (!$pk || $pk <= 0) {
            // Return empty item for new records
            $item                            = new \stdClass();
            $item->j2commerce_filtergroup_id = 0;
            $item->id                        = 0;
            $item->group_name                = '';
            $item->ordering                  = 0;
            $item->enabled                   = 1;
            $item->published                 = 1;
            $item->filters                   = [];
            return $item;
        }

        if ($item = parent::getItem($pk)) {
            // Map database column names to form field names
            if (property_exists($item, 'j2commerce_filtergroup_id')) {
                $item->id = $item->j2commerce_filtergroup_id;
            }

            // Safely access the enabled property with fallback methods
            if (property_exists($item, 'enabled')) {
                // Try getter method first (preferred approach)
                if (method_exists($item, 'getEnabled')) {
                    $item->published = $item->getEnabled();
                }
                // Try generic get method (common in Joomla objects)
                elseif (method_exists($item, 'get') && \is_callable([$item, 'get'])) {
                    $item->published = $item->get('enabled');
                }
                // Use reflection as last resort for private properties
                else {
                    try {
                        $reflection = new \ReflectionObject($item);
                        if ($reflection->hasProperty('enabled')) {
                            $property = $reflection->getProperty('enabled');
                            $property->setAccessible(true);
                            $item->published = $property->getValue($item);
                        }
                    } catch (\ReflectionException $e) {
                        // If all else fails, set a default value
                        $item->published = 0;
                    }
                }
            }

            // Load associated filters
            $item->filters = $this->getFilters($item->j2commerce_filtergroup_id);
        }

        return $item;
    }

    /**
     * Method to get filters associated with a filter group.
     *
     * @param   integer  $groupId  The filter group ID.
     *
     * @return  array  Array of filter objects.
     *
     * @since  6.0.0
     */
    protected function getFilters($groupId)
    {
        if (!$groupId) {
            return [];
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('j2commerce_filter_id'),
                $db->quoteName('group_id'),
                $db->quoteName('filter_name'),
                $db->quoteName('ordering'),
            ])
            ->from($db->quoteName('#__j2commerce_filters'))
            ->where($db->quoteName('group_id') . ' = :groupId')
            ->order($db->quoteName('ordering') . ' ASC')
            ->bind(':groupId', $groupId, ParameterType::INTEGER);

        $db->setQuery($query);

        try {
            $filters = $db->loadObjectList();
            return \is_array($filters) ? $filters : [];
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to load filters for group ' . $groupId . ': ' . $e->getMessage());
        }
    }

    /**
     * Method to get a table object, load it if necessary.
     *
     * @param   string  $type    The table name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  Table  A Table object
     *
     * @since  6.0.0
     */
    public function getTable($type = 'Filtergroup', $prefix = 'Administrator', $config = [])
    {
        return parent::getTable($type, $prefix, $config);
    }

    /**
     * Method to save the form data.
     *
     * @param   array  $data  The form data.
     *
     * @return  boolean  True on success, False on error.
     *
     * @since  6.0.0
     */
    public function save($data)
    {

        // Map form field names to database column names
        if (isset($data['published'])) {
            $data['enabled'] = $data['published'];
            unset($data['published']);
        }

        // Ensure the correct ID field is used
        if (isset($data['id']) && !isset($data['j2commerce_filtergroup_id'])) {
            $data['j2commerce_filtergroup_id'] = $data['id'];
        }

        // Validate group_name is not empty
        if (empty(trim($data['group_name'] ?? ''))) {
            throw new \RuntimeException(Text::_('COM_J2COMMERCE_ERROR_FILTERGROUP_GROUP_NAME_REQUIRED'));
        }

        // Handle save2copy — generate unique group_name and force new record
        $app = Factory::getApplication();
        if ($app->getInput()->get('task') === 'save2copy') {
            $origTable = clone $this->getTable();
            $origTable->load($app->getInput()->getInt('id'));

            if ($data['group_name'] === $origTable->group_name) {
                [$name]             = $this->generateNewTitle(null, null, $data['group_name']);
                $data['group_name'] = $name;
            }

            // Force new record: clear the PK so AdminModel::save() doesn't
            // fall back to getState() which still holds the original ID
            $data['j2commerce_filtergroup_id'] = 0;
            $this->setState('filtergroup.id', 0);
        }

        // Extract filters data before saving the parent record
        $filtersData = $data['filters'] ?? [];
        unset($data['filters']);

        // Include the content plugins for the on save events.
        PluginHelper::importPlugin('content');

        $parentSaveResult = parent::save($data);

        if ($parentSaveResult) {
            // Get the saved record ID using multiple fallback methods
            $recordId = null;

            // Method 1: Try to get ID from original data (for existing records)
            if (isset($data['j2commerce_filtergroup_id']) && (int)$data['j2commerce_filtergroup_id'] > 0) {
                $recordId = (int)$data['j2commerce_filtergroup_id'];
            }

            // Method 2: Try to get ID from model state (set by parent::save)
            if (!$recordId) {
                $stateId = $this->getState('filtergroup.id');
                if ($stateId && (int)$stateId > 0) {
                    $recordId = (int)$stateId;
                }
            }

            // Method 3: Load the table and try to find the record by group_name (for new records)
            if (!$recordId && !empty($data['group_name'])) {
                $table = $this->getTable();
                $db    = Factory::getContainer()->get('DatabaseDriver');
                $query = $db->getQuery(true)
                    ->select($db->quoteName('j2commerce_filtergroup_id'))
                    ->from($db->quoteName('#__j2commerce_filtergroups'))
                    ->where($db->quoteName('group_name') . ' = :groupName')
                    ->bind(':groupName', $data['group_name'], ParameterType::STRING)
                    ->order($db->quoteName('j2commerce_filtergroup_id') . ' DESC'); // Get the latest one

                $db->setQuery($query);
                $foundId = $db->loadResult();

                if ($foundId && (int)$foundId > 0) {
                    $recordId = (int)$foundId;
                }
            }

            if ($recordId) {
                // Set the ID for redirect after save
                $this->setState('filtergroup.id', $recordId);

                // Save the filters data
                try {
                    $this->saveFilters($recordId, $filtersData);
                } catch (\RuntimeException $e) {
                    throw new \RuntimeException('Failed to save filter group: ' . $e->getMessage());
                }
            } else {
                throw new \RuntimeException('Failed to determine the saved record ID');
            }

            return true;
        }

        return false;
    }

    /**
     * Method to change the published state of one or more records.
     *
     * @param   array    &$pks   A list of the primary keys to change.
     * @param   integer  $value  The value of the published state.
     *
     * @return  boolean  True on success.
     *
     * @since  6.0.0
     */
    public function publish(&$pks, $value = 1)
    {
        // Include the content plugins for the change of state event.
        PluginHelper::importPlugin('content');

        return parent::publish($pks, $value);
    }

    /**
     * Method to adjust the ordering of a row.
     *
     * @param   integer  $pks    The ID of the primary key to move.
     * @param   integer  $delta  Increment, usually +1 or -1
     *
     * @return  boolean  False on failure or error, true otherwise.
     *
     * @since  6.0.0
     */
    public function reorder($pks, $delta = 0)
    {
        $table  = $this->getTable();
        $pks    = (array) $pks;
        $result = true;

        $allowed = true;

        foreach ($pks as $i => $pk) {
            $table->reset();

            if ($table->load($pk)) {
                // Access checks.
                if (!$this->canEditState($table)) {
                    // Prune items that you can't change.
                    unset($pks[$i]);
                    $allowed = false;
                    continue;
                }

                $where = [];

                if (!$table->move($delta, $where)) {
                    throw new \RuntimeException($table->getError());
                }
            } else {
                throw new \RuntimeException($table->getError());
            }
        }

        if ($allowed === false && empty($pks)) {
            $result = null;
        }

        // Clear the component's cache
        $this->cleanCache();

        return $result;
    }

    /**
     * Method to save filters associated with a filter group.
     *
     * @param   integer  $groupId      The filter group ID.
     * @param   array    $filtersData  Array of filter data.
     *
     * @return  boolean  True on success, false on failure.
     *
     * @since  6.0.0
     */
    protected function saveFilters($groupId, $filtersData)
    {
        if (!$groupId) {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        try {
            // Start a database transaction
            $db->transactionStart();

            // First, delete all existing filters for this group
            $deleteQuery = $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_filters'))
                ->where($db->quoteName('group_id') . ' = :groupId')
                ->bind(':groupId', $groupId, ParameterType::INTEGER);

            $db->setQuery($deleteQuery);
            $db->execute();

            // Now insert the new filters with auto-ordering
            if (!empty($filtersData) && \is_array($filtersData)) {
                $orderingCounter = 1; // Start ordering from 1

                foreach ($filtersData as $filterData) {
                    // Skip empty filter names
                    if (empty(trim($filterData['filter_name'] ?? ''))) {
                        continue;
                    }

                    $insertQuery = $db->getQuery(true)
                        ->insert($db->quoteName('#__j2commerce_filters'))
                        ->columns([
                            $db->quoteName('group_id'),
                            $db->quoteName('filter_name'),
                            $db->quoteName('ordering'),
                        ])
                        ->values(':groupId, :filterName, :ordering')
                        ->bind(':groupId', $groupId, ParameterType::INTEGER)
                        ->bind(':filterName', trim($filterData['filter_name']), ParameterType::STRING)
                        ->bind(':ordering', $orderingCounter, ParameterType::INTEGER);

                    $db->setQuery($insertQuery);
                    $db->execute();

                    // Increment ordering counter for next filter
                    $orderingCounter++;
                }
            }

            // Commit the transaction
            $db->transactionCommit();

            return true;

        } catch (\Exception $e) {
            // Rollback the transaction on error
            $db->transactionRollback();
            throw new \RuntimeException('Failed to save filters for group ' . $groupId . ': ' . $e->getMessage());
        }
    }

    /**
     * Method to generate a unique group name for copies.
     *
     * @param   integer|null  $categoryId  Unused (required for parent signature).
     * @param   string|null   $alias       Unused (required for parent signature).
     * @param   string        $title       The group name.
     *
     * @return  array  Contains the modified title and alias.
     *
     * @since   6.1.5
     */
    protected function generateNewTitle($categoryId, $alias, $title): array
    {
        $table = $this->getTable();

        while ($table->load(['group_name' => $title])) {
            $title = StringHelper::increment($title);
        }

        return [$title, $alias];
    }
}
