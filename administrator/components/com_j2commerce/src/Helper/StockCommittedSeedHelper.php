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

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * One-time seeding of stock_committed on the orders that already hold deducted stock.
 *
 * Deliberately not an UPDATE in the schema delta: Joomla only builds check queries for
 * RENAME/ALTER/CREATE, so Database -> Fix skips an UPDATE while still advancing the stored
 * schema version past it. That path also adds the column without running the installer
 * script at all, so the seed has to converge from the component boot as well.
 *
 * The marker lives in the component params so it is independent of #__schemas, and the seed
 * is a one-shot: re-running it later would re-commit orders whose stock has since been
 * legitimately returned. The marker is therefore claimed before the seed runs, and both
 * writes share a transaction so a failed seed releases the claim.
 *
 * @since  6.5.0
 */
class StockCommittedSeedHelper
{
    /** Extension-params key marking the seed as done. Declared in config.xml so an Options save keeps it. */
    public const SEED_FLAG = 'stock_committed_seeded';

    /** Mirrors InventoryHelper::NON_HOLDING_STATUSES — Failed, New, Cancelled. Keep the two in step. */
    private const UNCOMMITTED_STATES = [3, 5, 6];

    /**
     * Seed stock_committed unless the marker is already set. Never throws: a failure leaves
     * the marker unset so the next boot or update retries.
     *
     * @return  bool  True when the marker is set on return.
     */
    public static function ensureSeeded(?callable $logger = null): bool
    {
        $log = $logger ?? static function (string $message): void {
            Log::add($message, Log::DEBUG, 'com_j2commerce');
        };

        $inTransaction = false;

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $columns = $db->getTableColumns('#__j2commerce_orders', false);

            if (!isset($columns['stock_committed'])) {
                $log('STOCK SEED: column not present yet, nothing to seed');

                return false;
            }

            $query = $db->getQuery(true)
                ->select([$db->quoteName('extension_id'), $db->quoteName('params')])
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_j2commerce'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
            $db->setQuery($query);
            $extension = $db->loadObject();

            if (!$extension) {
                $log('STOCK SEED: com_j2commerce extension row not found — skipped');

                return false;
            }

            $storedParams = (string) $extension->params;
            $params       = new Registry($storedParams);

            if ((int) $params->get(self::SEED_FLAG, 0) === 1) {
                return true;
            }

            $db->transactionStart();
            $inTransaction = true;

            // Claim the marker first. The boot path is concurrent, so two requests can both
            // pass the check above; only the one that writes over the params it read owns
            // the seed, and the rollback below releases the claim if the seed then fails.
            if (!self::claimMarker($db, $params, (int) $extension->extension_id, $storedParams)) {
                $db->transactionRollback();

                $log('STOCK SEED: extension params changed while claiming — retry on next request');

                return false;
            }

            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_orders'))
                    ->set($db->quoteName('stock_committed') . ' = 1')
                    ->whereNotIn($db->quoteName('order_state_id'), self::UNCOMMITTED_STATES)
            );
            $db->execute();

            $seeded = $db->getAffectedRows();

            $db->transactionCommit();
            $inTransaction = false;

            $log('STOCK SEED: seeded ' . $seeded . ' order(s)');

            return true;
        } catch (\Throwable $e) {
            if ($inTransaction) {
                try {
                    $db->transactionRollback();
                } catch (\Throwable $rollbackError) {
                    $log('STOCK SEED: rollback failed: ' . $rollbackError->getMessage());
                }
            }

            $log('STOCK SEED failed: ' . $e->getMessage());

            return false;
        }
    }

    /** Set the marker only while the stored params still match the value they were read from. */
    private static function claimMarker(
        DatabaseInterface $db,
        Registry $params,
        int $extensionId,
        string $expected
    ): bool {
        $params->set(self::SEED_FLAG, 1);

        $paramsJson = $params->toString();

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('extension_id') . ' = :id')
            ->where($db->quoteName('params') . ' = :expected')
            ->bind(':params', $paramsJson)
            ->bind(':expected', $expected)
            ->bind(':id', $extensionId, ParameterType::INTEGER);

        $db->setQuery($query)->execute();

        return $db->getAffectedRows() > 0;
    }
}
