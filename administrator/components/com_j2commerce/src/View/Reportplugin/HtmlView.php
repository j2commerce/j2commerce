<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Reportplugin;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\MenuHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Event\Event;
use Joomla\Registry\Registry;

/**
 * Generic container view for report plugin admin pages.
 *
 * Fires onJ2CommerceReportPluginView event so report plugins can render
 * their own content, set the page title, and add toolbar buttons.
 *
 * @since  6.0.0
 */
class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    /**
     * The HTML content rendered by the report plugin.
     *
     * @var    string
     * @since  6.0.0
     */
    public string $pluginHtml = '';

    /**
     * The navbar HTML.
     *
     * @var    string
     * @since  6.0.0
     */
    public string $navbar = '';

    /**
     * Form object for search filters (set by report plugins via event).
     *
     * @var    Form|null
     * @since  6.0.0
     */
    public ?Form $filterForm = null;

    /**
     * The active search filters (set by report plugins via event).
     *
     * @var    array
     * @since  6.0.0
     */
    public array $activeFilters = [];

    /**
     * The model state (set by report plugins via event).
     *
     * @var    Registry|null
     * @since  6.0.0
     */
    public ?Registry $state = null;

    /**
     * The pagination object (set by report plugins via event).
     *
     * @var    Pagination|null
     * @since  6.0.0
     */
    public ?Pagination $pagination = null;

    /**
     * An array of items (set by report plugins via event).
     *
     * @var    array
     * @since  6.0.0
     */
    public array $items = [];

    /**
     * Display the view.
     *
     * @param   string|null  $tpl  The template name.
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

        $app        = Factory::getApplication();
        $pluginName = $app->getInput()->getCmd('plugin', '');
        $pluginView = $app->getInput()->getCmd('pluginview', 'report');
        $id         = $app->getInput()->getInt('id', 0);

        if (empty($pluginName)) {
            $app->enqueueMessage(Text::_('COM_J2COMMERCE_ERR_NO_REPORT_PLUGIN_SPECIFIED'), 'error');
            $app->redirect('index.php?option=com_j2commerce&view=reports');
            return;
        }

        // Load the report plugin's language file
        $app->getLanguage()->load(
            'plg_j2commerce_' . $pluginName,
            JPATH_PLUGINS . '/j2commerce/' . $pluginName,
            null,
            true
        );

        $this->navbar = $this->getNavbar();

        // Get the toolbar instance
        $toolbar = $this->getDocument()->getToolbar();

        // Import report plugins and fire the view event
        PluginHelper::importPlugin('j2commerce');

        $event = new Event('onJ2CommerceReportPluginView', [
            'plugin'     => $pluginName,
            'pluginview' => $pluginView,
            'id'         => $id,
            'toolbar'    => $toolbar,
            'input'      => $app->getInput(),
        ]);

        $app->getDispatcher()->dispatch('onJ2CommerceReportPluginView', $event);

        // Collect rendered HTML from plugin
        $results          = $event->getArgument('result', []);
        $this->pluginHtml = implode('', array_filter($results, 'is_string'));

        // Collect optional ListModel artifacts from plugin
        $this->filterForm    = $event->getArgument('filterForm', null);
        $this->activeFilters = $event->getArgument('activeFilters', []);
        $this->state         = $event->getArgument('state', null);
        $this->pagination    = $event->getArgument('pagination', null);
        $this->items         = $event->getArgument('items', []);

        // Set page title from plugin
        $title = $event->getArgument('title', Text::_('COM_J2COMMERCE_REPORT_PLUGIN'));
        ToolbarHelper::title($title, 'fa-solid fa-chart-bar');

        // Always provide a Back button to reports list
        $toolbar->back('JTOOLBAR_BACK', 'index.php?option=com_j2commerce&view=reports');

        if (empty($this->pluginHtml)) {
            $this->pluginHtml = '<div class="alert alert-info">'
                . Text::_('COM_J2COMMERCE_REPORT_PLUGIN_NO_CONTENT')
                . '</div>';
        }

        parent::display($tpl);
    }

    /**
     * Get navbar HTML.
     *
     * @return  string
     *
     * @since   6.0.0
     */
    protected function getNavbar(): string
    {
        $displayData = [
            'items'  => MenuHelper::getMenuItems(),
            'active' => MenuHelper::getActiveView(),
        ];

        return LayoutHelper::render('navbar.default', $displayData, JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
    }
}
