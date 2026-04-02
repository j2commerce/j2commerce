<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Model;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\MessageHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Versioning\VersionableModelTrait;

/**
 * Emailtemplate Model
 *
 * @since  6.0.0
 */
class EmailtemplateModel extends AdminModel
{
    use VersionableModelTrait;

    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  6.0.0
     */
    public $typeAlias = 'com_j2commerce.emailtemplate';

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
    public function getTable($name = 'Emailtemplate', $prefix = 'Administrator', $options = [])
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
        $form = $this->loadForm('com_j2commerce.emailtemplate', 'emailtemplate', ['control' => 'jform', 'load_data' => $loadData]);

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
        $data = $app->getUserState('com_j2commerce.edit.emailtemplate.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        $this->preprocessData('com_j2commerce.emailtemplate', $data);

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

        // Custom validation for email template
        if (empty($validData['email_type'])) {
            $this->setError(Text::_('COM_J2COMMERCE_ERROR_EMAILTEMPLATE_TYPE_REQUIRED'));
            return false;
        }

        if (empty($validData['receiver_type'])) {
            $this->setError(Text::_('COM_J2COMMERCE_ERROR_EMAILTEMPLATE_RECEIVER_TYPE_REQUIRED'));
            return false;
        }

        if (empty($validData['subject'])) {
            $this->setError(Text::_('COM_J2COMMERCE_ERROR_EMAILTEMPLATE_SUBJECT_REQUIRED'));
            return false;
        }

        if (empty($validData['body']) && empty($validData['body_source_file'])) {
            $this->setError(Text::_('COM_J2COMMERCE_ERROR_EMAILTEMPLATE_BODY_REQUIRED'));
            return false;
        }

        // File-based body source can execute arbitrary PHP — restrict to super users
        if (($validData['body_source'] ?? '') === 'file'
            && !Factory::getApplication()->getIdentity()->authorise('core.admin')
        ) {
            $this->setError(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'));
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
        $app = Factory::getApplication();
        $input = $app->getInput();

        // Initialise variables;
        $table = $this->getTable();
        $key = $table->getKeyName();
        $pk = (!empty($data[$key])) ? $data[$key] : (int) $this->getState($this->getName() . '.id');
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

            if (in_array(false, $result, true)) {
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
     * Duplicate one or more email templates.
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
            $table->j2commerce_emailtemplate_id = 0;

            // Update the subject to indicate it's a copy
            $table->subject = $table->subject . ' (Copy)';

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
            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select('MAX(ordering)')
                ->from($db->quoteName('#__j2commerce_emailtemplates'));
            $db->setQuery($query);

            $max = $db->loadResult();
            $table->ordering = (int) $max + 1;
        }

        // Trim subject
        $table->subject = trim($table->subject);

        // Set language if not provided
        if (empty($table->language)) {
            $table->language = '*';
        }

        // Handle body source
        if ($table->body_source === 'file' && !empty($table->body_source_file)) {
            // Clear the editor body if using file source
            $table->body = '';
        } elseif ($table->body_source === 'editor' || $table->body_source === 'visual') {
            // Clear the file source if using editor or visual
            $table->body_source_file = '';
        }

        // Process shortcodes in the body if using editor or visual
        if (in_array($table->body_source, ['editor', 'visual'], true) && !empty($table->body)) {
            $table->body = $this->processShortcodes($table->body);
        }
    }

    /**
     * Process shortcodes in email template content
     *
     * @param   string  $content  The content to process
     *
     * @return  string  The processed content
     *
     * @since   6.0.0
     */
    protected function processShortcodes($content)
    {
        // For now, just return the content as-is
        // This can be expanded to validate shortcodes or perform preprocessing
        return $content;
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
        $user = Factory::getApplication()->getIdentity();
        $table = $this->getTable();
        $pks = (array) $pks;

        // Access checks.
        foreach ($pks as $i => $pk) {
            if ($table->load($pk)) {
                if (!$user->authorise('core.edit.state', 'com_j2commerce.emailtemplate.' . $pk)) {
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
        $user = Factory::getApplication()->getIdentity();
        $table = $this->getTable();
        $pks = (array) $pks;

        // Access checks.
        foreach ($pks as $i => $pk) {
            if ($table->load($pk)) {
                if (!$user->authorise('core.delete', 'com_j2commerce.emailtemplate.' . $pk)) {
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
     * Get available shortcodes/tags for email templates
     *
     * @return  array  Array of shortcodes
     *
     * @since   6.0.0
     */
    public function getAvailableShortcodes()
    {
        // Try to get shortcodes from MessageHelper if available, otherwise return basic set
        if (class_exists('J2Commerce\Component\J2commerce\Administrator\Helper\MessageHelper')) {
            try {
                return MessageHelper::getMessageTags();
            } catch (\Exception $e) {
                // Fall back to basic shortcodes if MessageHelper fails
            }
        }

        // Basic shortcode set for email templates
        return [
            '{order_id}' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_ORDER_ID'),
            '{order_date}' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_ORDER_DATE'),
            '{customer_name}' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_CUSTOMER_NAME'),
            '{customer_email}' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_CUSTOMER_EMAIL'),
            '{order_total}' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_ORDER_TOTAL'),
            '{order_items}' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_ORDER_ITEMS'),
            '{billing_address}' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_BILLING_ADDRESS'),
            '{shipping_address}' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_SHIPPING_ADDRESS'),
            '{payment_method}' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_PAYMENT_METHOD'),
            '{shipping_method}' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_SHIPPING_METHOD'),
            '{store_name}' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_STORE_NAME'),
            '{store_url}' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_STORE_URL'),
            '{site_name}' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_SITE_NAME'),
            '{current_date}' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_CURRENT_DATE'),
            '{current_time}' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_CURRENT_TIME'),
        ];
    }



    /**
     * Get receiver type options
     *
     * @return  array  Array of receiver type options
     *
     * @since   6.0.0
     */
    public function getReceiverTypeOptions()
    {
        return [
            'customer' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_RECEIVER_CUSTOMER'),
            'admin' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_RECEIVER_ADMIN'),
            '*' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_RECEIVER_BOTH'),
        ];
    }
}
