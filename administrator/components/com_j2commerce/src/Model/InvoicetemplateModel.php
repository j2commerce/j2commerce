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

use J2Commerce\Component\J2commerce\Administrator\Helper\MessageHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Versioning\VersionableModelTrait;

/**
 * Invoicetemplate Model
 *
 * @since  6.0.0
 */
class InvoicetemplateModel extends AdminModel
{
    use VersionableModelTrait;

    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  6.0.0
     */
    public $typeAlias = 'com_j2commerce.invoicetemplate';

    /**
     * Method to get a table object, load it if necessary.
     *
     * @param   string  $name     The table name. Optional.
     * @param   string  $prefix   The class prefix. Optional.
     * @param   array   $options  Configuration array for model. Optional.
     *
     * @return  Table  A Table object
     *
     * @since   6.0.0
     * @throws  \Exception
     */
    public function getTable($name = 'Invoicetemplate', $prefix = 'Administrator', $options = [])
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  \Joomla\CMS\Form\Form|false  A Form object on success, false on failure
     *
     * @since   6.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm('com_j2commerce.invoicetemplate', 'invoicetemplate', ['control' => 'jform', 'load_data' => $loadData]);

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
        $app = Factory::getApplication();

        // Check the session for previously entered form data.
        $data = $app->getUserState('com_j2commerce.edit.invoicetemplate.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        $this->preprocessData('com_j2commerce.invoicetemplate', $data);

        return $data;
    }

    /**
     * Method to validate the form data.
     *
     * @param   \Joomla\CMS\Form\Form  $form   The form to validate against.
     * @param   array                  $data   The data to validate.
     * @param   string                 $group  The name of the field group to validate.
     *
     * @return  array|false  Array of filtered data if valid, false otherwise.
     *
     * @since   6.0.0
     */
    public function validate($form, $data, $group = null)
    {
        $validData = parent::validate($form, $data, $group);

        if ($validData === false) {
            return false;
        }

        // Custom validation for invoice template
        if (empty($validData['title'])) {
            $this->setError(Text::_('COM_J2COMMERCE_ERROR_INVOICETEMPLATE_TITLE_REQUIRED'));
            return false;
        }

        if (empty($validData['invoice_type'])) {
            $this->setError(Text::_('COM_J2COMMERCE_ERROR_INVOICETEMPLATE_TYPE_REQUIRED'));
            return false;
        }

        if (empty($validData['body'])) {
            $this->setError(Text::_('COM_J2COMMERCE_ERROR_INVOICETEMPLATE_BODY_REQUIRED'));
            return false;
        }

        return $validData;
    }

    /**
     * Method to save the form data.
     *
     * @param   array  $data  The form data.
     *
     * @return  boolean  True on success, false on failure.
     *
     * @since   6.0.0
     */
    public function save($data)
    {
        $app   = Factory::getApplication();
        $input = $app->getInput();

        // Initialise variables;
        $table = $this->getTable();
        $key   = $table->getKeyName();
        $pk    = (!empty($data[$key])) ? $data[$key] : (int) $this->getState($this->getName() . '.id');
        $isNew = true;

        // Include the plugins for the save events.
        PluginHelper::importPlugin($this->events_map['save']);

        // Allow an exception to be thrown.
        try {
            // Load the row if saving an existing record.
            if ($pk > 0) {
                $table->load($pk);
                $isNew = false;
            }

            // Bind the data.
            if (!$table->bind($data)) {
                $this->setError($table->getError());
                return false;
            }

            // Prepare the row for saving
            $this->prepareTable($table);

            // Check the data.
            if (!$table->check()) {
                $this->setError($table->getError());
                return false;
            }

            // Trigger the before save event.
            $result = $app->triggerEvent($this->event_before_save, [$this->option . '.' . $this->name, $table, $isNew]);

            if (\in_array(false, $result, true)) {
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

            // Trigger the after save event.
            $app->triggerEvent($this->event_after_save, [$this->option . '.' . $this->name, $table, $isNew]);
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());
            return false;
        }

        if (isset($table->$key)) {
            $this->setState($this->getName() . '.id', $table->$key);
        }

        $this->setState($this->getName() . '.new', $isNew);

        return true;
    }

    /**
     * Duplicate one or more invoice templates.
     *
     * @param   array  $pks  IDs to duplicate
     *
     * @return  boolean
     * @throws  \Exception
     * @since   6.0.0
     */
    public function duplicate(&$pks)
    {
        $user = Factory::getApplication()->getIdentity();
        $pks  = (array) $pks;

        if (!$user->authorise('core.create', 'com_j2commerce')) {
            throw new \Exception(\Joomla\CMS\Language\Text::_('JLIB_APPLICATION_ERROR_CREATE_NOT_PERMITTED'), 403);
        }

        $table = $this->getTable();

        foreach ($pks as $pk) {
            // Load the original
            if (!$table->load((int) $pk)) {
                throw new \Exception($table->getError() ?: 'Unable to load record: ' . (int) $pk);
            }

            // Reset PK to insert a new row
            $table->j2commerce_invoicetemplate_id = 0;

            if (!$table->check()) {
                throw new \Exception($table->getError() ?: 'Validation failed while duplicating record: ' . (int) $pk);
            }

            if (!$table->store()) {
                throw new \Exception($table->getError() ?: 'Store failed while duplicating record: ' . (int) $pk);
            }
        }

        // Clean cache after changes
        $this->cleanCache();

        return true;
    }


