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
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Shared persistence + token helpers for customer file uploads.
 *
 * @since  6.3.0
 */
final class UploadHelper
{
    /** Cryptographically random 32-hex-character token (16 bytes). */
    public static function randomToken(): string
    {
        return bin2hex(random_bytes(16));
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

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__j2commerce_uploads'))
            ->columns($db->quoteName([
                'original_name', 'mangled_name', 'saved_name', 'mime_type',
                'order_id', 'cart_id', 'status', 'file_size',
                'created_by', 'created_on', 'expires_on',
            ]))
            ->values(':origName, :mangledName, :savedName, :mime, :orderId, :cartId, :status, :fileSize, :createdBy, :createdOn, :expiresOn')
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

        try {
            $db->setQuery($query);
            $db->execute();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
