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
 * Dispatched for per-row lifecycle events.
 *
 * Event names:
 *   onJ2CommerceMigratorBeforeRow      — row is mutable (pre-write)
 *   onJ2CommerceMigratorAfterRow       — row is read-only; targetPk and result available
 *   onJ2CommerceMigratorTransformRow   — row is mutable (transformation pipeline)
 *
 * Plugin handlers use $event->setRow() to modify the row before it is written.
 */
final class MigratorRowEvent extends MigratorEvent
{
    public function getSourceTable(): string
    {
        return (string) $this->getArgument('sourceTable', '');
    }

    public function getTargetTable(): string
    {
        return (string) $this->getArgument('targetTable', '');
    }

    /** The row being migrated. Mutable for BeforeRow and TransformRow events. */
    public function getRow(): array
    {
        return (array) $this->getArgument('row', []);
    }

    public function setRow(array $row): void
    {
        $this->setArgument('row', $row);
    }

    /**
     * Available on AfterRow events — the PK value assigned in the target table.
     */
    public function getTargetPk(): int|string|null
    {
        return $this->getArgument('targetPk');
    }

    /**
     * Available on AfterRow events — outcome of the INSERT/UPDATE.
     * Typical values: 'inserted' | 'skipped' | 'overwritten' | 'merged'
     */
    public function getResult(): string
    {
        return (string) $this->getArgument('result', '');
    }
}
