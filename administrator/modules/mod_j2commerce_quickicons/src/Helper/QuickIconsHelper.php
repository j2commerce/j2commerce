<?php

/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_quickicons
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Module\J2commerceQuickicons\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

class QuickIconsHelper
{
    public function getButtons(Registry $params): array
    {
        if (!ComponentHelper::isEnabled('com_j2commerce')) {
            return [];
        }

        $app     = Factory::getApplication();
        $buttons = [];

        // Only show dashboard icon when NOT already on the dashboard view
        $currentOption = $app->getInput()->getCmd('option', '');
        $currentView   = $app->getInput()->getCmd('view', '');
        $onDashboard   = ($currentOption === 'com_j2commerce' && $currentView === 'dashboard');

        if ($params->get('show_dashboard', 1) && !$onDashboard) {
            $buttons[] = [
                'image'  => 'fa-solid fa-tachometer-alt',
                'link'   => Route::_('index.php?option=com_j2commerce&view=dashboard'),
                'name'   => 'COM_J2COMMERCE_DASHBOARD',
                'access' => ['core.manage', 'com_j2commerce'],
                'group'  => 'MOD_J2COMMERCE_QUICKICONS_GROUP',
            ];
        }

        if ($params->get('show_orders', 1)) {
            $buttons[] = [
                'image'  => 'fa-solid fa-list-alt',
                'link'   => Route::_('index.php?option=com_j2commerce&view=orders'),
                'name'   => 'COM_J2COMMERCE_ORDERS',
                'access' => ['j2commerce.vieworders', 'com_j2commerce'],
                'group'  => 'MOD_J2COMMERCE_QUICKICONS_GROUP',
            ];
        }

        if ($params->get('show_products', 1)) {
            $buttons[] = [
                'image'  => 'icon-tag',
                'link'   => Route::_('index.php?option=com_j2commerce&view=products'),
                'name'   => 'COM_J2COMMERCE_PRODUCTS',
                'access' => ['j2commerce.viewproducts', 'com_j2commerce'],
                'group'  => 'MOD_J2COMMERCE_QUICKICONS_GROUP',
            ];
        }

        if ($params->get('show_customers', 1)) {
            $buttons[] = [
                'image'  => 'icon-users',
                'link'   => Route::_('index.php?option=com_j2commerce&view=customers'),
                'name'   => 'COM_J2COMMERCE_CUSTOMERS',
                'access' => ['j2commerce.vieworders', 'com_j2commerce'],
                'group'  => 'MOD_J2COMMERCE_QUICKICONS_GROUP',
            ];
        }

        if ($params->get('show_apps', 1)) {
            $buttons[] = [
                'image'  => 'icon-puzzle',
                'link'   => Route::_('index.php?option=com_j2commerce&view=apps'),
                'name'   => 'COM_J2COMMERCE_APPS',
                'access' => ['core.manage', 'com_j2commerce'],
                'group'  => 'MOD_J2COMMERCE_QUICKICONS_GROUP',
            ];
        }

        if ($params->get('show_payment', 1)) {
            $buttons[] = [
                'image'  => 'fa-regular fa-credit-card',
                'link'   => Route::_('index.php?option=com_j2commerce&view=paymentmethods'),
                'name'   => 'COM_J2COMMERCE_PAYMENT_METHODS',
                'access' => ['j2commerce.viewsetup', 'com_j2commerce'],
                'group'  => 'MOD_J2COMMERCE_QUICKICONS_GROUP',
            ];
        }

        if ($params->get('show_shipping', 1)) {
            $buttons[] = [
                'image'  => 'fa-solid fa-truck-plane',
                'link'   => Route::_('index.php?option=com_j2commerce&view=shippingmethods'),
                'name'   => 'COM_J2COMMERCE_SHIPPING_METHODS',
                'access' => ['j2commerce.viewsetup', 'com_j2commerce'],
                'group'  => 'MOD_J2COMMERCE_QUICKICONS_GROUP',
            ];
        }

        if ($params->get('show_statistics', 1)) {
            $buttons[] = [
                'image'  => 'fa-solid fa-chart-pie',
                'link'   => Route::_('index.php?option=com_j2commerce&view=analytics'),
                'name'   => 'COM_J2COMMERCE_STATISTICS',
                'access' => ['j2commerce.viewreports', 'com_j2commerce'],
                'group'  => 'MOD_J2COMMERCE_QUICKICONS_GROUP',
            ];
        }

        if ($params->get('show_reports', 1)) {
            $buttons[] = [
                'image'  => 'fa-solid fa-chart-bar',
                'link'   => Route::_('index.php?option=com_j2commerce&view=reports'),
                'name'   => 'COM_J2COMMERCE_REPORTS',
                'access' => ['j2commerce.viewreports', 'com_j2commerce'],
                'group'  => 'MOD_J2COMMERCE_QUICKICONS_GROUP',
            ];
        }

        if ($params->get('show_config', 1)) {
            $returnUrl = base64_encode('index.php?option=com_j2commerce&view=dashboard');
            $buttons[] = [
                'image'  => 'icon-cog',
                'link'   => Route::_('index.php?option=com_config&view=component&component=com_j2commerce&return=' . $returnUrl),
                'name'   => 'COM_J2COMMERCE_CONFIGURATION',
                'access' => ['core.admin', 'com_j2commerce'],
                'group'  => 'MOD_J2COMMERCE_QUICKICONS_GROUP',
            ];
        }

        if ($params->get('show_plugin_icons', 1)) {
            try {
                $dispatcher = $app->getDispatcher();
                $event      = new \Joomla\Event\Event('onJ2CommerceGetQuickIcons', ['context' => 'j2commerce_cpanel']);

                \Joomla\CMS\Plugin\PluginHelper::importPlugin('j2commerce');
                $dispatcher->dispatch('onJ2CommerceGetQuickIcons', $event);

                $results = $event->getArgument('result', []);

                foreach ($results as $icon) {
                    if (isset($icon['link']) && (isset($icon['name']) || isset($icon['text']))) {
                        $icon['group'] = $icon['group'] ?? 'MOD_J2COMMERCE_QUICKICONS_GROUP';
                        $buttons[]     = $icon;
                    }
                }
            } catch (\Throwable $e) {
                // Silently skip plugin icons if component isn't fully booted
            }
        }

        return $buttons;
    }
}
