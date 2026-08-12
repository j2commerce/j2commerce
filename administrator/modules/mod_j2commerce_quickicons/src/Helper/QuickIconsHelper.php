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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Router\Route;
use Joomla\CMS\User\User;
use Joomla\Registry\Registry;

class QuickIconsHelper
{
    /**
     * Buttons in the same order as the component navbar (MenuHelper::getMenuItems):
     * Dashboard, Catalog, Sales, Localization, Design, Setup, Analytics, Apps, Configuration.
     * Titles and icons are the menu's, so the grid and the navbar cannot drift apart.
     */
    private const BUTTONS = [
        ['param' => 'show_dashboard', 'default' => 1, 'view' => 'dashboard', 'name' => 'COM_J2COMMERCE_DASHBOARD', 'image' => 'fa-solid fa-tachometer-alt', 'access' => 'core.manage'],

        ['param' => 'show_products', 'default' => 1, 'view' => 'products', 'name' => 'COM_J2COMMERCE_PRODUCTS', 'image' => 'fa-solid fa-tags', 'access' => 'j2commerce.viewproducts'],
        ['param' => 'show_inventory', 'view' => 'inventory', 'name' => 'COM_J2COMMERCE_INVENTORY', 'image' => 'fa-solid fa-barcode', 'access' => 'j2commerce.viewproducts'],
        ['param' => 'show_vendors', 'view' => 'vendors', 'name' => 'COM_J2COMMERCE_VENDORS', 'image' => 'fa-solid fa-user-tag', 'access' => 'j2commerce.viewproducts'],
        ['param' => 'show_manufacturers', 'view' => 'manufacturers', 'name' => 'COM_J2COMMERCE_MANUFACTURERS', 'image' => 'fa-solid fa-city', 'access' => 'j2commerce.viewproducts'],
        ['param' => 'show_filters', 'view' => 'filtergroups', 'name' => 'COM_J2COMMERCE_FILTERS', 'image' => 'fa-solid fa-filter', 'access' => 'j2commerce.viewproducts'],

        ['param' => 'show_orders', 'default' => 1, 'view' => 'orders', 'name' => 'COM_J2COMMERCE_ORDERS', 'image' => 'fa-solid fa-list-alt', 'access' => 'j2commerce.vieworders'],
        ['param' => 'show_customers', 'default' => 1, 'view' => 'customers', 'name' => 'COM_J2COMMERCE_CUSTOMERS', 'image' => 'fa-solid fa-users', 'access' => 'j2commerce.vieworders'],
        ['param' => 'show_coupons', 'view' => 'coupons', 'name' => 'COM_J2COMMERCE_COUPONS', 'image' => 'fa-solid fa-scissors', 'access' => 'j2commerce.vieworders'],
        ['param' => 'show_vouchers', 'view' => 'vouchers', 'name' => 'COM_J2COMMERCE_VOUCHERS', 'image' => 'fa-solid fa-money-check', 'access' => 'j2commerce.vieworders'],

        ['param' => 'show_countries', 'view' => 'countries', 'name' => 'COM_J2COMMERCE_COUNTRIES', 'image' => 'fa-solid fa-earth-americas', 'access' => 'core.manage'],
        ['param' => 'show_zones', 'view' => 'zones', 'name' => 'COM_J2COMMERCE_ZONES', 'image' => 'fa-solid fa-location-dot', 'access' => 'core.manage'],
        ['param' => 'show_geozones', 'view' => 'geozones', 'name' => 'COM_J2COMMERCE_GEOZONES', 'image' => 'fa-solid fa-border-none', 'access' => 'core.manage'],
        ['param' => 'show_taxrates', 'view' => 'taxrates', 'name' => 'COM_J2COMMERCE_TAX_RATES', 'image' => 'fa-solid fa-calculator', 'access' => 'core.manage'],
        ['param' => 'show_taxprofiles', 'view' => 'taxprofiles', 'name' => 'COM_J2COMMERCE_TAX_PROFILES', 'image' => 'fa-solid fa-sitemap', 'access' => 'core.manage'],

        ['param' => 'show_emailtemplates', 'view' => 'emailtemplates', 'name' => 'COM_J2COMMERCE_TEMPLATES_EMAIL', 'image' => 'fa-solid fa-envelope', 'access' => 'core.manage'],
        ['param' => 'show_invoicetemplates', 'view' => 'invoicetemplates', 'name' => 'COM_J2COMMERCE_TEMPLATES_INVOICE', 'image' => 'fa-solid fa-print', 'access' => 'core.manage'],

        ['param' => 'show_customfields', 'view' => 'customfields', 'name' => 'COM_J2COMMERCE_CUSTOM_FIELDS', 'image' => 'fa-solid fa-th-list', 'access' => 'j2commerce.viewsetup'],
        ['param' => 'show_payment', 'default' => 1, 'view' => 'paymentmethods', 'name' => 'COM_J2COMMERCE_PAYMENT_METHODS', 'image' => 'fa-solid fa-credit-card', 'access' => 'j2commerce.viewsetup'],
        ['param' => 'show_shipping', 'default' => 1, 'view' => 'shippingmethods', 'name' => 'COM_J2COMMERCE_SHIPPING_METHODS', 'image' => 'fa-solid fa-truck-plane', 'access' => 'j2commerce.viewsetup'],
        ['param' => 'show_queues', 'view' => 'queues', 'name' => 'COM_J2COMMERCE_QUEUES', 'image' => 'fa-solid fa-list-check', 'access' => 'j2commerce.viewsetup'],

        ['param' => 'show_statistics', 'default' => 1, 'view' => 'analytics', 'name' => 'COM_J2COMMERCE_STATISTICS_DASHBOARD', 'image' => 'fa-solid fa-chart-pie', 'access' => 'j2commerce.viewreports'],
        ['param' => 'show_reports', 'default' => 1, 'view' => 'reports', 'name' => 'COM_J2COMMERCE_REPORTS', 'image' => 'fa-solid fa-chart-bar', 'access' => 'j2commerce.viewreports'],

        ['param' => 'show_apps', 'default' => 1, 'view' => 'apps', 'name' => 'COM_J2COMMERCE_APPS', 'image' => 'fa-solid fa-puzzle-piece', 'access' => 'core.manage'],

        ['param' => 'show_config', 'default' => 1, 'view' => 'configuration', 'name' => 'COM_J2COMMERCE_CONFIGURATION', 'image' => 'fa-solid fa-gear', 'access' => 'core.options'],
    ];