    /**
     * Method to prepare a table object for saving.
     *
     * @param   Table  $table  The table object to prepare.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function prepareTable($table)
    {
        // Clean up GrapesJS data-j2c-src placeholders before saving
        if (!empty($table->body) && str_contains($table->body, 'data-j2c-src')) {
            $table->body = preg_replace_callback(
                '/<img([^>]*?)data-j2c-src="(\[[A-Z_]+\])"([^>]*?)>/i',
                static function (array $m): string {
                    $attrs = preg_replace('/\ssrc="[^"]*"/i', '', $m[1] . $m[3]);
                    return '<img' . $attrs . ' src="' . $m[2] . '">';
                },
                $table->body
            );
        }

        // Set default values
        if (!isset($table->enabled)) {
            $table->enabled = 1;
        }

        // Set ordering to the last item if not set
        if (empty($table->ordering)) {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select('MAX(ordering)')
                ->from($db->quoteName('#__j2commerce_invoicetemplates'));
            $db->setQuery($query);

            $max             = $db->loadResult();
            $table->ordering = (int) $max + 1;
        }

        // Trim title
        $table->title = trim($table->title);

        // Set language if not provided
        if (empty($table->language)) {
            $table->language = '*';
        }
    }

    /**
     * Method to change the enabled state of one or more records.
     *
     * @param   array    $pks    A list of the primary keys to change.
     * @param   integer  $value  The value of the enabled state.
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
            if ($table->load($pk)) {
                if (!$user->authorise('core.edit.state', 'com_j2commerce.invoicetemplate.' . $pk)) {
                    // Prune items that you can't change.
                    unset($pks[$i]);
                    Log::add(Text::_('JERROR_CORE_EDIT_STATE_NOT_PERMITTED'), Log::WARNING, 'jerror');
                }
            }
        }

        // Attempt to change the state of the records.
        if (!$table->publish($pks, $value, $user->get('id'))) {
            $this->setError($table->getError());
            return false;
        }

        return true;
    }

    /**
     * Method to delete one or more records.
     *
     * @param   array  $pks  A list of the primary keys to delete.
     *
     * @return  boolean  True on success, false on failure.
     *
     * @since   6.0.0
     */
    public function delete(&$pks)
    {
        $user  = Factory::getApplication()->getIdentity();
        $table = $this->getTable();
        $pks   = (array) $pks;

        // Access checks.
        foreach ($pks as $i => $pk) {
            if ($table->load($pk)) {
                if (!$user->authorise('core.delete', 'com_j2commerce.invoicetemplate.' . $pk)) {
                    // Prune items that you can't delete.
                    unset($pks[$i]);
                    Log::add(Text::_('JERROR_CORE_DELETE_NOT_PERMITTED'), Log::WARNING, 'jerror');
                }
            }
        }

        if (empty($pks)) {
            return true;
        }

        // Attempt to delete the records.
        foreach ($pks as $pk) {
            if (!$table->delete($pk)) {
                $this->setError($table->getError());
                return false;
            }
        }

        return true;
    }



    /**
     * Get available shortcodes/tags for templates
     *
     * @return  array  Array of shortcodes
     *
     * @since   6.0.0
     */
    public function getAvailableShortcodes()
    {
        $shortcodes = MessageHelper::getMessageTags();

        return $shortcodes;
        /*return [
            'SITE_NAME', 'SITE_URL', 'CURRENT_DATE', 'CURRENT_TIME',
            'CUSTOMER_NAME', 'CUSTOMER_EMAIL', 'CUSTOMER_PHONE',
            'BILLING_ADDRESS', 'SHIPPING_ADDRESS',
            'ORDER_ID', 'ORDER_DATE', 'ORDER_TOTAL', 'ORDER_SUBTOTAL',
            'ORDER_TAX', 'ORDER_SHIPPING', 'ORDER_DISCOUNT',
            'INVOICE_NUMBER', 'INVOICE_DATE', 'DUE_DATE',
            'PAYMENT_METHOD', 'ORDER_STATUS',
            'ORDER_ITEMS_TABLE', 'TAX_BREAKDOWN',
            'COMPANY_NAME', 'COMPANY_ADDRESS', 'COMPANY_PHONE',
            'COMPANY_EMAIL', 'COMPANY_VAT', 'COMPANY_LOGO'
        ];*/
    }




}
