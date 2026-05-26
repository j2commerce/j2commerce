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
     * Move all pending uploads referenced by an order's just-saved orderitemattributes
     * into the order's permanent folder and flip their DB rows to status='attached'.
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

        // Load each orderitem's attributes blob (JSON-encoded in orderitems.orderitem_attributes).
        $query = $db->getQuery(true)
            ->select($db->quoteName(['product_id', 'orderitem_attributes']))
            ->from($db->quoteName('#__j2commerce_orderitems'))
            ->where($db->quoteName('order_id') . ' = :orderVarchar')
            ->bind(':orderVarchar', $orderVarchar);
        $db->setQuery($query);
        $itemRows = $db->loadObjectList() ?: [];

        // Extract mangled→attribute-type mapping for file/image-typed attributes via the shared parser.
        $mangledTypeMap = [];

        foreach ($itemRows as $itemRow) {
            $raw = (string) ($itemRow->orderitem_attributes ?? '');

            if ($raw === '') {
                continue;
            }

            $attrs = OrderItemAttributeHelper::parseRawAttributes($raw, (int) ($itemRow->product_id ?? 0));

            foreach ($attrs as $attr) {
                $type  = $attr->orderitemattribute_type ?? '';
                $value = $attr->orderitemattribute_value ?? '';

                if (($type === 'file' || $type === 'image') && $value !== '') {
                    $mangledTypeMap[(string) $value] = $type;
                }
            }
        }

        if (empty($mangledTypeMap)) {
            return ['moved' => 0, 'failed' => 0];
        }

        // Find matching pending upload rows.
        $uploadQuery = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_upload_id', 'mangled_name', 'saved_name', 'cart_id']))
            ->from($db->quoteName('#__j2commerce_uploads'))
            ->whereIn($db->quoteName('mangled_name'), array_keys($mangledTypeMap), ParameterType::STRING)
            ->where($db->quoteName('status') . ' = ' . $db->quote('pending'));
        $db->setQuery($uploadQuery);
        $rows = $db->loadObjectList() ?: [];

        if (empty($rows)) {
            return ['moved' => 0, 'failed' => 0];
        }

        $orderDir = $root . '/orders/' . $orderVarchar;

        if (!is_dir($orderDir) && !@mkdir($orderDir, 0755, true) && !is_dir($orderDir)) {
            return ['moved' => 0, 'failed' => \count($rows)];
        }

        $moved            = 0;
        $failed           = 0;
        $tmpDirsTouched   = [];
        $now              = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        foreach ($rows as $row) {
            $sourceCartId = (int) ($row->cart_id ?? 0);
            $tmpDir       = $root . '/tmp/' . $sourceCartId;
            $src          = $tmpDir . '/' . $row->saved_name;
            $dst          = $orderDir . '/' . $row->saved_name;

            if (!is_file($src) || !@rename($src, $dst)) {
                $failed++;
                continue;
            }

            if ($sourceCartId > 0) {
                $tmpDirsTouched[$tmpDir] = true;
            }

            $attrType = $mangledTypeMap[$row->mangled_name] ?? 'file';
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
}
