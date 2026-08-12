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
use J2Commerce\Component\J2commerce\Administrator\Helper\UploadHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\Folder;

/**
 * Relocates customer files an install wrote before the storage tree moved.
 *
 * Uploads used to land flat in media/com_j2commerce/uploads; they now live under the
 * configured attachment root, in orders/{order_id} once attached and tmp/{cart_id} before
 * that. No column records a path — the destination is derived from the row — so moving the
 * file is the whole migration and nothing needs rewriting afterwards.
 */
class UploadmigrationModel extends BaseDatabaseModel
{
    public const LEGACY_RELATIVE_PATH = 'media/com_j2commerce/uploads';

    /** Files the legacy folder carries for its own sake, never customer content. */
    private const IGNORED_NAMES = ['index.html', 'index.php', '.htaccess', 'web.config'];

    /** A file with a row and a free destination. */
    public const STATE_MOVABLE = 'movable';

    /** The destination already holds this name — that copy is the one the site resolves. */
    public const STATE_PRESENT = 'present';

    /** No row names this file. */
    public const STATE_ORPHAN = 'orphan';

    /** A row exists but says nothing usable about where the file belongs. */
    public const STATE_UNRESOLVED = 'unresolved';

    public function getLegacyPath(): ?string
    {
        $path = realpath(JPATH_ROOT . '/' . self::LEGACY_RELATIVE_PATH);

        return ($path !== false && is_dir($path)) ? $path : null;
    }

    /**
     * Classify everything sitting in the legacy folder without touching any of it.
     *
     * @return array{root: ?string, root_display: string, legacy: ?string, entries: array<int, array<string, mixed>>, counts: array<string, int>}
     */
    public function scan(): array
    {
        $legacy  = $this->getLegacyPath();
        $root    = ConfigHelper::getAttachmentAbsolutePath();
        $names   = $this->legacyNames($legacy);
        $rows    = $this->rowsFor($names);
        $entries = [];

        foreach ($names as $name) {
            $entries[] = $this->classify($name, (string) $legacy, $root, $rows[$name] ?? []);
        }

        usort($entries, static fn (array $a, array $b) => [$a['state'], $a['name']] <=> [$b['state'], $b['name']]);

        // root_display rather than the absolute path: the report is open to viewsetup, which is
        // grantable without the System Information screen that would otherwise carry it.
        return [
            'root'         => $root,
            'root_display' => $root !== null ? $this->relative($root) : '',
            'legacy'       => $legacy,
            'entries'      => $entries,
            'counts'       => $this->tally($entries),
        ];
    }

    /**
     * Move every file the scan called movable. Copy, verify, then unlink: a half-written
     * destination is worse than an unmoved source, and a source that outlives its copy is
     * simply skipped by the next run.
     *
     * @return array{moved: int, skipped: int, orphan: int, failed: int, notes: array<int, string>}
     */
    public function migrate(): array
    {
        $scan   = $this->scan();
        $result = ['moved' => 0, 'skipped' => 0, 'orphan' => 0, 'failed' => 0, 'notes' => []];

        if ($scan['legacy'] === null || $scan['root'] === null) {
            return $result;
        }

        foreach ($scan['entries'] as $entry) {
            if ($entry['state'] === self::STATE_ORPHAN) {
                $result['orphan']++;
                continue;
            }

            if ($entry['state'] === self::STATE_PRESENT) {
                $result['skipped']++;
                continue;
            }

            if ($entry['state'] !== self::STATE_MOVABLE) {
                $result['failed']++;
                continue;
            }

            $moved = $this->move($scan['legacy'] . '/' . $entry['name'], (string) $entry['directory'], $entry['name'], $scan['root'], $result['notes']);
            $result[$moved]++;
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $notes
     *
     * @return 'moved'|'skipped'|'failed'
     */
    private function move(string $source, string $directory, string $name, string $root, array &$notes): string
    {
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

    /** @return array<int, string> */
    private function legacyNames(?string $legacy): array
    {
        if ($legacy === null) {
            return [];
        }

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
     * @param  array<int, object>  $rows  Every upload row carrying this saved_name.
     *
     * @return array<string, mixed>
     */
    private function classify(string $name, string $legacy, ?string $root, array $rows): array
    {
        $entry = [
            'name'      => $name,
            'size'      => (int) (filesize($legacy . '/' . $name) ?: 0),
            'status'    => '',
            'target'    => '',
            'directory' => '',
            'state'     => self::STATE_ORPHAN,
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

        $row              = $rows[0];
        $entry['status']  = (string) $row->status;
        $directory        = $this->destinationFor($row, $root);

        if ($directory === null) {
            $entry['state'] = self::STATE_UNRESOLVED;

            return $entry;
        }

        $entry['directory'] = $directory;
        $entry['target']    = $this->relative($directory) . '/' . $name;
        $entry['state']     = is_file($directory . '/' . $name) ? self::STATE_PRESENT : self::STATE_MOVABLE;

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
        if ($savedNames === []) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_upload_id', 'saved_name', 'order_id', 'cart_id', 'status']))
            ->from($db->quoteName('#__j2commerce_uploads'))
            ->whereIn($db->quoteName('saved_name'), $savedNames, ParameterType::STRING);

        $grouped = [];

        foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row) {
            $grouped[(string) $row->saved_name][] = $row;
        }

        return $grouped;
    }

    /** Where the current code would look for this row's file. */
    private function destinationFor(object $row, string $root): ?string
    {
        $orderId = (string) ($row->order_id ?? '');

        if ((string) $row->status === 'attached' && $orderId !== '') {
            return $this->isPlainSegment($orderId) ? $root . '/orders/' . $orderId : null;
        }

        return $root . '/tmp/' . (int) ($row->cart_id ?? 0);
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
            self::STATE_MOVABLE    => 0,
            self::STATE_PRESENT    => 0,
            self::STATE_ORPHAN     => 0,
            self::STATE_UNRESOLVED => 0,
        ];

        foreach ($entries as $entry) {
            $counts[$entry['state']]++;
        }

        return $counts;
    }
}
