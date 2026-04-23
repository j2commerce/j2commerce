<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\Service;

use J2Commerce\Component\J2commercemigrator\Administrator\Adapter\MigratorAdapterInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;

class AdapterRegistry
{
    /** @var MigratorAdapterInterface[]|null */
    private ?array $adapters = null;

    public function getAll(): array
    {
        if ($this->adapters !== null) {
            return $this->adapters;
        }

        // Import plugin group so CLI adapter commands see registered adapters.
        // In console apps, non-console plugin groups are not auto-imported.
        PluginHelper::importPlugin('j2commercemigrator');

        $dispatcher = Factory::getApplication()->getDispatcher();
        $event      = new \Joomla\Event\Event('onJ2CommerceMigratorRegister', ['result' => []]);

        $dispatcher->dispatch('onJ2CommerceMigratorRegister', $event);

        $result = $event->getArgument('result', []);

        $this->adapters = [];

        foreach (array_filter($result, static fn($a) => $a instanceof MigratorAdapterInterface) as $adapter) {
            $this->adapters[$adapter->getKey()] = $adapter;
        }

        return $this->adapters;
    }

    public function get(string $key): ?MigratorAdapterInterface
    {
        return $this->getAll()[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->getAll()[$key]);
    }
}
