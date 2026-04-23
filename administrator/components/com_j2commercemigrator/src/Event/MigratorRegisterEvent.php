<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\Event;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commercemigrator\Administrator\Adapter\MigratorAdapterInterface;

/**
 * Dispatched at component boot so adapter plugins can register themselves.
 *
 * Event name: onJ2CommerceMigratorRegister
 *
 * Plugin handlers MUST use $event->addAdapter() or $event->setArgument('result', ...) to register.
 * Do NOT return values from SubscriberInterface handlers — the Joomla 6 dispatcher discards them.
 */
final class MigratorRegisterEvent extends MigratorEvent
{
    public function __construct(string $name, array $arguments = [])
    {
        $arguments['result'] ??= [];
        parent::__construct($name, $arguments);
    }

    public function addAdapter(MigratorAdapterInterface $adapter): void
    {
        $result   = $this->getArgument('result', []);
        $result[] = $adapter;
        $this->setArgument('result', $result);
    }

    /** @return MigratorAdapterInterface[] */
    public function getAdapters(): array
    {
        return (array) $this->getArgument('result', []);
    }
}
