<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Controller;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\EmailHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\MessageHelper;
use Joomla\CMS\Event\GenericEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Versioning\VersionableControllerTrait;
use Joomla\Database\DatabaseInterface;

/**
 * Controller for a single email template
 *
 * @since  6.0.0
 */
class EmailtemplateController extends FormController
{
    use VersionableControllerTrait;

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $text_prefix = 'COM_J2COMMERCE';

    /**
     * Method to run batch operations.
     *
     * @param   object  $model  The model.
     *
     * @return  boolean  True if successful, false otherwise and the model will be set the error
     *
     * @since   6.0.0
     */
    public function batch($model = null)
    {
        \Joomla\CMS\Session\Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        // Set the model
        $model = $this->getModel('Emailtemplate', '', []);

        // Preset the redirect
        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=emailtemplates', false));

        return parent::batch($model);
    }

    /**
     * Function that allows child controller access to model data after the data has been saved.
     *
     * @param   BaseDatabaseModel  $model      The data model object.
     * @param   array              $validData  The validated data.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function postSaveHook(BaseDatabaseModel $model, $validData = [])
    {
        $task = $this->getTask();

        switch ($task) {
            case 'apply':
                // Set the record data in the session.
                $recordId = $model->getState($this->context . '.id');
                $this->holdEditId($this->context, $recordId);
                $this->setRedirect(
                    Route::_(
                        'index.php?option=' . $this->option . '&view=' . $this->view_item . $this->getRedirectToItemAppend($recordId, 'j2commerce_emailtemplate_id'),
                        false
                    )
                );
                break;

            case 'save2new':
                // Clear the record id and data from the session.
                $this->releaseEditId($this->context, $model->getState($this->context . '.id'));
                $this->setRedirect(
                    Route::_(
                        'index.php?option=' . $this->option . '&view=' . $this->view_item . $this->getRedirectToItemAppend(),
                        false
                    )
                );
                break;

            default:
                // Clear the record id and data from the session.
                $this->releaseEditId($this->context, $model->getState($this->context . '.id'));
                $this->setRedirect(
                    Route::_(
                        'index.php?option=' . $this->option . '&view=' . $this->view_list . $this->getRedirectToListAppend(),
                        false
                    )
                );
                break;
        }
    }

    /**
     * Method override to check if you can add a new record.
     *
     * @param   array  $data  An array of input data.
     *
     * @return  boolean
     *
     * @since   6.0.0
     */
    protected function allowAdd($data = [])
    {
        return parent::allowAdd($data);
    }

    /**
     * Method override to check if you can edit an existing record.
     *
     * @param   array   $data  An array of input data.
     * @param   string  $key   The name of the key for the primary key.
     *
     * @return  boolean
     *
     * @since   6.0.0
     */
    protected function allowEdit($data = [], $key = 'j2commerce_emailtemplate_id')
    {
        $recordId = (int) isset($data[$key]) ? $data[$key] : 0;
        $user     = $this->app->getIdentity();

        // Zero record (id:0), return component edit permission by calling parent
        if (!$recordId) {
            return parent::allowEdit($data, $key);
        }

        // Check edit on the record asset (explicit or inherited)
        if ($user->authorise('core.edit', 'com_j2commerce.emailtemplate.' . $recordId)) {
            return true;
        }

        // Check edit own on the record asset (explicit or inherited)
        if ($user->authorise('core.edit.own', 'com_j2commerce.emailtemplate.' . $recordId)) {
            // Existing record already has an owner, get it
            $record = $this->getModel()->getItem($recordId);

            if (empty($record)) {
                return false;
            }

            // Grant if current user is owner of the record
            return $user->get('id') == $record->created_by;
        }

        return false;
    }

    /**
     * Gets the URL arguments to append to an item redirect.
     *
     * @param   integer  $recordId  The primary key id for the item.
     * @param   string   $urlVar    The name of the URL variable for the id.
     *
     * @return  string  The arguments to append to the redirect URL.
     *
     * @since   6.0.0
     */
    protected function getRedirectToItemAppend($recordId = null, $urlVar = 'j2commerce_emailtemplate_id')
    {
        // Need to override the parent method completely.
        $tmpl   = $this->input->get('tmpl');
        $layout = $this->input->get('layout', 'edit', 'string');
        $append = '';

        // Setup redirect info.
        if ($tmpl) {
            $append .= '&tmpl=' . $tmpl;
        }

        if ($layout) {
            $append .= '&layout=' . $layout;
        }

        if ($recordId) {
            $append .= '&' . $urlVar . '=' . $recordId;
        }

        $return = $this->input->get('return', null, 'base64');

        if ($return) {
            $append .= '&return=' . $return;
        }

        return $append;
    }

