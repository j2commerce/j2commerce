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

/**
 * Tax Profile item model class.
 *
 * @since  6.0.7
 */
class TaxprofileModel extends AdminModel
{
    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  6.0.7
     */
    public $typeAlias = 'com_j2commerce.taxprofile';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.7
     */
    protected $text_prefix = 'COM_J2COMMERCE_TAXPROFILE';

    /**
     * Method to auto-populate the model state.
     *
     * CRITICAL: This override is required because the parent AdminModel::populateState()
     * looks for a URL parameter named after the table's primary key (j2commerce_taxprofile_id),
     * but Joomla's standard convention uses 'id' as the URL parameter.
     *
     * Without this override:
     * - URL has: ?id=1
     * - Parent looks for: ?j2commerce_taxprofile_id=1 (not found!)
     * - State gets: taxprofile.id = 0
     * - getItem() loads: nothing
     *
     * @return  void
     *
     * @since   6.0.7
     */
    protected function populateState(): void
    {
        $app = Factory::getApplication();

        // Get the primary key from the URL parameter 'id' (standard Joomla convention)
        // NOT from 'j2commerce_taxprofile_id' (the table's column name)
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
     * @since   6.0.7
     */
    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_j2commerce.taxprofile', 'taxprofile', ['control' => 'jform', 'load_data' => $loadData]);

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
     * @since   6.0.7
     */
    public function getTable($name = 'Taxprofile', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   6.0.7
     */
    protected function loadFormData(): mixed
    {
        $app  = Factory::getApplication();
        $data = $app->getUserState('com_j2commerce.edit.taxprofile.data', []);

        if (empty($data)) {
            $data = $this->getItem();
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
     * @since   6.0.7
     */
    protected function prepareTable($table): void
    {
        // Trim tax profile name
        if (!empty($table->taxprofile_name)) {
            $table->taxprofile_name = trim($table->taxprofile_name);
        }
    }

    /**
     * Method to save the form data.
     *
     * Overridden to handle saving taxrules associated with the taxprofile.
     *
     * @param   array  $data  The form data.
     *
     * @return  boolean  True on success, false on failure.
     *
     * @since   6.0.7
     */
    public function save($data): bool
    {
        $app = Factory::getApplication();
        $db  = $this->getDatabase();

        // Extract taxrules before calling parent save
        $taxrules = $data['taxrules'] ?? [];
        unset($data['taxrules']);

        // Save the tax profile using parent method
        if (!parent::save($data)) {
            return false;
        }

        // Get the tax profile ID (either new or existing)
        $taxprofileId = (int) $this->getState($this->getName() . '.id');

        if ($taxprofileId > 0) {
            // Delete existing tax rules for this profile
            $query = $db->getQuery(true);
            $query->delete($db->quoteName('#__j2commerce_taxrules'))
                ->where($db->quoteName('taxprofile_id') . ' = :taxprofile_id')
                ->bind(':taxprofile_id', $taxprofileId, \Joomla\Database\ParameterType::INTEGER);
            $db->setQuery($query);
            $db->execute();

            // Insert new tax rules
            if (!empty($taxrules)) {
                $ordering = 1;
                foreach ($taxrules as $rule) {
                    // Skip empty rules
                    if (empty($rule['taxrate_id'])) {
                        continue;
                    }

                    $ruleData                = new \stdClass();
                    $ruleData->taxprofile_id = $taxprofileId;
                    $ruleData->taxrate_id    = (int) $rule['taxrate_id'];
                    $ruleData->address       = $rule['address'] ?? 'shipping';
                    $ruleData->ordering      = $ordering++;

                    try {
                        $db->insertObject('#__j2commerce_taxrules', $ruleData);
                    } catch (\Exception $e) {
                        $app->enqueueMessage($e->getMessage(), 'error');
                        return false;
                    }
                }
            }
        }

        return true;
    }
}
