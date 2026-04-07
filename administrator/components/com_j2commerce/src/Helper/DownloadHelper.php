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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

final class DownloadHelper
{
    /**
     * Create orderdownload records for each downloadable item in an order.
     * Called after order is saved. Records are created with NULL access_granted
     * — downloads are not yet available until order status changes to an allowed status.
     */
    public static function createOrderDownloads(string $orderId, int $userId, string $userEmail): void
    {
        if (empty($orderId)) {
            return;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // Find downloadable items in this order
        $query = $db->getQuery(true)
            ->select($db->quoteName('product_id'))
            ->from($db->quoteName('#__j2commerce_orderitems'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->where($db->quoteName('product_type') . ' = ' . $db->quote('downloadable'))
            ->bind(':orderId', $orderId);

        $db->setQuery($query);
        $productIds = $db->loadColumn();

        if (empty($productIds)) {
            return;
        }

        $now      = Factory::getDate()->toSql();
        $nullDate = $db->getNullDate();

        foreach ($productIds as $productId) {
            $productId = (int) $productId;

            // Skip if record already exists (order resume scenario)
            $existsQuery = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__j2commerce_orderdownloads'))
                ->where($db->quoteName('order_id') . ' = :orderId')
                ->where($db->quoteName('product_id') . ' = :productId')
                ->bind(':orderId', $orderId)
                ->bind(':productId', $productId, ParameterType::INTEGER);

            $db->setQuery($existsQuery);

            if ((int) $db->loadResult() > 0) {
                continue;
            }

            $columns = ['order_id', 'product_id', 'user_email', 'user_id', 'limit_count', 'access_granted', 'access_expires'];
            $values  = [
                $db->quote($orderId),
                $productId,
                $db->quote($userEmail),
                $userId,
                0,
                $db->quote($nullDate),
                $db->quote($nullDate),
            ];

            $insertQuery = $db->getQuery(true)
                ->insert($db->quoteName('#__j2commerce_orderdownloads'))
                ->columns($db->quoteName($columns))
                ->values(implode(',', $values));

            $db->setQuery($insertQuery);
            $db->execute();
        }
    }

    /**
     * Grant download access for an order — set access_granted and calculate access_expires.
     * Called when order status changes to an allowed download status.
     */
    public static function grantDownloads(string $orderId): void
    {
        if (empty($orderId)) {
            return;
        }

        $db       = Factory::getContainer()->get(DatabaseInterface::class);
        $nullDate = $db->getNullDate();

        // Load download records that haven't been granted yet
        // Check for SQL NULL, null date string, or '0000-00-00 00:00:00'
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_orderdownloads'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->where('(' . $db->quoteName('access_granted') . ' IS NULL OR '
                . $db->quoteName('access_granted') . ' = :nullDate OR '
                . $db->quoteName('access_granted') . ' = ' . $db->quote('0000-00-00 00:00:00') . ')')
            ->bind(':orderId', $orderId)
            ->bind(':nullDate', $nullDate);

        $db->setQuery($query);
        $downloads = $db->loadObjectList();

        if (empty($downloads)) {
            return;
        }

        $now    = Factory::getDate();
        $nowSql = $now->toSql();

        foreach ($downloads as $download) {
            $accessExpires = $nullDate;

            // Load product params to get download_expiry (days)
            $productId  = (int) $download->product_id;
            $paramQuery = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__j2commerce_products'))
                ->where($db->quoteName('j2commerce_product_id') . ' = :pid')
                ->bind(':pid', $productId, ParameterType::INTEGER);

            $db->setQuery($paramQuery);
            $rawParams = $db->loadResult();

            if (!empty($rawParams)) {
                $registry   = new Registry($rawParams);
                $expiryDays = (int) $registry->get('download_expiry', 0);

                if ($expiryDays > 0) {
                    $accessExpires = Factory::getDate('+' . $expiryDays . ' days')->toSql();
                }
            }

            $downloadId  = (int) $download->j2commerce_orderdownload_id;
            $updateQuery = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_orderdownloads'))
                ->set($db->quoteName('access_granted') . ' = :granted')
                ->set($db->quoteName('access_expires') . ' = :expires')
                ->where($db->quoteName('j2commerce_orderdownload_id') . ' = :id')
                ->bind(':granted', $nowSql)
                ->bind(':expires', $accessExpires)
                ->bind(':id', $downloadId, ParameterType::INTEGER);

            $db->setQuery($updateQuery);
            $db->execute();
        }

        OrderHistoryHelper::add(
            orderId: $orderId,
            comment: Text::_('COM_J2COMMERCE_ORDER_DOWNLOAD_PERMISSION_GRANTED'),
        );
    }

    /**
     * Reset download limits (limit_count = 0) for all downloads in an order.
     */
    public static function resetDownloadLimits(string $orderId): void
    {
        if (empty($orderId)) {
            return;
        }

        $db   = Factory::getContainer()->get(DatabaseInterface::class);
        $zero = 0;

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_orderdownloads'))
            ->set($db->quoteName('limit_count') . ' = :zero')
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':zero', $zero, ParameterType::INTEGER)
            ->bind(':orderId', $orderId);

        $db->setQuery($query);
        $db->execute();

        OrderHistoryHelper::add(
            orderId: $orderId,
            comment: Text::_('COM_J2COMMERCE_ORDER_DOWNLOAD_LIMIT_RESET'),
        );
    }

    /**
     * Reset download access (re-grant with new expiry) for all downloads in an order.
     */
    public static function resetDownloadAccess(string $orderId): void
    {
        if (empty($orderId)) {
            return;
        }

        $db       = Factory::getContainer()->get(DatabaseInterface::class);
        $nullDate = $db->getNullDate();

        // Reset access_granted to null so grantDownloads() will re-process them
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_orderdownloads'))
            ->set($db->quoteName('access_granted') . ' = :nullDate')
            ->set($db->quoteName('access_expires') . ' = :nullDate2')
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':nullDate', $nullDate)
            ->bind(':nullDate2', $nullDate)
            ->bind(':orderId', $orderId);

        $db->setQuery($query);
        $db->execute();

        // Re-grant with fresh dates
        self::grantDownloads($orderId);

        OrderHistoryHelper::add(
            orderId: $orderId,
            comment: Text::_('COM_J2COMMERCE_ORDER_DOWNLOAD_ACCESS_RESET'),
        );
    }

    /**
     * Get the download limit for a product from its params.
     * Returns 0 for unlimited, >0 for limited.
     */
    public static function getDownloadLimit(int $productId): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__j2commerce_products'))
            ->where($db->quoteName('j2commerce_product_id') . ' = :pid')
            ->bind(':pid', $productId, ParameterType::INTEGER);

        $db->setQuery($query);
        $rawParams = $db->loadResult();

        if (empty($rawParams)) {
            return 0;
        }

        return (int) (new Registry($rawParams))->get('download_limit', 0);
    }
}
