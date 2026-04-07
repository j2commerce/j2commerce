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
 * Vendor item model class.
 *
 * @since  6.0.6
 */
class VendorModel extends AdminModel
{
    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  6.0.6
     */
    public $typeAlias = 'com_j2commerce.vendor';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.6
     */
    protected $text_prefix = 'COM_J2COMMERCE_VENDOR';

    /**
     * Method to auto-populate the model state.
     *
     * CRITICAL: This override is required because the parent AdminModel::populateState()
     * looks for a URL parameter named after the table's primary key (j2commerce_vendor_id),
     * but Joomla's standard convention uses 'id' as the URL parameter.
     *
     * Without this override:
     * - URL has: ?id=1
     * - Parent looks for: ?j2commerce_vendor_id=1 (not found!)
     * - State gets: vendor.id = 0
     * - getItem() loads: nothing
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function populateState(): void
    {
        $app = Factory::getApplication();

        // Get the primary key from the URL parameter 'id' (standard Joomla convention)
        // NOT from 'j2commerce_vendor_id' (the table's column name)
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
     * @since   6.0.6
     */
    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_j2commerce.vendor', 'vendor', ['control' => 'jform', 'load_data' => $loadData]);

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
     * @since   6.0.6
     */
    public function getTable($name = 'Vendor', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get a single record.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  mixed  Object on success, false on failure.
     *
     * @since   6.0.7
     */
    public function getItem($pk = null): mixed
    {
        $item = parent::getItem($pk);

        if ($item && $item->address_id) {
            // Load the associated address record
            $db    = $this->getDatabase();
            $query = $db->getQuery(true);

            $query->select('*')
                ->from($db->quoteName('#__j2commerce_addresses'))
                ->where($db->quoteName('j2commerce_address_id') . ' = :address_id')
                ->bind(':address_id', $item->address_id, \Joomla\Database\ParameterType::INTEGER);

            $db->setQuery($query);
            $address = $db->loadObject();

            if ($address) {
                // Merge address fields into the item
                $item->j2commerce_address_id = $address->j2commerce_address_id;
                $item->first_name            = $address->first_name;
                $item->last_name             = $address->last_name;
                $item->email                 = $address->email;
                $item->address_1             = $address->address_1;
                $item->address_2             = $address->address_2;
                $item->city                  = $address->city;
                $item->zip                   = $address->zip;
                $item->country_id            = $address->country_id;
                $item->zone_id               = $address->zone_id;
                $item->phone_1               = $address->phone_1;
                $item->phone_2               = $address->phone_2;
                $item->company               = $address->company;
                $item->tax_number            = $address->tax_number;

                // Load custom address fields dynamically
                $customFields = $this->getAddressCustomFields();
                foreach ($customFields as $field) {
                    if (isset($address->{$field->field_namekey})) {
                        $item->{$field->field_namekey} = $address->{$field->field_namekey};
                    }
                }
            }
        }

        return $item;
    }

    /**
     * Get enabled custom fields for address table.
     *
     * @return  array  Array of custom field objects.
     *
     * @since   6.0.7
     */
    public function getAddressCustomFields(): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName(['field_namekey', 'field_name', 'field_type', 'field_required']))
            ->from($db->quoteName('#__j2commerce_customfields'))
            ->where($db->quoteName('field_table') . ' = ' . $db->quote('address'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('field_type') . ' != ' . $db->quote('customtext'))
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);
        return $db->loadObjectList() ?: [];
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   6.0.6
     */
    protected function loadFormData(): mixed
    {
        $app  = Factory::getApplication();
        $data = $app->getUserState('com_j2commerce.edit.vendor.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        return $data;
    }

    /**
     * Method to save the form data.
     *
     * @param   array  $data  The form data.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.7
     */
    public function save($data): bool
    {
        // Extract address fields
        $addressFields = [
            'first_name', 'last_name', 'email', 'address_1', 'address_2',
            'city', 'zip', 'country_id', 'zone_id', 'phone_1', 'phone_2',
            'company', 'tax_number',
        ];

        $addressData = [];
        foreach ($addressFields as $field) {
            if (isset($data[$field])) {
                $addressData[$field] = $data[$field];
            }
        }

        // Add custom address fields
        $customFields = $this->getAddressCustomFields();
        foreach ($customFields as $field) {
            if (isset($data[$field->field_namekey])) {
                $addressData[$field->field_namekey] = $data[$field->field_namekey];
            }
        }

        // Save or create address record
        if (!empty($data['address_id']) && $data['address_id'] > 0) {
            // Update existing address
            $addressData['j2commerce_address_id'] = $data['address_id'];
            $this->saveAddress($addressData);
        } else {
            // Create new address
            $addressData['user_id'] = (int) ($data['j2commerce_user_id'] ?? 0);
            $addressData['type']    = 'vendor';
            $addressId              = $this->saveAddress($addressData);
            $data['address_id']     = $addressId;
        }

        // Remove address fields from vendor data before saving
        foreach ($addressFields as $field) {
            unset($data[$field]);
        }
        foreach ($customFields as $field) {
            unset($data[$field->field_namekey]);
        }

        return parent::save($data);
    }

    /**
     * Save address record.
     *
     * @param   array  $addressData  Address data to save.
     *
     * @return  int  The address ID.
     *
     * @throws  \RuntimeException  On database error.
     *
     * @since   6.0.7
     */
    protected function saveAddress(array $addressData): int
    {
        $db    = $this->getDatabase();
        $isNew = empty($addressData['j2commerce_address_id']);

        if ($isNew) {
            // Insert new address
            $columns = [];
            $values  = [];
            $binds   = [];

            foreach ($addressData as $key => $value) {
                $columns[]           = $db->quoteName($key);
                $placeholder         = ':' . $key;
                $values[]            = $placeholder;
                $binds[$placeholder] = $value;
            }

            $query = $db->getQuery(true);
            $query->insert($db->quoteName('#__j2commerce_addresses'))
                ->columns($columns)
                ->values(implode(', ', $values));

            foreach ($binds as $placeholder => $value) {
                $query->bind($placeholder, $binds[$placeholder]);
            }

            $db->setQuery($query);
            $db->execute();

            return (int) $db->insertid();
        }
        // Update existing address
        $addressId = (int) $addressData['j2commerce_address_id'];
        unset($addressData['j2commerce_address_id']);

        $fields = [];
        $binds  = [];

        foreach ($addressData as $key => $value) {
            $placeholder         = ':' . $key;
            $fields[]            = $db->quoteName($key) . ' = ' . $placeholder;
            $binds[$placeholder] = $value;
        }

        $query = $db->getQuery(true);
        $query->update($db->quoteName('#__j2commerce_addresses'))
            ->set($fields)
            ->where($db->quoteName('j2commerce_address_id') . ' = :address_id')
            ->bind(':address_id', $addressId, \Joomla\Database\ParameterType::INTEGER);

        foreach ($binds as $placeholder => $value) {
            $query->bind($placeholder, $binds[$placeholder]);
        }

        $db->setQuery($query);
        $db->execute();

        return $addressId;

    }

    /**
     * Prepare and sanitize the table before saving.
     *
     * @param   Table  $table  The Table object.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function prepareTable($table): void
    {
        // Ensure user_id and address_id are integers
        if (!empty($table->j2commerce_user_id)) {
            $table->j2commerce_user_id = (int) $table->j2commerce_user_id;
        }

        if (!empty($table->address_id)) {
            $table->address_id = (int) $table->address_id;
        }
    }
}
