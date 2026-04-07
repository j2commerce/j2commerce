<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_webservices_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\WebServices\J2Commerce\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Event\Application\BeforeApiRouteEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\ApiRouter;
use Joomla\Event\SubscriberInterface;
use Joomla\Router\Route;

final class J2Commerce extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeApiRoute' => 'onBeforeApiRoute',
        ];
    }

    public function onBeforeApiRoute(BeforeApiRouteEvent $event): void
    {
        $router    = $event->getRouter();
        $component = ['component' => 'com_j2commerce'];

        // Products — public GET, authenticated write
        $router->createCRUDRoutes('v1/j2commerce/products', 'products', $component);

        // Orders — authenticated
        $router->createCRUDRoutes('v1/j2commerce/orders', 'orders', $component);

        // Customers — authenticated
        $router->createCRUDRoutes('v1/j2commerce/customers', 'customers', $component);

        // Inventory — authenticated
        $router->createCRUDRoutes('v1/j2commerce/inventory', 'inventory', $component);

        // Coupons — authenticated CRUD
        $router->createCRUDRoutes('v1/j2commerce/coupons', 'coupons', $component);

        // Vouchers — authenticated CRUD
        $router->createCRUDRoutes('v1/j2commerce/vouchers', 'vouchers', $component);

        // Catalog reference data — public GET
        $router->createCRUDRoutes('v1/j2commerce/manufacturers', 'manufacturers', $component);
        $router->createCRUDRoutes('v1/j2commerce/currencies', 'currencies', $component);
        $router->createCRUDRoutes('v1/j2commerce/countries', 'countries', $component);
        $router->createCRUDRoutes('v1/j2commerce/zones', 'zones', $component);

        // Shipping & Payment — authenticated
        $router->createCRUDRoutes('v1/j2commerce/shippingmethods', 'shippingmethods', $component);
        $router->createCRUDRoutes('v1/j2commerce/paymentmethods', 'paymentmethods', $component);

        // Tax — authenticated
        $router->createCRUDRoutes('v1/j2commerce/taxprofiles', 'taxprofiles', $component);
        $router->createCRUDRoutes('v1/j2commerce/taxrates', 'taxrates', $component);

        // Order statuses — authenticated
        $router->createCRUDRoutes('v1/j2commerce/orderstatuses', 'orderstatuses', $component);

        // Nested & custom routes
        $this->createNestedRoutes($router, $component);
        $this->createReportRoutes($router, $component);
        $this->createConfigRoutes($router, $component);
    }

    private function createNestedRoutes(ApiRouter $router, array $defaults): void
    {
        $private = array_merge(['public' => false], $defaults);

        $router->addRoutes([
            // Order items
            new Route(['GET'], 'v1/j2commerce/orders/:id/items', 'orderitems.displayList', ['id' => '(\d+)'], $private),

            // Order history
            new Route(['GET'], 'v1/j2commerce/orders/:id/history', 'orderhistories.displayList', ['id' => '(\d+)'], $private),
            new Route(['POST'], 'v1/j2commerce/orders/:id/history', 'orderhistories.add', ['id' => '(\d+)'], $private),

            // Product variants
            new Route(['GET'], 'v1/j2commerce/products/:id/variants', 'variants.displayList', ['id' => '(\d+)'], $private),

            // Customer addresses
            new Route(['GET'], 'v1/j2commerce/customers/:id/addresses', 'addresses.displayList', ['id' => '(\d+)'], $private),

            // Customer orders
            new Route(['GET'], 'v1/j2commerce/customers/:id/orders', 'customerorders.displayList', ['id' => '(\d+)'], $private),

            // Country zones
            new Route(['GET'], 'v1/j2commerce/countries/:id/zones', 'zones.displayList', ['id' => '(\d+)'], $private),
        ]);
    }

    private function createReportRoutes(ApiRouter $router, array $defaults): void
    {
        $private = array_merge(['public' => false], $defaults);

        $router->addRoutes([
            new Route(['GET'], 'v1/j2commerce/reports/sales', 'reports.displayList', [], array_merge($private, ['report_type' => 'sales'])),
            new Route(['GET'], 'v1/j2commerce/reports/products', 'reports.displayList', [], array_merge($private, ['report_type' => 'products'])),
            new Route(['GET'], 'v1/j2commerce/reports/customers', 'reports.displayList', [], array_merge($private, ['report_type' => 'customers'])),
            new Route(['GET'], 'v1/j2commerce/reports/inventory', 'reports.displayList', [], array_merge($private, ['report_type' => 'inventory'])),
        ]);
    }

    private function createConfigRoutes(ApiRouter $router, array $defaults): void
    {
        $private = array_merge(['public' => false], $defaults);

        $router->addRoutes([
            new Route(['GET'], 'v1/j2commerce/config', 'config.displayList', [], $private),
        ]);
    }
}
