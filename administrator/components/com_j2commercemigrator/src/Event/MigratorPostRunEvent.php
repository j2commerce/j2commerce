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
 * Dispatched after every migration run, whether it succeeded or failed.
 *
 * Event name: onJ2CommerceMigratorPostRun
 *
 * Summary shape: [adapter, runId, success, counts, errorCount, duration]
 */
final class MigratorPostRunEvent extends MigratorEvent
{
    public function getRunId(): int
    {
        return (int) $this->getArgument('runId', 0);
    }

    public function isSuccess(): bool
    {
        return (bool) $this->getArgument('success', false);
    }

    /**
     * Summary bag containing the final run statistics.
     * Shape: {inserted, skipped, overwritten, merged, errors, duration}
     */
    public function getSummary(): array
    {
        return (array) $this->getArgument('summary', []);
    }
}
