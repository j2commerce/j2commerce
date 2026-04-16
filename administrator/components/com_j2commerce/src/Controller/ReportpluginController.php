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
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Event\Event;

/**
 * Generic Report Plugin dispatcher controller.
 *
 * Routes admin requests to report plugins via events, allowing each plugin
 * to handle its own views and AJAX operations independently.
 *
 * @since  6.0.0
 */
class ReportpluginController extends BaseController
{
    /**
     * Display the report plugin view.
     *
     * @param   bool   $cachable   If true, the view output will be cached.
     * @param   array  $urlparams  An array of safe URL parameters.
     *
     * @return  static  This object to support chaining.
     *
     * @since   6.0.0
     */
    public function display($cachable = false, $urlparams = []): static
    {
        $this->input->set('view', 'reportplugin');

        return parent::display($cachable, $urlparams);
    }

    /**
     * Handle AJAX requests from report plugins.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function ajax(): void
    {
        $method = $this->input->getMethod();

        if ($method === 'GET') {
            if (!Session::checkToken('get')) {
                $this->sendJsonError(Text::_('JINVALID_TOKEN'));
                return;
            }
        } else {
            if (!Session::checkToken()) {
                $this->sendJsonError(Text::_('JINVALID_TOKEN'));
                return;
            }
        }

        $plugin = $this->input->getCmd('plugin', '');
        $action = $this->input->getCmd('action', '');

        if (empty($plugin) || empty($action)) {
            $this->sendJsonError(Text::_('COM_J2COMMERCE_ERR_INVALID_REQUEST'));
            return;
        }

        PluginHelper::importPlugin('j2commerce');

        $event = new Event('onJ2CommerceReportPluginAjax', [
            'plugin' => $plugin,
            'action' => $action,
            'input'  => $this->input,
        ]);

        Factory::getApplication()->getDispatcher()->dispatch('onJ2CommerceReportPluginAjax', $event);

        $jsonResult = $event->getArgument('jsonResult', null);

        if ($jsonResult !== null) {
            $this->sendJsonResponse($jsonResult);
        } else {
            $this->sendJsonError($event->getArgument('jsonError', Text::_('COM_J2COMMERCE_ERR_NO_HANDLER')));
        }
    }

    /**
     * Handle CSV export from report plugins.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function exportCsv(): void
    {
        $this->checkToken('get');

        $app    = Factory::getApplication();
        $plugin = $this->input->getCmd('plugin', '');

        if (empty($plugin)) {
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=reports', false));
            $this->setMessage(Text::_('COM_J2COMMERCE_ERR_NO_REPORT_PLUGIN_SPECIFIED'), 'error');
            return;
        }

        // Load plugin language
        $app->getLanguage()->load(
            'plg_j2commerce_' . $plugin,
            JPATH_PLUGINS . '/j2commerce/' . $plugin,
            null,
            true
        );

        PluginHelper::importPlugin('j2commerce');

        $event = new Event('onJ2CommerceReportPluginExport', [
            'plugin' => $plugin,
            'input'  => $this->input,
        ]);

        $app->getDispatcher()->dispatch('onJ2CommerceReportPluginExport', $event);

        $results = $event->getArgument('result', []);

        if (empty($results) || empty($results[0])) {
            $this->setRedirect(Route::_(
                'index.php?option=com_j2commerce&view=reportplugin&plugin=' . $plugin . '&pluginview=report',
                false
            ));
            $this->setMessage(Text::_('COM_J2COMMERCE_REPORT_NO_DATA'), 'warning');
            return;
        }

        $items       = $results[0];
        $csvFilename = 'report_' . $plugin . '_' . date('Y-m-d') . '_' . time() . '.csv';

        // Set headers for CSV download
        $app->setHeader('Content-Type', 'text/csv; charset=utf-8');
        $app->setHeader('Content-Disposition', 'attachment; filename="' . $csvFilename . '"');
        $app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
        $app->setHeader('Pragma', 'no-cache');
        $app->setHeader('Expires', '0');
        $app->sendHeaders();

        // Output CSV
        $output = fopen('php://output', 'w');

        // Add UTF-8 BOM for Excel ensures currency symbols and other characters display correctly
        fprintf($output, \chr(0xEF) . \chr(0xBB) . \chr(0xBF));

        if (!empty($items)) {
            // Header row from object keys
            $firstItem = reset($items);
            $keys      = array_keys((array) $firstItem);
            fputcsv($output, $keys, ',', '"', '\\');

            // Data rows
            foreach ($items as $item) {
                $row = [];

                foreach ($keys as $key) {
                    $value = $item->$key ?? '';

                    if (\is_array($value)) {
                        $value = 'Array';
                    } elseif (\is_object($value)) {
                        $value = 'Object';
                    }

                    $row[] = $value;
                }

                fputcsv($output, $row, ',', '"', '\\');
            }
        }

        fclose($output);
        $app->close();
    }

    /**
     * Send a JSON success response and close.
     *
     * @param   mixed  $data  The response data.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function sendJsonResponse(mixed $data): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $app->sendHeaders();

        echo json_encode(['success' => true, 'data' => $data], JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
        $app->close();
    }

    /**
     * Send a JSON error response and close.
     *
     * @param   string  $message  The error message.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function sendJsonError(string $message): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $app->sendHeaders();

        echo json_encode(['success' => false, 'message' => $message], JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
        $app->close();
    }
}
