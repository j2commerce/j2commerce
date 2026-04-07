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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;

/**
 * Geozone item model class.
 *
 * Handles geozone records with child geozonerules (subform).
 *
 * @since  6.0.3
 */
class GeozoneModel extends AdminModel
{
    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  6.0.3
     */
    public $typeAlias = 'com_j2commerce.geozone';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.3
     */
    protected $text_prefix = 'COM_J2COMMERCE_GEOZONE';

    /**
     * Method to auto-populate the model state.
     *
     * CRITICAL: This override is required because the parent AdminModel::populateState()
     * looks for a URL parameter named after the table's primary key (j2commerce_geozone_id),
     * but Joomla's standard convention uses 'id' as the URL parameter.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    protected function populateState(): void
    {
        $app = Factory::getApplication();

        // Get the primary key from the URL parameter 'id' (standard Joomla convention)
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
     * @since   6.0.3
     */
    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_j2commerce.geozone', 'geozone', ['control' => 'jform', 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
        }

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
     * @since   6.0.3
     */
    public function getTable($name = 'Geozone', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get a single record.
     *
     * Loads the geozone record and its child geozonerules.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  mixed  Object on success, false on failure.
     *
     * @since   6.0.3
     */
    public function getItem($pk = null): mixed
    {
        $item = parent::getItem($pk);

        if ($item && $item->j2commerce_geozone_id) {
            // Load geozonerules for this geozone
            $db    = $this->getDatabase();
            $query = $db->getQuery(true);

            $query->select($db->quoteName([
                    'j2commerce_geozonerule_id',
                    'geozone_id',
                    'country_id',
                    'zone_id',
                ]))
                ->from($db->quoteName('#__j2commerce_geozonerules'))
                ->where($db->quoteName('geozone_id') . ' = :geozone_id')
                ->bind(':geozone_id', $item->j2commerce_geozone_id, ParameterType::INTEGER)
                ->order($db->quoteName('j2commerce_geozonerule_id') . ' ASC');

            $db->setQuery($query);
            $rules = $db->loadObjectList();

            // Convert to subform format
            $item->geozonerules = [];
            if ($rules) {
                foreach ($rules as $rule) {
                    $item->geozonerules[] = [
                        'j2commerce_geozonerule_id' => $rule->j2commerce_geozonerule_id,
                        'country_id'                => $rule->country_id,
                        'zone_id'                   => $rule->zone_id,
                    ];
                }
            }
        }

        return $item;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   6.0.3
     */
    protected function loadFormData(): mixed
    {
        $app  = Factory::getApplication();
        $data = $app->getUserState('com_j2commerce.edit.geozone.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        return $data;
    }

    /**
     * Method to save the form data.
     *
     * Saves the geozone record and its child geozonerules.
     *
     * @param   array  $data  The form data.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.3
     */
    public function save($data): bool
    {
        // Extract geozonerules data before saving parent
        $geozonerules = $data['geozonerules'] ?? [];
        unset($data['geozonerules']);

        // Save the parent geozone record
        if (!parent::save($data)) {
            return false;
        }

        // Get the geozone ID (either from existing record or newly created)
        $geozoneId = (int) $this->getState($this->getName() . '.id');

        // Save geozonerules
        return $this->saveGeozoneRules($geozoneId, $geozonerules);
    }

    /**
     * Save geozone rules for a geozone.
     *
     * Deletes all existing rules and creates new ones from subform data.
     *
     * @param   int    $geozoneId      The geozone ID.
     * @param   array  $geozonerules   Array of geozonerule data.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.3
     */
    protected function saveGeozoneRules(int $geozoneId, array $geozonerules): bool
    {
        $db = $this->getDatabase();

        // Delete existing rules for this geozone
        $query = $db->getQuery(true);
        $query->delete($db->quoteName('#__j2commerce_geozonerules'))
            ->where($db->quoteName('geozone_id') . ' = :geozone_id')
            ->bind(':geozone_id', $geozoneId, ParameterType::INTEGER);

        $db->setQuery($query);
        $db->execute();

        // Insert new rules
        if (!empty($geozonerules)) {
            foreach ($geozonerules as $rule) {
                // Skip empty rows
                if (empty($rule['country_id'])) {
                    continue;
                }

                // Cast values to int for strict type binding
                $countryId = (int) $rule['country_id'];
                $zoneId    = (int) ($rule['zone_id'] ?? 0);

                $query = $db->getQuery(true);
                $query->insert($db->quoteName('#__j2commerce_geozonerules'))
                    ->columns($db->quoteName(['geozone_id', 'country_id', 'zone_id']))
                    ->values(':geozone_id, :country_id, :zone_id')
                    ->bind(':geozone_id', $geozoneId, ParameterType::INTEGER)
                    ->bind(':country_id', $countryId, ParameterType::INTEGER)
                    ->bind(':zone_id', $zoneId, ParameterType::INTEGER);

                $db->setQuery($query);
                $db->execute();
            }
        }

        return true;
    }

    /**
     * Method to delete one or more records.
     *
     * Deletes the geozone and its child geozonerules.
     *
     * @param   array  $pks  An array of record primary keys.
     *
     * @return  boolean  True if successful, false if an error occurs.
     *
     * @since   6.0.3
     */
    public function delete(&$pks): bool
    {
        $db = $this->getDatabase();

        // Delete child geozonerules first
        foreach ($pks as $pk) {
            $query = $db->getQuery(true);
            $query->delete($db->quoteName('#__j2commerce_geozonerules'))
                ->where($db->quoteName('geozone_id') . ' = :geozone_id')
                ->bind(':geozone_id', $pk, ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();
        }

        // Delete parent geozones
        return parent::delete($pks);
    }

    /**
     * Prepare and sanitize the table before saving.
     *
     * @param   Table  $table  The Table object.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    protected function prepareTable($table): void
    {
        // Trim geozone name
        if (!empty($table->geozone_name)) {
            $table->geozone_name = trim($table->geozone_name);
        }
    }
}
