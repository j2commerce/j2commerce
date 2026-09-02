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
 * Order file attachment + lookup helpers.
 *
 * @since  6.3.0
 */
final class OrderUploadHelper
{
    /**
     * Move all uploads referenced by an order's orderitemattributes into that order's
     * permanent folder and flip their DB rows to status='attached'. Rows still pending
     * come from tmp/; rows a prior unpaid order for the same cart already took come from
     * that order's folder. Safe to call more than once for the same order.
     *
     * Lookup is by mangled_name JOIN against orderitemattributes (not by cart_id) —
     * product-option uploads happen on the product detail page BEFORE a cart exists,
     * so the upload row's cart_id is often 0. The orderitemattribute_value preserves
     * the mangled token, giving us a reliable post-placement link.
     *
     * @return  array{moved: int, failed: int}
     */
    public static function attachUploadsToOrder(int $orderPk, string $orderVarchar): array
    {
        if ($orderPk <= 0 || $orderVarchar === '') {
            return ['moved' => 0, 'failed' => 0];
        }

        $root = ConfigHelper::getAttachmentAbsolutePath();

        if ($root === null) {
            return ['moved' => 0, 'failed' => 0];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $mangledTypeMap = self::productOptionTokenMap($db, $orderVarchar);

        // The cart this order was built from. It scopes the reclaim below, and a cart-less
        // order (admin-created, migrated) simply takes the pending rows and nothing else.
        $cartQuery = $db->getQuery(true)
            ->select($db->quoteName('cart_id'))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('order_id') . ' = :orderVarchar')
            ->bind(':orderVarchar', $orderVarchar);
        $db->setQuery($cartQuery);
        $cartId = (int) $db->loadResult();

        // Nothing to look for: no product-option token on the order, and no cart whose own
        // pending uploads could belong to it.
        if (empty($mangledTypeMap) && $cartId <= 0) {
            return ['moved' => 0, 'failed' => 0];
        }

        // Pending rows are the first save's work. The rest is a reclaim: one cart can
        // produce more than one order — the confirm step re-persists whenever the cart
        // changes underneath it — and the first save consumes every pending row, leaving
        // the files filed under an order nobody will look at. So rows already attached to
        // another order for THIS cart are taken back, provided that order never claimed
        // anything: new, cancelled or failed. The same set inventory treats as holding no
        // stock is the set holding no files either, and an order that has been settled is
        // outside it, so its attachments are never disturbed.
        //
        // Both halves of the predicate carry weight. The mangled token arrives in the
        // request, so cart_id — read from the orders row, never from the upload row — is
        // what keeps the match inside the shopper's own carts.
        $nonHolding   = implode(',', array_map('intval', InventoryHelper::NON_HOLDING_STATUSES));
        $statusClause = $db->quoteName('u.status') . ' = ' . $db->quote('pending');

        if ($cartId > 0) {
            $statusClause = '(' . $statusClause
                . ' OR (' . $db->quoteName('u.status') . ' = ' . $db->quote('attached')
                . ' AND ' . $db->quoteName('u.order_id') . ' <> :ownVarchar'
                . ' AND ' . $db->quoteName('o.order_state_id') . ' IN (' . $nonHolding . ')'
                . ' AND ' . $db->quoteName('o.cart_id') . ' = :cartId))';
        }

        $uploadQuery = $db->getQuery(true)
            ->select($db->quoteName([
                'u.j2commerce_upload_id',
                'u.mangled_name',
                'u.saved_name',
                'u.cart_id',
                'u.order_id',
                'u.status',
                'u.mime_type',
            ]))
            ->from($db->quoteName('#__j2commerce_uploads', 'u'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_orders', 'o')
                . ' ON ' . $db->quoteName('o.order_id') . ' = ' . $db->quoteName('u.order_id')
            )
            ->where($statusClause);

        // Two ways a row belongs to this order. A product-option upload happens on the
        // product page before a cart exists, so its row carries cart_id 0 and the mangled
        // token preserved in the order line is the only link. A checkout custom-field
        // upload is the other shape: it can only happen once the shopper has a cart, its
        // row carries that cart_id, and no order line ever quotes its token, so nothing
        // but the cart identifies it. Neither source alone covers both — and cart_id is
        // read from the orders row, never from the request.
        $ownership = [];

        if (!empty($mangledTypeMap)) {
            $ownership[] = $db->quoteName('u.mangled_name') . ' IN ('
                . implode(',', $uploadQuery->bindArray(array_keys($mangledTypeMap), ParameterType::STRING)) . ')';
        }

        if ($cartId > 0) {
            $ownership[] = $db->quoteName('u.cart_id') . ' = :ownedCartId';
            $uploadQuery->bind(':ownedCartId', $cartId, ParameterType::INTEGER);
        }

        $uploadQuery->where('(' . implode(' OR ', $ownership) . ')');

        if ($cartId > 0) {
            $uploadQuery->bind(':ownVarchar', $orderVarchar)
                ->bind(':cartId', $cartId, ParameterType::INTEGER);
        }

        $db->setQuery($uploadQuery);
        $rows = $db->loadObjectList() ?: [];

        if (empty($rows)) {
            return ['moved' => 0, 'failed' => 0];
        }

        $orderDir = $root . '/orders/' . $orderVarchar;

        if (!is_dir($orderDir) && !@mkdir($orderDir, 0755, true) && !is_dir($orderDir)) {
            return ['moved' => 0, 'failed' => \count($rows)];
        }

        UploadHelper::ensureIndexHtml($orderDir);

        $moved            = 0;
        $failed           = 0;
        $tmpDirsTouched   = [];
        $now              = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        foreach ($rows as $row) {
            $isReclaim    = ($row->status ?? '') === 'attached';
            $sourceCartId = (int) ($row->cart_id ?? 0);
            $tmpDir       = $root . '/tmp/' . $sourceCartId;
            $src          = ($isReclaim ? $root . '/orders/' . $row->order_id : $tmpDir) . '/' . $row->saved_name;
            $dst          = $orderDir . '/' . $row->saved_name;

            // A prior run that moved the file and then failed to write the row leaves the
            // file at the destination with the row still naming the old location. Repairing
            // the row is all that is left to do, so the file already being here counts as
            // moved — otherwise that row fails on this and every later run, forever.
            if (!is_file($dst) && (!is_file($src) || !@rename($src, $dst))) {
                $failed++;
                continue;
            }

            if (!$isReclaim && $sourceCartId > 0) {
                $tmpDirsTouched[$tmpDir] = true;
            }

            // A checkout upload has no order line to take a type from, so the stored MIME
            // decides. The column is an enum of exactly these two.
            $attrType = $mangledTypeMap[$row->mangled_name]
                ?? (str_starts_with((string) ($row->mime_type ?? ''), 'image/') ? 'image' : 'file');
            $update   = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_uploads'))
                ->set($db->quoteName('order_id') . ' = :orderId')
                ->set($db->quoteName('status') . ' = ' . $db->quote('attached'))
                ->set($db->quoteName('attribute_type') . ' = :attrType')
                ->set($db->quoteName('expires_on') . ' = NULL')
                ->set($db->quoteName('modified_on') . ' = :modOn')
                ->where($db->quoteName('j2commerce_upload_id') . ' = :pk')
                ->bind(':orderId', $orderVarchar)
                ->bind(':attrType', $attrType)
                ->bind(':modOn', $now)
                ->bind(':pk', $row->j2commerce_upload_id, ParameterType::INTEGER);

            try {
                $db->setQuery($update)->execute();
                $moved++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        // Best-effort cleanup of emptied tmp dirs. tmp/0/ is a shared pool — never auto-remove.
        foreach (array_keys($tmpDirsTouched) as $tmpDir) {
            if (is_dir($tmpDir) && \count(@scandir($tmpDir) ?: []) <= 2) {
                @rmdir($tmpDir);
            }
        }

        return ['moved' => $moved, 'failed' => $failed];
    }

    /**
     * mangled token → attribute type for every file/image option on an order's lines.
     * These are the uploads an order line quotes; anything attached to the order and
     * absent from this map arrived through a checkout custom field instead.
     *
     * @return  array<string, string>
     */
    private static function productOptionTokenMap(DatabaseInterface $db, string $orderVarchar): array
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName(['product_id', 'orderitem_attributes']))
            ->from($db->quoteName('#__j2commerce_orderitems'))
            ->where($db->quoteName('order_id') . ' = :orderVarchar')
            ->bind(':orderVarchar', $orderVarchar);
        $db->setQuery($query);

        $map = [];

        foreach ($db->loadObjectList() ?: [] as $itemRow) {
            $raw = (string) ($itemRow->orderitem_attributes ?? '');

            if ($raw === '') {
                continue;
            }

            $attrs = OrderItemAttributeHelper::parseRawAttributes($raw, (int) ($itemRow->product_id ?? 0));

            foreach ($attrs as $attr) {
                $type  = $attr->orderitemattribute_type ?? '';
                $value = $attr->orderitemattribute_value ?? '';

                if (($type === 'file' || $type === 'image') && $value !== '') {
                    $map[(string) $value] = $type;
                }
            }
        }

        return $map;
    }

    /**
     * Uploads attached to an order that no order line quotes — what a shopper sent through
     * a checkout custom field. The admin order view lists these on their own, since the
     * product-option ones are already rendered against the line they belong to.
     *
     * @return  list<object>
     */
    public static function getCheckoutUploads(string $orderVarchar): array
    {
        if ($orderVarchar === '') {
            return [];
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_upload_id', 'original_name', 'mangled_name', 'file_size', 'mime_type']))
            ->from($db->quoteName('#__j2commerce_uploads'))
            ->where($db->quoteName('order_id') . ' = :orderVarchar')
            ->where($db->quoteName('status') . ' = ' . $db->quote('attached'))
            ->order($db->quoteName('j2commerce_upload_id') . ' ASC')
            ->bind(':orderVarchar', $orderVarchar);
        $db->setQuery($query);

        $rows = $db->loadObjectList() ?: [];

        if ($rows === []) {
            return [];
        }

        $productOptionTokens = self::productOptionTokenMap($db, $orderVarchar);

        return array_values(array_filter(
            $rows,
            static fn (object $row): bool => !isset($productOptionTokens[(string) $row->mangled_name])
        ));
    }

    /** Fetch an attached-upload row by mangled token; null if not found or not attached. */
    public static function getAttachedByMangled(string $mangledName): ?object
    {
        if ($mangledName === '') {
            return null;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_uploads'))
            ->where($db->quoteName('mangled_name') . ' = :mangled')
            ->where($db->quoteName('status') . ' = ' . $db->quote('attached'))
            ->bind(':mangled', $mangledName);
        $db->setQuery($query);

        $row = $db->loadObject();

        return $row ?: null;
    }

    /**
     * Resolve an attached upload's absolute on-disk path with traversal guard.
     * Returns null if the file is missing or escapes the attachment root.
     */
    public static function resolveOrderFilePath(string $orderVarchar, string $savedName): ?string
    {
        if ($orderVarchar === '' || $savedName === '') {
            return null;
        }

        $root = ConfigHelper::getAttachmentAbsolutePath();

        if ($root === null) {
            return null;
        }

        $candidate = $root . '/orders/' . $orderVarchar . '/' . $savedName;
        $real      = realpath($candidate);

        if ($real === false) {
            return null;
        }

        $ordersRoot = realpath($root . '/orders');

        if ($ordersRoot === false || !str_starts_with($real, $ordersRoot . \DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $real;
    }

    /** Cart-bucket twin of resolveOrderFilePath() — uploads still in the tmp/{cart_id} bucket. */
    public static function resolveCartFilePath(int $cartId, string $savedName): ?string
    {
        if ($cartId <= 0 || $savedName === '') {
            return null;
        }

        $root = ConfigHelper::getAttachmentAbsolutePath();

        if ($root === null) {
            return null;
        }

        $real    = realpath($root . '/tmp/' . $cartId . '/' . $savedName);
        $tmpRoot = realpath($root . '/tmp');

        if ($real === false || $tmpRoot === false || !str_starts_with($real, $tmpRoot . \DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $real;
    }

    /** Remove an order's upload rows and their files. Returns the row count removed. */
    public static function purgeForOrder(DatabaseInterface $db, string $orderVarchar): int
    {
        if ($orderVarchar === '') {
            return 0;
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_upload_id', 'saved_name', 'cart_id']))
            ->from($db->quoteName('#__j2commerce_uploads'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderVarchar);

        $rows = $db->setQuery($query)->loadObjectList() ?: [];

        foreach ($rows as $row) {
            $path = self::resolveOrderFilePath($orderVarchar, (string) $row->saved_name)
                ?? self::resolveCartFilePath((int) $row->cart_id, (string) $row->saved_name);

            if ($path !== null) {
                @unlink($path);
            }
        }

        if ($rows === []) {
            return 0;
        }

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_uploads'))
                ->where($db->quoteName('order_id') . ' = :orderId')
                ->bind(':orderId', $orderVarchar)
        )->execute();

        return \count($rows);
    }

    /** Remove a cart's upload rows and their files. Returns the row count removed. */
    public static function purgeForCart(DatabaseInterface $db, int $cartId): int
    {
        if ($cartId <= 0) {
            return 0;
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_upload_id', 'saved_name', 'order_id']))
            ->from($db->quoteName('#__j2commerce_uploads'))
            ->where($db->quoteName('cart_id') . ' = :cartId')
            ->where('COALESCE(' . $db->quoteName('order_id') . ", '') = ''")
            ->bind(':cartId', $cartId, ParameterType::INTEGER);

        $rows = $db->setQuery($query)->loadObjectList() ?: [];

        foreach ($rows as $row) {
            $path = self::resolveCartFilePath($cartId, (string) $row->saved_name);

            if ($path !== null) {
                @unlink($path);
            }
        }

        if ($rows === []) {
            return 0;
        }

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_uploads'))
                ->where($db->quoteName('cart_id') . ' = :cartId')
                ->where('COALESCE(' . $db->quoteName('order_id') . ", '') = ''")
                ->bind(':cartId', $cartId, ParameterType::INTEGER)
        )->execute();

        return \count($rows);
    }
}
