<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Shippingplugin;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\MenuHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Event\Event;

/**
 * Generic container view for shipping plugin admin pages.
 *
 * Fires onJ2CommerceShippingPluginView event so shipping plugins can render
 * their own content, set the page title, and add toolbar buttons.
 *
 * @since  6.0.0
 */
class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    /**
     * The HTML content rendered by the shipping plugin.
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
        if (!J2CommerceHelper::canAccess('j2commerce.viewsetup')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();

        $app          = Factory::getApplication();
        $pluginName   = $app->getInput()->getCmd('plugin', '');
        $pluginView   = $app->getInput()->getCmd('pluginview', 'default');
        $id           = $app->getInput()->getInt('id', 0);

        if (empty($pluginName)) {
            $app->enqueueMessage(Text::_('COM_J2COMMERCE_ERR_NO_PLUGIN_SPECIFIED'), 'error');
            $app->redirect('index.php?option=com_j2commerce&view=shippingmethods');
            return;
        }

        // Load the shipping plugin's language file
        $app->getLanguage()->load(
            'plg_j2commerce_' . $pluginName,
            JPATH_PLUGINS . '/j2commerce/' . $pluginName,
            null,
            true
        );

        $this->navbar = $this->getNavbar();

        // Get the toolbar instance
        $toolbar = $this->getDocument()->getToolbar();

        // Import shipping plugins and fire the view event
        PluginHelper::importPlugin('j2commerce');

        $event = new Event('onJ2CommerceShippingPluginView', [
            'plugin'     => $pluginName,
            'pluginview' => $pluginView,
            'id'         => $id,
            'toolbar'    => $toolbar,
            'input'      => $app->getInput(),
        ]);

        $app->getDispatcher()->dispatch('onJ2CommerceShippingPluginView', $event);

        // Collect rendered HTML from plugin
        $results          = $event->getArgument('result', []);
        $this->pluginHtml = implode('', array_filter($results, 'is_string'));

        // Set page title from plugin
        $title = $event->getArgument('title', Text::_('COM_J2COMMERCE_SHIPPING_PLUGIN'));
        ToolbarHelper::title($title, 'fa-solid fa-truck-plane');

        // Always provide a Back button to shipping methods
        $toolbar->back('JTOOLBAR_BACK', 'index.php?option=com_j2commerce&view=shippingmethods');

        if (empty($this->pluginHtml)) {
            $this->pluginHtml = '<div class="alert alert-info">'
                . Text::_('COM_J2COMMERCE_SHIPPING_PLUGIN_NO_CONTENT')
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
