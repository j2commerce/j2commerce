<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\Database\ParameterType;

/**
 * Tax Profile item controller class.
 *
 * Handles single-item operations: edit, save, apply, cancel.
 * Also provides AJAX endpoints for tax rate/geozone loading and rule removal.
 *
 * @since  6.0.7
 */
class TaxprofileController extends FormController
{
    /**
     * The URL option for the component.
     *
     * @var    string
     * @since  6.0.7
     */
    protected $option = 'com_j2commerce';

    /**
     * The URL view item variable.
     *
     * @var    string
     * @since  6.0.7
     */
    protected $view_item = 'taxprofile';

    /**
     * The URL view list variable.
     *
     * @var    string
     * @since  6.0.7
     */
    protected $view_list = 'taxprofiles';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.7
     */
    protected $text_prefix = 'COM_J2COMMERCE_TAXPROFILE';

    /**
     * The primary key name for the table.
     * Required for J2Commerce tables which use j2commerce_*_id format.
     *
     * @var    string
     * @since  6.0.7
     */
    protected $key = 'j2commerce_taxprofile_id';

    public function edit($key = null, $urlVar = 'id')
    {
        return parent::edit($key, $urlVar);
    }

    public function cancel($key = 'id')
    {
        return parent::cancel($key);
    }

    /**
     * Method to save a record.
     *
     * Overridden to capture taxrules from raw input since they're outside jform namespace.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable for the id.
     *
     * @return  boolean  True if successful, false otherwise.
     *
     * @since   6.0.7
     */
    public function save($key = null, $urlVar = 'id')
    {
        // Check for request forgeries
        $this->checkToken();

        $app     = $this->app;
        $model   = $this->getModel();
        $table   = $model->getTable();
        $data    = $this->input->post->get('jform', [], 'array');
        $context = "$this->option.edit.$this->context";

        // Capture taxrules from raw input (outside jform namespace)
        $taxrules = $this->input->post->get('taxrules', [], 'array');
        if (!empty($taxrules)) {
            $data['taxrules'] = $taxrules;
        }

        // Determine the name of the primary key for the data
        if (empty($key)) {
            $key = $table->getKeyName();
        }

        // To avoid data collisions the urlVar may be different from the primary key
        if (empty($urlVar)) {
            $urlVar = $key;
        }

        // Get the record id from URL or form data
        $recordId = $this->input->getInt($urlVar, 0);

        // Populate the row id from the session if it exists
        if ($data[$key] ?? 0) {
            $recordId = $data[$key];
        }

        // Access check
        if (!$this->allowSave($data, $key)) {
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_SAVE_NOT_PERMITTED'), 'error');
            $this->setRedirect(
                \Joomla\CMS\Router\Route::_(
                    'index.php?option=' . $this->option . '&view=' . $this->view_list
                    . $this->getRedirectToListAppend(),
                    false
                )
            );

            return false;
        }

        // Validate the posted data
        $form = $model->getForm($data, false);

        if (!$form) {
            $app->enqueueMessage($model->getError(), 'error');
            return false;
        }

        // Test whether the data is valid
        $validData = $model->validate($form, $data);

        // Check for validation errors
        if ($validData === false) {
            $errors = $model->getErrors();

            foreach ($errors as $error) {
                if ($error instanceof \Exception) {
                    $app->enqueueMessage($error->getMessage(), 'warning');
                } else {
                    $app->enqueueMessage($error, 'warning');
                }
            }

            // Save the data in the session
            $app->setUserState($context . '.data', $data);

            // Redirect back to the edit screen
            $this->setRedirect(
                \Joomla\CMS\Router\Route::_(
                    'index.php?option=' . $this->option . '&view=' . $this->view_item
                    . $this->getRedirectToItemAppend($recordId, $urlVar),
                    false
                )
            );

            return false;
        }

        // Add taxrules back to validated data (they're not in the form so not validated)
        if (!empty($taxrules)) {
            $validData['taxrules'] = $taxrules;
        }

        // Attempt to save the data
        if (!$model->save($validData)) {
            // Save the data in the session
            $app->setUserState($context . '.data', $validData);

            // Redirect back to the edit screen
            $this->setMessage(Text::sprintf('JLIB_APPLICATION_ERROR_SAVE_FAILED', $model->getError()), 'error');
            $this->setRedirect(
                \Joomla\CMS\Router\Route::_(
                    'index.php?option=' . $this->option . '&view=' . $this->view_item
                    . $this->getRedirectToItemAppend($recordId, $urlVar),
                    false
                )
            );

            return false;
        }

        // Clear the session data
        $this->releaseEditId($context, $recordId);
        $app->setUserState($context . '.data', null);

        $this->setMessage(
            Text::_(
                ($this->input->get('task') === 'apply' ? 'JLIB_APPLICATION_SAVE_SUCCESS' : 'JLIB_APPLICATION_SAVE_SUCCESS')
            )
        );

        // Get the new record id
        $recordId = $model->getState($model->getName() . '.id');
        $this->holdEditId($context, $recordId);

        // Redirect based on the task
        switch ($this->getTask()) {
            case 'apply':
                // Redirect back to the edit screen
                $this->setRedirect(
                    \Joomla\CMS\Router\Route::_(
                        'index.php?option=' . $this->option . '&view=' . $this->view_item
                        . $this->getRedirectToItemAppend($recordId, $urlVar),
                        false
                    )
                );
                break;

            case 'save2new':
                // Clear the record id and data from the session
                $this->releaseEditId($context, $recordId);
                $app->setUserState($context . '.data', null);

                // Redirect to a new record
                $this->setRedirect(
                    \Joomla\CMS\Router\Route::_(
                        'index.php?option=' . $this->option . '&view=' . $this->view_item
                        . $this->getRedirectToItemAppend(null, $urlVar),
                        false
                    )
                );
                break;

            default:
                // Redirect to the list screen
                $this->setRedirect(
                    \Joomla\CMS\Router\Route::_(
                        'index.php?option=' . $this->option . '&view=' . $this->view_list
                        . $this->getRedirectToListAppend(),
                        false
                    )
                );
                break;
        }

        return true;
    }

