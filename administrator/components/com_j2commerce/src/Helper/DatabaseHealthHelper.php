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

    /**
     * @return array[] Check definitions, merged with anything a plugin adds via onJ2CommerceGetHealthChecks.
     *
     * A listener receives the whole array and may return a different one, so it can replace or drop
     * a core check as well as add its own — including swapping the callable behind a core check's
     * fix. That is the same reach any plugin already has, but a listener should add rows and leave
     * the core ones as it found them.
     */
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
                'id'             => 'cart_gc_backlog',
                'labelKey'       => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_CART_GC_BACKLOG_LABEL',
                'descriptionKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_CART_GC_BACKLOG_DESC',
                'repairable'     => true,
                'destructive'    => false,
                'setupGuideLink' => true,
                'count'          => [self::class, 'countCartGcBacklog'],
                'fix'            => [self::class, 'fixCartGcBacklog'],
            ],
            [
                'id'             => 'orphan_cartitems',
                'labelKey'       => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_CARTITEMS_LABEL',
                'descriptionKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_CARTITEMS_DESC',
                'repairable'     => true,
                'destructive'    => true,
                'count'          => [self::class, 'countOrphanCartitems'],
                'fix'            => [self::class, 'fixOrphanCartitems'],
            ],
            [
                'id'             => 'orphan_productquantities',
                'labelKey'       => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_PRODUCTQUANTITIES_LABEL',
                'descriptionKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_PRODUCTQUANTITIES_DESC',
                'repairable'     => true,
                'destructive'    => false,
                'count'          => [self::class, 'countOrphanProductquantities'],
                'fix'            => [self::class, 'fixOrphanProductquantities'],
            ],
            [
                'id'             => 'missing_productquantity',
                'labelKey'       => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_MISSING_PRODUCTQUANTITY_LABEL',
                'descriptionKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_MISSING_PRODUCTQUANTITY_DESC',
                'repairable'     => true,
                'destructive'    => false,
                'count'          => [self::class, 'countMissingProductquantity'],
                'fix'            => [self::class, 'fixMissingProductquantity'],
            ],
            [
                'id'             => 'stale_on_hold',
                'labelKey'       => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_STALE_ON_HOLD_LABEL',
                'descriptionKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_STALE_ON_HOLD_DESC',
                'repairable'     => true,
                'destructive'    => false,
                'count'          => [self::class, 'countStaleOnHold'],
                'fix'            => [self::class, 'fixStaleOnHold'],
            ],
            [
                'id'             => 'orphan_productimages',
                'labelKey'       => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_PRODUCTIMAGES_LABEL',
                'descriptionKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_PRODUCTIMAGES_DESC',
                'repairable'     => true,
                'destructive'    => false,
                'count'          => [self::class, 'countOrphanProductimages'],
                'fix'            => [self::class, 'fixOrphanProductimages'],
            ],
            [
                'id'             => 'zero_date_variants',
                'labelKey'       => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ZERO_DATE_VARIANTS_LABEL',
                'descriptionKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ZERO_DATE_VARIANTS_DESC',
                'repairable'     => true,
                'destructive'    => false,
                'count'          => [self::class, 'countZeroDateVariants'],
                'fix'            => [self::class, 'fixZeroDateVariants'],
            ],
            [
                'id'             => 'price_index_stale',
                'labelKey'       => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_PRICE_INDEX_STALE_LABEL',
                'descriptionKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_PRICE_INDEX_STALE_DESC',
                'repairable'     => true,
                'destructive'    => false,
                'count'          => [self::class, 'countPriceIndexStale'],
                'fix'            => [self::class, 'fixPriceIndexStale'],
            ],
            [
                'id'                    => 'orders_without_items',
                'labelKey'              => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORDERS_WITHOUT_ITEMS_LABEL',
                'descriptionKey'        => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORDERS_WITHOUT_ITEMS_DESC',
                'repairable'            => true,
                'destructive'           => true,
                'destructiveWarningKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_ORDERS_WITHOUT_ITEMS_DESTRUCTIVE_WARNING',
                'count'                 => [self::class, 'countOrdersWithoutItems'],
                'fix'                   => [self::class, 'fixOrdersWithoutItems'],
            ],
            [
                'id'                    => 'orphan_orderitems',
                'labelKey'              => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_ORDERITEMS_LABEL',
                'descriptionKey'        => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_ORDERITEMS_DESC',
                'repairable'            => true,
                'destructive'           => true,
                'destructiveWarningKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_ORPHAN_ORDERITEMS_DESTRUCTIVE_WARNING',
                'count'                 => [self::class, 'countOrphanOrderitems'],
                'fix'                   => [self::class, 'fixOrphanOrderitems'],
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
                'id'             => 'orphan_zones',
                'labelKey'       => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_ZONES_LABEL',
                'descriptionKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_ZONES_DESC',
                'repairable'     => true,
                'destructive'    => true,
                'count'          => [self::class, 'countOrphanZones'],
                'fix'            => [self::class, 'fixOrphanZones'],
            ],
            [
                'id'             => 'orphan_geozonerules',
                'labelKey'       => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_GEOZONERULES_LABEL',
                'descriptionKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_GEOZONERULES_DESC',
                'repairable'     => true,
                'destructive'    => true,
                'count'          => [self::class, 'countOrphanGeozonerules'],
                'fix'            => [self::class, 'fixOrphanGeozonerules'],
            ],
            [
                'id'             => 'orphan_shippingrates',
                'labelKey'       => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_SHIPPINGRATES_LABEL',
                'descriptionKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_SHIPPINGRATES_DESC',
                'repairable'     => true,
                'destructive'    => true,
                'count'          => [self::class, 'countOrphanShippingrates'],
                'fix'            => [self::class, 'fixOrphanShippingrates'],
            ],
            [
                'id'             => 'orphan_taxrules',
                'labelKey'       => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_TAXRULES_LABEL',
                'descriptionKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_TAXRULES_DESC',
                'repairable'     => true,
                'destructive'    => true,
                'count'          => [self::class, 'countOrphanTaxrules'],
                'fix'            => [self::class, 'fixOrphanTaxrules'],
            ],
            [
                'id'             => 'orphan_product_options',
                'labelKey'       => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_PRODUCT_OPTIONS_LABEL',
                'descriptionKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_PRODUCT_OPTIONS_DESC',
                'repairable'     => true,
                'destructive'    => true,
                'count'          => [self::class, 'countOrphanProductOptions'],
                'fix'            => [self::class, 'fixOrphanProductOptions'],
            ],
            [
                'id'             => 'orphan_product_optionvalues',
                'labelKey'       => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_PRODUCT_OPTIONVALUES_LABEL',
                'descriptionKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_PRODUCT_OPTIONVALUES_DESC',
                'repairable'     => true,
                'destructive'    => true,
                'count'          => [self::class, 'countOrphanProductOptionvalues'],
                'fix'            => [self::class, 'fixOrphanProductOptionvalues'],
            ],
            [
                'id'             => 'orphan_orderitemattributes',
                'labelKey'       => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_ORDERITEMATTRIBUTES_LABEL',
                'descriptionKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_ORDERITEMATTRIBUTES_DESC',
                'repairable'     => true,
                'destructive'    => true,
                'count'          => [self::class, 'countOrphanOrderitemattributes'],
                'fix'            => [self::class, 'fixOrphanOrderitemattributes'],
            ],
            [
                'id'             => 'orphan_uploads',
                'labelKey'       => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_UPLOADS_LABEL',
                'descriptionKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_UPLOADS_DESC',
                'repairable'     => true,
                'destructive'    => true,
                'count'          => [self::class, 'countOrphanUploads'],
                'fix'            => [self::class, 'fixOrphanUploads'],
            ],
            [
                'id'             => 'orphan_ordertransactions',
                'labelKey'       => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_ORDERTRANSACTIONS_LABEL',
                'descriptionKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_ORDERTRANSACTIONS_DESC',
                'repairable'     => false,
                'destructive'    => false,
                'count'          => [self::class, 'countOrphanOrdertransactions'],
                'fix'            => null,
            ],
            [
                'id'             => 'orphan_voucheradjustments',
                'labelKey'       => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_VOUCHERADJUSTMENTS_LABEL',
                'descriptionKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ORPHAN_VOUCHERADJUSTMENTS_DESC',
                'repairable'     => false,
                'destructive'    => false,
                'count'          => [self::class, 'countOrphanVoucheradjustments'],
                'fix'            => null,
            ],
            [
                'id'             => 'products_without_master_variant',
                'labelKey'       => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_PRODUCTS_WITHOUT_MASTER_VARIANT_LABEL',
                'descriptionKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_PRODUCTS_WITHOUT_MASTER_VARIANT_DESC',
                'repairable'     => false,
                'destructive'    => false,
                'reviewUrl'      => 'index.php?option=com_j2commerce&view=databasehealthproducts&tmpl=component',
                'count'          => [self::class, 'countProductsWithoutMasterVariant'],
                'fix'            => null,
            ],
            [
                'id'             => 'migrator_residue',
                'labelKey'       => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_MIGRATOR_RESIDUE_LABEL',
                'descriptionKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_MIGRATOR_RESIDUE_DESC',
                'repairable'     => false,
                'destructive'    => false,
                'count'          => [self::class, 'countMigratorResidue'],
                'fix'            => null,
            ],
            [
                'id'             => 'entity_encoded_zone_names',
                'labelKey'       => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ENTITY_ENCODED_ZONE_NAMES_LABEL',
                'descriptionKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_ENTITY_ENCODED_ZONE_NAMES_DESC',
                'repairable'     => true,
                'destructive'    => false,
                'count'          => [self::class, 'countEntityEncodedZoneNames'],
                'fix'            => [self::class, 'fixEntityEncodedZoneNames'],
            ],
            ...self::orphanOrderChildChecks(),
        ];
    }

    /**
     * Order children that key on the varchar order number and share one orphan predicate. The
     * table and column names come from here and nowhere else — never from a request.
     */
    private const ORPHAN_ORDER_CHILD_TABLES = [
        'orderinfos'     => 'j2commerce_orderinfo_id',
        'ordershippings' => 'j2commerce_ordershipping_id',
        'orderdiscounts' => 'j2commerce_orderdiscount_id',
        'orderfees'      => 'j2commerce_orderfee_id',
        'ordertaxes'     => 'j2commerce_ordertax_id',
        'orderdownloads' => 'j2commerce_orderdownload_id',
    ];

    private static function orphanOrderChildChecks(): array
    {
        $checks = [];

        foreach (self::ORPHAN_ORDER_CHILD_TABLES as $name => $pk) {
            $suffix = 'ORPHAN_' . strtoupper($name);

            $checks[] = [
                'id'                    => 'orphan_' . $name,
                'labelKey'              => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_' . $suffix . '_LABEL',
                'descriptionKey'        => 'COM_J2COMMERCE_DATABASE_HEALTH_CHECK_' . $suffix . '_DESC',
                'repairable'            => true,
                'destructive'           => true,
                'destructiveWarningKey' => 'COM_J2COMMERCE_DATABASE_HEALTH_ORPHAN_ORDER_CHILD_DESTRUCTIVE_WARNING',
                'count'                 => static fn (DatabaseInterface $db): int => self::countOrphanOrderChild($db, $name, $pk),
                'fix'                   => static fn (DatabaseInterface $db): int => self::fixOrphanOrderChild($db, $name, $pk),
            ];
        }

        return $checks;
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
        $productService  = new ProductService();
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

        $before = self::countOrdersWithoutItems($db);

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

            // A refusal (an order holding payment records needs an explicit confirmation) would
            // otherwise re-select the same rows every batch and report them all as repaired.
            if (!$model->delete($ids)) {
                break;
            }
        }

        // Measured, not counted: the report has to be the rows that actually went.
        return $before - self::countOrdersWithoutItems($db);
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

    // =========================================================================
    // Delete-cascade orphans (#2072). Every pair below shares one predicate factory, so the
    // number the card shows is the number the fix removes. A parent column of 0 means "any" on
    // several of these tables, so a 0 is never treated as a broken reference.
    // =========================================================================

    /** @param  callable(DatabaseInterface): QueryInterface  $factory  Predicate with FROM/JOIN/WHERE only. */
    private static function countOrphans(DatabaseInterface $db, callable $factory, string $pkColumn): int
    {
        $query = $factory($db)->select('COUNT(DISTINCT ' . $db->quoteName($pkColumn) . ')');

        return (int) $db->setQuery($query)->loadResult();
    }

    /**
     * @param  callable(DatabaseInterface): QueryInterface  $factory  Same predicate as the count.
     * @param  callable(array): void|null                   $onBatch  Runs before each batch is deleted.
     */
    private static function purgeOrphans(
        DatabaseInterface $db,
        callable $factory,
        string $pkColumn,
        string $table,
        array $extraColumns = [],
        ?callable $onBatch = null
    ): int {
        $pkName    = substr($pkColumn, (int) strrpos($pkColumn, '.') + 1);
        $processed = 0;

        for ($batch = 0; $batch < self::MAX_BATCHES_PER_RUN; $batch++) {
            $query = $factory($db)
                ->select($db->quoteName(array_merge([$pkColumn], $extraColumns)))
                ->setLimit(self::BATCH_SIZE);

            $rows = $db->setQuery($query)->loadObjectList() ?: [];

            if ($rows === []) {
                break;
            }

            if ($onBatch !== null) {
                $onBatch($rows);
            }

            $ids = array_map(static fn (object $row): int => (int) $row->{$pkName}, $rows);

            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName($table))
                    ->whereIn($db->quoteName($pkName), $ids)
            )->execute();

            $processed += \count($rows);
        }

        return $processed;
    }

    private static function orphanZonesQuery(DatabaseInterface $db): QueryInterface
    {
        return $db->getQuery(true)
            ->from($db->quoteName('#__j2commerce_zones', 'z'))
            ->leftJoin($db->quoteName('#__j2commerce_countries', 'c') . ' ON ' . $db->quoteName('c.j2commerce_country_id') . ' = ' . $db->quoteName('z.country_id'))
            ->where($db->quoteName('c.j2commerce_country_id') . ' IS NULL');
    }

    public static function countOrphanZones(DatabaseInterface $db): int
    {
        return self::countOrphans($db, [self::class, 'orphanZonesQuery'], 'z.j2commerce_zone_id');
    }

    public static function fixOrphanZones(DatabaseInterface $db): int
    {
        return self::purgeOrphans($db, [self::class, 'orphanZonesQuery'], 'z.j2commerce_zone_id', '#__j2commerce_zones');
    }

    private static function orphanGeozonerulesQuery(DatabaseInterface $db): QueryInterface
    {
        // country_id / zone_id carry 0 for "the whole geozone", and a 0 never matches the join.
        return $db->getQuery(true)
            ->from($db->quoteName('#__j2commerce_geozonerules', 'r'))
            ->leftJoin($db->quoteName('#__j2commerce_geozones', 'g') . ' ON ' . $db->quoteName('g.j2commerce_geozone_id') . ' = ' . $db->quoteName('r.geozone_id'))
            ->leftJoin($db->quoteName('#__j2commerce_countries', 'c') . ' ON ' . $db->quoteName('c.j2commerce_country_id') . ' = ' . $db->quoteName('r.country_id'))
            ->leftJoin($db->quoteName('#__j2commerce_zones', 'z') . ' ON ' . $db->quoteName('z.j2commerce_zone_id') . ' = ' . $db->quoteName('r.zone_id'))
            ->where(
                '(' . $db->quoteName('g.j2commerce_geozone_id') . ' IS NULL'
                . ' OR (' . $db->quoteName('r.country_id') . ' <> 0 AND ' . $db->quoteName('c.j2commerce_country_id') . ' IS NULL)'
                . ' OR (' . $db->quoteName('r.zone_id') . ' <> 0 AND ' . $db->quoteName('z.j2commerce_zone_id') . ' IS NULL))'
            );
    }

    public static function countOrphanGeozonerules(DatabaseInterface $db): int
    {
        return self::countOrphans($db, [self::class, 'orphanGeozonerulesQuery'], 'r.j2commerce_geozonerule_id');
    }

    public static function fixOrphanGeozonerules(DatabaseInterface $db): int
    {
        return self::purgeOrphans($db, [self::class, 'orphanGeozonerulesQuery'], 'r.j2commerce_geozonerule_id', '#__j2commerce_geozonerules');
    }

    private static function orphanShippingratesQuery(DatabaseInterface $db): QueryInterface
    {
        return $db->getQuery(true)
            ->from($db->quoteName('#__j2commerce_shippingrates', 's'))
            ->leftJoin($db->quoteName('#__j2commerce_geozones', 'g') . ' ON ' . $db->quoteName('g.j2commerce_geozone_id') . ' = ' . $db->quoteName('s.geozone_id'))
            ->where($db->quoteName('s.geozone_id') . ' <> 0')
            ->where($db->quoteName('g.j2commerce_geozone_id') . ' IS NULL');
    }

    public static function countOrphanShippingrates(DatabaseInterface $db): int
    {
        return self::countOrphans($db, [self::class, 'orphanShippingratesQuery'], 's.j2commerce_shippingrate_id');
    }

    public static function fixOrphanShippingrates(DatabaseInterface $db): int
    {
        return self::purgeOrphans($db, [self::class, 'orphanShippingratesQuery'], 's.j2commerce_shippingrate_id', '#__j2commerce_shippingrates');
    }

    private static function orphanTaxrulesQuery(DatabaseInterface $db): QueryInterface
    {
        return $db->getQuery(true)
            ->from($db->quoteName('#__j2commerce_taxrules', 'tr'))
            ->leftJoin($db->quoteName('#__j2commerce_taxprofiles', 'tp') . ' ON ' . $db->quoteName('tp.j2commerce_taxprofile_id') . ' = ' . $db->quoteName('tr.taxprofile_id'))
            ->leftJoin($db->quoteName('#__j2commerce_taxrates', 'ta') . ' ON ' . $db->quoteName('ta.j2commerce_taxrate_id') . ' = ' . $db->quoteName('tr.taxrate_id'))
            ->where(
                '(' . $db->quoteName('tp.j2commerce_taxprofile_id') . ' IS NULL'
                . ' OR ' . $db->quoteName('ta.j2commerce_taxrate_id') . ' IS NULL)'
            );
    }

    public static function countOrphanTaxrules(DatabaseInterface $db): int
    {
        return self::countOrphans($db, [self::class, 'orphanTaxrulesQuery'], 'tr.j2commerce_taxrule_id');
    }

    public static function fixOrphanTaxrules(DatabaseInterface $db): int
    {
        return self::purgeOrphans($db, [self::class, 'orphanTaxrulesQuery'], 'tr.j2commerce_taxrule_id', '#__j2commerce_taxrules');
    }

    private static function orphanProductOptionsQuery(DatabaseInterface $db): QueryInterface
    {
        return $db->getQuery(true)
            ->from($db->quoteName('#__j2commerce_product_options', 'po'))
            ->leftJoin($db->quoteName('#__j2commerce_products', 'p') . ' ON ' . $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('po.product_id'))
            ->leftJoin($db->quoteName('#__j2commerce_options', 'o') . ' ON ' . $db->quoteName('o.j2commerce_option_id') . ' = ' . $db->quoteName('po.option_id'))
            ->where(
                '(' . $db->quoteName('p.j2commerce_product_id') . ' IS NULL'
                . ' OR ' . $db->quoteName('o.j2commerce_option_id') . ' IS NULL)'
            );
    }

    public static function countOrphanProductOptions(DatabaseInterface $db): int
    {
        return self::countOrphans($db, [self::class, 'orphanProductOptionsQuery'], 'po.j2commerce_productoption_id');
    }

    public static function fixOrphanProductOptions(DatabaseInterface $db): int
    {
        return self::purgeOrphans($db, [self::class, 'orphanProductOptionsQuery'], 'po.j2commerce_productoption_id', '#__j2commerce_product_options');
    }

    private static function orphanProductOptionvaluesQuery(DatabaseInterface $db): QueryInterface
    {
        return $db->getQuery(true)
            ->from($db->quoteName('#__j2commerce_product_optionvalues', 'pov'))
            ->leftJoin($db->quoteName('#__j2commerce_product_options', 'po') . ' ON ' . $db->quoteName('po.j2commerce_productoption_id') . ' = ' . $db->quoteName('pov.productoption_id'))
            ->leftJoin($db->quoteName('#__j2commerce_optionvalues', 'ov') . ' ON ' . $db->quoteName('ov.j2commerce_optionvalue_id') . ' = ' . $db->quoteName('pov.optionvalue_id'))
            ->where(
                '(' . $db->quoteName('po.j2commerce_productoption_id') . ' IS NULL'
                . ' OR ' . $db->quoteName('ov.j2commerce_optionvalue_id') . ' IS NULL)'
            );
    }

    public static function countOrphanProductOptionvalues(DatabaseInterface $db): int
    {
        return self::countOrphans($db, [self::class, 'orphanProductOptionvaluesQuery'], 'pov.j2commerce_product_optionvalue_id');
    }

    public static function fixOrphanProductOptionvalues(DatabaseInterface $db): int
    {
        return self::purgeOrphans($db, [self::class, 'orphanProductOptionvaluesQuery'], 'pov.j2commerce_product_optionvalue_id', '#__j2commerce_product_optionvalues');
    }

    private static function orphanOrderitemattributesQuery(DatabaseInterface $db): QueryInterface
    {
        return $db->getQuery(true)
            ->from($db->quoteName('#__j2commerce_orderitemattributes', 'oia'))
            ->leftJoin($db->quoteName('#__j2commerce_orderitems', 'oi') . ' ON ' . $db->quoteName('oi.j2commerce_orderitem_id') . ' = ' . $db->quoteName('oia.orderitem_id'))
            ->where($db->quoteName('oi.j2commerce_orderitem_id') . ' IS NULL');
    }

    public static function countOrphanOrderitemattributes(DatabaseInterface $db): int
    {
        return self::countOrphans($db, [self::class, 'orphanOrderitemattributesQuery'], 'oia.j2commerce_orderitemattribute_id');
    }

    public static function fixOrphanOrderitemattributes(DatabaseInterface $db): int
    {
        return self::purgeOrphans($db, [self::class, 'orphanOrderitemattributesQuery'], 'oia.j2commerce_orderitemattribute_id', '#__j2commerce_orderitemattributes');
    }

    private static function orphanUploadsQuery(DatabaseInterface $db): QueryInterface
    {
        return $db->getQuery(true)
            ->from($db->quoteName('#__j2commerce_uploads', 'u'))
            ->leftJoin($db->quoteName('#__j2commerce_orders', 'o') . ' ON ' . $db->quoteName('o.order_id') . ' = ' . $db->quoteName('u.order_id'))
            ->leftJoin($db->quoteName('#__j2commerce_carts', 'c') . ' ON ' . $db->quoteName('c.j2commerce_cart_id') . ' = ' . $db->quoteName('u.cart_id'))
            ->where(
                '((COALESCE(' . $db->quoteName('u.order_id') . ", '') <> '' AND " . $db->quoteName('o.order_id') . ' IS NULL)'
                . ' OR (COALESCE(' . $db->quoteName('u.order_id') . ", '') = '' AND COALESCE(" . $db->quoteName('u.cart_id') . ', 0) <> 0 AND '
                . $db->quoteName('c.j2commerce_cart_id') . ' IS NULL))'
            );
    }

    public static function countOrphanUploads(DatabaseInterface $db): int
    {
        return self::countOrphans($db, [self::class, 'orphanUploadsQuery'], 'u.j2commerce_upload_id');
    }

    /** Removes the file as well as the row — a row-only sweep would strand the bytes on disk. */
    public static function fixOrphanUploads(DatabaseInterface $db): int
    {
        return self::purgeOrphans(
            $db,
            [self::class, 'orphanUploadsQuery'],
            'u.j2commerce_upload_id',
            '#__j2commerce_uploads',
            ['u.saved_name', 'u.order_id', 'u.cart_id'],
            static function (array $rows): void {
                foreach ($rows as $row) {
                    $path = OrderUploadHelper::resolveOrderFilePath((string) ($row->order_id ?? ''), (string) $row->saved_name)
                        ?? OrderUploadHelper::resolveCartFilePath((int) ($row->cart_id ?? 0), (string) $row->saved_name);

                    if ($path !== null) {
                        @unlink($path);
                    }
                }
            }
        );
    }

    /**
     * Report-only, like orphan_orderhistories. These are financial records whose parent order is
     * already gone, and a one-click sweep is the wrong tool for them.
     */
    public static function countOrphanOrdertransactions(DatabaseInterface $db): int
    {
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_ordertransactions', 'ot'))
            ->leftJoin($db->quoteName('#__j2commerce_orders', 'o') . ' ON ' . $db->quoteName('o.j2commerce_order_id') . ' = ' . $db->quoteName('ot.order_id'))
            ->where($db->quoteName('o.j2commerce_order_id') . ' IS NULL');

        return (int) $db->setQuery($query)->loadResult();
    }

    /** Report-only: the rows carry a balance_before/balance_after chain that cannot be rebuilt. */
    public static function countOrphanVoucheradjustments(DatabaseInterface $db): int
    {
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_voucheradjustments', 'va'))
            ->leftJoin($db->quoteName('#__j2commerce_vouchers', 'v') . ' ON ' . $db->quoteName('v.j2commerce_voucher_id') . ' = ' . $db->quoteName('va.j2commerce_voucher_id'))
            ->where($db->quoteName('v.j2commerce_voucher_id') . ' IS NULL');

        return (int) $db->setQuery($query)->loadResult();
    }

    // =========================================================================
    // Order children keyed on the varchar order number. One predicate, one pair of runners,
    // driven by ORPHAN_ORDER_CHILD_TABLES — orderitems, orderhistories and ordertransactions
    // keep their own checks because their predicates differ.
    // =========================================================================

    private static function orphanOrderChildQuery(DatabaseInterface $db, string $table): QueryInterface
    {
        return $db->getQuery(true)
            ->from($db->quoteName('#__j2commerce_' . $table, 'c'))
            ->leftJoin($db->quoteName('#__j2commerce_orders', 'o') . ' ON ' . $db->quoteName('o.order_id') . ' = ' . $db->quoteName('c.order_id'))
            ->where($db->quoteName('o.j2commerce_order_id') . ' IS NULL');
    }

    public static function countOrphanOrderChild(DatabaseInterface $db, string $table, string $pk): int
    {
        return self::countOrphans(
            $db,
            static fn (DatabaseInterface $d): QueryInterface => self::orphanOrderChildQuery($d, $table),
            'c.' . $pk
        );
    }

    public static function fixOrphanOrderChild(DatabaseInterface $db, string $table, string $pk): int
    {
        return self::purgeOrphans(
            $db,
            static fn (DatabaseInterface $d): QueryInterface => self::orphanOrderChildQuery($d, $table),
            'c.' . $pk,
            '#__j2commerce_' . $table
        );
    }

    // =========================================================================
    // Zone names stored as HTML character entities (#2093). The decode happens in PHP rather
    // than through a REPLACE() chain: the set includes a numeric entity (&#039;) as well as the
    // named accented ones, and html_entity_decode() leaves an ampersand that does not form an
    // entity exactly as it found it, so a zone legitimately named with "&" is never touched.
    // =========================================================================

    /** Candidate rows, cheap enough to read whole: the table is a few thousand rows of seed data. */
    private static function entityEncodedZoneNames(DatabaseInterface $db): array
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_zone_id', 'zone_name']))
            ->from($db->quoteName('#__j2commerce_zones'))
            ->where($db->quoteName('zone_name') . ' LIKE ' . $db->quote('%&%'));

        $decoded = [];

        foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row) {
            $name = (string) $row->zone_name;

            // The same entity table the write path uses — InputFilter::cleanString() decodes with
            // ENT_QUOTES, which is HTML 4.01. Decoding with a larger table here would turn the
            // entities that filter deliberately lets through into live markup on the way back in.
            $plain = html_entity_decode($name, \ENT_QUOTES, 'UTF-8');

            // Rows can reach this table without passing that filter at all — a migrator writes
            // them with a raw INSERT — so a decode that yields markup is refused outright. Leaving
            // such a row visibly encoded is the safe outcome.
            if ($plain !== strip_tags($plain)) {
                continue;
            }

            // Only a row the decode actually changes is a row to repair, so the number reported
            // and the number written are the same number.
            if ($plain !== $name) {
                $decoded[(int) $row->j2commerce_zone_id] = $plain;
            }
        }

        return $decoded;
    }

    public static function countEntityEncodedZoneNames(DatabaseInterface $db): int
    {
        return \count(self::entityEncodedZoneNames($db));
    }

    public static function fixEntityEncodedZoneNames(DatabaseInterface $db): int
    {
        $processed = 0;

        foreach (self::entityEncodedZoneNames($db) as $zoneId => $name) {
            if ($processed >= self::BATCH_SIZE * self::MAX_BATCHES_PER_RUN) {
                break;
            }

            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_zones'))
                    ->set($db->quoteName('zone_name') . ' = :name')
                    ->where($db->quoteName('j2commerce_zone_id') . ' = :zoneId')
                    ->bind(':name', $name)
                    ->bind(':zoneId', $zoneId, ParameterType::INTEGER)
            )->execute();

            $processed++;
        }

        return $processed;
    }
}
