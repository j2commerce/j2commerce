<?php

/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_orders
 *
 * @copyright   (C) 2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Module\Orders\Administrator\Dispatcher;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Module\Orders\Administrator\Helper\OrdersHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    public function dispatch(): void
    {
        if (!J2CommerceHelper::canAccess('j2commerce.vieworders')) {
            return;
        }

        parent::dispatch();
    }

    protected function getLayoutData(): array
    {
        $app = Factory::getApplication();

        $data = parent::getLayoutData();

        $app->getLanguage()->load('com_j2commerce', JPATH_ADMINISTRATOR);

        /** @var OrdersHelper $helper */
        $helper = $this->getHelperFactory()->getHelper('OrdersHelper');

        $limit        = (int) $data['params']->get('limit', 5);
        $filterStatus = $data['params']->get('filter_status', []);

        $data['orders']      = $helper->getLatestOrders($limit, (array) $filterStatus);
        $data['date_format'] = ComponentHelper::getParams('com_j2commerce')->get('date_format', 'Y-m-d H:i:s');

        return $data;
    }
}
