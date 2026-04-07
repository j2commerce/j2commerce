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

/**
 * Customer model class.
 *
 * Handles single customer (address) record editing.
 *
 * @since  6.0.7
 */
class CustomerModel extends AdminModel
{
    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  6.0.7
     */
    public $typeAlias = 'com_j2commerce.customer';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.7
     */
    protected $text_prefix = 'COM_J2COMMERCE_CUSTOMER';

    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  Form|boolean  A Form object on success, false on failure
     *
     * @since   6.0.7
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm(
            'com_j2commerce.customer',
            'customer',
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
     * @since   6.0.7
     */
    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_j2commerce.edit.customer.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        return $data;
    }

    /**
     * Method to get a table object.
     *
     * @param   string  $name     The table name.
     * @param   string  $prefix   The class prefix.
     * @param   array   $options  Configuration array for table.
     *
     * @return  \Joomla\CMS\Table\Table
     *
     * @since   6.0.7
     */
    public function getTable($name = 'Customer', $prefix = 'Administrator', $options = [])
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to auto-populate the model state.
     *
     * @return  void
     *
     * @since   6.0.7
     */
    protected function populateState(): void
    {
        $app = Factory::getApplication();

        // Load the primary key from request (using 'id' parameter)
        $pk = $app->getInput()->getInt('id', 0);
        $this->setState($this->getName() . '.id', $pk);

        // Load component params
        $params = ComponentHelper::getParams('com_j2commerce');
        $this->setState('params', $params);
    }

    /**
     * Prepare and sanitise the table data prior to saving.
     *
     * @param   \Joomla\CMS\Table\Table  $table  The table object.
     *
     * @return  void
     *
     * @since   6.0.7
     */
    protected function prepareTable($table): void
    {
        // Clean up any whitespace in text fields
        if (isset($table->first_name)) {
            $table->first_name = trim($table->first_name);
        }

        if (isset($table->last_name)) {
            $table->last_name = trim($table->last_name);
        }

        if (isset($table->email)) {
            $table->email = trim(strtolower($table->email));
        }
    }
}
