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
 * Order Status item model class.
 *
 * @since  6.0.7
 */
class OrderstatusModel extends AdminModel
{
    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  6.0.7
     */
    public $typeAlias = 'com_j2commerce.orderstatus';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.7
     */
    protected $text_prefix = 'COM_J2COMMERCE_ORDERSTATUS';

    /**
     * Method to auto-populate the model state.
     *
     * CRITICAL: This override is required because the parent AdminModel::populateState()
     * looks for a URL parameter named after the table's primary key (j2commerce_orderstatus_id),
     * but Joomla's standard convention uses 'id' as the URL parameter.
     *
     * Without this override:
     * - URL has: ?id=1
     * - Parent looks for: ?j2commerce_orderstatus_id=1 (not found!)
     * - State gets: orderstatus.id = 0
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
        // NOT from 'j2commerce_orderstatus_id' (the table's column name)
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
        $form = $this->loadForm(
            'com_j2commerce.orderstatus',
            'orderstatus',
            ['control' => 'jform', 'load_data' => $loadData]
        );

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
    public function getTable($name = 'Orderstatus', $prefix = 'Administrator', $options = []): Table
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
        $data = $app->getUserState('com_j2commerce.edit.orderstatus.data', []);

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
        // Trim status name
        if (!empty($table->orderstatus_name)) {
            $table->orderstatus_name = trim($table->orderstatus_name);
        }

        // Trim CSS class
        if (!empty($table->orderstatus_cssclass)) {
            $table->orderstatus_cssclass = trim($table->orderstatus_cssclass);
        }

        // Ensure ordering is set for new records
        if (empty($table->ordering)) {
            $table->ordering = 0;
        }

        // Ensure enabled is set for new records
        if (!isset($table->enabled) || $table->enabled === '') {
            $table->enabled = 1;
        }
    }
}
