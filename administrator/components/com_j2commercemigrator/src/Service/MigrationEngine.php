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
use J2Commerce\Component\J2commercemigrator\Administrator\Helper\MigrationLogger;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\Reader\JoomlaSourceReader;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\Reader\SourceDatabaseReaderInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

class MigrationEngine
{
    private DataTransformer $transformer;

    public function __construct(
        private DatabaseInterface $db,
        private MigrationLogger $logger,
        private ?SourceDatabaseReaderInterface $sourceReader = null
    ) {
        $this->transformer = new DataTransformer();
        $this->sourceReader ??= new JoomlaSourceReader($db);
    }

    public function setSourceReader(SourceDatabaseReaderInterface $reader): void
    {
        $this->sourceReader = $reader;
    }

    /** @return array<int, \J2Commerce\Component\J2commercemigrator\Administrator\Dto\TierDefinition> keyed by tier number */
    private function indexTiers(MigratorAdapterInterface $adapter): array
    {
        $indexed = [];
        foreach ($adapter->getTierDefinitions() as $tierDef) {
            $indexed[$tierDef->tier] = $tierDef;
        }
        return $indexed;
    }

    public function audit(MigratorAdapterInterface $adapter): array
    {
        $results  = [];
        $tableMap = $adapter->getTableMap();

        foreach ($this->indexTiers($adapter) as $tierNum => $tierDef) {
            $tierResults = [];

            foreach ($tierDef->tables as $sourceTable) {
                $targetTable = $tableMap[$sourceTable] ?? null;

                if (!$targetTable) {
                    $tierResults[$sourceTable] = ['error' => 'No mapping found'];
                    continue;
                }

                $bareTarget  = str_replace($this->db->getPrefix(), '', $targetTable);
                $bareTarget  = ltrim($bareTarget, '#__');
                $src         = $this->getSourceRowCount($sourceTable);
                $tgt         = $this->getTargetRowCount($bareTarget);

                $tierResults[$sourceTable] = [
                    'source_table'  => $sourceTable,
                    'target_table'  => $targetTable,
                    'source_count'  => $src['count'],
                    'source_error'  => $src['error'],
                    'target_count'  => $tgt['count'],
                    'target_error'  => $tgt['error'],
                    'status'        => ($src['error'] !== null || $tgt['error'] !== null)
                        ? 'error'
                        : $this->getTableStatus($src['count'], $tgt['count']),
                    'error'         => $tgt['error'] ?? $src['error'],
                ];
            }

            $results[$tierNum] = [
                'name'   => $tierDef->name,
                'tables' => $tierResults,
            ];
        }

        return ['success' => true, 'tiers' => $results, '_v' => 2];
    }

    public function runTier(
        MigratorAdapterInterface $adapter,
        int $tier,
        int $batchSize = 200,
        string $conflictMode = 'skip',
        array $conflictResolutions = []
    ): array {
        $tiers = $this->indexTiers($adapter);

        if (!isset($tiers[$tier])) {
            return ['error' => "Invalid tier: {$tier}"];
        }

        $tierDef  = $tiers[$tier];
        $tableMap = $adapter->getTableMap();
        $this->logger->tierStart($tier, $tierDef->name);

        $totals       = ['inserted' => 0, 'skipped' => 0, 'overwritten' => 0, 'merged' => 0, 'errors' => 0];
        $tableResults = [];
        $hasErrors    = false;

        foreach ($tierDef->tables as $sourceTable) {
            $targetTable = $tableMap[$sourceTable] ?? null;

            if (!$targetTable) {
                $tableResults[$sourceTable] = ['error' => 'No mapping found'];
                $hasErrors = true;
                continue;
            }

            $bareTarget = $this->bareTable($targetTable);

            try {
                $counts = $this->migrateTable($adapter, $sourceTable, $bareTarget, $batchSize, $conflictMode, $conflictResolutions);

                $tableResults[$sourceTable] = ['success' => true] + $counts;

                foreach ($totals as $key => &$val) {
                    $val += $counts[$key] ?? 0;
                }
                unset($val);
            } catch (\Throwable $e) {
                $this->logger->error("Table {$sourceTable}: " . $e->getMessage());
                $tableResults[$sourceTable] = ['error' => $e->getMessage()];
                $hasErrors = true;
                $totals['errors']++;
            }
        }

        $this->logger->tierEnd($tier, $tierDef->name, $totals['inserted'] + $totals['overwritten'] + $totals['merged']);

        return [
            'success'        => !$hasErrors,
            'tier'           => $tier,
            'name'           => $tierDef->name,
            'total_migrated' => $totals['inserted'] + $totals['overwritten'] + $totals['merged'],
            'counts'         => $totals,
            'tables'         => $tableResults,
            'has_errors'     => $hasErrors,
        ];
    }

