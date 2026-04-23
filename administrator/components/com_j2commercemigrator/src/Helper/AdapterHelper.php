<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\Helper;

use J2Commerce\Component\J2commercemigrator\Administrator\Adapter\MigratorAdapterInterface;
use J2Commerce\Component\J2commercemigrator\Administrator\Dto\TierDefinition;

/**
 * Static convenience wrappers for adapter authors.
 *
 * All methods are pure functions — no side effects, no database access.
 * PRD reference: §23.3–§23.6.
 */
final class AdapterHelper
{
    // -------------------------------------------------------------------------
    // TierDefinition builders (§23.3)
    // -------------------------------------------------------------------------

    /** Build a single TierDefinition with an optional depends-on list. */
    public static function tier(int $tier, string $name, array $tables, array $dependsOn = []): TierDefinition
    {
        return new TierDefinition(tier: $tier, name: $name, tables: $tables, dependsOn: $dependsOn);
    }

    /**
     * Build a full tier list from a compact array spec.
     *
     * The spec format is:
     *   [ [tier, name, [tables], ?[dependsOn]], ... ]
     *
     * Example:
     *   AdapterHelper::tiersFromSpec([
     *       [1, 'Lookups',  ['src_countries', 'src_currencies']],
     *       [2, 'Catalog',  ['src_products'],                    [1]],
     *       [3, 'Orders',   ['src_orders'],                      [2]],
     *   ]);
     */
    public static function tiersFromSpec(array $spec): array
    {
        return array_map(
            static fn(array $row) => new TierDefinition(
                tier:      (int) $row[0],
                name:      (string) $row[1],
                tables:    (array) $row[2],
                dependsOn: (array) ($row[3] ?? []),
            ),
            $spec,
        );
    }

    // -------------------------------------------------------------------------
    // Column-map helpers (§23.3)
    // -------------------------------------------------------------------------

    /**
     * Build a column map by renaming a source prefix to a target prefix across all
     * listed column names.
     *
     * Example:
     *   AdapterHelper::prefixRenameMap(['wc_product_id', 'wc_price'], 'wc_', 'j2commerce_');
     *   // → ['wc_product_id' => 'j2commerce_product_id', 'wc_price' => 'j2commerce_price']
     */
    public static function prefixRenameMap(array $columns, string $fromPrefix, string $toPrefix): array
    {
        $map = [];

        foreach ($columns as $col) {
            $map[$col] = str_starts_with($col, $fromPrefix)
                ? $toPrefix . substr($col, strlen($fromPrefix))
                : $col;
        }

        return $map;
    }

    /**
     * Drop listed columns from a column map (set their target to null).
     *
     * Null-mapped columns are skipped by the MigrationEngine during INSERT.
     */
    public static function dropColumns(array $map, string ...$toDrop): array
    {
        foreach ($toDrop as $col) {
            $map[$col] = null;
        }

        return $map;
    }

    /**
     * Add static overrides to a column map, for columns that always receive
     * a fixed target name regardless of source naming.
     *
     * Example:
     *   AdapterHelper::withRenames($map, ['legacy_note' => null, 'old_sku' => 'product_sku']);
     */
    public static function withRenames(array $map, array $renames): array
    {
        return array_merge($map, $renames);
    }

    // -------------------------------------------------------------------------
    // Row transformer helpers (§23.4)
    // -------------------------------------------------------------------------

    /**
     * Build a timestamp pair ('created_at', 'updated_at') from Unix epoch columns.
     *
     * Many non-Joomla sources store timestamps as Unix integers; J2Commerce
     * expects 'Y-m-d H:i:s' strings.  Pass this as a pre-processor in your
     * row transformer.
     *
     * @param  array  $row         The source row.
     * @param  string $createdCol  Source column holding the created-at Unix timestamp.
     * @param  string $updatedCol  Source column holding the updated-at Unix timestamp.
     * @param  string $targetCreated  Target column name for the created timestamp.
     * @param  string $targetUpdated  Target column name for the updated timestamp.
     * @return array The row with timestamp columns converted.
     */
    public static function timestampsFromUnix(
        array  $row,
        string $createdCol  = 'created_at',
        string $updatedCol  = 'updated_at',
        string $targetCreated = 'created_on',
        string $targetUpdated = 'modified_on',
    ): array {
        if (isset($row[$createdCol]) && is_numeric($row[$createdCol])) {
            $row[$targetCreated] = date('Y-m-d H:i:s', (int) $row[$createdCol]);
            unset($row[$createdCol]);
        }

        if (isset($row[$updatedCol]) && is_numeric($row[$updatedCol])) {
            $row[$targetUpdated] = date('Y-m-d H:i:s', (int) $row[$updatedCol]);
            unset($row[$updatedCol]);
        }

        return $row;
    }

