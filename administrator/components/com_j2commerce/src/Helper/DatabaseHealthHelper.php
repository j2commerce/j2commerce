<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Helper;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Service\ProductService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Event\Event as GenericEvent;

/**
 * Registry + batched runner for the Dashboard "Database Health" card
 * (docs/plans/dashboard_database_health_card_prd.md). Every count is a cheap read query;
 * every fix is a bounded batch so a large store never locks a table for long.
 */
final class DatabaseHealthHelper
{
    /** Rows per statement, so row locks stay off the table while checkout is live. */
    private const BATCH_SIZE = 500;

    /** Work ceiling for a single run: the rest is picked up by the next click. */
    private const MAX_BATCHES_PER_RUN = 20;

    public static function getResults(): array
    {
        $db      = self::getDatabase();
        $results = [];

        foreach (self::getCheckDefinitions() as $check) {
            $count = 0;

            try {
                $count = (int) ($check['count'])($db);
            } catch (\Throwable $e) {
                Log::add('Database health check "' . $check['id'] . '" failed: ' . $e->getMessage(), Log::WARNING, 'com_j2commerce');
            }

            $results[] = [
                'id'                 => $check['id'],
                'label'              => Text::_($check['labelKey']),
                'description'        => Text::_($check['descriptionKey']),
                'count'              => $count,
                'repairable'         => $check['repairable'],
                'destructive'        => $check['destructive'],
                'setupGuideLink'     => $check['setupGuideLink'] ?? false,
                'reviewUrl'          => $check['reviewUrl'] ?? null,
                'destructiveWarning' => Text::_($check['destructiveWarningKey'] ?? 'COM_J2COMMERCE_DATABASE_HEALTH_DESTRUCTIVE_WARNING'),
            ];
        }

        return ['checks' => $results];
    }

    /** @throws \InvalidArgumentException When $id is unknown or not repairable. */
    public static function runFix(string $id): array
    {
        $check = null;

        foreach (self::getCheckDefinitions() as $candidate) {
            if ($candidate['id'] === $id) {
                $check = $candidate;
                break;
            }
        }

        if ($check === null || !$check['repairable'] || $check['fix'] === null) {
            throw new \InvalidArgumentException('Unknown or non-repairable check: ' . $id);
        }

        $db    = self::getDatabase();
        $fixed = (int) ($check['fix'])($db);

        return [
            'id'        => $id,
            'fixed'     => $fixed,
            'remaining' => (int) ($check['count'])($db),
        ];
    }

    private static function getDatabase(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }

    /** @return array[] Check definitions, merged with anything a plugin adds via onJ2CommerceGetHealthChecks. */
    private static function getCheckDefinitions(): array
    {
        $checks = self::checks();

        try {
            $event = new GenericEvent('onJ2CommerceGetHealthChecks', ['checks' => $checks]);
            Factory::getApplication()->getDispatcher()->dispatch('onJ2CommerceGetHealthChecks', $event);
            $checks = $event->getArgument('checks', $checks);
        } catch (\Throwable) {
            // A plugin error must never break the dashboard card.
        }

        return $checks;
    }

