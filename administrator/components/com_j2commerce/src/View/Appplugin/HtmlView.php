<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Appplugin;

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
 * Generic container view for app plugin admin pages.
 *
 * Fires onJ2CommerceAppPluginView so app plugins can render their own content,
 * set the page title, and add toolbar buttons.
 *
 * @since  6.0.0
 */
class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    public string $pluginHtml = '';
    public string $navbar     = '';

    public function display($tpl = null): void
    {
        if (!J2CommerceHelper::canAccess('j2commerce.viewsetup')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();

        $app        = Factory::getApplication();
        $pluginName = $app->getInput()->getCmd('plugin', '');
        $pluginView = $app->getInput()->getCmd('pluginview', 'default');
        $id         = $app->getInput()->getInt('id', 0);

        if (empty($pluginName)) {
            $app->enqueueMessage(Text::_('COM_J2COMMERCE_ERR_NO_PLUGIN_SPECIFIED'), 'error');
            $app->redirect('index.php?option=com_j2commerce&view=apps');
            return;
        }

        $app->getLanguage()->load(
            'plg_j2commerce_' . $pluginName,
            JPATH_PLUGINS . '/j2commerce/' . $pluginName,
            null,
            true
        );

        $this->navbar = $this->getNavbar();

        $toolbar = $this->getDocument()->getToolbar();

        PluginHelper::importPlugin('j2commerce');

        $event = new Event('onJ2CommerceAppPluginView', [
            'plugin'     => $pluginName,
            'pluginview' => $pluginView,
            'id'         => $id,
            'toolbar'    => $toolbar,
            'input'      => $app->getInput(),
        ]);

        $app->getDispatcher()->dispatch('onJ2CommerceAppPluginView', $event);

        $results          = $event->getArgument('result', []);
        $this->pluginHtml = implode('', array_filter($results, 'is_string'));

        $title = $event->getArgument('title', Text::_('COM_J2COMMERCE_APPS'));
        ToolbarHelper::title($title, 'fa-solid fa-puzzle-piece');

        $toolbar->back('JTOOLBAR_BACK', 'index.php?option=com_j2commerce&view=apps');

        if (empty($this->pluginHtml)) {
            $this->pluginHtml = '<div class="alert alert-info">'
                . Text::_('COM_J2COMMERCE_APP_PLUGIN_NO_CONTENT')
                . '</div>';
        }

        parent::display($tpl);
    }

    protected function getNavbar(): string
    {
        $displayData = [
            'items'  => MenuHelper::getMenuItems(),
            'active' => MenuHelper::getActiveView(),
        ];

        return LayoutHelper::render('navbar.default', $displayData, JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
    }
}