    /**
     * Gets the URL arguments to append to a list redirect.
     *
     * @return  string  The arguments to append to the redirect URL.
     *
     * @since   6.0.0
     */
    protected function getRedirectToListAppend()
    {
        $tmpl   = $this->input->get('tmpl');
        $append = '';

        // Setup redirect info.
        if ($tmpl) {
            $append .= '&tmpl=' . $tmpl;
        }

        return $append;
    }

    /**
     * Method to save a record.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable if different from the primary key (sometimes required to avoid router collisions).
     *
     * @return  boolean  True if successful, false otherwise.
     *
     * @since   6.0.0
     */
    /** Render a preview of the email template with sample order data. */
    public function preview(): void
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $body      = $this->input->post->getRaw('body', '');
        $subject   = $this->input->post->getString('subject', '');
        $customCss = $this->input->post->getRaw('custom_css', '');

        // Restore data-j2c-src placeholders back to src (editor injects these to prevent 404s)
        // GrapesJS may reorder attributes, so data-j2c-src may not be adjacent to src
        $body = preg_replace_callback(
            '/<img([^>]*?)data-j2c-src="(\[[A-Z_]+\])"([^>]*?)>/i',
            static function (array $m): string {
                $attrs = preg_replace('/\ssrc="[^"]*"/i', '', $m[1] . $m[3]);
                return '<img' . $attrs . ' src="' . $m[2] . '">';
            },
            $body
        );

        $emailHelper = EmailHelper::getInstance();
        $order       = $emailHelper->getSampleOrderData();

        $processedBody    = $emailHelper->processTags($body, $order);
        $processedSubject = $emailHelper->processTags($subject, $order);

        // Build full HTML with custom CSS
        $headStyles = '';
        $customCss  = trim($customCss);
        if ($customCss !== '') {
            $headStyles = '<style type="text/css">' . $customCss . '</style>';
        }

