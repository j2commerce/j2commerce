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
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Table;

/**
 * Product Price Model
 *
 * Handles advanced pricing for product variants.
 *
 * @since  6.0.0
 */
class ProductpriceModel extends AdminModel
{
    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  6.0.0
     */
    public $typeAlias = 'com_j2commerce.productprice';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $text_prefix = 'COM_J2COMMERCE_PRODUCTPRICE';

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

        // Read from URL param 'id', NOT from the table's column name
        $pk = $app->getInput()->getInt('id', 0);
        $this->setState($this->getName() . '.id', $pk);

        // Also get variant_id from request
        $variantId = $app->getInput()->getInt('variant_id', 0);
        $this->setState('productprice.variant_id', $variantId);

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
     * @since   6.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        // Add the form path explicitly to ensure Joomla can find it
        Form::addFormPath(JPATH_ADMINISTRATOR . '/components/com_j2commerce/forms');
        Form::addFieldPath(JPATH_ADMINISTRATOR . '/components/com_j2commerce/src/Field');

        // Get the form
        $form = $this->loadForm(
            'com_j2commerce.productprice',
            'productprice',
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
        // Check the session for previously entered form data.
        $data = Factory::getApplication()->getUserState('com_j2commerce.edit.productprice.data', []);

        if (empty($data)) {
            $data = $this->getItem();

            // Prime some default values for new records
            if (empty($data->j2commerce_productprice_id)) {
                $app              = Factory::getApplication();
                $data->variant_id = $app->getInput()->getInt('variant_id', 0);

                // Set default dates
                $now             = Factory::getDate()->toSql();
                $data->date_from = $now;

                // Set valid_to to one year from now
                $future        = Factory::getDate('+1 year')->toSql();
                $data->date_to = $future;

                $data->quantity_from     = 0;
                $data->quantity_to       = 0;
                $data->customer_group_id = 0;
                $data->price             = 0.00;
            }
        }

        $this->preprocessData('com_j2commerce.productprice', $data);

        return $data;
    }

    /**
     * Method to get a single record.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  mixed  Object on success, false on failure.
     *
     * @since   6.0.0
     */
    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if ($item) {
            // Ensure numeric fields are properly typed
            if (isset($item->variant_id)) {
                $item->variant_id = (int) $item->variant_id;
            }

            if (isset($item->quantity_from)) {
                $item->quantity_from = (int) $item->quantity_from;
            }

            if (isset($item->quantity_to)) {
                $item->quantity_to = (int) $item->quantity_to;
            }

            if (isset($item->customer_group_id)) {
                $item->customer_group_id = (int) $item->customer_group_id;
            }

            if (isset($item->price)) {
                $item->price = (float) $item->price;
            }

            // Handle empty dates
            if (isset($item->date_from) && $item->date_from === '0000-00-00 00:00:00') {
                $item->date_from = '';
            }

            if (isset($item->date_to) && $item->date_to === '0000-00-00 00:00:00') {
                $item->date_to = '';
            }
        }

        return $item;
    }

    /**
     * Method to get a table object.
     *
     * @param   string  $type    The table name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  Table  A Table object
     *
     * @since   6.0.0
     */
    public function getTable($type = 'Productprice', $prefix = 'Administrator', $config = [])
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
     * @since   6.0.0
     */
    public function save($data)
    {
        // Ensure numeric fields are properly formatted
        if (isset($data['price'])) {
            $data['price'] = (float) $data['price'];
        }

        if (isset($data['quantity_from'])) {
            $data['quantity_from'] = (int) $data['quantity_from'];
        }

        if (isset($data['quantity_to'])) {
            $data['quantity_to'] = (int) $data['quantity_to'];
        }

        if (isset($data['customer_group_id'])) {
            $data['customer_group_id'] = (int) $data['customer_group_id'];
        }

        if (isset($data['variant_id'])) {
            $data['variant_id'] = (int) $data['variant_id'];
        }

        // Handle date fields - convert empty strings to null
        if (isset($data['date_from']) && empty($data['date_from'])) {
            $data['date_from'] = null;
        }

        if (isset($data['date_to']) && empty($data['date_to'])) {
            $data['date_to'] = null;
        }

        // Include the content plugins for the on save events.
        PluginHelper::importPlugin('content');

        return parent::save($data);
    }
}
