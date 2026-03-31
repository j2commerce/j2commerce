<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;



class MenuHelper
{
    /**
     * Get menu items based on user permissions
     *
     * @return array
     */
    public static function getMenuItems(): array
    {
        $user = Factory::getApplication()->getIdentity();
        $items = [];
        $currentUrl = Uri::getInstance()->toString();
        $encodedReturn = base64_encode($currentUrl);

        $canViewOrders   = J2CommerceHelper::canAccess('j2commerce.vieworders');
        $canViewProducts = J2CommerceHelper::canAccess('j2commerce.viewproducts');
        $canViewReports  = J2CommerceHelper::canAccess('j2commerce.viewreports');
        $canViewSetup    = J2CommerceHelper::canAccess('j2commerce.viewsetup');
        // Dashboard
        if ($user->authorise('core.manage', 'com_j2commerce')) {
            $items[] = [
                'title' => 'COM_J2COMMERCE_DASHBOARD',
                'view' => 'dashboard',
                'link' => 'index.php?option=com_j2commerce',
                'icon' => 'fas fa-solid fa-tachometer-alt'
            ];
        }

        // Products dropdown
        if ($canViewProducts) {
            $items[] = [
                'title' => 'COM_J2COMMERCE_CATALOG',
                'view' => 'products',
                'icon' => 'fa-solid fa-tags',
                'children' => [
                    [
                        'title' => 'COM_J2COMMERCE_PRODUCTS',
                        'view' => 'products',
                        'link' => 'index.php?option=com_j2commerce&view=products',
                        'icon' => 'fa-solid fa-tags'
                    ],
                    [
                        'title' => 'COM_J2COMMERCE_INVENTORY',
                        'view' => 'inventory',
                        'link' => 'index.php?option=com_j2commerce&view=inventory',
                        'icon' => 'fa-solid fa-barcode'
                    ],
                    [
                        'title' => 'COM_J2COMMERCE_OPTIONS',
                        'view' => 'options',
                        'link' => 'index.php?option=com_j2commerce&view=options',
                        'icon' => 'fa-solid fa-list-ol'
                    ],
                    [
                        'title' => 'COM_J2COMMERCE_VENDORS',
                        'view' => 'vendors',
                        'link' => 'index.php?option=com_j2commerce&view=vendors',
                        'icon' => 'fa-solid fa-user-tag'
                    ],
                    [
                        'title' => 'COM_J2COMMERCE_MANUFACTURERS',
                        'view' => 'manufacturers',
                        'link' => 'index.php?option=com_j2commerce&view=manufacturers',
                        'icon' => 'fa-solid fa-city'
                    ],
                    [
                        'title' => 'COM_J2COMMERCE_FILTERS',
                        'view' => 'filtergroups',
                        'link' => 'index.php?option=com_j2commerce&view=filtergroups',
                        'icon' => 'fa-solid fa-filter'
                    ]
                ]
            ];
        }

        // Orders dropdown
        if ($canViewOrders) {
            $items[] = [
                'title' => 'COM_J2COMMERCE_SALES',
                'view' => 'orders',
                'icon' => 'fa-solid fa-money-bill',
                'children' => [
                    [
                        'title' => 'COM_J2COMMERCE_ORDERS',
                        'view' => 'orders',
                        'link' => 'index.php?option=com_j2commerce&view=orders',
                        'icon' => 'fa-solid fa-list-alt'
                    ],
                    [
                        'title' => 'COM_J2COMMERCE_CUSTOMERS',
                        'view' => 'customers',
                        'link' => 'index.php?option=com_j2commerce&view=customers',
                        'icon' => 'fa-solid fa-users'
                    ],
                    [
                        'title' => 'COM_J2COMMERCE_COUPONS',
                        'view' => 'coupons',
                        'link' => 'index.php?option=com_j2commerce&view=coupons',
                        'icon' => 'fa-solid fa-scissors'
                    ],
                    [
                        'title' => 'COM_J2COMMERCE_VOUCHERS',
                        'view' => 'vouchers',
                        'link' => 'index.php?option=com_j2commerce&view=vouchers',
                        'icon' => 'fa-solid fa-money-check'
                    ]
                ]
            ];
        }

        // Localization
        if ($user->authorise('core.manage', 'com_j2commerce')) {
            $items[] = [
                'title' => 'COM_J2COMMERCE_LOCALIZATION',
                'view' => 'geozones',
                'icon' => 'fa-solid fa-location-crosshairs',
                'children' => [
                    [
                        'title' => 'COM_J2COMMERCE_COUNTRIES',
                        'view' => 'countries',
                        'link' => 'index.php?option=com_j2commerce&view=countries',
                        'icon' => 'fa-solid fa-earth-americas'
                    ],
                    [
                        'title' => 'COM_J2COMMERCE_ZONES',
                        'view' => 'zones',
                        'link' => 'index.php?option=com_j2commerce&view=zones',
                        'icon' => 'fa-solid fa-location-dot'
                    ],
                    [
                        'title' => 'COM_J2COMMERCE_GEOZONES',
                        'view' => 'geozones',
                        'link' => 'index.php?option=com_j2commerce&view=geozones',
                        'icon' => 'fa-solid fa-border-none'
                    ],
                    [
                        'title' => 'COM_J2COMMERCE_CURRENCIES',
                        'view' => 'currencies',
                        'link' => 'index.php?option=com_j2commerce&view=currencies',
                        'icon' => 'fa-solid fa-dollar fa-dollar-sign'
                    ],
                    [
                        'title' => 'COM_J2COMMERCE_TAX_RATES',
                        'view' => 'taxrates',
                        'link' => 'index.php?option=com_j2commerce&view=taxrates',
                        'icon' => 'fa-solid fa-calculator'
                    ],
                    [
                        'title' => 'COM_J2COMMERCE_TAX_PROFILES',
                        'view' => 'taxprofiles',
                        'link' => 'index.php?option=com_j2commerce&view=taxprofiles',
                        'icon' => 'fa-solid fa fa-sitemap'
                    ],
                    [
                        'title' => 'COM_J2COMMERCE_LENGTHS',
                        'view' => 'lengths',
                        'link' => 'index.php?option=com_j2commerce&view=lengths',
                        'icon' => 'fa-solid fa-ruler-combined'
                    ],
                    [
                        'title' => 'COM_J2COMMERCE_WEIGHTS',
                        'view' => 'weights',
                        'link' => 'index.php?option=com_j2commerce&view=weights',
                        'icon' => 'fa-solid fa-weight-scale'
                    ]
                ]
            ];
        }

        // Design
        if ($user->authorise('core.manage', 'com_j2commerce')) {
            $designChildren = [];

            // Template overrides — super users only (can write arbitrary PHP)
            if ($user->authorise('core.admin')) {
                $designChildren[] = [
                    'title' => 'COM_J2COMMERCE_TEMPLATE_OVERRIDES',
                    'view' => 'overrides',
                    'link' => 'index.php?option=com_j2commerce&view=overrides',
                    'icon' => 'fa-solid fa-layer-group'
                ];
            }

            $designChildren[] = [
                'title' => 'COM_J2COMMERCE_TEMPLATES_EMAIL',
                'view' => 'emailtemplates',
                'link' => 'index.php?option=com_j2commerce&view=emailtemplates',
                'icon' => 'fa-solid fa-envelope'
            ];
            $designChildren[] = [
                'title' => 'COM_J2COMMERCE_TEMPLATES_INVOICE',
                'view' => 'invoice',
                'link' => 'index.php?option=com_j2commerce&view=invoicetemplates',
                'icon' => 'fa-solid fa-print'
            ];

            $items[] = [
                'title' => 'COM_J2COMMERCE_DESIGN',
                'view' => !empty($designChildren) && $designChildren[0]['view'] === 'overrides' ? 'overrides' : 'emailtemplates',
                'icon' => 'fa-solid fa-compass-drafting',
                'children' => $designChildren,
            ];
        }
        // Setup
        if ($canViewSetup) {
            $setupChildren = [];

            // Configuration (first item) — requires core.admin
            if ($user->authorise('core.admin', 'com_j2commerce')) {
                $setupChildren[] = [
                    'title' => 'COM_J2COMMERCE_CONFIGURATION',
                    'view' => 'configuration',
                    'link' => 'index.php?option=com_config&view=component&component=com_j2commerce&return=' . $encodedReturn,
                    'icon' => 'fa-solid fa-gear'
                ];
            }

            $setupChildren[] = [
                'title' => 'COM_J2COMMERCE_CUSTOM_FIELDS',
                'view' => 'customfields',
                'link' => 'index.php?option=com_j2commerce&view=customfields',
                'icon' => 'fa-solid fa-th-list'
            ];
            $setupChildren[] = [
                'title' => 'COM_J2COMMERCE_ORDER_STATUSES',
                'view' => 'orderstatuses',
                'link' => 'index.php?option=com_j2commerce&view=orderstatuses',
                'icon' => 'fa-solid fa-check-square'
            ];
            $setupChildren[] = [
                'title' => 'COM_J2COMMERCE_PAYMENT_METHODS',
                'view' => 'paymentmethods',
                'link' => 'index.php?option=com_j2commerce&view=paymentmethods',
                'icon' => 'fa-solid fa-credit-card'
            ];
            $setupChildren[] = [
                'title' => 'COM_J2COMMERCE_SHIPPING_METHODS',
                'view' => 'shippingmethods',
                'link' => 'index.php?option=com_j2commerce&view=shippingmethods',
                'icon' => 'fa-solid fa-truck-plane'
            ];
            $setupChildren[] = [
                'title' => 'COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER',
                'view' => 'shippingtroubles',
                'link' => 'index.php?option=com_j2commerce&view=shippingtroubles',
                'icon' => 'fa-solid fa-truck-medical'
            ];
            $setupChildren[] = [
                'title' => 'COM_J2COMMERCE_QUEUES',
                'view' => 'queues',
                'link' => 'index.php?option=com_j2commerce&view=queues',
                'icon' => 'fa-solid fa-list-check'
            ];

            $items[] = [
                'title' => 'COM_J2COMMERCE_SETUP',
                'view' => 'component',
                'icon' => 'fa-solid fa-cogs',
                'children' => $setupChildren
            ];
        }

        // Analytics (after Setup)
        if ($canViewReports) {
            $items[] = [
                'title' => 'COM_J2COMMERCE_ANALYTICS',
                'view' => 'analytics',
                'icon' => 'fa-solid fa-chart-line',
                'children' => [
                    [
                        'title' => 'COM_J2COMMERCE_STATISTICS_DASHBOARD',
                        'view' => 'analytics',
                        'link' => 'index.php?option=com_j2commerce&view=analytics',
                        'icon' => 'fa-solid fa-chart-pie'
                    ],
                    [
                        'title' => 'COM_J2COMMERCE_REPORTS',
                        'view' => 'reports',
                        'link' => 'index.php?option=com_j2commerce&view=reports',
                        'icon' => 'fa-solid fa-chart-bar'
                    ]
                ]
            ];
        }

        // Apps (last position)
        if ($user->authorise('core.manage', 'com_j2commerce')) {
            $items[] = [
                'title' => 'COM_J2COMMERCE_APPS',
                'view' => 'apps',
                'link' => 'index.php?option=com_j2commerce&view=apps',
                'icon' => 'fa-solid fa-puzzle-piece'
            ];
        }
        $j2pluginHelper = J2CommerceHelper::plugin();
        $j2pluginHelper->event('AddDashboardMenuInJ2Commerce', array(&$items));

        return $items;
    }

    /**
     * Get the active view
     *
     * @return string
     */
    public static function getActiveView(): string
    {
        $input = Factory::getApplication()->getInput();
        return $input->get('view', 'dashboard');
    }
}
