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
 * Product Option model class for single item operations.
 *
 * Handles CRUD operations for a single product option record.
 *
 * @since  6.0.0
 */
class ProductoptionModel extends AdminModel
{
    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  6.0.0
     */
    public $typeAlias = 'com_j2commerce.productoption';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $text_prefix = 'COM_J2COMMERCE_PRODUCTOPTION';

    /**
     * Method to auto-populate the model state.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function populateState(): void
    {
        $app = Factory::getApplication();

        // Get the primary key from the request data
        $pk = $app->getInput()->getInt('id', 0);
        $this->setState($this->getName() . '.id', $pk);

        // Load the parameters
        $params = ComponentHelper::getParams('com_j2commerce');
        $this->setState('params', $params);
    }

    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  Form|boolean  A Form object on success, false on failure.
     *
     * @since   6.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm(
            'com_j2commerce.productoption',
            'productoption',
            ['control' => 'jform', 'load_data' => $loadData]
        );

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   6.0.0
     */
    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState(
            'com_j2commerce.edit.productoption.data',
            []
        );

        if (empty($data)) {
            $data = $this->getItem();
        }

        return $data;
    }

    /**
     * Method to get a table object, load it if necessary.
     *
     * @param   string  $name     The table name. Optional.
     * @param   string  $prefix   The class prefix. Optional.
     * @param   array   $options  Configuration array for model. Optional.
     *
     * @return  Table|null  A Table object.
     *
     * @since   6.0.0
     */
    public function getTable($name = 'Productoption', $prefix = 'Administrator', $options = []): ?Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get a single record.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  \stdClass|false  Object on success, false on failure.
     *
     * @since   6.0.0
     */
    public function getItem($pk = null)
    {
        $pk = $pk ?? (int) $this->getState($this->getName() . '.id');

        if (!$pk) {
            // Return empty item for new records
            $item                              = new \stdClass();
            $item->j2commerce_productoption_id = 0;
            $item->option_id                   = 0;
            $item->parent_id                   = 0;
            $item->product_id                  = 0;
            $item->ordering                    = 0;
            $item->required                    = 0;
            $item->is_variant                  = 0;
            return $item;
        }

        $item = parent::getItem($pk);

        if ($item) {
            // Load associated option details
            $item = $this->loadOptionDetails($item);
        }

        return $item;
    }

    /**
     * Load option details for a product option record.
     *
     * @param   \stdClass  $item  The product option item.
     *
     * @return  \stdClass  The item with option details.
     *
     * @since   6.0.0
     */
    protected function loadOptionDetails(\stdClass $item): \stdClass
    {
        if (empty($item->option_id)) {
            return $item;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName([
                'option_unique_name',
                'option_name',
                'type',
                'option_params',
            ]))
            ->from($db->quoteName('#__j2commerce_options'))
            ->where($db->quoteName('j2commerce_option_id') . ' = :optionId')
            ->bind(':optionId', $item->option_id, ParameterType::INTEGER);

        $db->setQuery($query);

        try {
            $optionDetails = $db->loadObject();
            if ($optionDetails) {
                $item->option_unique_name = $optionDetails->option_unique_name;
                $item->option_name        = $optionDetails->option_name;
                $item->type               = $optionDetails->type;
                $item->option_params      = $optionDetails->option_params;
            }
        } catch (\Exception $e) {
            // Silently fail - option details are supplementary
        }

        return $item;
    }

    /**
     * Method to save the form data.
     *
     * @param   array  $data  The form data.
     *
     * @return  boolean  True on success, False on error.
     *
     * @since   6.0.0
     */
    public function save($data): bool
    {
        // Ensure ID field mapping
        if (isset($data['id']) && !isset($data['j2commerce_productoption_id'])) {
            $data['j2commerce_productoption_id'] = $data['id'];
        }

        return parent::save($data);
    }

    /**
     * Method to delete one or more records.
     *
     * @param   array  &$pks  An array of record primary keys.
     *
     * @return  boolean  True if successful, false if an error occurs.
     *
     * @since   6.0.0
     */
    public function delete(&$pks): bool
    {
        $db = $this->getDatabase();

        try {
            $db->transactionStart();

            // Delete associated option values first
            foreach ($pks as $pk) {
                $pk = (int) $pk;

                $query = $db->getQuery(true)
                    ->delete($db->quoteName('#__j2commerce_product_optionvalues'))
                    ->where($db->quoteName('productoption_id') . ' = :pk')
                    ->bind(':pk', $pk, ParameterType::INTEGER);

                $db->setQuery($query);
                $db->execute();
            }

            // Now delete the product option records
            $result = parent::delete($pks);

            $db->transactionCommit();

            return $result;
        } catch (\Exception $e) {
            $db->transactionRollback();
            $this->setError($e->getMessage());
            return false;
        }
    }

    /**
     * Get all option values for a product option.
     *
     * @param   int  $productoptionId  The product option ID.
     *
     * @return  array  Array of option value objects.
     *
     * @since   6.0.0
     */
    public function getOptionValues(int $productoptionId): array
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_product_optionvalues'))
            ->where($db->quoteName('productoption_id') . ' = :productoptionId')
            ->bind(':productoptionId', $productoptionId, ParameterType::INTEGER)
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);

        try {
            return $db->loadObjectList() ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }
}