    private static function checks(): array
    {
        return [
            [
                'id'              => 'cart_gc_backlog',
                'labelKey'        => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_CART_GC_BACKLOG_LABEL',
                'descriptionKey'  => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_CART_GC_BACKLOG_DESC',
                'repairable'      => true,
                'destructive'     => false,
                'setupGuideLink'  => true,
                'count'           => [self::class, 'countCartGcBacklog'],
                'fix'             => [self::class, 'fixCartGcBacklog'],
            ],
            [
                'id'              => 'orphan_cartitems',
                'labelKey'        => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_CARTITEMS_LABEL',
                'descriptionKey'  => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_CARTITEMS_DESC',
                'repairable'      => true,
                'destructive'     => true,
                'count'           => [self::class, 'countOrphanCartitems'],
                'fix'             => [self::class, 'fixOrphanCartitems'],
            ],
            [
                'id'              => 'orphan_productquantities',
                'labelKey'        => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_PRODUCTQUANTITIES_LABEL',
                'descriptionKey'  => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_PRODUCTQUANTITIES_DESC',
                'repairable'      => true,
                'destructive'     => false,
                'count'           => [self::class, 'countOrphanProductquantities'],
                'fix'             => [self::class, 'fixOrphanProductquantities'],
            ],
            [
                'id'              => 'missing_productquantity',
                'labelKey'        => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_MISSING_PRODUCTQUANTITY_LABEL',
                'descriptionKey'  => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_MISSING_PRODUCTQUANTITY_DESC',
                'repairable'      => true,
                'destructive'     => false,
                'count'           => [self::class, 'countMissingProductquantity'],
                'fix'             => [self::class, 'fixMissingProductquantity'],
            ],
            [
                'id'              => 'stale_on_hold',
                'labelKey'        => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_STALE_ON_HOLD_LABEL',
                'descriptionKey'  => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_STALE_ON_HOLD_DESC',
                'repairable'      => true,
                'destructive'     => false,
                'count'           => [self::class, 'countStaleOnHold'],
                'fix'             => [self::class, 'fixStaleOnHold'],
            ],
            [
                'id'              => 'orphan_productimages',
                'labelKey'        => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_PRODUCTIMAGES_LABEL',
                'descriptionKey'  => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_PRODUCTIMAGES_DESC',
                'repairable'      => true,
                'destructive'     => false,
                'count'           => [self::class, 'countOrphanProductimages'],
                'fix'             => [self::class, 'fixOrphanProductimages'],
            ],
            [
                'id'              => 'zero_date_variants',
                'labelKey'        => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ZERO_DATE_VARIANTS_LABEL',
                'descriptionKey'  => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ZERO_DATE_VARIANTS_DESC',
                'repairable'      => true,
                'destructive'     => false,
                'count'           => [self::class, 'countZeroDateVariants'],
                'fix'             => [self::class, 'fixZeroDateVariants'],
            ],
            [
                'id'              => 'price_index_stale',
                'labelKey'        => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_PRICE_INDEX_STALE_LABEL',
                'descriptionKey'  => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_PRICE_INDEX_STALE_DESC',
                'repairable'      => true,
                'destructive'     => false,
                'count'           => [self::class, 'countPriceIndexStale'],
                'fix'             => [self::class, 'fixPriceIndexStale'],
            ],
            [
                'id'                     => 'orders_without_items',
                'labelKey'               => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORDERS_WITHOUT_ITEMS_LABEL',
                'descriptionKey'         => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORDERS_WITHOUT_ITEMS_DESC',
                'repairable'             => true,
                'destructive'            => true,
                'destructiveWarningKey'  => 'COM_J2COMMERCE_DATABASE_HEALTH_ORDERS_WITHOUT_ITEMS_DESTRUCTIVE_WARNING',
                'count'                  => [self::class, 'countOrdersWithoutItems'],
                'fix'                    => [self::class, 'fixOrdersWithoutItems'],
            ],
            [
                'id'                     => 'orphan_orderitems',
                'labelKey'               => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_ORDERITEMS_LABEL',
                'descriptionKey'         => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_ORDERITEMS_DESC',
                'repairable'             => true,
                'destructive'            => true,
                'destructiveWarningKey'  => 'COM_J2COMMERCE_DATABASE_HEALTH_ORPHAN_ORDERITEMS_DESTRUCTIVE_WARNING',
                'count'                  => [self::class, 'countOrphanOrderitems'],
                'fix'                    => [self::class, 'fixOrphanOrderitems'],
            ],
            [
                'id'                    => 'orphan_orderhistories',
                'labelKey'              => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_ORDERHISTORIES_LABEL',
                'descriptionKey'        => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_ORDERHISTORIES_DESC',
                'repairable'            => true,
                'destructive'           => true,
                'destructiveWarningKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_ORDERHISTORIES_DESTRUCTIVE_WARNING',
                'count'                 => [self::class, 'countOrphanOrderhistories'],
                'fix'                   => [self::class, 'fixOrphanOrderhistories'],
            ],
            [
                'id'              => 'products_without_master_variant',
                'labelKey'        => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_PRODUCTS_WITHOUT_MASTER_VARIANT_LABEL',
                'descriptionKey'  => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_PRODUCTS_WITHOUT_MASTER_VARIANT_DESC',
                'repairable'      => false,
                'destructive'     => false,
                'reviewUrl'       => 'index.php?option=com_j2commerce&view=databasehealthproducts&tmpl=component',
                'count'           => [self::class, 'countProductsWithoutMasterVariant'],
                'fix'             => null,
            ],
            [
                'id'              => 'migrator_residue',
                'labelKey'        => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_MIGRATOR_RESIDUE_LABEL',
                'descriptionKey'  => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_MIGRATOR_RESIDUE_DESC',
                'repairable'      => false,
                'destructive'     => false,
                'count'           => [self::class, 'countMigratorResidue'],
                'fix'             => null,
            ],
        ];
    }

