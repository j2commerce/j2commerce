<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;

/**
 * Payment Method Model
 *
 * @since  6.0.0
 */
class PaymentmethodModel extends AdminModel
{
    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  6.0.0
     */
    public $typeAlias = 'com_j2commerce.paymentmethod';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $text_prefix = 'COM_J2COMMERCE_PAYMENT_METHOD';

    /**
     * Method to get a table object, load it if necessary.
     *
     * @param   string  $type    The table name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  Table  A Table object
     *
     * @since   6.0.0
     * @throws  \Exception
     */
    public function getTable($type = 'Extension', $prefix = '\\Joomla\\CMS\\Table\\', $config = [])
    {
        return Table::getInstance($type, $prefix, $config);
    }

    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  Form|bool  A Form object on success, false on failure
     *
     * @since   6.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm(
            'com_j2commerce.Paymentmethod',
            'Paymentmethod',
            [
                'control'   => 'jform',
                'load_data' => $loadData,
            ]
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
        $data = Factory::getApplication()->getUserState('com_j2commerce.edit.Paymentmethod.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        $this->preprocessData('com_j2commerce.Paymentmethod', $data);

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
        $pk = (int) ($pk ?: $this->getState($this->getName() . '.id'));

        if (!$pk) {
            return parent::getItem($pk);
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('extension_id') . ' = ' . $pk)
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('j2commerce'));

        $db->setQuery($query);
        $item = $db->loadObject();

        if ($item) {
            // Parse manifest cache for additional info
            if (!empty($item->manifest_cache)) {
                $manifest = json_decode($item->manifest_cache);
                if (\is_object($manifest)) {
                    $item->version     = isset($manifest->version) ? $manifest->version : '';
                    $item->author      = isset($manifest->author) ? $manifest->author : '';
                    $item->description = isset($manifest->description) ? $manifest->description : '';
                }
            }

            // Check if plugin files exist
            $pluginPath        = JPATH_SITE . '/plugins/j2commerce/' . $item->element;
            $item->files_exist = is_dir($pluginPath);
        }

        return $item;
    }

    /**
     * Method to save the form data.
     *
     * @param   array  $data  The form data.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.0
     */
    public function save($data)
    {
        $pk = (int) (!empty($data['extension_id']) ? $data['extension_id'] : $this->getState($this->getName() . '.id'));

        // Get a row instance.
        $table = $this->getTable();

        // This binds onto the shared #__extensions table, so confine it the way getItem()
        // already does. Unconfined, an extension_id from the request rewrites params or
        // enabled on any extension row on the site, which is a core.admin-class capability.
        // There is no create path either: these rows are plugins, discovered by the installer.
        if ($pk <= 0 || !$table->load($pk) || $table->type !== 'plugin' || $table->folder !== 'j2commerce') {
            $this->setError(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'));

            return false;
        }

        // The row's identity is not the caller's to rewrite. extension_id goes too: the table
        // is already loaded, and a falsy one in the payload would bind over the primary key
        // and make store() INSERT a clone of the row instead of updating it.
        unset($data['type'], $data['folder'], $data['element'], $data['extension_id']);

        // Bind the data.
        if (!$table->bind($data)) {
            $this->setError($table->getError());
            return false;
        }

        // Check the data.
        if (!$table->check()) {
            $this->setError($table->getError());
            return false;
        }

        // Store the data.
        if (!$table->store()) {
            $this->setError($table->getError());
            return false;
        }

        // Clean the cache.
        $this->cleanCache();

        return true;
    }

    /**
     * Method to change the published state of one or more records.
     *
     * @param   array    $pks    A list of the primary keys to change.
     * @param   integer  $value  The new state.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.0
     */
    public function publish(&$pks, $value = 1)
    {
        $user  = Factory::getApplication()->getIdentity();
        $table = $this->getTable();
        $pks   = (array) $pks;

        // Access checks.
        foreach ($pks as $i => $pk) {
            // Same confinement getItem() and save() declare: this model owns j2commerce plugin
            // rows on the shared #__extensions table, and nothing else on it.
            if (!$table->load($pk) || $table->type !== 'plugin' || $table->folder !== 'j2commerce') {
                unset($pks[$i]);
                Factory::getApplication()->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'error');
                continue;
            }

            if (!$user->authorise('core.edit.state', 'com_j2commerce.Paymentmethod.' . $pk)) {
                // Prune items that you can't change.
                unset($pks[$i]);
                Factory::getApplication()->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'), 'error');
            }
        }

        // Every key was pruned. Table::publish() falls back to the row still loaded on the
        // instance when it is handed an empty set, so it must not be called with one.
        if (!$pks) {
            return true;
        }

        // Attempt to change the state of the records.
        if (!$table->publish($pks, $value, $user->id)) {
            $this->setError($table->getError());
            return false;
        }

        // Clean the cache.
        $this->cleanCache();

        return true;
    }

    /**
     * A protected method to get a set of ordering conditions.
     *
     * @param   object  $table  A Table object.
     *
     * @return  array  An array of conditions to add to ordering queries.
     *
     * @since   6.0.0
     */
    protected function getReorderConditions($table)
    {
        $condition   = [];
        $condition[] = 'type = ' . $this->_db->quote('plugin');
        $condition[] = 'folder = ' . $this->_db->quote('j2commerce');

        return $condition;
    }

    /**
     * Method to delete one or more records.
     *
     * @param   array  $pks  An array of record primary keys.
     *
     * @return  boolean  True if successful, false if an error occurs.
     *
     * @since   6.0.0
     */
    public function delete(&$pks)
    {
        $app = Factory::getApplication();
        $app->enqueueMessage(Text::_('COM_J2COMMERCE_Payment_METHOD_DELETE_NOT_ALLOWED'), 'error');
        return false;
    }
}
