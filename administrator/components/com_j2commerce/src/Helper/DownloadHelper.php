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

use Joomla\CMS\Component\ComponentHelper;
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

        // Every item in the order, not only the ones whose own product_type says
        // 'downloadable'. A composite type keeps its downloadable children in params,
        // so its order row names the container; the product type itself is the only
        // thing that can resolve those children, and it does so off the event below.
        $query = $db->getQuery(true)
            ->select($db->quoteName(['product_id', 'product_type']))
            ->from($db->quoteName('#__j2commerce_orderitems'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);

        $db->setQuery($query);
        $items = $db->loadObjectList();

        if (empty($items)) {
            return;
        }

        $nullDate = $db->getNullDate();
        $order    = (object) [
            'order_id'   => $orderId,
            'user_id'    => $userId,
            'user_email' => $userEmail,
        ];

        foreach ($items as $item) {
            $productId = (int) $item->product_id;

            if ($item->product_type === 'downloadable') {
                // Skip if record already exists (order resume scenario)
                $existsQuery = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__j2commerce_orderdownloads'))
                    ->where($db->quoteName('order_id') . ' = :orderId')
                    ->where($db->quoteName('product_id') . ' = :productId')
                    ->bind(':orderId', $orderId)
                    ->bind(':productId', $productId, ParameterType::INTEGER);

                $db->setQuery($existsQuery);

                if ((int) $db->loadResult() === 0) {
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

            J2CommerceHelper::plugin()->event('SaveOrderFiles', [$order, &$item]);
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
     * One row per downloadable file in an order, carrying the same availability rules the
     * download endpoint enforces. Every surface that offers a link reads them from here, so
     * an offered link and the endpoint that answers it can never disagree.
     *
     * @return  list<object>
     */
    public static function getOrderDownloads(string $orderId): array
    {
        if ($orderId === '') {
            return [];
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName([
                'd.j2commerce_orderdownload_id',
                'd.order_id',
                'd.product_id',
                'd.limit_count',
                'd.access_granted',
                'd.access_expires',
                'f.j2commerce_productfile_id',
                'f.product_file_display_name',
                'f.product_file_save_name',
            ]))
            ->select($db->quoteName('p.params', 'product_params'))
            ->from($db->quoteName('#__j2commerce_orderdownloads', 'd'))
            ->join('INNER', $db->quoteName('#__j2commerce_productfiles', 'f')
                . ' ON ' . $db->quoteName('f.product_id') . ' = ' . $db->quoteName('d.product_id'))
            ->join('INNER', $db->quoteName('#__j2commerce_orders', 'o')
                . ' ON ' . $db->quoteName('o.order_id') . ' = ' . $db->quoteName('d.order_id'))
            ->join('LEFT', $db->quoteName('#__j2commerce_products', 'p')
                . ' ON ' . $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('d.product_id'))
            ->where($db->quoteName('d.order_id') . ' = :orderId')
            ->bind(':orderId', $orderId)
            ->order($db->quoteName('f.j2commerce_productfile_id') . ' ASC');

        // Same order-status gate the account Downloads list applies, so a link is never
        // offered for an order the endpoint would refuse to serve.
        $statusIds = self::allowedDownloadStatuses();

        if ($statusIds !== []) {
            $placeholders = [];

            // bind() takes its value by reference, so each placeholder must name its own
            // array slot rather than a loop variable every iteration would overwrite.
            foreach (array_keys($statusIds) as $i) {
                $placeholders[] = ':dlStatus' . $i;
                $query->bind(':dlStatus' . $i, $statusIds[$i], ParameterType::INTEGER);
            }

            $query->where($db->quoteName('o.order_state_id') . ' IN (' . implode(',', $placeholders) . ')');
        }

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $nullDate = $db->getNullDate();
        $now      = time();

        foreach ($rows as $row) {
            $granted = (string) ($row->access_granted ?? '');
            $expires = (string) ($row->access_expires ?? '');

            $row->pending = $granted === '' || $granted === $nullDate || $granted === '0000-00-00 00:00:00';
            $row->expired = !$row->pending
                && $expires !== '' && $expires !== $nullDate && $expires !== '0000-00-00 00:00:00'
                && strtotime($expires) < $now;

            $limit      = empty($row->product_params)
                ? 0
                : (int) (new Registry($row->product_params))->get('download_limit', 0);
            $limitCount = (int) ($row->limit_count ?? 0);

            $row->limit_reached = $limit > 0 && $limitCount >= $limit;
            $row->remaining     = $limit > 0 ? max(0, $limit - $limitCount) : -1;
            $row->can_download  = !$row->pending && !$row->expired && !$row->limit_reached
                && !empty($row->product_file_save_name);
        }

        return $rows;
    }

    /**
     * Order states downloads are released in, from `limit_orderstatuses`.
     * An empty list means the setting places no restriction.
     *
     * @return  list<int>
     */
    private static function allowedDownloadStatuses(): array
    {
        $configured = ComponentHelper::getParams('com_j2commerce')->get('limit_orderstatuses', '');

        if (empty($configured)) {
            return [];
        }

        $statusIds = \is_array($configured)
            ? array_map('intval', $configured)
            : array_map('intval', explode(',', (string) $configured));

        return array_values(array_filter($statusIds, static fn (int $id): bool => $id > 0));
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

    /**
     * Allowed local roots for downloadable-product files: the configured attachments
     * folder plus the legacy images dir. Absolute (native separators, unresolved).
     *
     * @return  list<string>
     */
    public static function allowedDownloadRoots(): array
    {
        $roots = [];

        // Read through ConfigHelper so this reader normalises the value the way the installer
        // and the upload paths do. Reading it raw meant a Windows-typed 'files\com_j2commerce'
        // named one literal directory here, whose realpath() fails on Linux and dropped the
        // attachment root from the union without a word.
        $attach = ConfigHelper::getAttachmentPath();

        // Segment scan, not a whole-string compare: 'files/../..' passed the old test intact
        // and resolved to the site's parent, at which point the prefix test in
        // isAllowedResolvedPath() accepted everything beneath it. A '.' segment carries no
        // traversal, so it is dropped rather than treated as hostile — rejecting it would
        // send a working 'files/./x' to the default root and break its downloads.
        $segments = array_filter(
            explode('/', $attach),
            static fn (string $segment): bool => $segment !== '' && $segment !== '.'
        );

        $attach = $segments === [] || \in_array('..', $segments, true)
            ? AttachmentDenyFileHelper::defaultPath()
            : implode('/', $segments);

        foreach (array_unique([$attach, 'images']) as $root) {
            // Absolute attachment roots are not servable by the download endpoint,
            // which always resolves stored paths against JPATH_SITE.
            if ($root === '' || preg_match('#^(?:/|[a-zA-Z]:)#', $root)) {
                continue;
            }

            $roots[] = JPATH_SITE . '/' . $root;
        }

        return $roots;
    }

    /** Save-side validation: the path must resolve inside an allowed root. */
    public static function isAllowedLocalPath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        if (str_starts_with($normalized, '/') || preg_match('#^[a-zA-Z]:#', $normalized)) {
            return false;
        }

        if (preg_match('#(?:^|/)\.\.(?:/|$)#', $normalized) || preg_match('#(?:^|/)\.[^/]#', $normalized)) {
            return false;
        }

        if (basename($normalized) === 'configuration.php') {
            return false;
        }

        $sitePrefix = rtrim(str_replace('\\', '/', JPATH_SITE), '/') . '/';

        foreach (self::allowedDownloadRoots() as $root) {
            $root = rtrim(str_replace('\\', '/', $root), '/') . '/';

            // Express the root site-relative: stored paths are site-relative.
            if (str_starts_with($root, $sitePrefix)) {
                $root = substr($root, \strlen($sitePrefix));
            } elseif (preg_match('#^(?:/|[a-zA-Z]:)#', $root)) {
                // Root outside the site — a relative path can never point into it.
                continue;
            }

            if ($root !== '' && str_starts_with($normalized, $root)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sink-side validation of a realpath-resolved product file: must resolve inside
     * an allowed root and not be configuration.php. Dotfile segments are checked by
     * the caller against the stored relative path (the server prefix may legitimately
     * contain dot-leading segments on some hosts).
     */
    public static function isAllowedResolvedPath(string $realPath): bool
    {
        if (basename($realPath) === 'configuration.php') {
            return false;
        }

        foreach (self::allowedDownloadRoots() as $root) {
            $resolved = @realpath($root);

            if ($resolved !== false && str_starts_with($realPath, $resolved . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }
}