    public function resetTier(MigratorAdapterInterface $adapter, int $tier): array
    {
        $tiers = $this->indexTiers($adapter);

        if (!isset($tiers[$tier])) {
            return ['error' => "Invalid tier: {$tier}"];
        }

        $tierDef  = $tiers[$tier];
        $tableMap = $adapter->getTableMap();
        $this->logger->info("Resetting tier {$tier}: {$tierDef->name}");

        $results = [];

        foreach ($tierDef->tables as $sourceTable) {
            $targetTable = $tableMap[$sourceTable] ?? null;

            if (!$targetTable) {
                continue;
            }

            $bareTarget = $this->bareTable($targetTable);

            try {
                $this->db->truncateTable('#__' . $bareTarget);
                $results[$bareTarget] = ['success' => true];
            } catch (\Throwable $e) {
                $this->logger->error("Reset {$bareTarget}: " . $e->getMessage());
                $results[$bareTarget] = ['error' => $e->getMessage()];
            }
        }

        return ['success' => true, 'tier' => $tier, 'tables' => $results];
    }

    public function getProgress(MigratorAdapterInterface $adapter): array
    {
        $progress = [];
        $tableMap = $adapter->getTableMap();

        foreach ($this->indexTiers($adapter) as $tierNum => $tierDef) {
            $sourceTotal = 0;
            $targetTotal = 0;
            $tables      = [];

            foreach ($tierDef->tables as $sourceTable) {
                $targetTable = $tableMap[$sourceTable] ?? null;

                if (!$targetTable) {
                    continue;
                }

                $bareTarget  = $this->bareTable($targetTable);
                $src         = $this->getSourceRowCount($sourceTable);
                $tgt         = $this->getTargetRowCount($bareTarget);

                $sourceTotal += $src['count'];
                $targetTotal += $tgt['count'];

                $tables[$sourceTable] = [
                    'source'       => $src['count'],
                    'source_error' => $src['error'],
                    'target'       => $tgt['count'],
                    'target_error' => $tgt['error'],
                ];
            }

            $progress[$tierNum] = [
                'name'         => $tierDef->name,
                'source_total' => $sourceTotal,
                'target_total' => $targetTotal,
                'tables'       => $tables,
                'status'       => match (true) {
                    $sourceTotal === 0           => 'empty',
                    $targetTotal === 0           => 'pending',
                    $targetTotal >= $sourceTotal => 'completed',
                    default                      => 'partial',
                },
            ];
        }

        return ['success' => true, 'tiers' => $progress];
    }

