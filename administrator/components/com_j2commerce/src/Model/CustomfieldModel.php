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

use J2Commerce\Component\J2commerce\Administrator\Helper\CustomFieldHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;

/**
 * Customfield item model class.
 *
 * @since  6.0.4
 */
class CustomfieldModel extends AdminModel
{
    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  6.0.4
     */
    public $typeAlias = 'com_j2commerce.customfield';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.4
     */
    protected $text_prefix = 'COM_J2COMMERCE';

    /**
     * Method to auto-populate the model state.
     *
     * CRITICAL: This override is required because the parent AdminModel::populateState()
     * looks for a URL parameter named after the table's primary key (j2commerce_customfield_id),
     * but Joomla's standard convention uses 'id' as the URL parameter.
     *
     * Without this override:
     * - URL has: ?id=1
     * - Parent looks for: ?j2commerce_customfield_id=1 (not found!)
     * - State gets: customfield.id = 0
     * - getItem() loads: nothing
     *
     * @return  void
     *
     * @since   6.0.4
     */
    protected function populateState(): void
    {
        $app = Factory::getApplication();

        // Get the primary key from the URL parameter 'id' (standard Joomla convention)
        // NOT from 'j2commerce_customfield_id' (the table's column name)
        $pk = $app->getInput()->getInt('id', 0);
        $this->setState($this->getName() . '.id', $pk);

        // Load the component parameters
        $params = ComponentHelper::getParams('com_j2commerce');
        $this->setState('params', $params);
    }

    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  Form|false  A Form object on success, false on failure
     *
     * @since   6.0.4
     */
    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_j2commerce.customfield', 'customfield', ['control' => 'jform', 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
        }

        // Make field_namekey readonly when editing an existing record (not for save2copy which needs a new unique key)
        $id   = (int) $this->getState('customfield.id');
        $task = Factory::getApplication()->getInput()->get('task', '', 'cmd');

        if ($id > 0 && $task !== 'customfield.save2copy') {
            $form->setFieldAttribute('field_namekey', 'readonly', 'true');
            $form->setFieldAttribute('field_namekey', 'hint', 'COM_J2COMMERCE_FIELD_NAMEKEY_READONLY_HINT');
        }

        // Inject plugin display area switchers into the 'display' fieldset
        $pluginAreas = CustomFieldHelper::getRegisteredAreas();

        if (!empty($pluginAreas)) {
            $xml = '<form><fieldset name="display">';

            foreach ($pluginAreas as $area) {
                $key   = htmlspecialchars($area['key'], ENT_QUOTES, 'UTF-8');
                $label = htmlspecialchars($area['label'], ENT_QUOTES, 'UTF-8');
                $desc  = htmlspecialchars($area['description'] ?? '', ENT_QUOTES, 'UTF-8');
                $xml .= '<field name="plugin_area_' . $key . '" type="radio"'
                       . ' label="' . $label . '"'
                       . ' description="' . $desc . '"'
                       . ' layout="joomla.form.field.radio.switcher"'
                       . ' filter="integer" default="0">'
                       . '<option value="0">JNO</option>'
                       . '<option value="1">JYES</option>'
                       . '</field>';
            }

            $xml .= '</fieldset></form>';
            $form->load(new \SimpleXMLElement($xml));

            // Re-bind data so plugin area values populate the newly injected fields.
            // The initial loadForm() binding happened before these fields existed.
            if ($loadData) {
                $form->bind($this->loadFormData());
            }
        }

        // Let plugins inject additional fieldsets/tabs
        $formData = $loadData ? $this->loadFormData() : $data;
        J2CommerceHelper::plugin()->event('CustomFieldFormPrepare', [
            'form' => $form,
            'data' => $formData,
        ]);

        return $form;
    }

    /**
     * Method to get a table object.
     *
     * @param   string  $name     The table name. Optional.
     * @param   string  $prefix   The class prefix. Optional.
     * @param   array   $options  Configuration array for model. Optional.
     *
     * @return  Table  A Table object
     *
     * @since   6.0.4
     */
    public function getTable($name = 'Customfield', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   6.0.4
     */
    protected function loadFormData(): mixed
    {
        $app  = Factory::getApplication();
        $data = $app->getUserState('com_j2commerce.edit.customfield.data', []);

        if (empty($data)) {
            $data = $this->getItem();

            // Extract settings from field_options JSON
            if (isset($data->field_options) && $data->field_options !== '' && $data->field_options !== null) {
                $options = json_decode($data->field_options, true);
                if (\is_array($options)) {
                    if (isset($options['zone_type'])) {
                        $data->field_zonetype = $options['zone_type'];
                    }
                    // Phone country settings — support both new (phone_country_mode) and legacy (phone_all_countries) formats
                    if (isset($options['phone_country_mode'])) {
                        $data->phone_country_mode = $options['phone_country_mode'];
                    } elseif (isset($options['phone_all_countries'])) {
                        // Backward compat: map old boolean flag to new 3-option mode
                        $data->phone_country_mode = ((int) $options['phone_all_countries'] === 1) ? 'all' : 'selected';
                    } else {
                        $data->phone_country_mode = 'all';
                    }
                    if (isset($options['phone_countries'])) {
                        // Stored as JSON array; form field expects the raw array
                        $data->phone_countries = $options['phone_countries'];
                    }
                    // Multiuploader settings
                    if (isset($options['upload_max_files'])) {
                        $data->upload_max_files = $options['upload_max_files'];
                    }
                    if (isset($options['upload_max_file_size'])) {
                        $data->upload_max_file_size = $options['upload_max_file_size'];
                    }
                    if (isset($options['upload_allowed_types'])) {
                        $data->upload_allowed_types = $options['upload_allowed_types'];
                    }
                    if (isset($options['upload_directory'])) {
                        $data->upload_directory = $options['upload_directory'];
                    }
                }
            }

            // Map field_default to the appropriate zone dropdown if field_type is zone
            if (isset($data->field_type) && $data->field_type === 'zone' && isset($data->field_default)) {
                $zoneType = $data->field_zonetype ?? 'country';
                if ($zoneType === 'zone') {
                    $data->field_default_zone = $data->field_default;
                } else {
                    $data->field_default_country = $data->field_default;
                }
            }

            // Decode field_value JSON for subform (dropdown/radio/checkbox options)
            if (isset($data->field_value) && $data->field_value !== '' && $data->field_value !== null) {
                $decoded = json_decode($data->field_value, true);
                if (\is_array($decoded)) {
                    $data->field_value = $decoded;
                }
            }

            // Load plugin area toggles from field_display JSON
            if (!empty($data->field_display) && $data->field_display !== '') {
                $displayData = json_decode($data->field_display, true);
                if (\is_array($displayData)) {
                    foreach ($displayData as $areaKey => $areaConfig) {
                        $data->{'plugin_area_' . $areaKey} = (int) ($areaConfig['enabled'] ?? 0);
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Prepare and sanitize the table before saving.
     *
     * @param   Table  $table  The Table object.
     *
     * @return  void
     *
     * @since   6.0.4
     */
    protected function prepareTable($table): void
    {
        if (isset($table->field_namekey) && $table->field_namekey !== '') {
            $table->field_namekey = strtolower(trim($table->field_namekey));
        }

        if (isset($table->field_name) && $table->field_name !== '') {
            $table->field_name = trim($table->field_name);
        }
    }

    public function save($data): bool
    {
        // Auto-generate field_namekey from field_name when empty (like Joomla alias)
        if (empty($data['field_namekey']) && !empty($data['field_name'])) {
            $data['field_namekey'] = preg_replace('/[^a-z0-9_]/', '', str_replace([' ', '-'], '_', strtolower(trim($data['field_name']))));
        }

        // Normalize field_namekey early (before addAddressColumn which uses it for DDL)
        if (!empty($data['field_namekey'])) {
            $data['field_namekey'] = strtolower(trim($data['field_namekey']));
        }

        // Ensure field_table is 'address' when empty (both new and existing records)
        if (empty($data['field_table'])) {
            $data['field_table'] = 'address';
        }

        // Protect field_namekey from modification on existing records (server-side enforcement)
        if (!empty($data['j2commerce_customfield_id'])) {
            $existing = $this->getItem((int) $data['j2commerce_customfield_id']);

            if ($existing && !empty($existing->field_namekey)) {
                $data['field_namekey'] = $existing->field_namekey;
            }
        }

        // For save2copy: generate a unique field_namekey to avoid UNIQUE constraint violation
        $task = Factory::getApplication()->getInput()->get('task', '', 'cmd');

        if ($task === 'customfield.save2copy' && !empty($data['field_namekey'])) {
            $data['j2commerce_customfield_id'] = 0;
            $data['field_namekey']             = $this->generateUniqueNamekey($data['field_namekey']);
        }

        // Consolidate zone dropdown values into field_default
        if ($data['field_type'] === 'zone') {
            $zoneType = $data['field_zonetype'] ?? 'country';
            if ($zoneType === 'zone' && !empty($data['field_default_zone'])) {
                $data['field_default'] = $data['field_default_zone'];
            } elseif (!empty($data['field_default_country'])) {
                $data['field_default'] = $data['field_default_country'];
            }
        }

        // Remove virtual dropdown fields before save
        unset($data['field_default_country'], $data['field_default_zone']);

        // Merge settings into field_options JSON
        $fieldOptionsData = [];
        if (!empty($data['field_options'])) {
            $decoded = json_decode($data['field_options'], true);
            if (\is_array($decoded)) {
                $fieldOptionsData = $decoded;
            }
        }

        if ($data['field_type'] === 'zone' && !empty($data['field_zonetype'])) {
            $fieldOptionsData['zone_type'] = $data['field_zonetype'];
        }

        if ($data['field_type'] === 'telephone') {
            $mode = $data['phone_country_mode'] ?? 'all';
            // Ensure only valid mode values are stored
            if (!\in_array($mode, ['none', 'all', 'selected'], true)) {
                $mode = 'all';
            }
            $fieldOptionsData['phone_country_mode'] = $mode;
            // Remove legacy key on save to keep options clean
            unset($fieldOptionsData['phone_all_countries']);

            if ($mode === 'selected' && isset($data['phone_countries'])) {
                // phone_countries arrives as array from the checkboxes field
                $raw = $data['phone_countries'];
                if (\is_array($raw) && isset($raw[0]) && \is_array($raw[0])) {
                    $raw = $raw[0];
                }
                $phoneCountries                      = \is_array($raw) ? array_values($raw) : [];
                $fieldOptionsData['phone_countries'] = $phoneCountries;
            } else {
                unset($fieldOptionsData['phone_countries']);
            }
        }

        if ($data['field_type'] === 'multiuploader') {
            $fieldOptionsData['upload_max_files']     = (int) ($data['upload_max_files'] ?? 5);
            $fieldOptionsData['upload_max_file_size'] = (float) ($data['upload_max_file_size'] ?? 10);
            $fieldOptionsData['upload_allowed_types'] = trim($data['upload_allowed_types'] ?? '');
            $fieldOptionsData['upload_directory']     = trim($data['upload_directory'] ?? 'images/checkout-uploads');
        }

        if (!empty($fieldOptionsData)) {
            $data['field_options'] = json_encode($fieldOptionsData, JSON_UNESCAPED_UNICODE);
        }

        // Remove virtual fields before save
        unset($data['field_zonetype'], $data['phone_all_countries'], $data['phone_country_mode'], $data['phone_countries'],
            $data['upload_max_files'], $data['upload_max_file_size'], $data['upload_allowed_types'], $data['upload_directory']);

        // Encode field_value subform data to JSON for dropdown/radio/checkbox options
        if (\in_array($data['field_type'], ['singledropdown', 'radio', 'checkbox'], true)) {
            if (isset($data['field_value']) && \is_array($data['field_value'])) {
                // Filter out empty rows and encode to JSON
                $filtered = array_filter($data['field_value'], function ($row) {
                    return !empty($row['name']);
                });
                $data['field_value'] = json_encode(array_values($filtered), JSON_UNESCAPED_UNICODE);
            } else {
                $data['field_value'] = '';
            }
        } else {
            // For non-option field types, ensure field_value is empty string
            $data['field_value'] = '';
        }

        // For NEW custom fields with field_table='address' and field_type != 'customtext',
        // add the field_namekey as a column to the addresses table
        $isNew = empty($data['j2commerce_customfield_id']);
        if ($isNew &&
            isset($data['field_table']) && $data['field_table'] === 'address' &&
            isset($data['field_type']) && $data['field_type'] !== 'customtext' && $data['field_type'] !== 'multiuploader' &&
            !empty($data['field_namekey'])) {

            $this->addAddressColumn($data['field_namekey']);
        }

        // Sync plugin area toggles into field_display JSON before saving
        $pluginAreas = CustomFieldHelper::getRegisteredAreas();

        if (!empty($pluginAreas)) {
            $existingDisplay = [];

            if (!empty($data['field_display'])) {
                $decoded = json_decode($data['field_display'], true);
                if (\is_array($decoded)) {
                    $existingDisplay = $decoded;
                }
            }

            foreach ($pluginAreas as $area) {
                $areaKey = $area['key'];
                $formKey = 'plugin_area_' . $areaKey;
                $enabled = (int) ($data[$formKey] ?? 0);

                if (!isset($existingDisplay[$areaKey])) {
                    $existingDisplay[$areaKey] = ['enabled' => 0, 'ordering' => 0];
                }

                $existingDisplay[$areaKey]['enabled'] = $enabled;

                // Remove virtual form field before passing to parent::save()
                unset($data[$formKey]);
            }

            $data['field_display'] = json_encode($existingDisplay, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        return parent::save($data);
    }

    /**
     * Add a custom field column to the addresses table.
     *
     * @param   string  $fieldNamekey  The field namekey to add as a column.
     *
     * @return  void
     *
     * @throws  \RuntimeException  If the column name is invalid or already exists.
     *
     * @since   6.0.7
     */
    protected function addAddressColumn(string $fieldNamekey): void
    {
        // Validate field_namekey is not a MySQL reserved word
        $reservedWords = [
            'accessible', 'add', 'all', 'alter', 'analyze', 'and', 'as', 'asc',
            'asensitive', 'before', 'between', 'bigint', 'binary', 'blob', 'both',
            'by', 'call', 'cascade', 'case', 'change', 'char', 'character', 'check',
            'collate', 'column', 'condition', 'constraint', 'continue', 'convert',
            'create', 'cross', 'cube', 'current_date', 'current_time', 'current_timestamp',
            'current_user', 'cursor', 'database', 'databases', 'day_hour', 'day_microsecond',
            'day_minute', 'day_second', 'dec', 'decimal', 'declare', 'default', 'delayed',
            'delete', 'desc', 'describe', 'deterministic', 'distinct', 'distinctrow',
            'div', 'double', 'drop', 'dual', 'each', 'else', 'elseif', 'enclosed',
            'escaped', 'exists', 'exit', 'explain', 'false', 'fetch', 'float', 'float4',
            'float8', 'for', 'force', 'foreign', 'from', 'fulltext', 'get', 'grant',
            'group', 'grouping', 'having', 'high_priority', 'hour_microsecond',
            'hour_minute', 'hour_second', 'if', 'ignore', 'in', 'index', 'infile',
            'inner', 'inout', 'insensitive', 'insert', 'int', 'int1', 'int2', 'int3',
            'int4', 'int8', 'integer', 'interval', 'into', 'io_after_gtids',
            'io_before_gtids', 'is', 'iterate', 'join', 'key', 'keys', 'kill',
            'leading', 'leave', 'left', 'like', 'limit', 'linear', 'lines', 'load',
            'localtime', 'localtimestamp', 'lock', 'long', 'longblob', 'longtext',
            'loop', 'low_priority', 'master_bind', 'master_ssl_verify_server_cert',
            'match', 'maxvalue', 'mediumblob', 'mediumint', 'mediumtext', 'middleint',
            'minute_microsecond', 'minute_second', 'mod', 'modifies', 'natural', 'not',
            'no_write_to_binlog', 'null', 'numeric', 'on', 'optimize', 'option',
            'optionally', 'or', 'order', 'out', 'outer', 'outfile', 'partition',
            'precision', 'primary', 'procedure', 'purge', 'range', 'read', 'reads',
            'read_write', 'real', 'references', 'regexp', 'release', 'rename', 'repeat',
            'replace', 'require', 'resignal', 'restrict', 'return', 'revoke', 'right',
            'rlike', 'schema', 'schemas', 'second_microsecond', 'select', 'sensitive',
            'separator', 'set', 'show', 'signal', 'smallint', 'spatial', 'specific',
            'sql', 'sqlexception', 'sqlstate', 'sqlwarning', 'sql_big_result',
            'sql_calc_found_rows', 'sql_small_result', 'ssl', 'starting', 'stored',
            'straight_join', 'table', 'terminated', 'then', 'tinyblob', 'tinyint',
            'tinytext', 'to', 'trailing', 'trigger', 'true', 'undo', 'union', 'unique',
            'unlock', 'unsigned', 'update', 'usage', 'use', 'using', 'utc_date',
            'utc_time', 'utc_timestamp', 'values', 'varbinary', 'varchar', 'varcharacter',
            'varying', 'virtual', 'when', 'where', 'while', 'window', 'with', 'write',
            'xor', 'year_month', 'zerofill',
        ];

        if (\in_array(strtolower($fieldNamekey), $reservedWords, true)) {
            throw new \RuntimeException(
                Text::_('COM_J2COMMERCE_ERR_CUSTOMFIELD_RESERVED_WORD')
            );
        }

        $db = $this->getDatabase();

        // Check if column already exists
        $columns = $db->getTableColumns('#__j2commerce_addresses');
        if (isset($columns[$fieldNamekey])) {
            throw new \RuntimeException(
                Text::_('COM_J2COMMERCE_ERR_CUSTOMFIELD_COLUMN_EXISTS')
            );
        }

        // Add the column to the addresses table
        $query = 'ALTER TABLE ' . $db->quoteName('#__j2commerce_addresses') .
                 ' ADD ' . $db->quoteName($fieldNamekey) . ' TEXT NULL';

        try {
            $db->setQuery($query);
            $db->execute();
        } catch (\Exception $e) {
            throw new \RuntimeException(
                Text::_('COM_J2COMMERCE_ERR_CUSTOMFIELD_ALTER_TABLE_FAILED') .
                ': ' . $e->getMessage()
            );
        }
    }

    protected function generateUniqueNamekey(string $baseKey): string
    {
        $db        = $this->getDatabase();
        $candidate = $baseKey . '_copy';
        $i         = 1;

        while (true) {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__j2commerce_customfields'))
                ->where($db->quoteName('field_namekey') . ' = :namekey')
                ->bind(':namekey', $candidate);
            $db->setQuery($query);

            if ((int) $db->loadResult() === 0) {
                return $candidate;
            }

            $i++;
            $candidate = $baseKey . '_copy' . $i;
        }
    }
}
