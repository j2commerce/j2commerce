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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Reports Controller
 *
 * @since  6.0.0
 */
class ReportsController extends AdminController
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $text_prefix = 'COM_J2COMMERCE';

    /**
     * Method to get a model object, loading it if required.
     *
     * @param   string  $name    The model name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel  The model.
     *
     * @since   6.0.0
     */
    public function getModel($name = 'Report', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Display a single report plugin's view.
     *
     * Loads the report plugin row from #__extensions and dispatches
     * onJ2CommerceGetReportView (or onJ2CommerceGetReportExported for CSV).
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function view()
    {
        $id = $this->input->getInt('id', 0);

        if (!$id) {
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=reports', false));
            return;
        }

        // Check for CSV export request
        $format = $this->input->getString('format', '');

        if ($format === 'csv') {
            $this->exportCsv($id);
            return;
        }

        // Load the plugin row to get element name
        $model = $this->getModel('Report', 'Administrator');
        $row   = $model->getItem($id);

        if (!$row || empty($row->element)) {
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=reports', false));
            $this->setMessage(Text::_('COM_J2COMMERCE_REPORT_NOT_FOUND'), 'error');
            return;
        }

        // Redirect to the new ReportpluginController route
        $this->setRedirect(Route::_(
            'index.php?option=com_j2commerce&view=reportplugin&plugin=' . $row->element . '&pluginview=report',
            false
        ));
    }

    /**
     * Handle CSV export for a report plugin.
     *
     * @param   int  $id  The extension_id of the report plugin.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function exportCsv(int $id): void
    {
        $app = Factory::getApplication();

        // Load the report plugin row
        $model = $this->getModel('Report', 'Administrator');
        $row   = $model->getItem($id);

        if (!$row || empty($row->element)) {
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=reports', false));
            $this->setMessage(Text::_('COM_J2COMMERCE_REPORT_NOT_FOUND'), 'error');
            return;
        }

        // Load plugin language
        $app->getLanguage()->load('plg_j2commerce_' . $row->element, JPATH_PLUGINS . '/j2commerce/' . $row->element, null, true);

        // Dispatch export event
        PluginHelper::importPlugin('j2commerce');
        $event = new \Joomla\Event\Event('onJ2CommerceGetReportExported', [$row]);
        $app->getDispatcher()->dispatch('onJ2CommerceGetReportExported', $event);
        $results = $event->getArgument('result', []);

        if (empty($results) || empty($results[0])) {
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=reports&task=view&id=' . $id, false));
            $this->setMessage(Text::_('COM_J2COMMERCE_REPORT_NO_DATA'), 'warning');
            return;
        }

        $items       = $results[0];
        $csvFilename = 'report_' . $row->element . '_' . date('Y-m-d') . '_' . time() . '.csv';

        // Set headers for CSV download
        $app->setHeader('Content-Type', 'text/csv; charset=utf-8');
        $app->setHeader('Content-Disposition', 'attachment; filename="' . $csvFilename . '"');
        $app->setHeader('Pragma', 'public');
        $app->setHeader('Expires', '0');
        $app->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
        $app->sendHeaders();

        // Output CSV
        $output = fopen('php://output', 'w');

        if (!empty($items)) {
            // Header row from object keys
            $firstItem = reset($items);
            $keys      = array_keys((array) $firstItem);
            fputcsv($output, $keys);

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
                fputcsv($output, $row);
            }
        }

        fclose($output);
        $app->close();
    }

    /**
     * Method to checkin a list of items
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function checkin()
    {
        // Check for request forgeries
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        // Get items to checkin from the request.
        $cid = (array) $this->input->get('cid', [], 'int');

        if (empty($cid)) {
            $this->setMessage(Text::_('COM_J2COMMERCE_NO_ITEM_SELECTED'), 'warning');
        } else {
            // Get the model.
            $model = $this->getModel('Report', 'Administrator');

            // Make sure the item ids are integers
            $cid = array_map('intval', $cid);

            // Checkin the items.
            try {
                $model->checkin($cid);
                $this->setMessage(Text::plural('COM_J2COMMERCE_N_ITEMS_CHECKED_IN', \count($cid)));
            } catch (\Exception $e) {
                $this->setMessage($e->getMessage(), 'error');
            }
        }

        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=reports', false));
    }
}