    public function migrateOneTable(
        MigratorAdapterInterface $adapter,
        string $sourceTable,
        int $batchSize = 200,
        string $conflictMode = 'skip',
        int $offset = 0,
        array $conflictResolutions = []
    ): array {
        $tableMap    = $adapter->getTableMap();
        $targetTable = $tableMap[$sourceTable] ?? null;

        if (!$targetTable) {
            return ['error' => "No mapping found for {$sourceTable}"];
        }

        $bareTarget = $this->bareTable($targetTable);

        $targetColumns = array_flip(
            array_column($this->describeTargetTable($bareTarget), 'Field')
        );

        $sourcePk = $this->sourceReader->getPrimaryKey($sourceTable) ?? 'id';
        $targetPk = $adapter->getConflictKey($bareTarget);

        $totalSource = $this->sourceReader->count($sourceTable);
        $counts      = ['inserted' => 0, 'skipped' => 0, 'overwritten' => 0, 'merged' => 0, 'errors' => 0];

        $skipOnly = $conflictMode === 'skip' && empty($conflictResolutions);

        if ($skipOnly && $this->sourceReader instanceof JoomlaSourceReader) {
            $rows = $this->fetchSourceWithSkipJoin($sourceTable, $bareTarget, $sourcePk, $targetPk, $batchSize, $offset);
            $existingCount     = $this->getTargetRowCount($bareTarget);
            $counts['skipped'] = $existingCount['count'];
        } else {
            $rows = $this->sourceReader->fetchBatch($sourceTable, $sourcePk, $offset, $batchSize);
        }

        if (empty($rows)) {
            return [
                'done'         => true,
                'offset'       => $offset,
                'inserted'     => 0,
                'skipped'      => $counts['skipped'],
                'overwritten'  => 0,
                'merged'       => 0,
                'total_source' => $totalSource,
            ];
        }

        $this->db->transactionStart();

        try {
            foreach ($rows as $row) {
                $transformed = $this->transformer->transformRow(
                    $row,
                    $sourceTable,
                    $bareTarget,
                    [],
                    $adapter->getTokenReplacements(),
                    $adapter->getRowTransformers()
                );
                $transformed = array_intersect_key($transformed, $targetColumns);
                $pkVal  = $transformed[$targetPk] ?? null;
                $rowKey = $bareTarget . ':' . $pkVal;
                $rowMode = $conflictResolutions[$rowKey] ?? $conflictMode;

                match ($rowMode) {
                    'overwrite' => $this->doOverwrite($bareTarget, $transformed, $targetPk, $counts),
                    'merge'     => $this->doMerge($bareTarget, $transformed, $targetPk, $counts),
                    default     => $this->doSkip($bareTarget, $transformed, $targetPk, $counts),
                };
            }

            $this->db->transactionCommit();
        } catch (\Throwable $e) {
            $this->db->transactionRollback();
            return ['error' => $e->getMessage()];
        }

        $rowCount  = count($rows);
        $newOffset = $offset + $batchSize;
        $done      = $rowCount < $batchSize;

        unset($rows);
        gc_collect_cycles();

        return [
            'done'         => $done,
            'offset'       => $newOffset,
            'inserted'     => $counts['inserted'],
            'skipped'      => $counts['skipped'],
            'overwritten'  => $counts['overwritten'],
            'merged'       => $counts['merged'],
            'total_source' => $totalSource,
        ];
    }

