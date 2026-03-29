<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Report;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\MenuHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

/**
 * View class for displaying a single report plugin's content.
 *
 * @since  6.0.0
 */
class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    /**
     * The report plugin item from #__extensions
     *
     * @var  object|null
     */
    protected $item;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function display($tpl = null): void
    {
        if (!J2CommerceHelper::canAccess('j2commerce.viewreports')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();

        $app = Factory::getApplication();
        $id = $app->getInput()->getInt('id', 0);

        // Load the report plugin row
        $model = $this->getModel();
        $this->item = $model->getItem($id);

        if (!$this->item || empty($this->item->element)) {
            $app->enqueueMessage(Text::_('COM_J2COMMERCE_REPORT_NOT_FOUND'), 'error');
            $app->redirect('index.php?option=com_j2commerce&view=reports');
            return;
        }

        $this->navbar = $this->getNavbar();

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Get navbar configuration
     *
     * @return  string
     *
     * @since   6.0.0
     */
    protected function getNavbar(): string
    {
        $displayData = [
            'items' => MenuHelper::getMenuItems(),
            'active' => MenuHelper::getActiveView()
        ];

        return LayoutHelper::render('navbar.default', $displayData, JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function addToolbar(): void
    {
        $toolbar = Factory::getApplication()->getDocument()->getToolbar();

        ToolbarHelper::title(
            Text::_($this->item->name ?? 'COM_J2COMMERCE_REPORT'),
            'fa-solid fa-chart-bar'
        );

        $toolbar->back('JTOOLBAR_BACK', 'index.php?option=com_j2commerce&view=reports');
    }
}
