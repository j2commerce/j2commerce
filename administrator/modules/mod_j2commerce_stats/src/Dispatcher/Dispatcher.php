<?php

/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_stats
 *
 * @copyright   (C) 2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Module\Stats\Administrator\Dispatcher;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Module\Stats\Administrator\Helper\StatsHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dispatcher class for mod_j2commerce_stats
 *
 * @since  6.0.0
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * The figures below are the ones the dashboard resolves j2commerce.viewreports for, so the
     * module answers to that action rather than to the component floor. Gating in dispatch()
     * rather than getLayoutData() because the base class stops only on false, which the array
     * return type there cannot express — an empty array still renders the module.
     */
    public function dispatch(): void
    {
        if (!J2CommerceHelper::canAccess('j2commerce.viewreports')) {
            return;
        }

        parent::dispatch();
    }

    protected function getLayoutData(): array
    {
        $app = Factory::getApplication();

        $data = parent::getLayoutData();

        // Load the component language file for shared strings
        $app->getLanguage()->load('com_j2commerce', JPATH_ADMINISTRATOR);

        /** @var StatsHelper $helper */
        $helper = $this->getHelperFactory()->getHelper('StatsHelper');

        // Get order statuses from params (default to all)
        $orderStatuses = $data['params']->get('order_status', ['*']);

        // Ensure it's an array
        if (!\is_array($orderStatuses)) {
            $orderStatuses = [$orderStatuses];
        }

        // Get all statistics
        $data['stats'] = $helper->getAllStats($orderStatuses);

        return $data;
    }
}