    public function normalizeOrderStatusCssClasses(): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('j2commerce_orderstatus_id'),
                $this->db->quoteName('orderstatus_name'),
                $this->db->quoteName('orderstatus_cssclass'),
            ])
            ->from($this->db->quoteName('#__j2commerce_orderstatuses'));

        $rows    = $this->db->setQuery($query)->loadAssocList();
        $updated = 0;
        $details = [];

        foreach ($rows as $row) {
            $newClass = $this->transformer->normalizeOrderStatusCssClass($row['orderstatus_cssclass']);

            if ($newClass === null) {
                continue;
            }

            $id = (int) $row['j2commerce_orderstatus_id'];

            $update = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__j2commerce_orderstatuses'))
                ->set($this->db->quoteName('orderstatus_cssclass') . ' = :css')
                ->where($this->db->quoteName('j2commerce_orderstatus_id') . ' = :id')
                ->bind(':css', $newClass)
                ->bind(':id', $id, ParameterType::INTEGER);

            $this->db->setQuery($update)->execute();
            $updated++;

            $details[] = [
                'id'  => $id,
                'name' => $row['orderstatus_name'],
                'old' => $row['orderstatus_cssclass'],
                'new' => $newClass,
            ];
        }

        return ['success' => true, 'updated' => $updated, 'total' => count($rows), 'details' => $details];
    }

    public function normalizeDateColumnDefaults(MigratorAdapterInterface $adapter): array
    {
        $tables  = array_values($adapter->getTableMap());
        $updated = [];

        foreach ($tables as $targetTable) {
            $bareTarget = $this->bareTable($targetTable);

            try {
                $columns = $this->describeTargetTable($bareTarget);
            } catch (\Throwable) {
                continue;
            }

            foreach ($columns as $col) {
                $default = $col['Default'] ?? null;

                if ($default !== '0000-00-00 00:00:00' && $default !== '0000-00-00') {
                    continue;
                }

                $colName = $col['Field'];
                $colType = $col['Type'];

                $sql = 'ALTER TABLE ' . $this->db->quoteName('#__' . $bareTarget)
                    . ' MODIFY ' . $this->db->quoteName($colName) . ' ' . $colType . ' NULL DEFAULT NULL';

                try {
                    $this->rawQuery($sql);
                    $updated[] = "{$bareTarget}.{$colName}";
                } catch (\Throwable $e) {
                    $this->logger->error("Failed to normalize {$bareTarget}.{$colName}: " . $e->getMessage());
                }
            }
        }

        return ['success' => true, 'updated' => $updated];
    }

    private function migrateTable(
        MigratorAdapterInterface $adapter,
        string $sourceTable,
        string $bareTarget,
        int $batchSize,
        string $conflictMode = 'skip',
        array $conflictResolutions = []
    ): array {
        $targetColumns = array_flip(
            array_column($this->describeTargetTable($bareTarget), 'Field')
        );

        if (empty($targetColumns)) {
            throw new \RuntimeException("Target table {$bareTarget} has no columns or does not exist");
        }

        $sourcePk = $this->sourceReader->getPrimaryKey($sourceTable) ?? 'id';
        $targetPk = $adapter->getConflictKey($bareTarget);

        $counts = ['inserted' => 0, 'skipped' => 0, 'overwritten' => 0, 'merged' => 0, 'errors' => 0];
        $offset = 0;

        while (true) {
            $rows = $this->sourceReader->fetchBatch($sourceTable, $sourcePk, $offset, $batchSize);

            if (empty($rows)) {
                break;
            }

            $this->db->transactionStart();

            try {
                foreach ($rows as $row) {
                    $transformed = $this->transformer->transformRow(
                        $row,
                        $sourceTable,
                        $bareTarget,
                        [],
                        $adapter->getTokenReplacements(),
                        $adapter->getRowTransformers()
                    );
                    $transformed = array_intersect_key($transformed, $targetColumns);
                    $pkVal       = $transformed[$targetPk] ?? null;
                    $rowKey      = $bareTarget . ':' . $pkVal;
                    $rowMode     = $conflictResolutions[$rowKey] ?? $conflictMode;

                    match ($rowMode) {
                        'overwrite' => $this->doOverwrite($bareTarget, $transformed, $targetPk, $counts),
                        'merge'     => $this->doMerge($bareTarget, $transformed, $targetPk, $counts),
                        default     => $this->doSkip($bareTarget, $transformed, $targetPk, $counts),
                    };
                }

                $this->db->transactionCommit();
            } catch (\Throwable $e) {
                $this->db->transactionRollback();
                $this->logger->rowError($sourceTable, 'batch', $e->getMessage());
                throw $e;
            }

            $rowCount = count($rows);
            $offset  += $batchSize;
            unset($rows);
            gc_collect_cycles();

            if ($rowCount < $batchSize) {
                break;
            }
        }

        $total = $counts['inserted'] + $counts['overwritten'] + $counts['merged'];
        $this->logger->info("Migrated {$total} rows ({$counts['skipped']} skipped): {$sourceTable} → {$bareTarget}");

        return $counts;
    }

    private function fetchSourceWithSkipJoin(
        string $sourceTable,
        string $bareTarget,
        string $sourcePk,
        string $targetPk,
        int $batchSize,
        int $offset
    ): array {
        $query = $this->db->getQuery(true)
            ->select('s.*')
            ->from($this->db->quoteName('#__' . $sourceTable, 's'))
            ->leftJoin(
                $this->db->quoteName('#__' . $bareTarget, 't')
                . ' ON t.' . $this->db->quoteName($targetPk)
                . ' = s.' . $this->db->quoteName($sourcePk)
            )
            ->where('t.' . $this->db->quoteName($targetPk) . ' IS NULL')
            ->order('s.' . $this->db->quoteName($sourcePk))
            ->setLimit($batchSize, $offset);

        return $this->db->setQuery($query)->loadAssocList() ?: [];
    }

    private function doSkip(string $bareTarget, array $data, string $targetPk, array &$counts): void
    {
        $parts = $this->buildInsertParts($data);

        $sql = 'INSERT IGNORE INTO ' . $this->db->quoteName('#__' . $bareTarget)
            . ' (' . implode(', ', $parts['columns']) . ')'
            . ' VALUES (' . implode(', ', $parts['values']) . ')';

        $this->rawQuery($sql);

        if ($this->db->getConnection()->affected_rows === 0) {
            $counts['skipped']++;
        } else {
            $counts['inserted']++;
        }
    }

    private function doOverwrite(string $bareTarget, array $data, string $targetPk, array &$counts): void
    {
        $parts   = $this->buildInsertParts($data);
        $updates = [];

        foreach ($data as $column => $value) {
            if ($column !== $targetPk) {
                $quoted    = $this->db->quoteName($column);
                $updates[] = $quoted . ' = VALUES(' . $quoted . ')';
            }
        }

        $sql = 'INSERT INTO ' . $this->db->quoteName('#__' . $bareTarget)
            . ' (' . implode(', ', $parts['columns']) . ')'
            . ' VALUES (' . implode(', ', $parts['values']) . ')'
            . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);

        $this->rawQuery($sql);

        $affected = $this->db->getConnection()->affected_rows;

        if ($affected === 1) {
            $counts['inserted']++;
        } elseif ($affected >= 2) {
            $counts['overwritten']++;
        } else {
            $counts['skipped']++;
        }
    }

    private function doMerge(string $bareTarget, array $data, string $targetPk, array &$counts): void
    {
        $pkVal = $data[$targetPk] ?? null;

        if ($pkVal === null) {
            $this->doSkip($bareTarget, $data, $targetPk, $counts);
            return;
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__' . $bareTarget))
            ->where($this->db->quoteName($targetPk) . ' = :pk')
            ->bind(':pk', $pkVal);

        $existing = $this->db->setQuery($query)->loadAssoc();

        if ($existing === null) {
            $this->doSkip($bareTarget, $data, $targetPk, $counts);
            return;
        }

        $merged  = $existing;
        $changed = false;

        foreach ($data as $col => $sourceVal) {
            if ($col === $targetPk || !array_key_exists($col, $existing)) {
                continue;
            }

            $targetVal = $existing[$col];

            if (($targetVal === null || $targetVal === '' || $targetVal === '0.00000') && $sourceVal !== null && $sourceVal !== '') {
                $merged[$col] = $sourceVal;
                $changed      = true;
            }
        }

        if (!$changed) {
            $counts['skipped']++;
            return;
        }

        $sets = [];

        foreach ($merged as $col => $val) {
            if ($col === $targetPk) {
                continue;
            }
            $quoted = $this->db->quoteName($col);
            $sets[] = $quoted . ' = ' . ($val === null ? 'NULL' : $this->db->quote((string) $val));
        }

        $sql = 'UPDATE ' . $this->db->quoteName('#__' . $bareTarget)
            . ' SET ' . implode(', ', $sets)
            . ' WHERE ' . $this->db->quoteName($targetPk) . ' = ' . $this->db->quote((string) $pkVal);

        $this->rawQuery($sql);
        $counts['merged']++;
    }

    private function rawQuery(string $sql): void
    {
        $sql    = str_replace('#__', $this->db->getPrefix(), $sql);
        $result = $this->db->getConnection()->query($sql);

        if ($result === false) {
            throw new \RuntimeException($this->db->getConnection()->error);
        }
    }

    private function buildInsertParts(array $data): array
    {
        $columns = [];
        $values  = [];

        foreach ($data as $column => $value) {
            $columns[] = $this->db->quoteName($column);

            $values[] = match (true) {
                $value === null          => 'NULL',
                is_int($value) || is_float($value) => $value,
                default                  => $this->db->quote((string) $value),
            };
        }

        return ['columns' => $columns, 'values' => $values];
    }

    private function describeTargetTable(string $bareTable): array
    {
        $resolved = $this->db->getPrefix() . $bareTable;

        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('COLUMN_NAME', 'Field'),
                $this->db->quoteName('COLUMN_TYPE', 'Type'),
                $this->db->quoteName('IS_NULLABLE', 'Null'),
                $this->db->quoteName('COLUMN_KEY', 'Key'),
                $this->db->quoteName('COLUMN_DEFAULT', 'Default'),
                $this->db->quoteName('EXTRA', 'Extra'),
            ])
            ->from($this->db->quoteName('INFORMATION_SCHEMA.COLUMNS'))
            ->where($this->db->quoteName('TABLE_SCHEMA') . ' = DATABASE()')
            ->where($this->db->quoteName('TABLE_NAME') . ' = :tableName')
            ->order($this->db->quoteName('ORDINAL_POSITION'))
            ->bind(':tableName', $resolved);

        return $this->db->setQuery($query)->loadAssocList() ?: [];
    }

    /**
     * @return array{count: int, error: ?string}
     */
    private function getSourceRowCount(string $bareTable): array
    {
        try {
            return ['count' => $this->sourceReader->count($bareTable), 'error' => null];
        } catch (\Throwable $e) {
            return ['count' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{count: int, error: ?string}
     */
    private function getTargetRowCount(string $bareTable): array
    {
        try {
            $query = $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from($this->db->quoteName('#__' . $bareTable));

            return ['count' => (int) $this->db->setQuery($query)->loadResult(), 'error' => null];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();

            if (stripos($msg, "doesn't exist") !== false || stripos($msg, 'does not exist') !== false) {
                return ['count' => 0, 'error' => 'Table not found'];
            }

            return ['count' => 0, 'error' => $msg];
        }
    }

    private function getTableStatus(int $source, int $target): string
    {
        return match (true) {
            $source === 0       => 'empty',
            $target === 0       => 'pending',
            $target >= $source  => 'completed',
            default             => 'partial',
        };
    }

    /**
     * Resolve the source→target column map for a single source table.
     *
     * This is the component-level replacement for the old TableMapper::getColumnMapping().
     * It routes source-table introspection through the adapter's source reader and
     * target-table introspection through the local Joomla DB.
     *
     * Column matching rules (in priority order):
     *   1. Adapter-supplied explicit map via getColumnMap() — used as-is.
     *   2. Exact name match between source and target columns.
     *   3. j2store_ prefix stripped and j2commerce_ prepended (e.g. j2store_product_id → j2commerce_product_id).
     *
     * Returns an array with keys:
     *   - 'mapping'       array<string, string|null>  source_col → target_col (null = drop)
     *   - 'extra_target'  list<string>                target columns with no source equivalent
     *   - 'source_count'  int
     *   - 'target_count'  int
     *   - 'error'         string (present only on failure)
     */
    public function resolveColumnMap(MigratorAdapterInterface $adapter, string $sourceTable): array
    {
        $tableMap    = $adapter->getTableMap();
        $targetTable = $tableMap[$sourceTable] ?? null;

        if ($targetTable === null) {
            return ['error' => "No table mapping found for source table '{$sourceTable}'"];
        }

        $bareTarget = $this->bareTable($targetTable);

        $sourceColumns = $this->sourceReader->describe($sourceTable);
        $targetColumns = $this->describeTargetTable($bareTarget);

        if (empty($sourceColumns) || empty($targetColumns)) {
            $missing = [];
            if (empty($sourceColumns)) {
                $missing[] = 'source ' . $sourceTable;
            }
            if (empty($targetColumns)) {
                $missing[] = 'target ' . $bareTarget;
            }
            return ['error' => 'Could not describe: ' . implode(' and ', $missing)];
        }

        $sourceNames = array_column($sourceColumns, 'Field');
        $targetNames = array_column($targetColumns, 'Field');

        // Start with adapter-supplied explicit overrides (may include null = drop).
        $explicit = $adapter->getColumnMap($sourceTable);
        $mapping  = [];

        foreach ($sourceNames as $col) {
            if (array_key_exists($col, $explicit)) {
                $mapping[$col] = $explicit[$col];
                continue;
            }

            if (in_array($col, $targetNames, true)) {
                $mapping[$col] = $col;
                continue;
            }

            $renamed = str_replace('j2store_', 'j2commerce_', $col);

            if (in_array($renamed, $targetNames, true)) {
                $mapping[$col] = $renamed;
            }
        }

        $mappedTargets = array_filter(array_values($mapping));
        $extraTarget   = array_diff($targetNames, $mappedTargets);

        return [
            'mapping'      => $mapping,
            'extra_target' => array_values($extraTarget),
            'source_count' => count($sourceNames),
            'target_count' => count($targetNames),
        ];
    }

    /**
     * Describe a source table, routing through the configured source reader.
     *
     * Equivalent to the old TableMapper::describeTable() for target tables; for the
     * source side this method delegates to SourceDatabaseReaderInterface::describe()
     * so that modes A, B, and C are all handled transparently.
     *
     * Returns INFORMATION_SCHEMA-style rows: Field, Type, Null, Key, Default, Extra.
     */
    public function describeSourceTable(string $bareTable): array
    {
        return $this->sourceReader->describe($bareTable);
    }

    private function bareTable(string $table): string
    {
        $prefix = $this->db->getPrefix();
        $table  = str_replace(['#__', $prefix], '', $table);
        return ltrim($table, '_');
    }
}