        $html = '<!DOCTYPE html><html><head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>' . htmlspecialchars($processedSubject) . '</title>'
            . $headStyles
            . '</head><body>' . $processedBody . '</body></html>';

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        $this->app->close();
    }

    /** Send a test email using sample order data. */
    public function sendTest(): void
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $body      = $this->input->post->getRaw('body', '');
        $subject   = $this->input->post->getString('subject', '');
        $customCss = $this->input->post->getRaw('custom_css', '');
        $recipient = $this->input->post->getString('recipient', '');

        $json = ['success' => false, 'message' => ''];

        if (empty($recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $json['message'] = Text::_('COM_J2COMMERCE_EMAILTEMPLATE_INVALID_EMAIL');
            header('Content-Type: application/json');
            echo json_encode($json);
            $this->app->close();
            return;
        }

        try {
            $emailHelper = EmailHelper::getInstance();
            $order       = $emailHelper->getSampleOrderData();

            $processedBody    = $emailHelper->processTags($body, $order);
            $processedSubject = $emailHelper->processTags($subject, $order);

            // Build full HTML
            $headStyles = '';
            $customCss  = trim($customCss);
            if ($customCss !== '') {
                $headStyles = '<style type="text/css">' . $customCss . '</style>';
            }

            $htmlBody = '<html><head>'
                . '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
                . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
                . $headStyles
                . '</head><body>' . $processedBody . '</body></html>';

            $config = $this->app->getConfig();
            $mailer = \Joomla\CMS\Factory::getMailer();
            $mailer->setSender([$config->get('mailfrom'), $config->get('fromname')]);
            $mailer->addRecipient($recipient);
            $mailer->setSubject('[TEST] ' . $processedSubject);
            $mailer->isHTML(true);
            $mailer->CharSet  = 'utf-8';
            $mailer->Encoding = 'base64';
            $mailer->setBody($htmlBody);

            if ($mailer->Send()) {
                $json['success'] = true;
                $json['message'] = Text::sprintf('COM_J2COMMERCE_EMAILTEMPLATE_TEST_SENT', $recipient);
            } else {
                $json['message'] = Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TEST_FAILED');
            }
        } catch (\Throwable $e) {
            $json['message'] = $e->getMessage();
        }

        header('Content-Type: application/json');
        echo json_encode($json);
        $this->app->close();
    }

    /** Load a pre-made template file and return its HTML content. */
    public function loadTemplate(): void
    {
        Session::checkToken('get') or Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $type   = $this->input->getCmd('type', '');
        $design = $this->input->getCmd('design', '');

        $json = ['success' => false, 'body' => ''];

        if (!$type || !$design || str_contains($type, '..') || str_contains($design, '..')) {
            $json['message'] = Text::_('COM_J2COMMERCE_EMAILTEMPLATE_INVALID_SELECTION');
            header('Content-Type: application/json');
            echo json_encode($json);
            $this->app->close();
            return;
        }

        // Allow plugins to serve their own template HTML
        PluginHelper::importPlugin('j2commerce');
        $templateResult = ['body' => null];
        $event          = new GenericEvent('onJ2CommerceGetEmailTemplates', [
            'type'   => $type,
            'design' => $design,
            'result' => &$templateResult,
        ]);
        $this->app->getDispatcher()->dispatch('onJ2CommerceGetEmailTemplates', $event);

        if (!empty($templateResult['body'])) {
            $json['success'] = true;
            $json['body']    = $templateResult['body'];
            header('Content-Type: application/json');
            echo json_encode($json);
            $this->app->close();
            return;
        }

        $filePath = JPATH_ADMINISTRATOR . '/components/com_j2commerce/layouts/templates/email/' . $type . '/' . $design . '.html';

        if (!file_exists($filePath)) {
            $json['message'] = 'Template file not found.';
            header('Content-Type: application/json');
            echo json_encode($json);
            $this->app->close();
            return;
        }

        $json['success'] = true;
        $json['body']    = file_get_contents($filePath);

        header('Content-Type: application/json');
        echo json_encode($json);
        $this->app->close();
    }

    /** Return shortcode HTML and structured data for the given email type. */
    public function getShortcodes(): void
    {
        Session::checkToken('get') or jexit(Text::_('JINVALID_TOKEN'));

        $emailType = $this->input->getCmd('email_type', 'transactional');

        $coreTags = MessageHelper::getMessageTags();

        $typeTags = [];
        if ($emailType !== 'transactional') {
            PluginHelper::importPlugin('j2commerce');
            $registry = self::getEmailTypeRegistry();
            $typeTags = $registry->getGroupedTagsForType($emailType);

            // Load plugin language files so PLG_ labels resolve
            $lang = $this->app->getLanguage();
            foreach ($typeTags as $tags) {
                foreach ($tags as $tagConfig) {
                    $label = $tagConfig['label'] ?? '';
                    if (str_starts_with($label, 'PLG_') && $label === Text::_($label)) {
                        $parts = explode('_', strtolower($label));
                        if (\count($parts) >= 4) {
                            $ext = $parts[0] . '_' . $parts[1] . '_' . $parts[2] . '_' . $parts[3];
                            $lang->load($ext, JPATH_ADMINISTRATOR)
                                || $lang->load($ext, JPATH_PLUGINS . '/' . $parts[1] . '/' . $parts[2] . '_' . $parts[3]);
                        }
                    }
                }
            }

            // Resolve language string labels to translated text
            foreach ($typeTags as $group => $tags) {
                foreach ($tags as $tagName => $tagConfig) {
                    $typeTags[$group][$tagName] = Text::_($tagConfig['label'] ?? $tagName);
                }
            }
        }

        // Build structured shortcode data for GrapesJS (flat tag => label format)
        $shortcodeData = [];
        foreach ($coreTags as $group => $tags) {
            foreach ($tags as $tag => $label) {
                $shortcodeData[$tag] = $label;
            }
        }
        foreach ($typeTags as $group => $tags) {
            foreach ($tags as $tag => $label) {
                $shortcodeData["[$tag]"] = $label;
            }
        }

        // Render HTML for the sidebar
        ob_start();
        include JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/emailtemplate/edit_shortcodes_list.php';
        $html = ob_get_clean();

        $json = [
            'success'    => true,
            'html'       => $html,
            'shortcodes' => $shortcodeData,
            'typeTags'   => $typeTags,
        ];

        header('Content-Type: application/json');
        echo json_encode($json);
        $this->app->close();
    }

    private static function getEmailTypeRegistry(): \J2Commerce\Component\J2commerce\Administrator\Service\EmailTypeRegistry
    {
        static $registry = null;

        if ($registry !== null) {
            return $registry;
        }

        $db       = \Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class);
        $registry = new \J2Commerce\Component\J2commerce\Administrator\Service\EmailTypeRegistry($db);

        return $registry;
    }

    public function save($key = null, $urlVar = null)
    {
        // Check for request forgeries.
        $this->checkToken();

        $app     = $this->app;
        $lang    = $app->getLanguage();
        $model   = $this->getModel();
        $table   = $model->getTable();
        $data    = $this->input->post->get('jform', [], 'array');
        $checkin = property_exists($table, 'checked_out');
        $context = "$this->option.edit.$this->context";
        $task    = $this->getTask();

        // Determine the name of the primary key for the data.
        if (empty($key)) {
            $key = $table->getKeyName();
        }

        // To avoid data collisions the urlVar may be different from the primary key.
        if (empty($urlVar)) {
            $urlVar = $key;
        }

        $recordId = $this->input->getInt($urlVar);

        // Hold the edit ID if not already held (supports direct URL access)
        if (!$this->checkEditId($context, $recordId) && $recordId > 0) {
            $this->holdEditId($context, $recordId);
        }

        if (!$this->checkEditId($context, $recordId)) {
            // Somehow the person just went to the form and tried to save it. We don't allow that.
            if (!\count($app->getMessageQueue())) {
                $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_UNHELD_ID'), 'error');
            }

            $this->setRedirect(
                Route::_('index.php?option=' . $this->option . '&view=' . $this->view_list . $this->getRedirectToListAppend(), false)
            );

            return false;
        }

        // Populate the row id from the session.
        $data[$key] = $recordId;

        // The save2copy task needs to be handled slightly differently.
        if ($task === 'save2copy') {
            // Check-in the original row.
            if ($checkin && $model->checkin($data[$key]) === false) {
                // Check-in failed. Go back to the item and display a notice.
                $this->setMessage(Text::sprintf('JLIB_APPLICATION_ERROR_CHECKIN_FAILED', $model->getError()), 'error');

                $this->setRedirect(
                    Route::_(
                        'index.php?option=' . $this->option . '&view=' . $this->view_item
                        . $this->getRedirectToItemAppend($recordId, $urlVar),
                        false
                    )
                );

                return false;
            }

            // Reset the ID, the multilingual associations and then treat the request as for Apply.
            $data[$key]           = 0;
            $data['associations'] = [];
            $task                 = 'apply';
        }

        // Access check.
        if (!$this->allowSave($data, $key)) {
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_SAVE_NOT_PERMITTED'), 'error');

            $this->setRedirect(
                Route::_('index.php?option=' . $this->option . '&view=' . $this->view_list . $this->getRedirectToListAppend(), false)
            );

            return false;
        }

        // Validate the posted data.
        // Sometimes the form needs some posted data, such as for plugins and modules.
        $form = $model->getForm($data, false);

        if (!$form) {
            $app->enqueueMessage($model->getError(), 'error');

            return false;
        }

        // Test whether the data is valid.
        $validData = $model->validate($form, $data);

        // Check for validation errors.
        if ($validData === false) {
            // Get the validation messages.
            $errors = $model->getErrors();

            // Push up to three validation messages out to the user.
            for ($i = 0, $n = \count($errors); $i < $n && $i < 3; $i++) {
                if ($errors[$i] instanceof \Exception) {
                    $app->enqueueMessage($errors[$i]->getMessage(), 'warning');
                } else {
                    $app->enqueueMessage($errors[$i], 'warning');
                }
            }

            // Save the data in the session.
            $app->setUserState($context . '.data', $data);

            // Redirect back to the edit screen.
            $this->setRedirect(
                Route::_(
                    'index.php?option=' . $this->option . '&view=' . $this->view_item
                    . $this->getRedirectToItemAppend($recordId, $urlVar),
                    false
                )
            );

            return false;
        }

        // Attempt to save the data.
        if (!$model->save($validData)) {
            // Save the data in the session.
            $app->setUserState($context . '.data', $validData);

            // Redirect back to the edit screen.
            $this->setMessage(Text::sprintf('JLIB_APPLICATION_ERROR_SAVE_FAILED', $model->getError()), 'error');

            $this->setRedirect(
                Route::_(
                    'index.php?option=' . $this->option . '&view=' . $this->view_item
                    . $this->getRedirectToItemAppend($recordId, $urlVar),
                    false
                )
            );

            return false;
        }

        // Save succeeded, so check-in the record.
        if ($checkin && $model->checkin($validData[$key]) === false) {
            // Save the data in the session.
            $app->setUserState($context . '.data', $validData);

            // Check-in failed, so go back to the record and display a notice.
            $this->setMessage(Text::sprintf('JLIB_APPLICATION_ERROR_CHECKIN_FAILED', $model->getError()), 'error');

            $this->setRedirect(
                Route::_(
                    'index.php?option=' . $this->option . '&view=' . $this->view_item
                    . $this->getRedirectToItemAppend($recordId, $urlVar),
                    false
                )
            );

            return false;
        }

        $this->setMessage(
            Text::_(
                ($lang->hasKey($this->text_prefix . ($recordId === 0 && $app->isClient('site') ? '_SUBMIT' : '') . '_SAVE_SUCCESS')
                    ? $this->text_prefix
                    : 'JLIB_APPLICATION') . ($recordId === 0 && $app->isClient('site') ? '_SUBMIT' : '') . '_SAVE_SUCCESS'
            )
        );

        // Clear the record id and data from the session.
        $this->releaseEditId($context, $recordId);
        $app->setUserState($context . '.data', null);

        // Invoke the postSave method to allow for the child class to access the model.
        $this->postSaveHook($model, $validData);

        return true;
    }
}