    // =========================================================================
    // Cart GC backlog — reuses AppDiagnostics::clearOutdatedCartData(), never
    // re-implements the delete. Same retention term and UTC cutoff as the GC.
    // =========================================================================

    private static function cartRetentionCutoff(): string
    {
        $days = (int) J2CommerceHelper::config()->get('clear_outdated_cart_data_term', 90);

        return Factory::getDate('now -' . ($days * 1440) . ' minutes')->toSql();
    }

    public static function countCartGcBacklog(DatabaseInterface $db): int
    {
        $cartType = 'cart';
        $cutoff   = self::cartRetentionCutoff();

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_carts'))
            ->where($db->quoteName('cart_type') . ' = :cartType')
            ->where($db->quoteName('modified_on') . ' <= :cutoff')
            ->bind(':cartType', $cartType)
            ->bind(':cutoff', $cutoff);

        return (int) $db->setQuery($query)->loadResult();
    }

    public static function fixCartGcBacklog(DatabaseInterface $db): int
    {
        $before = self::countCartGcBacklog($db);

        if ($before === 0) {
            return 0;
        }

        PluginHelper::importPlugin('j2commerce');
        $dispatcher = Factory::getApplication()->getDispatcher();
        $dispatcher->dispatch('onJ2CommerceProcessCron', new GenericEvent('onJ2CommerceProcessCron', ['command' => 'clear_cart']));

        return $before - self::countCartGcBacklog($db);
    }

    // =========================================================================
    // Orphan cartitems — the GC's blind spot (docs/plans/dashboard_database_health_card_prd.md
    // "orphan_cartitems — the predicate"). Never touches cart_type = 'wishlist' rows unless
    // the product or variant itself is gone.
    // =========================================================================

    private static function orphanCartitemsQuery(DatabaseInterface $db, string $cutoff): QueryInterface
    {
        $cartType = 'cart';

        return $db->getQuery(true)
            ->from($db->quoteName('#__j2commerce_cartitems', 'ci'))
            ->leftJoin($db->quoteName('#__j2commerce_carts', 'c') . ' ON ' . $db->quoteName('c.j2commerce_cart_id') . ' = ' . $db->quoteName('ci.cart_id'))
            ->leftJoin($db->quoteName('#__j2commerce_products', 'p') . ' ON ' . $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('ci.product_id'))
            ->leftJoin($db->quoteName('#__j2commerce_variants', 'v') . ' ON ' . $db->quoteName('v.j2commerce_variant_id') . ' = ' . $db->quoteName('ci.variant_id'))
            // A cart line carries product_id/variant_id 0 when the master variant was missing at
            // add-to-cart time — seven Cart* behaviours coalesce to 0 for exactly that case. A 0
            // never matches the join, so without these guards the miss branches would delete a
            // live line for the condition products_without_master_variant deliberately only reports.
            ->where(
                '(' . $db->quoteName('c.j2commerce_cart_id') . ' IS NULL'
                . ' OR (' . $db->quoteName('ci.product_id') . ' <> 0 AND ' . $db->quoteName('p.j2commerce_product_id') . ' IS NULL)'
                . ' OR (' . $db->quoteName('ci.variant_id') . ' <> 0 AND ' . $db->quoteName('v.j2commerce_variant_id') . ' IS NULL)'
                . ' OR (' . $db->quoteName('c.cart_type') . ' = :cartType AND ' . $db->quoteName('c.modified_on') . ' <= :cutoff))'
            )
            ->bind(':cartType', $cartType)
            ->bind(':cutoff', $cutoff);
    }

