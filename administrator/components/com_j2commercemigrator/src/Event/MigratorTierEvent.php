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

/**
 * Dispatched before and after each migration tier.
 *
 * Event names:
 *   onJ2CommerceMigratorBeforeTier  — context is mutable
 *   onJ2CommerceMigratorAfterTier   — counts available; context read-only by convention
 *
 * Plugin handlers use $event->setArgument('context', ...) to mutate the context bag.
 */
final class MigratorTierEvent extends MigratorEvent
{
    public function getTier(): int
    {
        return (int) $this->getArgument('tier', 0);
    }

    /** Mutable context bag passed between listeners. */
    public function getContext(): array
    {
        return (array) $this->getArgument('context', []);
    }

    public function setContext(array $context): void
    {
        $this->setArgument('context', $context);
    }

    /**
     * Counts available on AfterTier events.
     * Shape: [inserted, skipped, overwritten, merged, errors]
     */
    public function getCounts(): array
    {
        return (array) $this->getArgument('counts', []);
    }
}
