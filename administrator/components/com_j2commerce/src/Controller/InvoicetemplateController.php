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
use J2Commerce\Component\J2commerce\Administrator\Helper\PackingSlipHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Versioning\VersionableControllerTrait;

/**
 * Controller for a single invoice template
 *
 * @since  6.0.0
 */
class InvoicetemplateController extends FormController
{
    use VersionableControllerTrait;

    protected $text_prefix = 'COM_J2COMMERCE';

    public function batch($model = null)
    {
        \Joomla\CMS\Session\Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        // Set the model
        $model = $this->getModel('Invoicetemplate', '', []);

        // Preset the redirect
        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=invoicetemplates', false));

        return parent::batch($model);
    }

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
                        'index.php?option=' . $this->option . '&view=' . $this->view_item . $this->getRedirectToItemAppend($recordId, 'j2commerce_invoicetemplate_id'),
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

    protected function allowAdd($data = [])
    {
        return parent::allowAdd($data);
    }

    protected function allowEdit($data = [], $key = 'j2commerce_invoicetemplate_id')
    {
        $recordId = (int) isset($data[$key]) ? $data[$key] : 0;
        $user     = $this->app->getIdentity();

        // Zero record (id:0), return component edit permission by calling parent
        if (!$recordId) {
            return parent::allowEdit($data, $key);
        }

        // Check edit on the record asset (explicit or inherited)
        if ($user->authorise('core.edit', 'com_j2commerce.invoicetemplate.' . $recordId)) {
            return true;
        }

        // Check edit own on the record asset (explicit or inherited)
        if ($user->authorise('core.edit.own', 'com_j2commerce.invoicetemplate.' . $recordId)) {
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

    protected function getRedirectToItemAppend($recordId = null, $urlVar = 'j2commerce_invoicetemplate_id')
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

    /** Render a preview of the invoice/packing slip template with sample order data. */
    public function preview(): void
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $body      = $this->input->post->getRaw('body', '');
        $customCss = trim($this->input->post->getRaw('custom_css', ''));

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

        $emailHelper   = EmailHelper::getInstance();
        $order         = $emailHelper->getSampleOrderData();
        $processedBody = $emailHelper->processTags($body, $order);
        $processedBody = PackingSlipHelper::getInstance()->stripPricingFromItemsTable($processedBody);

        // Extract <style> blocks from template body and move to <head>
        $extractedStyles = '';
        $processedBody   = preg_replace_callback(
            '/<style\b[^>]*>(.*?)<\/style>/si',
            function (array $m) use (&$extractedStyles): string {
                $extractedStyles .= $m[1] . "\n";
                return '';
            },
            $processedBody
        );

        $styles = '<style>'
            . 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;margin:0;padding:20px;color:#333;background:#f8fafc;-webkit-print-color-adjust:exact;print-color-adjust:exact;}'
            . 'table{border-collapse:collapse;border-spacing:0;}img{max-width:100%;height:auto;border:0;}'
            . $extractedStyles
            . ($customCss !== '' ? $customCss : '')
            . '@media print{body{margin:0;padding:0;background:#fff;}*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;color-adjust:exact!important;}}'
            . '</style>';

        $html = '<!DOCTYPE html><html><head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>Print Template Preview</title>'
            . $styles
            . '</head><body>' . $processedBody . '</body></html>';

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        $this->app->close();
    }

    /** Return available template presets for a given invoice type as JSON. */
    public function getTemplatePresets(): void
    {
        Session::checkToken('get') or Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $type = $this->input->getCmd('type', '');
        $json = ['success' => false, 'presets' => []];

        if (!$type || !preg_match('/^[a-z][a-z0-9_]{1,49}$/', $type)) {
            $json['message'] = Text::_('COM_J2COMMERCE_EMAILTEMPLATE_INVALID_SELECTION');
            header('Content-Type: application/json');
            echo json_encode($json);
            $this->app->close();
            return;
        }

        $basePath = JPATH_ADMINISTRATOR . '/components/com_j2commerce/layouts/templates/' . $type;
        $presets  = [];

        if (is_dir($basePath)) {
            $files = \Joomla\Filesystem\Folder::files($basePath, '\.html$');
            sort($files);

            foreach ($files as $file) {
                $design    = pathinfo($file, PATHINFO_FILENAME);
                $presets[] = [
                    'design' => $design,
                    'label'  => ucfirst(str_replace(['-', '_'], ' ', $design)),
                    'type'   => $type,
                ];
            }
        }

        $json['success']  = true;
        $json['presets']  = $presets;

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

        $filePath = JPATH_ADMINISTRATOR . '/components/com_j2commerce/layouts/templates/' . $type . '/' . $design . '.html';

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
