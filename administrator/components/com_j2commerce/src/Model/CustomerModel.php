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
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Database\ParameterType;

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

    /**
     * Save an address row.
     *
     * In card mode the customer edit page only exposes the user_id field on the page-level
     * form. When the store owner re-links the current address row to a different Joomla user
     * we propagate that change to every address that previously belonged to the OLD user
     * (otherwise the other cards on the page would orphan as soon as the form is saved).
     *
     * @param   array  $data  The form data.
     *
     * @return  bool  True on success.
     *
     * @since   6.0.7
     */
    public function save($data)
    {
        $oldUserId = null;

        // Capture the OLD user_id before parent::save() rewrites the row.
        $addressId = (int) ($data['j2commerce_address_id'] ?? 0);

        if ($addressId > 0 && \array_key_exists('user_id', $data)) {
            $existing = $this->getAddressForCard($addressId);

            if ($existing) {
                $oldUserId = (int) $existing->user_id;
            }
        }

        $result = parent::save($data);

        if (!$result) {
            return $result;
        }

        // Propagate the user_id change to every other address that belonged to the old user.
        if ($oldUserId !== null) {
            $newUserId = (int) ($data['user_id'] ?? 0);

            if ($oldUserId !== $newUserId && $oldUserId > 0) {
                $db    = $this->getDatabase();
                $query = $db->getQuery(true);

                $query->update($db->quoteName('#__j2commerce_addresses'))
                    ->set($db->quoteName('user_id') . ' = :newUserId')
                    ->where($db->quoteName('user_id') . ' = :oldUserId')
                    ->bind(':newUserId', $newUserId, ParameterType::INTEGER)
                    ->bind(':oldUserId', $oldUserId, ParameterType::INTEGER);

                try {
                    $db->setQuery($query)->execute();
                } catch (\Throwable $e) {
                    $this->setError($e->getMessage());
                }
            }
        }

        return $result;
    }

    /**
     * Re-link every address row that currently belongs to $oldUserId to $newUserId.
     *
     * Used by the AJAX user picker in the customer edit view's card mode sidebar.
     *
     * @param   int  $oldUserId  Current Joomla user ID linking the addresses.
     * @param   int  $newUserId  New Joomla user ID (0 to unlink to guest).
     *
     * @return  bool  True on success.
     *
     * @since   6.0.8
     */
    public function relinkUser(int $oldUserId, int $newUserId): bool
    {
        if ($oldUserId <= 0 || $oldUserId === $newUserId) {
            return false;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->update($db->quoteName('#__j2commerce_addresses'))
            ->set($db->quoteName('user_id') . ' = :newUserId')
            ->where($db->quoteName('user_id') . ' = :oldUserId')
            ->bind(':newUserId', $newUserId, ParameterType::INTEGER)
            ->bind(':oldUserId', $oldUserId, ParameterType::INTEGER);

        try {
            $db->setQuery($query)->execute();
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Get all addresses linked to a specific Joomla user (with country and zone names).
     *
     * @param   int  $userId  Joomla user ID.
     *
     * @return  array  Array of address objects (empty if no user).
     *
     * @since   6.0.8
     */
    public function getAddressesByUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        /** @var AddressesModel $addressesModel */
        $addressesModel = Factory::getApplication()
            ->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createModel('Addresses', 'Administrator', ['ignore_request' => true]);

        if (!$addressesModel) {
            return [];
        }

        return $addressesModel->getAddressesByUser($userId);
    }

    /**
     * Get all addresses belonging to a guest customer identified by email
     * (with country and zone names). Used when user_id = 0 so the edit view
     * can still render the card grid consistently with registered customers.
     *
     * @param   string  $email  Customer email.
     *
     * @return  array  Array of address objects (empty if email is blank).
     *
     * @since   6.0.9
     */
    public function getAddressesByEmail(string $email): array
    {
        if (trim($email) === '') {
            return [];
        }

        /** @var AddressesModel $addressesModel */
        $addressesModel = Factory::getApplication()
            ->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createModel('Addresses', 'Administrator', ['ignore_request' => true]);

        if (!$addressesModel) {
            return [];
        }

        return $addressesModel->getAddressesByEmail($email);
    }

    /**
     * Get enabled custom fields for the address table.
     *
     * Mirrors ManufacturerModel::getAddressCustomFields() so the customer edit view can
     * render custom address fields after the tax number.
     *
     * @return  array  Array of custom field objects.
     *
     * @since   6.0.8
     */
    public function getAddressCustomFields(): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_customfields'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->where(
                '(' . $db->quoteName('field_table') . ' = ' . $db->quote('address') . ' OR '
                . $db->quoteName('field_table') . ' IS NULL OR '
                . $db->quoteName('field_table') . ' = ' . $db->quote('') . ')'
            )
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('j2commerce_customfield_id') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Fetch a single address row with country_name / zone_name joined, ready for card rendering.
     *
     * @param   int  $addressId  Address primary key.
     *
     * @return  object|null  Address row or null when not found.
     *
     * @since   6.0.8
     */
    public function getAddressForCard(int $addressId): ?object
    {
        if ($addressId <= 0) {
            return null;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName([
            'a.j2commerce_address_id', 'a.user_id', 'a.first_name', 'a.last_name',
            'a.email', 'a.address_1', 'a.address_2', 'a.city', 'a.zip',
            'a.zone_id', 'a.country_id', 'a.phone_1', 'a.phone_2', 'a.fax',
            'a.type', 'a.company', 'a.tax_number',
        ]))
            ->from($db->quoteName('#__j2commerce_addresses', 'a'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_countries', 'c')
                . ' ON ' . $db->quoteName('c.j2commerce_country_id') . ' = ' . $db->quoteName('a.country_id')
            )
            ->select($db->quoteName('c.country_name'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_zones', 'z')
                . ' ON ' . $db->quoteName('z.j2commerce_zone_id') . ' = ' . $db->quoteName('a.zone_id')
            )
            ->select($db->quoteName('z.zone_name'))
            ->where($db->quoteName('a.j2commerce_address_id') . ' = :id')
            ->bind(':id', $addressId, ParameterType::INTEGER);

        $db->setQuery($query);

        $row = $db->loadObject();

        return $row ?: null;
    }

    /**
     * Save an address row from posted data (AJAX endpoint).
     *
     * @param   array  $data  Posted form data.
     *
     * @return  object|false  Fresh card-ready row on success, false on failure.
     *
     * @since   6.0.8
     */
    public function saveAddressRow(array $data): object|false
    {
        $table = $this->getTable();

        // On update, the posted address must belong to the posted user — without
        // this, any caller passing an arbitrary id could overwrite another
        // customer's address row.
        $addressId = (int) ($data['j2commerce_address_id'] ?? 0);

        if ($addressId > 0) {
            $existing = $this->getTable();

            if (!$existing->load($addressId)
                || (int) $existing->user_id !== (int) ($data['user_id'] ?? -1)
            ) {
                $this->setError(Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_SAVE_ERROR'));

                return false;
            }
        }

        if (!$table->bind($data)) {
            $this->setError($table->getError() ?: Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_SAVE_ERROR'));

            return false;
        }

        $this->prepareTable($table);

        if (!$table->check()) {
            $this->setError($table->getError() ?: Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_SAVE_ERROR'));

            return false;
        }

        if (!$table->store()) {
            $this->setError($table->getError() ?: Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_SAVE_ERROR'));

            return false;
        }

        return $this->getAddressForCard((int) $table->j2commerce_address_id);
    }

    /**
     * Delete an address row (AJAX endpoint).
     *
     * @param   int  $addressId  Address primary key.
     *
     * @return  bool  True on success.
     *
     * @since   6.0.8
     */
    public function deleteAddressRow(int $addressId): bool
    {
        if ($addressId <= 0) {
            $this->setError(Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_DELETE_ERROR'));

            return false;
        }

        $table = $this->getTable();

        if (!$table->load($addressId)) {
            $this->setError(Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_DELETE_ERROR'));

            return false;
        }

        if (!$table->delete($addressId)) {
            $this->setError($table->getError() ?: Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_DELETE_ERROR'));

            return false;
        }

        return true;
    }
}
