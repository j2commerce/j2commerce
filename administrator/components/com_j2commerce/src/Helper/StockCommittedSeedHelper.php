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
 * The marker lives in the component params so it is independent of #__schemas, and is
 * declared in config.xml so that saving Options — which binds the filtered form data over
 * params wholesale — cannot drop it. That matters because the seed is a one-shot:
 * re-running it later would re-commit orders whose stock has since been legitimately
 * returned, and orders that deliberately hold no stock.
 *
 * The seed runs in bounded batches and only ever moves a row from 0 to 1, so it is safe to
 * resume: a run that is cut short leaves the marker unset and the next one picks up the
 * rows still outstanding. The marker is written only once no rows remain.
 *
 * @since  6.5.0
 */
class StockCommittedSeedHelper
{
    /** Extension-params key marking the seed as done. Declared in config.xml so an Options save keeps it. */
    public const SEED_FLAG = 'stock_committed_seeded';

    /** Rows per statement, so the row locks stay off the whole table while checkout is live. */
    private const BATCH_SIZE = 500;

    /** Work ceiling for a single run: the rest is picked up by the next admin request. */
    private const MAX_BATCHES_PER_RUN = 20;

    /** Attempts at the marker write before giving up, in case Options is saved concurrently. */
    private const MARKER_WRITE_ATTEMPTS = 3;

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

            if ((int) (new Registry((string) $extension->params))->get(self::SEED_FLAG, 0) === 1) {
                return true;
            }

            [$seeded, $complete] = self::seedBatches($db);

            if (!$complete) {
                $log("STOCK SEED: {$seeded} order(s) seeded, work ceiling reached — resuming on the next request");

                return false;
            }

            if (!self::claimMarker($db, (int) $extension->extension_id)) {
                $log("STOCK SEED: {$seeded} order(s) seeded but the marker could not be written — will re-run");

                return false;
            }

            $log("STOCK SEED: seeded {$seeded} order(s)");

            return true;
        } catch (\Throwable $e) {
            // The installer passes debugLog() as the callback and that trace is web-served, so
            // it never carries exception text — an SQLSTATE string names the prefixed table.
            $log('STOCK SEED failed (see the j2commerce log)');
            Log::add('Stock committed seed failed: ' . $e->getMessage(), Log::WARNING, 'j2commerce');

            return false;
        }
    }

    /**
     * Commit the outstanding orders in batches.
     *
     * @return  array{0: int, 1: bool}  Rows seeded, and whether nothing is left to seed.
     */
    private static function seedBatches(DatabaseInterface $db): array
    {
        $seeded = 0;

        for ($batch = 0; $batch < self::MAX_BATCHES_PER_RUN; $batch++) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('j2commerce_order_id'))
                ->from($db->quoteName('#__j2commerce_orders'))
                ->where($db->quoteName('stock_committed') . ' = 0')
                ->whereNotIn($db->quoteName('order_state_id'), InventoryHelper::NON_HOLDING_STATUSES)
                ->setLimit(self::BATCH_SIZE);
            $db->setQuery($query);

            $ids = array_map('intval', (array) $db->loadColumn());

            if (!$ids) {
                return [$seeded, true];
            }

            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_orders'))
                    ->set($db->quoteName('stock_committed') . ' = 1')
                    ->whereIn($db->quoteName('j2commerce_order_id'), $ids)
            );
            $db->execute();

            // A concurrent run may have taken some of these rows first; the next pass
            // re-reads what is genuinely outstanding, so a short count is not an error.
            $seeded += $db->getAffectedRows();
        }

        return [$seeded, false];
    }

    /**
     * Set the marker, retrying if the params are saved from elsewhere mid-write. Each attempt
     * writes only over the value it read, so an administrator's Options save is never lost.
     */
    private static function claimMarker(DatabaseInterface $db, int $extensionId): bool
    {
        for ($attempt = 0; $attempt < self::MARKER_WRITE_ATTEMPTS; $attempt++) {
            $read = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('extension_id') . ' = :id')
                ->bind(':id', $extensionId, ParameterType::INTEGER);
            $db->setQuery($read);

            $expected = (string) $db->loadResult();
            $params   = new Registry($expected);

            if ((int) $params->get(self::SEED_FLAG, 0) === 1) {
                return true;
            }

            $params->set(self::SEED_FLAG, 1);
            $paramsJson = $params->toString();

            // CAST to BINARY so the compare-and-swap matches the bytes actually read: the
            // default utf8mb4_unicode_ci is case-insensitive, accent-insensitive and PAD SPACE,
            // so a bare compare could match a concurrently saved row and overwrite it.
            $write = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('params') . ' = :params')
                ->where($db->quoteName('extension_id') . ' = :id')
                ->where('CAST(' . $db->quoteName('params') . ' AS BINARY) = :expected')
                ->bind(':params', $paramsJson)
                ->bind(':expected', $expected)
                ->bind(':id', $extensionId, ParameterType::INTEGER);

            $db->setQuery($write)->execute();

            if ($db->getAffectedRows() > 0) {
                return true;
            }
        }

        return false;
    }
}