    public function getButtons(Registry $params): array
    {
        if (!ComponentHelper::isEnabled('com_j2commerce')) {
            return [];
        }

        $app     = Factory::getApplication();
        $user    = $app->getIdentity();
        $buttons = [];

        if (!$user) {
            return [];
        }

        // The dashboard icon is noise on the dashboard itself.
        $onDashboard = $app->getInput()->getCmd('option', '') === 'com_j2commerce'
            && $app->getInput()->getCmd('view', '') === 'dashboard';

        foreach (self::BUTTONS as $button) {
            if (!$params->get($button['param'], $button['default'] ?? 0)) {
                continue;
            }

            if ($button['param'] === 'show_dashboard' && $onDashboard) {
                continue;
            }

            if (!$this->canAccess($button['access'], $user)) {
                continue;
            }

            $buttons[] = [
                'image' => $button['image'],
                'link'  => $this->link($button['view']),
                'name'  => $button['name'],
            ];
        }

        if ($params->get('show_plugin_icons', 1) && $user->authorise('core.manage', 'com_j2commerce')) {
            $buttons = array_merge($buttons, $this->pluginButtons($params));
        }

        return $buttons;
    }

    private function link(string $view): string
    {
        if ($view === 'configuration') {
            $return = base64_encode('index.php?option=com_j2commerce&view=dashboard');

            return Route::_('index.php?option=com_config&view=component&component=com_j2commerce&return=' . $return);
        }

        return Route::_('index.php?option=com_j2commerce&view=' . $view);
    }

    /**
     * Resolved here rather than through the `access` pair-array that Icons::button()
     * walks, because that helper calls User::authorise() directly and would miss the
     * core.manage fallback J2CommerceHelper::canAccess() applies while a j2commerce.*
     * action is still awaiting its ACL seed — hiding icons whose menu entries are visible.
     */
    private function canAccess(string $action, User $user): bool
    {
        return match ($action) {
            'core.manage', 'core.admin' => $user->authorise($action, 'com_j2commerce'),
            // com_config accepts either, so the icon must not be stricter than the screen.
            'core.options' => $user->authorise('core.admin', 'com_j2commerce')
                || $user->authorise('core.options', 'com_j2commerce'),
            default => J2CommerceHelper::canAccess($action),
        };
    }

    /**
     * Dispatched through the component helper so subscribers receive the PluginEvent
     * they are written against — a plain Event fatals on the typed handlers and on
     * addResult(), and the dispatcher has no per-listener isolation.
     */
    private function pluginButtons(Registry $params): array
    {
        $icons = [];

        try {
            $event   = J2CommerceHelper::plugin()->event('GetQuickIcons', [
                'context' => (string) $params->get('context', 'j2commerce_cpanel'),
            ]);
            $results = $event->getArgument('result', []);

            foreach ($results as $icon) {
                if (\is_array($icon) && isset($icon['link']) && (isset($icon['name']) || isset($icon['text']))) {
                    $icons[] = $icon;
                }
            }
        } catch (\Throwable $e) {
            Log::add(
                'Quick icons from J2Commerce plugins could not be collected: ' . $e->getMessage(),
                Log::WARNING,
                'mod_j2commerce_quickicons'
            );
        }

        return $icons;
    }
}
