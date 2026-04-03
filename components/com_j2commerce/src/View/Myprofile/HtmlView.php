<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\View\Myprofile;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Event\PluginEvent;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

/**
 * My Profile HTML View
 *
 * Dispatches plugin events for tab injection (onJ2CommerceMyProfileTab,
 * onJ2CommerceMyProfileTabContent) so app plugins like Vendor Management
 * can add custom tabs to the My Profile page.
 *
 * @since  6.1.6
 */
class HtmlView extends BaseHtmlView
{
    public ?object $params = null;
    public ?object $user = null;
    public ?Registry $menuItemParams = null;
    public string $pluginTabHtml = '';
    public string $pluginContentHtml = '';
    public string $topMessagesHtml = '';
    public bool $useUnifiedPaymentTab = false;

    public function display($tpl = null): void
    {
        $app  = Factory::getApplication();
        $user = $app->getIdentity();

        $this->params   = J2CommerceHelper::config();
        $this->user     = $user;

        // Menu item params
        $menu = $app->getMenu()->getActive();
        $this->menuItemParams = $menu ? $menu->getParams() : new Registry();

        // Dispatch plugin tab events
        $dispatcher = $app->getDispatcher();

        PluginHelper::importPlugin('j2commerce');

        // Collect tab HTML from plugins
        $tabEvent = new PluginEvent('onJ2CommerceMyProfileTab', []);
        $dispatcher->dispatch('onJ2CommerceMyProfileTab', $tabEvent);
        $this->pluginTabHtml = implode("\n", $tabEvent->getEventResult() ?: []);

        // Collect tab content HTML from plugins
        $contentEvent = new PluginEvent('onJ2CommerceMyProfileTabContent', []);
        $dispatcher->dispatch('onJ2CommerceMyProfileTabContent', $contentEvent);
        $this->pluginContentHtml = implode("\n", $contentEvent->getEventResult() ?: []);

        // Collect top messages from plugins
        $msgEvent = new PluginEvent('onJ2CommerceMyProfileTopMessages', []);
        $dispatcher->dispatch('onJ2CommerceMyProfileTopMessages', $msgEvent);
        $this->topMessagesHtml = implode("\n", $msgEvent->getEventResult() ?: []);

        // Check for unified payment tab (payment plugins that support saved methods)
        $payEvent = new PluginEvent('onJ2CommerceGetSavedPaymentMethods', ['user_id' => $user->id ?? 0]);
        $dispatcher->dispatch('onJ2CommerceGetSavedPaymentMethods', $payEvent);
        $this->useUnifiedPaymentTab = !empty($payEvent->getEventResult());

        parent::display($tpl);
    }
}