    /**
     * Convert a boolean-like column to an integer 0/1 flag.
     *
     * Common source representations: 'yes'/'no', 'true'/'false', 't'/'f',
     * '1'/'0', and PHP booleans.
     */
    public static function boolFromChar(array $row, string $column, string $targetColumn = ''): array
    {
        if (!array_key_exists($column, $row)) {
            return $row;
        }

        $target           = $targetColumn !== '' ? $targetColumn : $column;
        $raw              = $row[$column];
        $row[$target]     = match (true) {
            is_bool($raw)                                  => (int) $raw,
            in_array(strtolower((string) $raw), ['1', 'yes', 'true', 't', 'y'], true) => 1,
            default                                        => 0,
        };

        if ($targetColumn !== '' && $targetColumn !== $column) {
            unset($row[$column]);
        }

        return $row;
    }

    /**
     * Coerce an empty string to null for nullable columns.
     *
     * Useful when a source stores '' where J2Commerce expects NULL.
     */
    public static function emptyToNull(array $row, string ...$columns): array
    {
        foreach ($columns as $col) {
            if (array_key_exists($col, $row) && $row[$col] === '') {
                $row[$col] = null;
            }
        }

        return $row;
    }

    /**
     * Rename a single column in a row — optionally dropping the old key.
     */
    public static function renameColumn(array $row, string $from, string $to): array
    {
        if (array_key_exists($from, $row)) {
            $row[$to] = $row[$from];
            unset($row[$from]);
        }

        return $row;
    }

    // -------------------------------------------------------------------------
    // Idmap shortcut lookups (§23.3)
    // -------------------------------------------------------------------------

    /**
     * Resolve a source PK to a target PK using the idmap repository.
     *
     * Convenience wrapper so adapter row-transformers don't need to import
     * IdmapRepository directly.
     *
     * @param  \J2Commerce\Component\J2commercemigrator\Administrator\Service\IdmapRepository $idmap
     * @param  string $adapter  Adapter key, e.g. 'j2store4'.
     * @param  string $entity   Logical entity name, e.g. 'product'.
     * @param  int    $sourceId Source primary key value.
     * @return int|null         Resolved target PK, or null if not yet migrated.
     */
    public static function resolveId(
        \J2Commerce\Component\J2commercemigrator\Administrator\Service\IdmapRepository $idmap,
        string $adapter,
        string $entity,
        int    $sourceId,
    ): ?int {
        return $idmap->lookupTarget($adapter, $entity, $sourceId);
    }

    // -------------------------------------------------------------------------
    // Prerequisite check helpers (§23.3)
    // -------------------------------------------------------------------------

    /**
     * Build a structured issue record for PrerequisiteReport.
     *
     * @param  string $message   Human-readable description of the problem.
     * @param  string $severity  'error' (blocks migration) or 'warning' (advisory).
     */
    public static function issue(string $message, string $severity = 'error'): array
    {
        return ['severity' => $severity, 'message' => $message];
    }

    /** Build a warning-level issue (does not block migration). */
    public static function warning(string $message): array
    {
        return self::issue($message, 'warning');
    }

    /** Build an error-level issue (blocks migration until resolved). */
    public static function error(string $message): array
    {
        return self::issue($message, 'error');
    }

    // -------------------------------------------------------------------------
    // Table name helpers
    // -------------------------------------------------------------------------

    /**
     * Strip a table prefix from a bare table name.
     *
     * Useful when a source stores table names with their own prefix
     * (e.g. WooCommerce 'wp_wc_orders') and you need the suffix only.
     */
    public static function stripPrefix(string $table, string $prefix): string
    {
        return str_starts_with($table, $prefix) ? substr($table, strlen($prefix)) : $table;
    }

    /**
     * Derive a J2Commerce target table name from a source table name by
     * replacing a source prefix with the j2commerce_ prefix.
     *
     * Example:
     *   AdapterHelper::toJ2CommerceTable('wc_orders', 'wc_');
     *   // → 'j2commerce_orders'
     */
    public static function toJ2CommerceTable(string $sourceTable, string $sourcePrefix): string
    {
        return 'j2commerce_' . self::stripPrefix($sourceTable, $sourcePrefix);
    }

    /**
     * Build a full TABLE_MAP from a list of source table names by applying
     * a source prefix → j2commerce_ substitution.
     *
     * Example:
     *   AdapterHelper::buildTableMap(['wc_products', 'wc_orders'], 'wc_');
     *   // → ['wc_products' => 'j2commerce_products', 'wc_orders' => 'j2commerce_orders']
     */
    public static function buildTableMap(array $sourceTables, string $sourcePrefix): array
    {
        $map = [];

        foreach ($sourceTables as $table) {
            $map[$table] = self::toJ2CommerceTable($table, $sourcePrefix);
        }

        return $map;
    }
}
