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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;
use RuntimeException;

/**
 * Option Model
 *
 * @since  6.0.0
 */
class OptionModel extends AdminModel
{
    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  6.0.0
     */
    public $typeAlias = 'com_j2commerce.option';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $text_prefix = 'COM_J2COMMERCE_OPTION';

    /**
     * Method to auto-populate the model state.
     *
     * @return  void
     *
     * @since  6.0.0
     */
    protected function populateState()
    {
        $app = Factory::getApplication();
        $input = $app->getInput();

        // Get the primary key from the request data
        $pk = $input->getInt('id');

        // Check if this is explicitly a new record request
        $task = $input->get('task', '');
        $layout = $input->get('layout', '');

        // If no ID in input, check user state (for post-redirect scenarios)
        // But only if we're not explicitly in a "new" or "add" context
        if (!$pk && !in_array($task, ['add', 'new']) && $layout !== 'edit') {
            $context = 'com_j2commerce.edit.option';
            $pk = (int) $app->getUserState($context . '.id');
        }

        // For new records, ensure pk is 0
        if (in_array($task, ['add', 'new']) || ($layout === 'edit' && !$pk)) {
            $pk = 0;
            // Clear any lingering user state for new records
            $context = 'com_j2commerce.edit.option';
            $app->setUserState($context . '.id', 0);
        }

        $this->setState('option.id', $pk);

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
        $form = $this->loadForm('com_j2commerce.option', 'option', ['control' => 'jform', 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
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
        $data = Factory::getApplication()->getUserState('com_j2commerce.edit.option.data', []);

        if (empty($data)) {
            $data = $this->getItem();

            // Prime some default values.
            if ($this->getState('option.id') == 0) {
                $app = Factory::getApplication();
                $data->published = $app->input->getInt('published', 1);
                $data->enabled = 1;
                $data->ordering = 0;
            }
        }

        $this->preprocessData('com_j2commerce.option', $data);

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
            $pk = $this->getState('option.id');
        }

        // Ensure we have a valid primary key
        if (!$pk || $pk <= 0) {
            // Return empty item for new records
            $item = new \stdClass();
            $item->j2commerce_option_id = 0;
            $item->id = 0;
            $item->enabled = 1;
            $item->published = 1;
            $item->type = '';
            $item->option_unique_name = '';
            $item->option_name = '';
            $item->ordering = 0;
            $item->option_params = '';
            $item->optionvalues = [];
            $item->optioncolorvalues = [];
            return $item;
        }

        if ($item = parent::getItem($pk)) {
            // Map database column names to form field names
            if (property_exists($item, 'j2commerce_option_id')) {
                $item->id = $item->j2commerce_option_id;
            }

            // Map enabled to published for consistent form handling
            if (property_exists($item, 'enabled')) {
                $item->published = $item->enabled;
            }

            // Ensure all required fields have default values
            $item->type = $item->type ?? '';
            $item->option_unique_name = $item->option_unique_name ?? '';
            $item->option_name = $item->option_name ?? '';
            $item->ordering = $item->ordering ?? 0;
            $item->option_params = $item->option_params ?? '';

            // Load option values based on the type
            if ($item->id > 0) {
                if (in_array($item->type, ['select', 'radio', 'checkbox'])) {
                    $item->optionvalues = $this->getOptionValues($item->id);
                    $item->optioncolorvalues = [];
                } elseif ($item->type === 'color') {
                    $item->optioncolorvalues = $this->getOptionColorValues($item->id);
                    $item->optionvalues = [];
                } else {
                    $item->optionvalues = [];
                    $item->optioncolorvalues = [];
                }
            } else {
                $item->optionvalues = [];
                $item->optioncolorvalues = [];
            }
        }

        return $item;
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
    public function getTable($type = 'Option', $prefix = 'Administrator', $config = [])
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
        if (isset($data['id']) && !isset($data['j2commerce_option_id'])) {
            $data['j2commerce_option_id'] = $data['id'];
        }

        // Generate unique name from option name if not provided
        if (empty($data['option_unique_name']) && !empty($data['option_name'])) {
            $data['option_unique_name'] = $this->generateUniqueAlias($data['option_name']);
        }

        // Validate unique name uniqueness
        if (!empty($data['option_unique_name'])) {
            $id = isset($data['j2commerce_option_id']) ? (int) $data['j2commerce_option_id'] : 0;
            if (!$this->validateUniqueAlias($data['option_unique_name'], $id)) {
                $this->setError(Text::_('COM_J2COMMERCE_OPTION_ERROR_UNIQUE_NAME_EXISTS'));
                return false;
            }
        }

        // Include the content plugins for the on save events.
        PluginHelper::importPlugin('content');

        // Extract subform data before saving the main record
        $optionValuesData = isset($data['optionvalues']) ? $data['optionvalues'] : [];
        $optionColorValuesData = isset($data['optioncolorvalues']) ? $data['optioncolorvalues'] : [];

        // Process subform data if it's JSON
        if (is_string($optionValuesData)) {
            $optionValuesData = json_decode($optionValuesData, true) ?: [];
        }
        if (is_string($optionColorValuesData)) {
            $optionColorValuesData = json_decode($optionColorValuesData, true) ?: [];
        }

        // Convert complex arrays to JSON for database storage
        if (is_array($optionValuesData) && count($optionValuesData) > 0) {
            $registry = new Registry($optionValuesData);
            $data['option_values'] = $registry->toString();
        } else {
            $data['option_values'] = '';
        }

        // Remove subform fields from main data to avoid table column issues
        unset($data['optionvalues']);
        unset($data['optioncolorvalues']);

        if (parent::save($data)) {
            // parent::save() stores the ID in state — use it instead of creating a new empty Table
            $optionId = (int) $this->getState($this->getName() . '.id');

            if ($optionId > 0 && isset($data['type'])) {
                if ($data['type'] === 'color') {
                    $this->saveOptionColorValues($optionId, $optionColorValuesData);
                } elseif (in_array($data['type'], ['select', 'radio', 'checkbox'])) {
                    $this->saveOptionValues($optionId, $optionValuesData);
                }
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
        $table = $this->getTable();
        $pks = (array) $pks;
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
                    throw new RuntimeException($table->getError());
                }
            } else {
                throw new RuntimeException($table->getError());
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
     * Generate a unique alias from a name
     *
     * @param   string  $name  The name to generate alias from
     *
     * @return  string  The generated alias
     *
     * @since  6.0.0
     */
    protected function generateUniqueAlias($name)
    {
        // Convert to lowercase and replace spaces/special chars with underscores
        $alias = preg_replace('/[^a-z0-9_]/i', '_', strtolower(trim($name)));
        $alias = preg_replace('/_+/', '_', $alias);
        $alias = trim($alias, '_');

        return $alias;
    }

    /**
     * Validate that the unique alias is actually unique
     *
     * @param   string   $alias  The alias to validate
     * @param   integer  $id     The current record ID (for updates)
     *
     * @return  boolean  True if unique, false otherwise
     *
     * @since  6.0.0
     */
    protected function validateUniqueAlias($alias, $id = 0)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_options'))
            ->where($db->quoteName('option_unique_name') . ' = :alias')
            ->bind(':alias', $alias, ParameterType::STRING);

        if ($id > 0) {
            $query->where($db->quoteName('j2commerce_option_id') . ' != :id')
                ->bind(':id', $id, ParameterType::INTEGER);
        }

        $db->setQuery($query);

        try {
            $count = (int) $db->loadResult();
            return $count === 0;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * Get list of available option types
     *
     * @return  array  Array of option types
     *
     * @since  6.0.0
     */
    public function getOptionTypes()
    {
        // Common option types for ecommerce
        return [
            'text' => Text::_('COM_J2COMMERCE_OPTION_TYPE_TEXT'),
            'textarea' => Text::_('COM_J2COMMERCE_OPTION_TYPE_TEXTAREA'),
            'select' => Text::_('COM_J2COMMERCE_OPTION_TYPE_SELECT'),
            'radio' => Text::_('COM_J2COMMERCE_OPTION_TYPE_RADIO'),
            'checkbox' => Text::_('COM_J2COMMERCE_OPTION_TYPE_CHECKBOX'),
            'date' => Text::_('COM_J2COMMERCE_OPTION_TYPE_DATE'),
            'datetime' => Text::_('COM_J2COMMERCE_OPTION_TYPE_DATETIME'),
            'time' => Text::_('COM_J2COMMERCE_OPTION_TYPE_TIME'),
            'file' => Text::_('COM_J2COMMERCE_OPTION_TYPE_FILE'),
            'image' => Text::_('COM_J2COMMERCE_OPTION_TYPE_IMAGE'),
            'color' => Text::_('COM_J2COMMERCE_OPTION_TYPE_COLOR'),
            'number' => Text::_('COM_J2COMMERCE_OPTION_TYPE_NUMBER'),
            'email' => Text::_('COM_J2COMMERCE_OPTION_TYPE_EMAIL'),
            'url' => Text::_('COM_J2COMMERCE_OPTION_TYPE_URL')
        ];
    }

    /**
     * Get option values for a given option
     *
     * @param   integer  $optionId  The option ID
     *
     * @return  array  Array of option values
     *
     * @since  6.0.0
     */
    protected function getOptionValues($optionId)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('j2commerce_optionvalue_id'),
                $db->quoteName('option_id'),
                $db->quoteName('optionvalue_name'),
                $db->quoteName('optionvalue_image'),
                $db->quoteName('ordering')
            ])
            ->from($db->quoteName('#__j2commerce_optionvalues'))
            ->where($db->quoteName('option_id') . ' = :option_id')
            ->order($db->quoteName('ordering') . ' ASC')
            ->bind(':option_id', $optionId, ParameterType::INTEGER);

        $db->setQuery($query);

        try {
            $values = $db->loadAssocList();

            // Format the data for the subform
            $formattedValues = [];
            foreach ($values as $value) {
                $formattedValues[] = [
                    'j2commerce_optionvalue_id' => $value['j2commerce_optionvalue_id'],
                    'option_id' => $value['option_id'],
                    'optionvalue_name' => $value['optionvalue_name'],
                    'optionvalue_image' => $value['optionvalue_image'],
                    'ordering' => $value['ordering']
                ];
            }

            return $formattedValues;
        } catch (\RuntimeException $e) {
            Factory::getApplication()->enqueueMessage(
                'Error loading option values: ' . $e->getMessage(),
                'error'
            );
            return [];
        }
    }

    /**
     * Get option color values for a given option
     *
     * @param   integer  $optionId  The option ID
     *
     * @return  array  Array of option color values
     *
     * @since  6.0.0
     */
    protected function getOptionColorValues($optionId)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('j2commerce_optionvalue_id'),
                $db->quoteName('option_id'),
                $db->quoteName('optionvalue_name'),
                $db->quoteName('optionvalue_image'),
                $db->quoteName('ordering')
            ])
            ->from($db->quoteName('#__j2commerce_optionvalues'))
            ->where($db->quoteName('option_id') . ' = :option_id')
            ->order($db->quoteName('ordering') . ' ASC')
            ->bind(':option_id', $optionId, ParameterType::INTEGER);

        $db->setQuery($query);

        try {
            $values = $db->loadAssocList();

            // Format the data for the color subform
            $formattedValues = [];
            foreach ($values as $value) {
                $formattedValues[] = [
                    'j2commerce_optionvalue_id' => $value['j2commerce_optionvalue_id'],
                    'option_id' => $value['option_id'],
                    'optionvalue_name' => $value['optionvalue_name'],
                    'optionvalue_color' => $value['optionvalue_image'], // Use optionvalue_image column for color
                    'ordering' => $value['ordering']
                ];
            }

            return $formattedValues;
        } catch (\RuntimeException $e) {
            Factory::getApplication()->enqueueMessage(
                'Error loading option color values: ' . $e->getMessage(),
                'error'
            );
            return [];
        }
    }

    /**
     * Save option values for a given option
     *
     * @param   integer  $optionId      The option ID
     * @param   array    $valuesData    Array of option values data
     *
     * @return  boolean  True on success, false on failure
     *
     * @since  6.0.0
     */
    protected function saveOptionValues($optionId, $valuesData)
    {
        if (!$optionId || !is_array($valuesData)) {
            return true;
        }

        $db = $this->getDatabase();

        try {
            $db->transactionStart();

            // Collect IDs that should survive (existing rows being updated)
            $keepIds  = [];
            $ordering = 1;

            foreach ($valuesData as $valueData) {
                if (empty($valueData['optionvalue_name'])) {
                    continue;
                }

                $existingId = (int) ($valueData['j2commerce_optionvalue_id'] ?? 0);
                $valueName  = $valueData['optionvalue_name'];
                $valueImage = $valueData['optionvalue_image'] ?? '';

                if ($existingId > 0) {
                    // Update existing row — preserves the ID
                    $query = $db->getQuery(true)
                        ->update($db->quoteName('#__j2commerce_optionvalues'))
                        ->set($db->quoteName('optionvalue_name') . ' = :name')
                        ->set($db->quoteName('optionvalue_image') . ' = :image')
                        ->set($db->quoteName('ordering') . ' = :ordering')
                        ->where($db->quoteName('j2commerce_optionvalue_id') . ' = :id')
                        ->bind(':name', $valueName, ParameterType::STRING)
                        ->bind(':image', $valueImage, ParameterType::STRING)
                        ->bind(':ordering', $ordering, ParameterType::INTEGER)
                        ->bind(':id', $existingId, ParameterType::INTEGER);

                    $db->setQuery($query)->execute();
                    $keepIds[] = $existingId;
                } else {
                    // Insert new row
                    $query = $db->getQuery(true)
                        ->insert($db->quoteName('#__j2commerce_optionvalues'))
                        ->columns([
                            $db->quoteName('option_id'),
                            $db->quoteName('optionvalue_name'),
                            $db->quoteName('optionvalue_image'),
                            $db->quoteName('ordering'),
                        ])
                        ->values(':option_id, :name, :image, :ordering')
                        ->bind(':option_id', $optionId, ParameterType::INTEGER)
                        ->bind(':name', $valueName, ParameterType::STRING)
                        ->bind(':image', $valueImage, ParameterType::STRING)
                        ->bind(':ordering', $ordering, ParameterType::INTEGER);

                    $db->setQuery($query)->execute();
                    $keepIds[] = (int) $db->insertid();
                }

                $ordering++;
            }

            // Delete only the rows that were removed from the subform
            $deleteQuery = $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_optionvalues'))
                ->where($db->quoteName('option_id') . ' = :option_id')
                ->bind(':option_id', $optionId, ParameterType::INTEGER);

            if (!empty($keepIds)) {
                $deleteQuery->whereNotIn($db->quoteName('j2commerce_optionvalue_id'), $keepIds);
            }

            $db->setQuery($deleteQuery)->execute();

            $db->transactionCommit();

            return true;
        } catch (\Exception $e) {
            $db->transactionRollback();

            Factory::getApplication()->enqueueMessage(
                'Error saving option values: ' . $e->getMessage(),
                'error'
            );

            return false;
        }
    }

    /**
     * Save option color values for a given option
     *
     * @param   integer  $optionId      The option ID
     * @param   array    $valuesData    Array of option color values data
     *
     * @return  boolean  True on success, false on failure
     *
     * @since  6.0.0
     */
    protected function saveOptionColorValues($optionId, $valuesData)
    {
        if (!$optionId || !is_array($valuesData)) {
            return true;
        }

        $db = $this->getDatabase();

        try {
            $db->transactionStart();

            $keepIds  = [];
            $ordering = 1;

            foreach ($valuesData as $valueData) {
                if (empty($valueData['optionvalue_name'])) {
                    continue;
                }

                $existingId = (int) ($valueData['j2commerce_optionvalue_id'] ?? 0);
                $valueName  = $valueData['optionvalue_name'];
                $colorValue = $valueData['optionvalue_color'] ?? '#000000';

                if ($existingId > 0) {
                    $query = $db->getQuery(true)
                        ->update($db->quoteName('#__j2commerce_optionvalues'))
                        ->set($db->quoteName('optionvalue_name') . ' = :name')
                        ->set($db->quoteName('optionvalue_image') . ' = :color')
                        ->set($db->quoteName('ordering') . ' = :ordering')
                        ->where($db->quoteName('j2commerce_optionvalue_id') . ' = :id')
                        ->bind(':name', $valueName, ParameterType::STRING)
                        ->bind(':color', $colorValue, ParameterType::STRING)
                        ->bind(':ordering', $ordering, ParameterType::INTEGER)
                        ->bind(':id', $existingId, ParameterType::INTEGER);

                    $db->setQuery($query)->execute();
                    $keepIds[] = $existingId;
                } else {
                    $query = $db->getQuery(true)
                        ->insert($db->quoteName('#__j2commerce_optionvalues'))
                        ->columns([
                            $db->quoteName('option_id'),
                            $db->quoteName('optionvalue_name'),
                            $db->quoteName('optionvalue_image'),
                            $db->quoteName('ordering'),
                        ])
                        ->values(':option_id, :name, :color, :ordering')
                        ->bind(':option_id', $optionId, ParameterType::INTEGER)
                        ->bind(':name', $valueName, ParameterType::STRING)
                        ->bind(':color', $colorValue, ParameterType::STRING)
                        ->bind(':ordering', $ordering, ParameterType::INTEGER);

                    $db->setQuery($query)->execute();
                    $keepIds[] = (int) $db->insertid();
                }

                $ordering++;
            }

            // Delete only rows removed from the subform
            $deleteQuery = $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_optionvalues'))
                ->where($db->quoteName('option_id') . ' = :option_id')
                ->bind(':option_id', $optionId, ParameterType::INTEGER);

            if (!empty($keepIds)) {
                $deleteQuery->whereNotIn($db->quoteName('j2commerce_optionvalue_id'), $keepIds);
            }

            $db->setQuery($deleteQuery)->execute();

            $db->transactionCommit();

            return true;
        } catch (\Exception $e) {
            $db->transactionRollback();

            Factory::getApplication()->enqueueMessage(
                'Error saving option color values: ' . $e->getMessage(),
                'error'
            );

            return false;
        }
    }
}
