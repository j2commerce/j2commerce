<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Model;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\ConfigHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderItemAttributeHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\UploadHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\Folder;

/**
 * Relocates customer files an install wrote before the storage tree moved.
 *
 * Uploads used to land flat in a single folder; they now live under the configured
 * attachment root, in orders/{order_id} once attached and tmp/{cart_id} before that.
 * A file's own row says nothing about which order it belongs to — the columns that
 * would carry that arrived with the 6.3 schema and hold their defaults on every legacy
 * row — so the order line is what identifies it: the option that took the upload stored
 * the mangled token, and that token is still on the line. Matching it gives both the
 * destination and the record repair, and the file resolves only when both are done.
 */
class UploadmigrationModel extends BaseDatabaseModel
{
    /**
     * Both folders the flat storage used. The name changed in 6.1.6 (8099ee40c); a store
     * that took uploads on 6.0–6.1.x has files under the older one and rows that look
     * exactly like the rest.
     */
    public const LEGACY_RELATIVE_PATHS = [
        'media/com_j2commerce/uploads',
        'media/j2store/uploads',
    ];

    /** Files the legacy folder carries for its own sake, never customer content. */
    private const IGNORED_NAMES = ['index.html', 'index.php', '.htaccess', 'web.config'];

    /** How many order lines one pass reads while hunting for upload tokens. */
    private const ORDERITEM_CHUNK = 500;

    /** An order line still quotes this file: it goes to that order and its row is repaired. */
    public const STATE_REASSOCIATE = 'reassociate';

    /** A file whose row already names its destination, and that destination is free. */
    public const STATE_MOVABLE = 'movable';

    /** The destination already holds this name — that copy is the one the site resolves. */
    public const STATE_PRESENT = 'present';

    /** No row names this file. */
    public const STATE_ORPHAN = 'orphan';

    /** A row, but no order quotes it and no cart owns it. Left where it is. */
    public const STATE_UNMATCHED = 'unmatched';

    /** A row exists but says nothing usable about where the file belongs. */
    public const STATE_UNRESOLVED = 'unresolved';

    /**
     * The legacy folders this site actually has.
     *
     * @return array<string, string>  Relative path => absolute path.
     */
    public function getLegacyPaths(): array
    {
        $found = [];

        foreach (self::LEGACY_RELATIVE_PATHS as $relative) {
            $path = realpath(JPATH_ROOT . '/' . $relative);

            if ($path !== false && is_dir($path)) {
                $found[$relative] = $path;
            }
        }

        return $found;
    }

    /**
     * Classify everything sitting in the legacy folders without touching any of it.
     *
     * @return array{root: ?string, root_display: string, folders: array<string, string>, entries: array<int, array<string, mixed>>, counts: array<string, int>}
     */
    public function scan(): array
    {
        $folders = $this->getLegacyPaths();
        $root    = ConfigHelper::getAttachmentAbsolutePath();
        $files   = [];

        foreach ($folders as $relative => $absolute) {
            foreach ($this->legacyNames($absolute) as $name) {
                $files[] = ['folder' => $relative, 'source' => $absolute . '/' . $name, 'name' => $name];
            }
        }

        $rows    = $this->rowsFor(array_column($files, 'name'));
        $tokens  = $this->orderTokens($rows);
        $entries = [];

        foreach ($files as $file) {
            $entries[] = $this->classify($file, $root, $rows[$file['name']] ?? [], $tokens);
        }

        usort($entries, static fn (array $a, array $b) => [$a['state'], $a['folder'], $a['name']] <=> [$b['state'], $b['folder'], $b['name']]);

        // root_display rather than the absolute path: the report is open to viewsetup, which is
        // grantable without the System Information screen that would otherwise carry it.
        return [
            'root'         => $root,
            'root_display' => $root !== null ? $this->relative($root) : '',
            'folders'      => $folders,
            'entries'      => $entries,
            'counts'       => $this->tally($entries),
        ];
    }

