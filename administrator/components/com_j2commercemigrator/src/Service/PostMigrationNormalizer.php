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
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

class PostMigrationNormalizer
{
    private DataTransformer $transformer;

    public function __construct(
        private DatabaseInterface $db,
        private MigrationLogger $logger
    ) {
        $this->transformer = new DataTransformer();
    }

    /**
     * Rewrites Bootstrap 2 label-* CSS classes on migrated order statuses to
     * Bootstrap 5 badge text-bg-* equivalents.
     */
    public function normalizeOrderStatusCssClasses(): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('j2commerce_orderstatus_id'),
                $this->db->quoteName('orderstatus_name'),
                $this->db->quoteName('orderstatus_cssclass'),
            ])
            ->from($this->db->quoteName('#__j2commerce_orderstatuses'));

        $rows    = $this->db->setQuery($query)->loadAssocList() ?: [];
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
                'id'   => $id,
                'name' => $row['orderstatus_name'],
                'old'  => $row['orderstatus_cssclass'],
                'new'  => $newClass,
            ];

            $this->logger->info("Order status #{$id} ({$row['orderstatus_name']}): '{$row['orderstatus_cssclass']}' → '{$newClass}'");
        }

        return [
            'success' => true,
            'updated' => $updated,
            'total'   => count($rows),
            'details' => $details,
        ];
    }

    /**
     * For every date/datetime column whose DEFAULT was '0000-00-00 00:00:00':
     *  1. ALTERs the column to NULL DEFAULT NULL.
     *  2. UPDATEs existing rows that contain a zero date to NULL.
     *
     * Accepts an adapter so it can resolve the target table list without coupling
     * to MigrationEngine.
     */
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
                    $this->logger->info("Normalized date default: {$bareTarget}.{$colName} -> NULL");
                } catch (\Throwable $e) {
                    $this->logger->error("Failed to normalize {$bareTarget}.{$colName}: " . $e->getMessage());
                }
            }
        }

        // Convert existing zero-date rows to NULL
        foreach ($tables as $targetTable) {
            $bareTarget = $this->bareTable($targetTable);

            try {
                $columns = $this->describeTargetTable($bareTarget);
            } catch (\Throwable) {
                continue;
            }

            foreach ($columns as $col) {
                $colType = strtolower($col['Type']);

                if (!str_contains($colType, 'date') && !str_contains($colType, 'time')) {
                    continue;
                }

                $colName = $col['Field'];

                $sql = 'UPDATE ' . $this->db->quoteName('#__' . $bareTarget)
                    . ' SET ' . $this->db->quoteName($colName) . ' = NULL'
                    . ' WHERE ' . $this->db->quoteName($colName) . " = '0000-00-00 00:00:00'"
                    . ' OR ' . $this->db->quoteName($colName) . " = '0000-00-00'";

                try {
                    $this->rawQuery($sql);
                    $affected = $this->db->getConnection()->affected_rows;

                    if ($affected > 0) {
                        $updated[] = "{$bareTarget}.{$colName}: {$affected} rows";
                        $this->logger->info("Converted zero dates: {$bareTarget}.{$colName} ({$affected} rows)");
                    }
                } catch (\Throwable $e) {
                    $this->logger->error("Failed to convert zero dates in {$bareTarget}.{$colName}: " . $e->getMessage());
                }
            }
        }

        return ['success' => true, 'updated' => $updated];
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

    private function rawQuery(string $sql): void
    {
        $sql    = str_replace('#__', $this->db->getPrefix(), $sql);
        $result = $this->db->getConnection()->query($sql);

        if ($result === false) {
            throw new \RuntimeException($this->db->getConnection()->error);
        }
    }

    private function bareTable(string $table): string
    {
        $prefix = $this->db->getPrefix();
        $table  = str_replace(['#__', $prefix], '', $table);
        return ltrim($table, '_');
    }
}
