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

use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

/**
 * Geozone item controller class.
 *
 * Handles single-item operations: edit, save, apply, cancel.
 * Also provides AJAX endpoints for zone loading and rule removal.
 *
 * @since  6.0.3
 */
class GeozoneController extends FormController
{
    /**
     * The URL option for the component.
     *
     * @var    string
     * @since  6.0.3
     */
    protected $option = 'com_j2commerce';

    /**
     * The URL view item variable.
     *
     * @var    string
     * @since  6.0.3
     */
    protected $view_item = 'geozone';

    /**
     * The URL view list variable.
     *
     * @var    string
     * @since  6.0.3
     */
    protected $view_list = 'geozones';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.3
     */
    protected $text_prefix = 'COM_J2COMMERCE_GEOZONE';

    /**
     * The primary key name for the table.
     * Required for J2Commerce tables which use j2commerce_*_id format.
     *
     * @var    string
     * @since  6.0.3
     */
    protected $key = 'j2commerce_geozone_id';

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
     * Overridden to capture geozonerules from raw input since they're outside jform namespace.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable for the id.
     *
     * @return  boolean  True if successful, false otherwise.
     *
     * @since   6.0.3
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

        // Capture geozonerules from raw input (outside jform namespace)
        $geozonerules = $this->input->post->get('geozonerules', [], 'array');

        // Saving replaces the whole rule set, so a POST cut short by max_input_vars would delete
        // the rows that never arrived. Refuse the save instead and say what to raise.
        if ($this->rulePostWasTruncated($geozonerules)) {
            $app->setUserState($context . '.data', $data);
            $this->setRedirect(
                Route::_(
                    'index.php?option=' . $this->option . '&view=' . $this->view_item
                    . $this->getRedirectToItemAppend((int) ($data['j2commerce_geozone_id'] ?? 0) ?: null, 'id'),
                    false
                ),
                $this->truncatedRulesMessage(),
                'error'
            );

            return false;
        }

