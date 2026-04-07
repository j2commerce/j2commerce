<?php

/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.ShippingFree
 *
 * @copyright   Copyright (C) 2024-2026 J2Commerce, LLC. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use J2Commerce\Plugin\J2Commerce\ShippingFree\Extension\ShippingFree;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $dispatcher = $container->get(DispatcherInterface::class);
                $plugin     = PluginHelper::getPlugin('j2commerce', 'shipping_free');

                return new ShippingFree(
                    $dispatcher,
                    (array) $plugin,
                    $container->get('DatabaseDriver')
                );
            }
        );
    }
};