    /**
     * Act on everything the scan resolved. Files with no order and no cart behind them are
     * counted and left alone: the flat folder is the only place that remembers them, while
     * tmp/ is swept on age as well as on expiry, so parking them there would put them in
     * front of the retention task rather than out of its way.
     *
     * @return array{reassociated: int, moved: int, skipped: int, unmatched: int, orphan: int, failed: int, notes: array<int, string>}
     */
    public function migrate(): array
    {
        $scan   = $this->scan();
        $result = ['reassociated' => 0, 'moved' => 0, 'skipped' => 0, 'unmatched' => 0, 'orphan' => 0, 'failed' => 0, 'notes' => []];

        if ($scan['folders'] === [] || $scan['root'] === null) {
            return $result;
        }

        foreach ($scan['entries'] as $entry) {
            switch ($entry['state']) {
                case self::STATE_ORPHAN:
                    $result['orphan']++;
                    break;

                case self::STATE_UNMATCHED:
                    $result['unmatched']++;
                    break;

                case self::STATE_PRESENT:
                    $result['skipped']++;
                    break;

                case self::STATE_MOVABLE:
                    $result[$this->move($entry, $scan['root'], $result['notes'])]++;
                    break;

                case self::STATE_REASSOCIATE:
                    $result[$this->reassociate($entry, $scan['root'], $result['notes'])]++;
                    break;

                default:
                    $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * Put the file under its order and repair the row. The file arriving is not the point on
     * its own — a record that still reads pending resolves nowhere — so the row deciding the
     * outcome is what makes a half-done run visible instead of silently successful.
     *
     * @param  array<string, mixed>  $entry
     * @param  array<int, string>    $notes
     *
     * @return 'reassociated'|'failed'
     */
    private function reassociate(array $entry, string $root, array &$notes): string
    {
        // 'skipped' here is the file already being at the destination, which leaves the row
        // as the only outstanding work — a prior run that moved and then failed to write.
        if ($this->move($entry, $root, $notes) === 'failed') {
            return 'failed';
        }

        return $this->attach((int) $entry['upload_id'], (string) $entry['order_id'], (string) $entry['attribute_type'])
            ? 'reassociated'
            : 'failed';
    }

    /**
     * Copy, verify, then unlink: a half-written destination is worse than an unmoved source,
     * and a source that outlives its copy is simply skipped by the next run.
     *
     * @param  array<string, mixed>  $entry
     * @param  array<int, string>    $notes
     *
     * @return 'moved'|'skipped'|'failed'
     */
    private function move(array $entry, string $root, array &$notes): string
    {
        $directory = (string) $entry['directory'];
        $name      = (string) $entry['name'];
        $source    = (string) $entry['source'];

        if (!is_dir($directory) && !Folder::create($directory)) {
            return 'failed';
        }

        UploadHelper::ensureIndexHtml($directory);

        $real = realpath($directory);

        if ($real === false || !$this->isWithin($real, $root)) {
            return 'failed';
        }

        $destination = $real . '/' . $name;

        // Re-checked here and not only in the scan: this is what makes a second run, or a run
        // after a partial failure, find nothing left to do rather than overwrite a good copy.
        if (file_exists($destination)) {
            return 'skipped';
        }

        $sourceSize = filesize($source);

        if ($sourceSize === false || !@copy($source, $destination)) {
            return 'failed';
        }

        clearstatcache(true, $destination);

        if (filesize($destination) !== $sourceSize) {
            @unlink($destination);

            return 'failed';
        }

        if (!@unlink($source)) {
            // The file resolves from here on; the source is now a duplicate the next run skips.
            $notes[] = $name;
        }

        return 'moved';
    }

    /** Flip a legacy row to the order its file was found on. */
    private function attach(int $pk, string $orderId, string $attributeType): bool
    {
        if ($pk <= 0 || $orderId === '') {
            return false;
        }

        $db   = $this->getDatabase();
        $now  = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $type = $attributeType === 'image' ? 'image' : 'file';

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_uploads'))
            ->set($db->quoteName('order_id') . ' = :orderId')
            ->set($db->quoteName('status') . ' = ' . $db->quote('attached'))
            ->set($db->quoteName('attribute_type') . ' = :attrType')
            ->set($db->quoteName('expires_on') . ' = NULL')
            ->set($db->quoteName('modified_on') . ' = :modOn')
            ->where($db->quoteName('j2commerce_upload_id') . ' = :pk')
            ->bind(':orderId', $orderId)
            ->bind(':attrType', $type)
            ->bind(':modOn', $now)
            ->bind(':pk', $pk, ParameterType::INTEGER);

        try {
            $db->setQuery($query)->execute();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<int, string> */
    private function legacyNames(string $legacy): array
    {
        $names = [];

        foreach ((array) scandir($legacy) as $name) {
            if (
                \in_array($name, ['.', '..'], true)
                || \in_array(strtolower((string) $name), self::IGNORED_NAMES, true)
                || !is_file($legacy . '/' . $name)
            ) {
                continue;
            }

            $names[] = (string) $name;
        }

        return $names;
    }

    /**
     * @param  array<string, mixed>                       $file    Folder, absolute source and name.
     * @param  array<int, object>                         $rows    Every upload row carrying this saved_name.
     * @param  array<string, ?array{order_id: string, type: string}>  $tokens  Order line each token was found on.
     *
     * @return array<string, mixed>
     */
    private function classify(array $file, ?string $root, array $rows, array $tokens): array
    {
        $entry = [
            'folder'         => $file['folder'],
            'name'           => $file['name'],
            'source'         => $file['source'],
            'size'           => (int) (filesize($file['source']) ?: 0),
            'status'         => '',
            'target'         => '',
            'directory'      => '',
            'order_id'       => '',
            'attribute_type' => '',
            'upload_id'      => 0,
            'state'          => self::STATE_ORPHAN,
        ];

        if ($rows === []) {
            return $entry;
        }

        // One name, two rows says nothing about which row owns the file, and picking one
        // could file a customer's attachment under a stranger's order.
        if (\count($rows) > 1 || $root === null) {
            $entry['state'] = self::STATE_UNRESOLVED;

            return $entry;
        }

        $row                = $rows[0];
        $entry['status']    = (string) $row->status;
        $entry['upload_id'] = (int) $row->j2commerce_upload_id;

        $mangled = (string) ($row->mangled_name ?? '');
        $match   = $mangled !== '' && \array_key_exists($mangled, $tokens) ? $tokens[$mangled] : false;

        // Present but null: more than one order quotes the token, so it is not the identifier
        // it is meant to be and neither claim is safe to act on.
        if ($match === null) {
            $entry['state'] = self::STATE_UNRESOLVED;

            return $entry;
        }

        if (\is_array($match)) {
            $entry['order_id']       = $match['order_id'];
            $entry['attribute_type'] = $match['type'];

            return $this->withOrderDestination($entry, $root, $match['order_id'], $row);
        }

        $orderId = (string) ($row->order_id ?? '');

        if ((string) $row->status === 'attached' && $orderId !== '') {
            $entry['order_id'] = $orderId;

            return $this->withOrderDestination($entry, $root, $orderId, $row);
        }

        $cartId = (int) ($row->cart_id ?? 0);

        if ($cartId <= 0) {
            $entry['state'] = self::STATE_UNMATCHED;

            return $entry;
        }

        return $this->withDestination($entry, $root . '/tmp/' . $cartId, false);
    }

    /**
     * @param  array<string, mixed>  $entry
     *
     * @return array<string, mixed>
     */
    private function withOrderDestination(array $entry, string $root, string $orderId, object $row): array
    {
        if (!$this->isPlainSegment($orderId)) {
            $entry['state'] = self::STATE_UNRESOLVED;

            return $entry;
        }

        // The file already being there leaves the row as the only outstanding work, so only a
        // row that already reads right makes this entry a no-op.
        $settled = (string) $row->status === 'attached' && (string) ($row->order_id ?? '') === $orderId;

        return $this->withDestination($entry, $root . '/orders/' . $orderId, !$settled);
    }

    /**
     * @param  array<string, mixed>  $entry
     *
     * @return array<string, mixed>
     */
    private function withDestination(array $entry, string $directory, bool $needsRow): array
    {
        $entry['directory'] = $directory;
        $entry['target']    = $this->relative($directory) . '/' . $entry['name'];
        $placed             = is_file($directory . '/' . $entry['name']);

        $entry['state'] = match (true) {
            $needsRow => self::STATE_REASSOCIATE,
            $placed   => self::STATE_PRESENT,
            default   => self::STATE_MOVABLE,
        };

        return $entry;
    }

    /**
     * Every row naming one of these files, grouped by saved_name. One query: a legacy folder
     * can hold an order history's worth of files.
     *
     * @param  array<int, string>  $savedNames
     *
     * @return array<string, array<int, object>>
     */
    private function rowsFor(array $savedNames): array
    {
        $savedNames = array_values(array_unique($savedNames));

        if ($savedNames === []) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_upload_id', 'saved_name', 'mangled_name', 'order_id', 'cart_id', 'status']))
            ->from($db->quoteName('#__j2commerce_uploads'))
            ->whereIn($db->quoteName('saved_name'), $savedNames, ParameterType::STRING);

        $grouped = [];

        foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row) {
            $grouped[(string) $row->saved_name][] = $row;
        }

        return $grouped;
    }

    /**
     * Find the order line each unplaced file is quoted on. Only rows that cannot already say
     * where they belong are looked for, so a folder holding nothing but settled files costs
     * no scan at all.
     *
     * @param  array<string, array<int, object>>  $rowsByName
     *
     * @return array<string, ?array{order_id: string, type: string}>  Null where more than one order claims the token.
     */
    private function orderTokens(array $rowsByName): array
    {
        $needles = [];

        foreach ($rowsByName as $rows) {
            if (\count($rows) !== 1) {
                continue;
            }

            $row = $rows[0];

            if ((string) $row->status === 'attached' && (string) ($row->order_id ?? '') !== '') {
                continue;
            }

            $mangled = (string) ($row->mangled_name ?? '');

            if ($mangled !== '') {
                $needles[$mangled] = true;
            }
        }

        return $needles === [] ? [] : $this->scanOrderItems($needles);
    }

    /**
     * The token cannot be matched in SQL: a line's attributes are as often base64 over a
     * serialised array as they are JSON, so the value never appears in the column as itself.
     * Reading the history in chunks is what that costs.
     *
     * @param  array<string, true>  $needles
     *
     * @return array<string, ?array{order_id: string, type: string}>
     */
    private function scanOrderItems(array $needles): array
    {
        $db     = $this->getDatabase();
        $found  = [];
        $lastId = 0;

        while (true) {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['j2commerce_orderitem_id', 'order_id', 'product_id', 'orderitem_attributes']))
                ->from($db->quoteName('#__j2commerce_orderitems'))
                ->where($db->quoteName('orderitem_attributes') . ' IS NOT NULL')
                ->where($db->quoteName('orderitem_attributes') . ' <> ' . $db->quote(''))
                ->where($db->quoteName('j2commerce_orderitem_id') . ' > :lastId')
                ->order($db->quoteName('j2commerce_orderitem_id') . ' ASC')
                ->bind(':lastId', $lastId, ParameterType::INTEGER);

            $rows = $db->setQuery($query, 0, self::ORDERITEM_CHUNK)->loadObjectList() ?: [];

            if ($rows === []) {
                return $found;
            }

            foreach ($rows as $row) {
                $lastId = (int) $row->j2commerce_orderitem_id;

                $this->collectTokens($row, $needles, $found);
            }

            if (\count($rows) < self::ORDERITEM_CHUNK) {
                return $found;
            }
        }
    }

    /**
     * @param  array<string, true>                                    $needles
     * @param  array<string, ?array{order_id: string, type: string}>  $found
     */
    private function collectTokens(object $row, array $needles, array &$found): void
    {
        $raw = (string) ($row->orderitem_attributes ?? '');

        if (!$this->quotesAny($raw, $needles)) {
            return;
        }

        $orderId = (string) ($row->order_id ?? '');

        foreach (OrderItemAttributeHelper::parseRawAttributes($raw, (int) ($row->product_id ?? 0)) as $attr) {
            $type  = (string) ($attr->orderitemattribute_type ?? '');
            $value = (string) ($attr->orderitemattribute_value ?? '');

            if (($type !== 'file' && $type !== 'image') || !isset($needles[$value])) {
                continue;
            }

            if (\array_key_exists($value, $found) && ($found[$value]['order_id'] ?? null) !== $orderId) {
                $found[$value] = null;

                continue;
            }

            $found[$value] = ['order_id' => $orderId, 'type' => $type];
        }
    }

    /**
     * Whether a line's attributes hold any of these tokens, read without touching the
     * database. The authoritative parse resolves option ids one query each, which is far too
     * much to spend on the many lines that quote no upload at all.
     *
     * @param  array<string, true>  $needles
     */
    private function quotesAny(string $raw, array $needles): bool
    {
        $decoded = json_decode($raw);

        if (!\is_array($decoded)) {
            $blob    = @base64_decode($raw, true);
            $decoded = $blob === false ? null : @unserialize($blob, ['allowed_classes' => false]);
        }

        if (!\is_array($decoded)) {
            return false;
        }

        foreach ($decoded as $item) {
            $value = match (true) {
                \is_object($item) => (string) ($item->orderitemattribute_value ?? ''),
                \is_array($item)  => (string) ($item['option_value'] ?? ''),
                \is_scalar($item) => (string) $item,
                default           => '',
            };

            if ($value !== '' && isset($needles[$value])) {
                return true;
            }
        }

        return false;
    }

    /** A path segment that stays one level down: no separators, no traversal, no emptiness. */
    private function isPlainSegment(string $segment): bool
    {
        return $segment !== ''
            && !\in_array($segment, ['.', '..'], true)
            && basename($segment) === $segment
            && !str_contains($segment, '\\');
    }

    private function isWithin(string $path, string $root): bool
    {
        $pathNorm = rtrim(str_replace('\\', '/', $path), '/');
        $rootNorm = rtrim(str_replace('\\', '/', $root), '/');

        return str_starts_with($pathNorm . '/', $rootNorm . '/');
    }

    private function relative(string $absolute): string
    {
        $rootNorm = rtrim(str_replace('\\', '/', JPATH_ROOT), '/');
        $pathNorm = str_replace('\\', '/', $absolute);

        return str_starts_with($pathNorm, $rootNorm . '/') ? substr($pathNorm, \strlen($rootNorm) + 1) : $pathNorm;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     *
     * @return array<string, int>
     */
    private function tally(array $entries): array
    {
        $counts = [
            self::STATE_REASSOCIATE => 0,
            self::STATE_MOVABLE     => 0,
            self::STATE_PRESENT     => 0,
            self::STATE_UNMATCHED   => 0,
            self::STATE_ORPHAN      => 0,
            self::STATE_UNRESOLVED  => 0,
        ];

        foreach ($entries as $entry) {
            $counts[$entry['state']]++;
        }

        return $counts;
    }
}