    public static function countOrphanCartitems(DatabaseInterface $db): int
    {
        $query = self::orphanCartitemsQuery($db, self::cartRetentionCutoff())
            ->select('COUNT(DISTINCT ' . $db->quoteName('ci.j2commerce_cartitem_id') . ')');

        return (int) $db->setQuery($query)->loadResult();
    }

    public static function fixOrphanCartitems(DatabaseInterface $db): int
    {
        $cutoff    = self::cartRetentionCutoff();
        $processed = 0;

        for ($batch = 0; $batch < self::MAX_BATCHES_PER_RUN; $batch++) {
            $query = self::orphanCartitemsQuery($db, $cutoff)
                ->select($db->quoteName('ci.j2commerce_cartitem_id'))
                ->setLimit(self::BATCH_SIZE);

            $ids = array_map('intval', (array) $db->setQuery($query)->loadColumn());

            if (empty($ids)) {
                break;
            }

            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__j2commerce_cartitems'))
                    ->whereIn($db->quoteName('j2commerce_cartitem_id'), $ids)
            )->execute();

            $processed += \count($ids);
        }

        if ($processed > 0) {
            CartHelper::flushCartCounts();
        }

        return $processed;
    }

    // =========================================================================
    // Orphan productquantities / missing productquantity
    // =========================================================================

    public static function countOrphanProductquantities(DatabaseInterface $db): int
    {
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_productquantities', 'pq'))
            ->leftJoin($db->quoteName('#__j2commerce_variants', 'v') . ' ON ' . $db->quoteName('v.j2commerce_variant_id') . ' = ' . $db->quoteName('pq.variant_id'))
            ->where($db->quoteName('v.j2commerce_variant_id') . ' IS NULL');

        return (int) $db->setQuery($query)->loadResult();
    }

    public static function fixOrphanProductquantities(DatabaseInterface $db): int
    {
        $processed = 0;

        for ($batch = 0; $batch < self::MAX_BATCHES_PER_RUN; $batch++) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('pq.j2commerce_productquantity_id'))
                ->from($db->quoteName('#__j2commerce_productquantities', 'pq'))
                ->leftJoin($db->quoteName('#__j2commerce_variants', 'v') . ' ON ' . $db->quoteName('v.j2commerce_variant_id') . ' = ' . $db->quoteName('pq.variant_id'))
                ->where($db->quoteName('v.j2commerce_variant_id') . ' IS NULL')
                ->setLimit(self::BATCH_SIZE);

            $ids = array_map('intval', (array) $db->setQuery($query)->loadColumn());

            if (empty($ids)) {
                break;
            }

            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__j2commerce_productquantities'))
                    ->whereIn($db->quoteName('j2commerce_productquantity_id'), $ids)
            )->execute();

            $processed += \count($ids);
        }

        return $processed;
    }

    public static function countMissingProductquantity(DatabaseInterface $db): int
    {
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_variants', 'v'))
            ->leftJoin($db->quoteName('#__j2commerce_productquantities', 'pq') . ' ON ' . $db->quoteName('pq.variant_id') . ' = ' . $db->quoteName('v.j2commerce_variant_id'))
            ->where($db->quoteName('pq.variant_id') . ' IS NULL');

        return (int) $db->setQuery($query)->loadResult();
    }

    public static function fixMissingProductquantity(DatabaseInterface $db): int
    {
        $processed = 0;

        for ($batch = 0; $batch < self::MAX_BATCHES_PER_RUN; $batch++) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('v.j2commerce_variant_id'))
                ->from($db->quoteName('#__j2commerce_variants', 'v'))
                ->leftJoin($db->quoteName('#__j2commerce_productquantities', 'pq') . ' ON ' . $db->quoteName('pq.variant_id') . ' = ' . $db->quoteName('v.j2commerce_variant_id'))
                ->where($db->quoteName('pq.variant_id') . ' IS NULL')
                ->setLimit(self::BATCH_SIZE);

            $ids = array_map('intval', (array) $db->setQuery($query)->loadColumn());

            if (empty($ids)) {
                break;
            }

            $rows = [];

            foreach ($ids as $id) {
                $rows[] = '(' . $id . ', 0, 0, 0, ' . $db->quote('') . ')';
            }

            $db->setQuery(
                'INSERT IGNORE INTO ' . $db->quoteName('#__j2commerce_productquantities')
                . ' (' . $db->quoteName('variant_id') . ', ' . $db->quoteName('quantity') . ', '
                . $db->quoteName('on_hold') . ', ' . $db->quoteName('sold') . ', ' . $db->quoteName('product_attributes') . ')'
                . ' VALUES ' . implode(', ', $rows)
            )->execute();

            $processed += \count($ids);
        }

        return $processed;
    }

    // =========================================================================
    // Stale on_hold — nothing in core writes on_hold above 0 anymore (superseded by
    // orders.stock_committed), so it is recomputed from orders that currently hold stock.
    // =========================================================================

    private static function staleOnHoldWhere(DatabaseInterface $db): string
    {
        $holding = implode(',', InventoryHelper::NON_HOLDING_STATUSES);

        return $db->quoteName('pq.on_hold') . ' <> COALESCE(('
            . 'SELECT SUM(CAST(' . $db->quoteName('oi.orderitem_quantity') . ' AS DECIMAL(12,4)))'
            . ' FROM ' . $db->quoteName('#__j2commerce_orderitems', 'oi')
            . ' INNER JOIN ' . $db->quoteName('#__j2commerce_orders', 'o') . ' ON ' . $db->quoteName('o.order_id') . ' = ' . $db->quoteName('oi.order_id')
            . ' WHERE ' . $db->quoteName('oi.variant_id') . ' = ' . $db->quoteName('pq.variant_id')
            . ' AND ' . $db->quoteName('o.order_state_id') . ' NOT IN (' . $holding . ')'
            . '), 0)';
    }

    public static function countStaleOnHold(DatabaseInterface $db): int
    {
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_productquantities', 'pq'))
            ->where(self::staleOnHoldWhere($db));

        return (int) $db->setQuery($query)->loadResult();
    }

    public static function fixStaleOnHold(DatabaseInterface $db): int
    {
        $holding   = implode(',', InventoryHelper::NON_HOLDING_STATUSES);
        $processed = 0;

        for ($batch = 0; $batch < self::MAX_BATCHES_PER_RUN; $batch++) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('pq.j2commerce_productquantity_id'))
                ->from($db->quoteName('#__j2commerce_productquantities', 'pq'))
                ->where(self::staleOnHoldWhere($db))
                ->setLimit(self::BATCH_SIZE);

            $ids = array_map('intval', (array) $db->setQuery($query)->loadColumn());

            if (empty($ids)) {
                break;
            }

            $db->setQuery(
                'UPDATE ' . $db->quoteName('#__j2commerce_productquantities')
                . ' SET ' . $db->quoteName('on_hold') . ' = COALESCE(('
                . 'SELECT SUM(CAST(' . $db->quoteName('oi.orderitem_quantity') . ' AS DECIMAL(12,4)))'
                . ' FROM ' . $db->quoteName('#__j2commerce_orderitems', 'oi')
                . ' INNER JOIN ' . $db->quoteName('#__j2commerce_orders', 'o') . ' ON ' . $db->quoteName('o.order_id') . ' = ' . $db->quoteName('oi.order_id')
                . ' WHERE ' . $db->quoteName('oi.variant_id') . ' = ' . $db->quoteName('#__j2commerce_productquantities') . '.' . $db->quoteName('variant_id')
                . ' AND ' . $db->quoteName('o.order_state_id') . ' NOT IN (' . $holding . ')'
                . '), 0)'
                . ' WHERE ' . $db->quoteName('j2commerce_productquantity_id') . ' IN (' . implode(',', $ids) . ')'
            )->execute();

            $processed += \count($ids);
        }

        return $processed;
    }

    // =========================================================================
    // Orphan productimages
    // =========================================================================

    public static function countOrphanProductimages(DatabaseInterface $db): int
    {
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_productimages', 'pi'))
            ->leftJoin($db->quoteName('#__j2commerce_products', 'p') . ' ON ' . $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('pi.product_id'))
            ->where($db->quoteName('pi.product_id') . ' IS NOT NULL')
            ->where($db->quoteName('p.j2commerce_product_id') . ' IS NULL');

        return (int) $db->setQuery($query)->loadResult();
    }

    public static function fixOrphanProductimages(DatabaseInterface $db): int
    {
        $processed = 0;

        for ($batch = 0; $batch < self::MAX_BATCHES_PER_RUN; $batch++) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('pi.j2commerce_productimage_id'))
                ->from($db->quoteName('#__j2commerce_productimages', 'pi'))
                ->leftJoin($db->quoteName('#__j2commerce_products', 'p') . ' ON ' . $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('pi.product_id'))
                ->where($db->quoteName('pi.product_id') . ' IS NOT NULL')
                ->where($db->quoteName('p.j2commerce_product_id') . ' IS NULL')
                ->setLimit(self::BATCH_SIZE);

            $ids = array_map('intval', (array) $db->setQuery($query)->loadColumn());

            if (empty($ids)) {
                break;
            }

            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__j2commerce_productimages'))
                    ->whereIn($db->quoteName('j2commerce_productimage_id'), $ids)
            )->execute();

            $processed += \count($ids);
        }

        return $processed;
    }

    // =========================================================================
    // Zero-date variants — variants.modified_on is a varchar, so this is a string
    // comparison, not a date one.
    // =========================================================================

    private const ZERO_DATE = '0000-00-00 00:00:00';

    public static function countZeroDateVariants(DatabaseInterface $db): int
    {
        $zeroDate = self::ZERO_DATE;

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_variants'))
            ->where($db->quoteName('modified_on') . ' = :zeroDate')
            ->bind(':zeroDate', $zeroDate);

        return (int) $db->setQuery($query)->loadResult();
    }

    public static function fixZeroDateVariants(DatabaseInterface $db): int
    {
        $zeroDate  = self::ZERO_DATE;
        $processed = 0;

        for ($batch = 0; $batch < self::MAX_BATCHES_PER_RUN; $batch++) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('j2commerce_variant_id'))
                ->from($db->quoteName('#__j2commerce_variants'))
                ->where($db->quoteName('modified_on') . ' = :zeroDate')
                ->bind(':zeroDate', $zeroDate)
                ->setLimit(self::BATCH_SIZE);

            $ids = array_map('intval', (array) $db->setQuery($query)->loadColumn());

            if (empty($ids)) {
                break;
            }

            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_variants'))
                    ->set($db->quoteName('modified_on') . ' = ' . $db->quoteName('created_on'))
                    ->whereIn($db->quoteName('j2commerce_variant_id'), $ids)
            )->execute();

            $processed += \count($ids);
        }

        return $processed;
    }

    // =========================================================================
    // Price index stale — variable-family products missing a productprice_index row.
    // The fix dispatches to the product's own behaviour (ProductService::getBehavior()
    // ->runIndexes()); it never rebuilds the min/max SQL itself.
    // =========================================================================

    private const VARIANT_FAMILY_TYPES = ['variable', 'flexivariable'];

    public static function countPriceIndexStale(DatabaseInterface $db): int
    {
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_products', 'p'))
            ->leftJoin($db->quoteName('#__j2commerce_productprice_index', 'idx') . ' ON ' . $db->quoteName('idx.product_id') . ' = ' . $db->quoteName('p.j2commerce_product_id'))
            ->whereIn($db->quoteName('p.product_type'), self::VARIANT_FAMILY_TYPES, ParameterType::STRING)
            ->where($db->quoteName('idx.product_id') . ' IS NULL');

        return (int) $db->setQuery($query)->loadResult();
    }

    public static function fixPriceIndexStale(DatabaseInterface $db): int
    {
        $productService = new ProductService();
        $processed       = 0;

        for ($batch = 0; $batch < self::MAX_BATCHES_PER_RUN; $batch++) {
            $query = $db->getQuery(true)
                ->select([$db->quoteName('p.j2commerce_product_id'), $db->quoteName('p.product_type')])
                ->from($db->quoteName('#__j2commerce_products', 'p'))
                ->leftJoin($db->quoteName('#__j2commerce_productprice_index', 'idx') . ' ON ' . $db->quoteName('idx.product_id') . ' = ' . $db->quoteName('p.j2commerce_product_id'))
                ->whereIn($db->quoteName('p.product_type'), self::VARIANT_FAMILY_TYPES, ParameterType::STRING)
                ->where($db->quoteName('idx.product_id') . ' IS NULL')
                ->setLimit(self::BATCH_SIZE);

            $rows = $db->setQuery($query)->loadObjectList();

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                try {
                    $behavior = $productService->getBehavior((string) $row->product_type);
                    $behavior->runIndexes((object) ['j2commerce_product_id' => (int) $row->j2commerce_product_id]);
                    $processed++;
                } catch (\Throwable $e) {
                    Log::add('Database health price_index_stale fix failed for product ' . $row->j2commerce_product_id . ': ' . $e->getMessage(), Log::WARNING, 'com_j2commerce');
                }
            }
        }

        return $processed;
    }

    // =========================================================================
    // Orders without items / orphan orderitems — repairable. orphan_orderhistories and
    // products_without_master_variant stay report-only below (see PRD "Report-only" table).
    // =========================================================================

    public static function countOrdersWithoutItems(DatabaseInterface $db): int
    {
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_orders', 'o'))
            ->leftJoin($db->quoteName('#__j2commerce_orderitems', 'oi') . ' ON ' . $db->quoteName('oi.order_id') . ' = ' . $db->quoteName('o.order_id'))
            ->where($db->quoteName('oi.j2commerce_orderitem_id') . ' IS NULL');

        return (int) $db->setQuery($query)->loadResult();
    }

    /**
     * Reuses OrderModel::delete() so the whole child cascade runs — see OrderModel.php. Never
     * a raw DELETE against #__j2commerce_orders, and never OrderTable::store().
     */
    public static function fixOrdersWithoutItems(DatabaseInterface $db): int
    {
        $model = Factory::getApplication()->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createModel('Order', 'Administrator', ['ignore_request' => true]);

        if ($model === null) {
            return 0;
        }

        $processed = 0;

        for ($batch = 0; $batch < self::MAX_BATCHES_PER_RUN; $batch++) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('o.j2commerce_order_id'))
                ->from($db->quoteName('#__j2commerce_orders', 'o'))
                ->leftJoin($db->quoteName('#__j2commerce_orderitems', 'oi') . ' ON ' . $db->quoteName('oi.order_id') . ' = ' . $db->quoteName('o.order_id'))
                ->where($db->quoteName('oi.j2commerce_orderitem_id') . ' IS NULL')
                ->setLimit(self::BATCH_SIZE);

            $ids = array_map('intval', (array) $db->setQuery($query)->loadColumn());

            if (empty($ids)) {
                break;
            }

            $model->delete($ids);
            $processed += \count($ids);
        }

        return $processed;
    }

    public static function countOrphanOrderitems(DatabaseInterface $db): int
    {
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_orderitems', 'oi'))
            ->leftJoin($db->quoteName('#__j2commerce_orders', 'o') . ' ON ' . $db->quoteName('o.order_id') . ' = ' . $db->quoteName('oi.order_id'))
            ->where($db->quoteName('o.j2commerce_order_id') . ' IS NULL');

        return (int) $db->setQuery($query)->loadResult();
    }

    /** Deletes orderitemattributes first (FK orderitem_id), then the orderitems. */
    public static function fixOrphanOrderitems(DatabaseInterface $db): int
    {
        $processed = 0;

        for ($batch = 0; $batch < self::MAX_BATCHES_PER_RUN; $batch++) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('oi.j2commerce_orderitem_id'))
                ->from($db->quoteName('#__j2commerce_orderitems', 'oi'))
                ->leftJoin($db->quoteName('#__j2commerce_orders', 'o') . ' ON ' . $db->quoteName('o.order_id') . ' = ' . $db->quoteName('oi.order_id'))
                ->where($db->quoteName('o.j2commerce_order_id') . ' IS NULL')
                ->setLimit(self::BATCH_SIZE);

            $ids = array_map('intval', (array) $db->setQuery($query)->loadColumn());

            if (empty($ids)) {
                break;
            }

            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__j2commerce_orderitemattributes'))
                    ->whereIn($db->quoteName('orderitem_id'), $ids)
            )->execute();

            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__j2commerce_orderitems'))
                    ->whereIn($db->quoteName('j2commerce_orderitem_id'), $ids)
            )->execute();

            $processed += \count($ids);
        }

        return $processed;
    }

    // =========================================================================
    // Report-only checks — never auto-fixed. orphan_orderhistories erases the last audit
    // trace of a deleted order; products_without_master_variant needs individual review
    // (see the "Review" modal — DatabasehealthproductsModel), never a bulk fix.
    // =========================================================================

    public static function countOrphanOrderhistories(DatabaseInterface $db): int
    {
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_orderhistories', 'oh'))
            ->leftJoin($db->quoteName('#__j2commerce_orders', 'o') . ' ON ' . $db->quoteName('o.order_id') . ' = ' . $db->quoteName('oh.order_id'))
            ->where($db->quoteName('o.j2commerce_order_id') . ' IS NULL');

        return (int) $db->setQuery($query)->loadResult();
    }

    public static function fixOrphanOrderhistories(DatabaseInterface $db): int
    {
        $processed = 0;

        for ($batch = 0; $batch < self::MAX_BATCHES_PER_RUN; $batch++) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('oh.j2commerce_orderhistory_id'))
                ->from($db->quoteName('#__j2commerce_orderhistories', 'oh'))
                ->leftJoin($db->quoteName('#__j2commerce_orders', 'o') . ' ON ' . $db->quoteName('o.order_id') . ' = ' . $db->quoteName('oh.order_id'))
                ->where($db->quoteName('o.j2commerce_order_id') . ' IS NULL')
                ->setLimit(self::BATCH_SIZE);

            $ids = array_map('intval', (array) $db->setQuery($query)->loadColumn());

            if (empty($ids)) {
                break;
            }

            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__j2commerce_orderhistories'))
                    ->whereIn($db->quoteName('j2commerce_orderhistory_id'), $ids)
            )->execute();

            $processed += \count($ids);
        }

        return $processed;
    }

    public static function countProductsWithoutMasterVariant(DatabaseInterface $db): int
    {
        $isMaster = 1;

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_products', 'p'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_variants', 'v')
                . ' ON ' . $db->quoteName('v.product_id') . ' = ' . $db->quoteName('p.j2commerce_product_id')
                . ' AND ' . $db->quoteName('v.is_master') . ' = :isMaster'
            )
            ->where($db->quoteName('v.j2commerce_variant_id') . ' IS NULL')
            ->bind(':isMaster', $isMaster, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult();
    }

    /** migrator_idmap is the map, not residue — count only, never touched here. */
    public static function countMigratorResidue(DatabaseInterface $db): int
    {
        try {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__j2commerce_migrator_idmap'));

            return (int) $db->setQuery($query)->loadResult();
        } catch (\Throwable) {
            // The migrator component is not installed on this store.
            return 0;
        }
    }
}
