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
use Joomla\Utilities\IpHelper;

/**
 * Shared persistence + token helpers for customer file uploads.
 *
 * @since  6.3.0
 */
final class UploadHelper
{
    /** Default number of uploads a single client may store per rolling hour. */
    public const HOURLY_UPLOAD_LIMIT = 30;

    /** Per-request memo of the client_ip schema probe. */
    private static ?bool $clientIpColumn = null;

    /** Whether the schema update adding uploads.client_ip has landed; a failed probe reports false. */
    public static function hasClientIpColumn(): bool
    {
        if (self::$clientIpColumn !== null) {
            return self::$clientIpColumn;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        try {
            $columns = $db->getTableColumns('#__j2commerce_uploads', true);
        } catch (\Throwable $e) {
            $columns = [];
        }

        self::$clientIpColumn = isset($columns['client_ip']);

        if (!self::$clientIpColumn) {
            // A CAN FAIL schema update can be skipped silently, so surface the missing column.
            Log::add(
                'Schema update 6.5.0-2026-07-30 is not applied: #__j2commerce_uploads.client_ip is missing.'
                    . ' Run Extensions - Manage - Database to complete it.',
                Log::WARNING,
                'com_j2commerce'
            );
        }

        return self::$clientIpColumn;
    }

    /** Cryptographically random 32-hex-character token (16 bytes). */
    public static function randomToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Salted, non-reversible throttle key for the requesting client, 64 chars either way.
     *
     * An authenticated uploader is bucketed on their user id, so shoppers sharing one
     * corporate or carrier NAT egress no longer share a limit and cannot burn each
     * other's. Guests have no durable identity — session or cart id would be
     * self-resetting, since any visitor can obtain a fresh session with a valid token
     * from a single page load — so they fall back to the address. IpHelper rather than
     * REMOTE_ADDR: Joomla honours X-Forwarded-For only when the merchant has set "Behind
     * Load Balancer", so proxied stores get one bucket per shopper instead of one bucket
     * for the whole store, while a direct client still cannot forge its own.
     *
     * The 'u:'/'i:' prefixes keep the two key spaces disjoint.
     */
    public static function clientKey(): string
    {
        $app    = Factory::getApplication();
        $secret = (string) $app->get('secret');
        $user   = $app->getIdentity();
        $userId = $user ? (int) $user->id : 0;

        if ($userId > 0) {
            return 'u:' . substr(hash('sha256', 'user|' . $userId . '|' . $secret), 0, 62);
        }

        return 'i:' . substr(hash('sha256', 'ip|' . IpHelper::getIp() . '|' . $secret), 0, 62);
    }

    /**
     * Seed a runtime-created upload directory with the same empty index.html the
     * installer writes into the parent tree, so a mis-configured server cannot
     * serve a directory listing of customer files.
     */
    public static function ensureIndexHtml(string $directory): void
    {
        $indexFile = rtrim($directory, '/\\') . '/index.html';

        if (!is_dir($directory) || file_exists($indexFile)) {
            return;
        }

        @file_put_contents($indexFile, '<!DOCTYPE html><title></title>');
    }

    /** True when this client already stored HOURLY_UPLOAD_LIMIT uploads within the trailing hour. */
    public static function hasExceededHourlyLimit(): bool
    {
        if (!self::hasClientIpColumn()) {
            return false;
        }

        $db        = Factory::getContainer()->get(DatabaseInterface::class);
        $clientKey = self::clientKey();
        $cutoff    = (new \DateTimeImmutable('now -1 hour', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_uploads'))
            ->where($db->quoteName('client_ip') . ' = :clientKey')
            ->where($db->quoteName('created_on') . ' >= :cutoff')
            ->bind(':clientKey', $clientKey, ParameterType::STRING)
            ->bind(':cutoff', $cutoff, ParameterType::STRING);

        try {
            $db->setQuery($query);

            return (int) $db->loadResult() >= self::HOURLY_UPLOAD_LIMIT;
        } catch (\Throwable $e) {
            // A throttle that cannot read its own counter must not block shoppers.
            return false;
        }
    }

    /**
     * Insert a pending upload row tied to the in-progress cart.
     *
     * @return  bool  True on success, false on DB error.
     */
    public static function createPendingUpload(
        int $cartId,
        string $originalName,
        string $mangledName,
        string $savedName,
        string $mimeType,
        int $fileSize,
        int $userId,
        int $expiresInDays = 7
    ): bool {
        $db        = Factory::getContainer()->get(DatabaseInterface::class);
        $now       = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $expiresOn = (new \DateTimeImmutable("now +{$expiresInDays} days", new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $status    = 'pending';
        $orderId   = '';
        $clientKey = self::clientKey();

        $columns = [
            'original_name', 'mangled_name', 'saved_name', 'mime_type',
            'order_id', 'cart_id', 'status', 'file_size',
        ];
        $placeholders = [
            ':origName', ':mangledName', ':savedName', ':mime',
            ':orderId', ':cartId', ':status', ':fileSize',
        ];

        // Only reference client_ip once the schema update has actually landed —
        // naming a column that does not exist would fail every upload.
        $storeClientKey = self::hasClientIpColumn();

        if ($storeClientKey) {
            $columns[]      = 'client_ip';
            $placeholders[] = ':clientKey';
        }

        array_push($columns, 'created_by', 'created_on', 'expires_on');
        array_push($placeholders, ':createdBy', ':createdOn', ':expiresOn');

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__j2commerce_uploads'))
            ->columns($db->quoteName($columns))
            ->values(implode(', ', $placeholders))
            ->bind(':origName', $originalName)
            ->bind(':mangledName', $mangledName)
            ->bind(':savedName', $savedName)
            ->bind(':mime', $mimeType)
            ->bind(':orderId', $orderId)
            ->bind(':cartId', $cartId, ParameterType::INTEGER)
            ->bind(':status', $status)
            ->bind(':fileSize', $fileSize, ParameterType::INTEGER)
            ->bind(':createdBy', $userId, ParameterType::INTEGER)
            ->bind(':createdOn', $now)
            ->bind(':expiresOn', $expiresOn);

        if ($storeClientKey) {
            $query->bind(':clientKey', $clientKey, ParameterType::STRING);
        }

        try {
            $db->setQuery($query);
            $db->execute();

            return true;
        } catch (\Throwable $e) {
            Log::add('Upload row could not be stored: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');

            return false;
        }
    }
}