    /**
     * AJAX: Get tax rates and geozones for dropdowns.
     *
     * Returns JSON with taxrates and geozones arrays.
     * Uses Joomla's JsonResponse correctly - pass data directly, not wrapped in success/data.
     *
     * @return  void
     *
     * @since   6.0.7
     */
    public function getTaxData(): void
    {
        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');

        try {
            // Get enabled tax rates
            $query = $db->getQuery(true);
            $query->select($db->quoteName(['j2commerce_taxrate_id', 'taxrate_name', 'tax_percent', 'geozone_id']))
                ->from($db->quoteName('#__j2commerce_taxrates'))
                ->where($db->quoteName('enabled') . ' = 1')
                ->order($db->quoteName('taxrate_name') . ' ASC');
            $db->setQuery($query);
            $taxrates = $db->loadObjectList();

            // Get enabled geozones
            $query = $db->getQuery(true);
            $query->select($db->quoteName(['j2commerce_geozone_id', 'geozone_name']))
                ->from($db->quoteName('#__j2commerce_geozones'))
                ->where($db->quoteName('enabled') . ' = 1')
                ->order($db->quoteName('geozone_name') . ' ASC');
            $db->setQuery($query);
            $geozones = $db->loadObjectList();

            // JsonResponse signature: ($response, $message = '', $error = false)
            // Pass data directly - JsonResponse wraps it in {success: true, data: ...}
            echo new JsonResponse([
                'taxrates' => $taxrates,
                'geozones' => $geozones,
            ]);

        } catch (\Exception $e) {
            // On error: pass null data, error message, and error=true
            echo new JsonResponse(null, $e->getMessage(), true);
        }

        $app->close();
    }

    /**
     * AJAX: Remove a tax rule.
     *
     * Uses Joomla's JsonResponse correctly - pass data directly, not wrapped in success/data.
     *
     * @return  void
     *
     * @since   6.0.7
     */
    public function removeRule(): void
    {
        $app = Factory::getApplication();

        // Check CSRF token
        if (!Session::checkToken('get') && !Session::checkToken('post')) {
            echo new JsonResponse(null, Text::_('JINVALID_TOKEN'), true);
            $app->close();
            return;
        }

        $ruleId = $app->getInput()->getInt('rule_id', 0);

        if ($ruleId > 0) {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);

            $query->delete($db->quoteName('#__j2commerce_taxrules'))
                ->where($db->quoteName('j2commerce_taxrule_id') . ' = :rule_id')
                ->bind(':rule_id', $ruleId, ParameterType::INTEGER);

            try {
                $db->setQuery($query);
                $db->execute();

                // JsonResponse: ($data, $message, $error)
                echo new JsonResponse(
                    ['success' => true, 'message' => Text::_('COM_J2COMMERCE_TAXRULE_DELETED')]
                );
            } catch (\Exception $e) {
                echo new JsonResponse(null, $e->getMessage(), true);
            }
        } else {
            // Rule not yet saved to DB, just return success for UI removal
            echo new JsonResponse(
                ['success' => true, 'message' => Text::_('COM_J2COMMERCE_TAXRULE_REMOVED')]
            );
        }

        $app->close();
    }
}
