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

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;

/**
 * Reports Controller
 *
 * @since  6.0.0
 */
class ReportsController extends AdminController
{
    use ExtensionCheckinTrait;

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
     * Resolves the report plugin row from #__extensions and hands off to the
     * reportplugin route, which owns both the view and the CSV export.
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

}