        if (!empty($geozonerules)) {
            $data['geozonerules'] = $geozonerules;
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
            Log::add('geozone.getForm failed: ' . $model->getError(), Log::ERROR, 'com_j2commerce');
            $app->enqueueMessage(Text::_('COM_J2COMMERCE_ERR_GENERIC'), 'error');
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

        // Add geozonerules back to validated data (they're not in the form so not validated)
        if (!empty($geozonerules)) {
            $validData['geozonerules'] = $geozonerules;
        }

        // Attempt to save the data
        if (!$model->save($validData)) {
            // Save the data in the session
            $app->setUserState($context . '.data', $validData);

            // Redirect back to the edit screen
            Log::add('geozone.save failed: ' . $model->getError(), Log::ERROR, 'com_j2commerce');
            $this->setMessage(Text::sprintf('JLIB_APPLICATION_ERROR_SAVE_FAILED', Text::_('COM_J2COMMERCE_ERR_GENERIC')), 'error');
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
     * Append a rule for every enabled country that the geo zone does not already list.
     *
     * Saves through the model rather than inserting directly: saveGeozoneRules() replaces the
     * whole set on every save, so the rules already on screen have to travel with the new ones
     * or they would be dropped. A country already present keeps the zone it was given.
     *
     * @since   6.5.0
     */
    public function addAllCountries(): void
    {
        $this->checkToken();

        $app      = $this->app;
        $model    = $this->getModel();
        $data     = $this->input->post->get('jform', [], 'array');
        $recordId = (int) ($data['j2commerce_geozone_id'] ?? 0);
        $context  = "$this->option.edit.$this->context";

        $redirect = Route::_(
            'index.php?option=' . $this->option . '&view=' . $this->view_item
            . $this->getRedirectToItemAppend($recordId ?: null, 'id'),
            false
        );

        if (!$this->allowSave($data, $this->key)) {
            $this->setRedirect($redirect, Text::_('JLIB_APPLICATION_ERROR_SAVE_NOT_PERMITTED'), 'error');

            return;
        }

        // The name is what makes the record saveable, and the button has to save to renumber the
        // rows it adds. The form guard catches this first; this is the backstop.
        if (trim((string) ($data['geozone_name'] ?? '')) === '') {
            $app->setUserState($context . '.data', $data);
            $this->setRedirect($redirect, Text::_('COM_J2COMMERCE_GEOZONE_ERR_NAME_REQUIRED_FOR_ADD_ALL'), 'warning');

            return;
        }

        $form = $model->getForm($data, false);

        if (!$form) {
            Log::add('geozone.addallcountries getForm failed: ' . $model->getError(), Log::ERROR, 'com_j2commerce');
            $this->setRedirect($redirect, Text::_('COM_J2COMMERCE_ERR_GENERIC'), 'error');

            return;
        }

        $validData = $model->validate($form, $data);

        if ($validData === false) {
            foreach ($model->getErrors() as $error) {
                $app->enqueueMessage($error instanceof \Exception ? $error->getMessage() : $error, 'warning');
            }

            $app->setUserState($context . '.data', $data);
            $this->setRedirect($redirect);

            return;
        }

        $submitted = $this->input->post->get('geozonerules', [], 'array');

        if ($this->rulePostWasTruncated($submitted)) {
            $this->setRedirect($redirect, $this->truncatedRulesMessage(), 'error');

            return;
        }

        $rules                     = $this->mergeAllCountriesIntoRules($submitted, $added);
        $validData['geozonerules'] = $rules;

        if (!$model->save($validData)) {
            $app->setUserState($context . '.data', $validData);
            Log::add('geozone.addallcountries save failed: ' . $model->getError(), Log::ERROR, 'com_j2commerce');
            $this->setRedirect($redirect, Text::sprintf('JLIB_APPLICATION_ERROR_SAVE_FAILED', Text::_('COM_J2COMMERCE_ERR_GENERIC')), 'error');

            return;
        }

        $recordId = (int) $model->getState($model->getName() . '.id');
        $this->holdEditId($context, $recordId);
        $app->setUserState($context . '.data', null);

        $this->warnIfNearInputVarLimit(\count($rules));

        $message = $added > 0
            ? Text::sprintf('COM_J2COMMERCE_GEOZONE_N_COUNTRIES_ADDED', $added)
            : Text::_('COM_J2COMMERCE_GEOZONE_ALL_COUNTRIES_PRESENT');

        $this->setRedirect(
            Route::_(
                'index.php?option=' . $this->option . '&view=' . $this->view_item
                . $this->getRedirectToItemAppend($recordId, 'id'),
                false
            ),
            $message,
            $added > 0 ? 'message' : 'info'
        );
    }

    /**
     * True when PHP dropped rule rows from the POST because it hit max_input_vars.
     *
     * Every save replaces the whole rule set, so a short POST does not merely fail to add rows —
     * it deletes the ones that never arrived. The form renders geozonerules_rendered after the
     * last rule row and PHP truncates in document order, so a missing marker means the rows above
     * it were cut; a marker higher than what arrived means the array itself was cut.
     *
     * @since   6.5.0
     */
    private function rulePostWasTruncated(array $submitted): bool
    {
        $rendered = $this->input->post->get('geozonerules_rendered', null, 'raw');

        // Absent on a form that predates the marker, and on a page with no rules to lose.
        if ($rendered === null || $rendered === '') {
            return $submitted !== [] && \count($_POST, COUNT_RECURSIVE) >= $this->maxInputVars();
        }

        return \count($submitted) < (int) $rendered;
    }

    /** Names the host limit and the value it needs, since the store owner cannot infer either. */
    private function truncatedRulesMessage(): string
    {
        $limit    = $this->maxInputVars();
        $rendered = (int) $this->input->post->get('geozonerules_rendered', 0, 'raw');

        $recommended = max(2000, (int) (ceil((($rendered * 3) + 100) / 500) * 500));

        return Text::sprintf(
            'COM_J2COMMERCE_GEOZONE_ERR_RULES_TRUNCATED',
            $limit === PHP_INT_MAX ? 0 : $limit,
            $recommended
        );
    }

    /** Rule rows the host can carry in one POST, from max_input_vars minus the rest of the form. */
    private function maxInputVars(): int
    {
        $limit = (int) \ini_get('max_input_vars');

        // 0 or unreadable means no ceiling is being enforced.
        return $limit > 0 ? $limit : PHP_INT_MAX;
    }

    /**
     * Warn when the rule set is close enough to max_input_vars that the next ordinary save
     * would be truncated, and name the value the host should be raised to.
     *
     * @since   6.5.0
     */
    private function warnIfNearInputVarLimit(int $ruleCount): void
    {
        $limit = $this->maxInputVars();

        if ($limit === PHP_INT_MAX) {
            return;
        }

        // Three inputs per rule, plus the rest of the form and Joomla's own fields.
        $needed = ($ruleCount * 3) + 100;

        if ($needed <= $limit * 0.9) {
            return;
        }

        // Round up to the next 500 so the advice survives the zone growing a little.
        $recommended = max(2000, (int) (ceil($needed / 500) * 500));

        $this->app->enqueueMessage(
            Text::sprintf('COM_J2COMMERCE_GEOZONE_WARN_MAX_INPUT_VARS', $ruleCount, $limit, $recommended),
            'warning'
        );
    }

    /**
     * Merge the submitted rules with one row per enabled country, keeping the zone already chosen.
     *
     * @param   array     $submitted  Rules as posted from the edit form.
     * @param   int|null  $added      Set to the number of countries appended.
     *
     * @since   6.5.0
     */
    private function mergeAllCountriesIntoRules(array $submitted, ?int &$added): array
    {
        $merged        = [];
        $seenPairs     = [];
        $seenCountries = [];

        foreach ($submitted as $rule) {
            $countryId = (int) ($rule['country_id'] ?? 0);
            $zoneId    = (int) ($rule['zone_id'] ?? 0);

            // An empty row is the blank the Add Country/Zone button leaves behind; drop it rather
            // than carrying it into a set that is about to list every country anyway.
            if ($countryId === 0) {
                continue;
            }

            // Keyed on the pair, not the country: one country may legitimately carry a row per
            // zone, and the model replaces the whole set on save, so collapsing to one row here
            // would delete the others outright.
            $pairKey = $countryId . ':' . $zoneId;

            if (isset($seenPairs[$pairKey])) {
                continue;
            }

            $seenPairs[$pairKey]         = true;
            $seenCountries[$countryId]   = true;
            $merged[]                    = [
                'j2commerce_geozonerule_id' => (int) ($rule['j2commerce_geozonerule_id'] ?? 0),
                'country_id'                => $countryId,
                'zone_id'                   => $zoneId,
            ];
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_country_id'))
            ->from($db->quoteName('#__j2commerce_countries'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->order($db->quoteName('country_name') . ' ASC');

        $db->setQuery($query);
        $added = 0;

        // A country holding any rule already counts as listed, whichever zones those rules name.
        foreach ($db->loadColumn() as $countryId) {
            $countryId = (int) $countryId;

            if (isset($seenCountries[$countryId])) {
                continue;
            }

            $seenCountries[$countryId] = true;
            $merged[]                  = [
                'j2commerce_geozonerule_id' => 0,
                'country_id'                => $countryId,
                'zone_id'                   => 0,
            ];
            $added++;
        }

        return $merged;
    }

    /**
     * Stage the JSON headers for an AJAX endpoint.
     *
     * These endpoints end in close(), which is exit() — respond() never runs, so nothing
     * sets a content type and the body would otherwise ship as text/html. Each exit
     * flushes with sendHeaders() before echoing.
     *
     * @param   CMSWebApplicationInterface  $app  The application.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    private function prepareJsonResponse(CMSWebApplicationInterface $app): void
    {
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $app->setHeader('X-Content-Type-Options', 'nosniff', true);
    }

    /**
     * AJAX: Remove a geozone rule.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function removeRule(): void
    {
        $app  = Factory::getApplication();
        $user = $app->getIdentity();

        $this->prepareJsonResponse($app);

        // Deleting a saved rule edits an existing record, so core.edit is the gate.
        // A CSRF token proves the request came from our form, not that the caller may delete.
        if ($user->guest || !$user->authorise('core.edit', 'com_j2commerce')) {
            $app->setHeader('status', 403, true);
            $app->sendHeaders();
            echo new JsonResponse(null, Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), true);
            $app->close();
        }

        if (!Session::checkToken('get') && !Session::checkToken('post')) {
            $app->setHeader('status', 403, true);
            $app->sendHeaders();
            echo new JsonResponse(null, Text::_('JINVALID_TOKEN'), true);
            $app->close();
        }

        $ruleId = $app->getInput()->getInt('rule_id', 0);

        // A row added in the browser but never saved has no PK yet — nothing to delete.
        if ($ruleId <= 0) {
            $app->sendHeaders();
            echo new JsonResponse(['success' => true, 'message' => Text::_('COM_J2COMMERCE_GEOZONE_RULE_REMOVED')]);
            $app->close();
        }

        try {
            $this->getModel('Geozone')->deleteRule($ruleId);

            $response = ['success' => true, 'message' => Text::_('COM_J2COMMERCE_GEOZONE_RULE_DELETED')];
        } catch (\Throwable $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');

            $app->setHeader('status', 500, true);
            $app->sendHeaders();

            $response = ['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERROR_DELETE_FAILED')];
        }

        $app->sendHeaders();
        echo new JsonResponse($response);
        $app->close();
    }

    /**
     * AJAX: Remove every selected geozone rule in one request.
     *
     * The ids travel as a single comma-separated value rather than one variable per id. The rule
     * table already spends three POST variables a row and a full zone runs to 239 rows, so this
     * screen sits close enough to max_input_vars that a per-id array would be the thing that
     * pushes it over.
     *
     * @since   6.5.0
     */
    public function removeRules(): void
    {
        $app  = Factory::getApplication();
        $user = $app->getIdentity();

        $this->prepareJsonResponse($app);

        // Same gate as removeRule: clearing rules edits an existing record. A CSRF token proves
        // the request came from our form, not that the caller may delete.
        if ($user->guest || !$user->authorise('core.edit', 'com_j2commerce')) {
            $app->setHeader('status', 403, true);
            $app->sendHeaders();
            echo new JsonResponse(null, Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), true);
            $app->close();
        }

        if (!Session::checkToken()) {
            $app->setHeader('status', 403, true);
            $app->sendHeaders();
            echo new JsonResponse(null, Text::_('JINVALID_TOKEN'), true);
            $app->close();
        }

        $input     = $app->getInput();
        $geozoneId = $input->post->getInt('geozone_id', 0);
        $ruleIds   = array_filter(
            array_map('intval', explode(',', $input->post->getString('rule_ids', ''))),
            static fn (int $id): bool => $id > 0
        );

        // Rows added in the browser but never saved have no PK yet — the page drops them itself.
        if ($ruleIds === []) {
            $app->sendHeaders();
            echo new JsonResponse([
                'success' => true,
                'deleted' => 0,
                'message' => Text::sprintf('COM_J2COMMERCE_GEOZONE_N_COUNTRIES_DELETED', 0),
            ]);
            $app->close();
        }

        try {
            $deleted = $this->getModel('Geozone')->deleteRules($geozoneId, $ruleIds);

            $response = [
                'success' => true,
                'deleted' => $deleted,
                'message' => Text::sprintf('COM_J2COMMERCE_GEOZONE_N_COUNTRIES_DELETED', $deleted),
            ];
        } catch (\Throwable $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');

            $app->setHeader('status', 500, true);
            $app->sendHeaders();

            $response = ['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERROR_DELETE_FAILED')];
        }

        $app->sendHeaders();
        echo new JsonResponse($response);
        $app->close();
    }
}
