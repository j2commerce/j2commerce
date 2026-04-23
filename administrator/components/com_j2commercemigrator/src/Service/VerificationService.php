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
use J2Commerce\Component\J2commercemigrator\Administrator\Service\Reader\JoomlaSourceReader;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\Reader\SourceDatabaseReaderInterface;
use Joomla\Database\DatabaseInterface;

class VerificationService
{
    public function __construct(
        private DatabaseInterface $db,
        private ?SourceDatabaseReaderInterface $sourceReader = null
    ) {
        $this->sourceReader ??= new JoomlaSourceReader($db);
    }

    public function setSourceReader(SourceDatabaseReaderInterface $reader): void
    {
        $this->sourceReader = $reader;
    }

    public function runAll(MigratorAdapterInterface $adapter): array
    {
        return [
            'success'               => true,
            'record_counts'         => $this->verifyRecordCounts($adapter),
            'referential_integrity' => $this->verifyReferentialIntegrity(),
            'financial_integrity'   => $this->verifyFinancialIntegrity(),
        ];
    }

    /** Tables whose record count is intentionally not verified (config is migrated differently). */
    private const SKIP_RECORD_COUNT = [
        'j2store_configurations',
    ];

    public function verifyRecordCounts(MigratorAdapterInterface $adapter): array
    {
        $results  = [];
        $tableMap = $adapter->getTableMap();

        foreach ($tableMap as $source => $target) {
            if (\in_array($source, self::SKIP_RECORD_COUNT, true)) {
                continue;
            }

            $bareTarget  = $this->bareTable($target);
            $sourceCount = $this->getSourceCount($source);
            $targetCount = $this->getTargetCount($bareTarget);

            $results[] = [
                'source_table' => $source,
                'target_table' => $bareTarget,
                'source_count' => $sourceCount,
                'target_count' => $targetCount,
                'match'        => $sourceCount === $targetCount,
            ];
        }

        return $results;
    }

    public function verifyReferentialIntegrity(): array
    {
        $checks = [
            [
                'name'         => 'Orders → Order Items',
                'parent_table' => '#__j2commerce_orders',
                'parent_pk'    => 'j2commerce_order_id',
                'child_table'  => '#__j2commerce_orderitems',
                'child_fk'     => 'order_id',
            ],
            [
                'name'         => 'Orders → Order Info',
                'parent_table' => '#__j2commerce_orders',
                'parent_pk'    => 'j2commerce_order_id',
                'child_table'  => '#__j2commerce_orderinfos',
                'child_fk'     => 'order_id',
            ],
            [
                'name'         => 'Orders → Order History',
                'parent_table' => '#__j2commerce_orders',
                'parent_pk'    => 'j2commerce_order_id',
                'child_table'  => '#__j2commerce_orderhistories',
                'child_fk'     => 'order_id',
            ],
            [
                'name'         => 'Products → Variants',
                'parent_table' => '#__j2commerce_products',
                'parent_pk'    => 'j2commerce_product_id',
                'child_table'  => '#__j2commerce_variants',
                'child_fk'     => 'product_id',
            ],
            [
                'name'         => 'Products → Product Images',
                'parent_table' => '#__j2commerce_products',
                'parent_pk'    => 'j2commerce_product_id',
                'child_table'  => '#__j2commerce_productimages',
                'child_fk'     => 'product_id',
            ],
            [
                'name'         => 'Carts → Cart Items',
                'parent_table' => '#__j2commerce_carts',
                'parent_pk'    => 'j2commerce_cart_id',
                'child_table'  => '#__j2commerce_cartitems',
                'child_fk'     => 'cart_id',
            ],
        ];

        $results = [];

        foreach ($checks as $check) {
            try {
                $query = $this->db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($this->db->quoteName($check['child_table'], 'c'))
                    ->leftJoin(
                        $this->db->quoteName($check['parent_table'], 'p')
                        . ' ON p.' . $this->db->quoteName($check['parent_pk'])
                        . ' = c.' . $this->db->quoteName($check['child_fk'])
                    )
                    ->where('p.' . $this->db->quoteName($check['parent_pk']) . ' IS NULL');

                $orphans = (int) $this->db->setQuery($query)->loadResult();

                $results[] = [
                    'check'   => $check['name'],
                    'orphans' => $orphans,
                    'passed'  => $orphans === 0,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'check'  => $check['name'],
                    'error'  => $e->getMessage(),
                    'passed' => false,
                ];
            }
        }

        return $results;
    }

    public function verifyFinancialIntegrity(): array
    {
        $checks = [];

        try {
            $sourceTotal = $this->getSourceSum('j2store_orders', 'order_total');
            $targetTotal = $this->getTargetSum('#__j2commerce_orders', 'order_total');

            $checks[] = [
                'check'        => 'Order Totals',
                'source_total' => $sourceTotal,
                'target_total' => $targetTotal,
                'match'        => abs($sourceTotal - $targetTotal) < 0.01,
            ];
        } catch (\Throwable $e) {
            $checks[] = [
                'check' => 'Order Totals',
                'error' => $e->getMessage(),
                'match' => false,
            ];
        }

        try {
            $sourceTotal = $this->getSourceSum('j2store_orderitems', 'orderitem_finalprice');
            $targetTotal = $this->getTargetSum('#__j2commerce_orderitems', 'orderitem_finalprice');

            $checks[] = [
                'check'        => 'Order Item Totals',
                'source_total' => $sourceTotal,
                'target_total' => $targetTotal,
                'match'        => abs($sourceTotal - $targetTotal) < 0.01,
            ];
        } catch (\Throwable $e) {
            $checks[] = [
                'check' => 'Order Item Totals',
                'error' => $e->getMessage(),
                'match' => false,
            ];
        }

        return $checks;
    }

    private function getSourceCount(string $bareTable): int
    {
        try {
            return $this->sourceReader->count($bareTable);
        } catch (\Throwable) {
            return -1;
        }
    }

    private function getTargetCount(string $bareTable): int
    {
        try {
            return (int) $this->db->setQuery(
                $this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName('#__' . $bareTable))
            )->loadResult();
        } catch (\Throwable) {
            return -1;
        }
    }

    private function getSourceSum(string $bareTable, string $column): float
    {
        if ($this->sourceReader instanceof JoomlaSourceReader) {
            return $this->getTargetSum('#__' . $bareTable, $column);
        }

        // For PDO reader, fetch all rows and sum in PHP
        $pk   = $this->sourceReader->getPrimaryKey($bareTable) ?? 'id';
        $rows = $this->sourceReader->fetchBatch($bareTable, $pk, 0, 100000);

        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float) ($row[$column] ?? 0);
        }

        return $total;
    }

    private function getTargetSum(string $qualifiedTable, string $column): float
    {
        return (float) $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('COALESCE(SUM(' . $this->db->quoteName($column) . '), 0)')
                ->from($this->db->quoteName($qualifiedTable))
        )->loadResult();
    }

    private function bareTable(string $table): string
    {
        $prefix = $this->db->getPrefix();
        $table  = str_replace(['#__', $prefix], '', $table);
        return ltrim($table, '_');
    }
}
