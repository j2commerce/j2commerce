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

use J2Commerce\Component\J2commerce\Administrator\Helper\AttachmentDenyFileHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ComponentParamsHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ConfigHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\DownloadHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\UploadHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\Folder;

/**
 * Moves customer files out of a storage directory this component does not own, into the
 * one it does, and repoints the records that name them.
 *
 * The directory a store ends up pointing at is often one it already had — a folder carried
 * over from another platform, holding files rows still name. Such a directory never receives
 * the deny pair ({@see AttachmentDenyFileHelper::ownsTree()} answers no for it, by design, so
 * a merchant's own directory is never claimed), and it cannot be typed out of the Options
 * form either: `config.xml` excludes `media`, and `FilePathRule` tests only a value's first
 * segment, so a stored `media/...` value makes the whole form unsaveable. The path therefore
 * has to be changed from outside the form, which is what this model is for.
 *
 * Two record shapes travel differently, and only one of them needs its rows touched:
 *
 *   - `#__j2commerce_productfiles` stores a site-relative path per row, so a moved file means
 *     a rewritten `product_file_save_name`.
 *   - `#__j2commerce_uploads` stores only a name and derives its directory from the attachment
 *     root at read time, so its files move and its rows are left exactly as they are.
 *
 * The param is written last. A run that dies halfway leaves the old path authoritative, so
 * every file still resolves from where it currently sits.
 *
 * @since  6.6.2
 */
final class AttachmentrelocateModel extends BaseDatabaseModel
{
    /** A row names this file, it is under the old root, and the destination is free. */
    public const STATE_MOVABLE = 'movable';

    /** The destination already holds this name — a prior run placed it. */
    public const STATE_PRESENT = 'present';

    /** A file in the old root that no record names. Reported, never moved. */
    public const STATE_ORPHAN = 'orphan';

    /** A row naming a scheme URI. A plugin delivers those; there is no local file to move. */
    public const STATE_REMOTE = 'remote';

    /** Files the storage tree carries for its own sake, never customer content. */
    private const IGNORED_NAMES = ['index.html', 'index.php', '.htaccess', 'web.config', 'readme.md'];

    /** Subtrees of the attachment root that belong to order uploads. */
    private const UPLOAD_SUBTREES = ['orders', 'tmp'];

    /**
     * Whether there is anything for this screen to offer: the configured root is one the
     * component does not own, and it holds something worth moving.
     */
    public function needsRelocation(): bool
    {
        $scan = $this->scan();

        return $scan['eligible'] && $scan['outstanding'] > 0;
    }

    /**
     * Classify everything the move would touch, without moving any of it.
     *
     * @return array{eligible: bool, owned: bool, source: ?string, source_display: string,
     *     destination: string, destination_display: string, entries: list<array<string, mixed>>,
     *     counts: array<string, int>, outstanding: int}
     */
    public function scan(): array
    {
        $sourceRelative = ConfigHelper::getAttachmentPath();
        $destination    = AttachmentDenyFileHelper::defaultPath();
        $sourceReal     = $this->realDirectory($sourceRelative);

        $owned = $sourceReal !== null && AttachmentDenyFileHelper::ownsTree(
            $sourceReal,
            false,
            $sourceRelative === $destination
        );

        $result = [
            'eligible'            => false,
            'owned'               => $owned,
            'source'              => $sourceReal,
            'source_display'      => $sourceRelative,
            'destination'         => $destination,
            'destination_display' => $destination,
            'entries'             => [],
            'counts'              => [
                self::STATE_MOVABLE => 0,
                self::STATE_PRESENT => 0,
                self::STATE_ORPHAN  => 0,
                self::STATE_REMOTE  => 0,
            ],
            'outstanding' => 0,
        ];

        // Nothing to offer when the component already owns the tree, when the configured path
        // does not resolve, or when it is already the destination.
        if ($owned || $sourceReal === null || $sourceRelative === $destination) {
            return $result;
        }

        $result['eligible'] = true;
        $entries            = array_merge(
            $this->productFileEntries($sourceRelative, $destination),
            $this->uploadEntries($sourceReal, $destination),
            $this->orphanEntries($sourceReal, $sourceRelative)
        );

        usort($entries, static fn (array $a, array $b) => [$a['state'], $a['name']] <=> [$b['state'], $b['name']]);

        foreach ($entries as $entry) {
            $result['counts'][$entry['state']]++;
        }

        $result['entries']     = $entries;
        $result['outstanding'] = $result['counts'][self::STATE_MOVABLE];

        return $result;
    }

    /**
     * Move up to $limit outstanding files, then repoint what moved.
     *
     * Outstanding work is re-derived on every call rather than paged by offset: each move
     * takes its own entry off the list, so an offset from the previous call would point past
     * files that have not been looked at yet.
     *
     * @return array{moved: int, rewritten: int, skipped: int, failed: int, orphan: int,
     *     remote: int, remaining: int, done: bool, finalized: bool, notes: list<string>}
     */
    public function relocate(int $limit): array
    {
        $limit  = max(1, min(50, $limit));
        $scan   = $this->scan();
        $result = [
            'moved'     => 0,
            'rewritten' => 0,
            'skipped'   => 0,
            'failed'    => 0,
            'orphan'    => $scan['counts'][self::STATE_ORPHAN],
            'remote'    => $scan['counts'][self::STATE_REMOTE],
            'remaining' => $scan['outstanding'],
            'done'      => false,
            'finalized' => false,
            'notes'     => [],
        ];

        if (!$scan['eligible']) {
            $result['done'] = true;

            return $result;
        }

        $root = $this->prepareDestination($scan['destination']);

        if ($root === null) {
            $result['failed']++;
            $result['notes'][] = $scan['destination'];
            $result['done']    = true;

            return $result;
        }

        $handled = 0;

        foreach ($scan['entries'] as $entry) {
            if ($handled >= $limit) {
                break;
            }

            if ($entry['state'] !== self::STATE_MOVABLE) {
                continue;
            }

            $handled++;
            $outcome = $this->move($entry, $root, $result['notes']);
            $result[$outcome]++;

            if ($outcome !== 'failed' && $entry['row_id'] > 0 && $this->repoint($entry)) {
                $result['rewritten']++;
            }
        }

        $after                 = $this->scan();
        $result['remaining']   = $after['outstanding'];

        // No progress with work still listed would loop the caller forever.
        $result['done'] = $after['outstanding'] === 0 || $handled === 0;

        if ($result['done'] && $after['outstanding'] === 0 && $result['failed'] === 0) {
            $result['finalized'] = $this->finalize($scan['destination']);
        }

        return $result;
    }

    /**
     * Point the component at the new root, and protect what now lives there.
     *
     * Written through ComponentParamsHelper so the other members of the blob survive, and
     * last of all so a failed move never leaves the param naming a tree the files are not in.
     */
    private function finalize(string $destination): bool
    {
        if (!ComponentParamsHelper::set('attachmentfolderpath', $destination)) {
            return false;
        }

        // What com_config does after writing component params (ComponentModel::save()). Without
        // it the stored value is correct but the Options screen keeps rendering the old path from
        // the cached extensions blob on the next request, which reads as the move having failed.
        $this->cleanCache('_system');

        DownloadHelper::protectRecordedFiles();

        return true;
    }

    /**
     * Create the destination and give it the deny pair before anything lands in it.
     *
     * @return  string|null  Absolute destination, or null when it cannot be prepared.
     */
    private function prepareDestination(string $destination): ?string
    {
        $absolute = JPATH_ROOT . '/' . $destination;
        $created  = !is_dir($absolute) && Folder::create($absolute);

        if (!$created && !is_dir($absolute)) {
            return null;
        }

        $real = realpath($absolute);

        if ($real === false || !$this->isWithin($real, (string) realpath(JPATH_ROOT))) {
            return null;
        }

        if ($created || !is_file($real . '/.htaccess')) {
            AttachmentDenyFileHelper::writeDenyPair(
                $real,
                $destination,
                AttachmentDenyFileHelper::ownsTree($real, $created, true)
            );
        }

        return $real;
    }

    /**
     * Rows naming a local file under the old root. Manifest-driven: a file is a candidate
     * because a row names it, never because it happens to sit in the directory.
     *
     * @return list<array<string, mixed>>
     */
    private function productFileEntries(string $sourceRelative, string $destination): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_productfile_id', 'product_file_save_name']))
            ->from($db->quoteName('#__j2commerce_productfiles'));

        $entries = [];

        foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row) {
            $stored = trim((string) $row->product_file_save_name);

            if ($stored === '') {
                continue;
            }

            if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $stored)) {
                $entries[] = $this->entry(self::STATE_REMOTE, basename($stored), '', '', '', (int) $row->j2commerce_productfile_id);

                continue;
            }

            $normalized = trim(str_replace('\\', '/', $stored), '/');

            if (!str_starts_with($normalized . '/', $sourceRelative . '/')) {
                continue;
            }

            $subPath = substr($normalized, \strlen($sourceRelative) + 1);

            if ($subPath === '' || !$this->isSafeSubPath($subPath)) {
                continue;
            }

            $entries[] = $this->pathEntry(
                JPATH_ROOT . '/' . $normalized,
                $subPath,
                $destination,
                (int) $row->j2commerce_productfile_id
            );
        }

        return $entries;
    }

    /**
     * Order-upload files, found by where they sit rather than by name: their rows store no
     * path, so every file under the root's own `orders/` and `tmp/` subtrees belongs to it.
     *
     * @return list<array<string, mixed>>
     */
    private function uploadEntries(string $sourceReal, string $destination): array
    {
        $entries = [];

        foreach (self::UPLOAD_SUBTREES as $subtree) {
            $base = $sourceReal . \DIRECTORY_SEPARATOR . $subtree;

            if (!is_dir($base)) {
                continue;
            }

            foreach ($this->filesUnder($base) as $absolute) {
                $subPath = $subtree . '/' . str_replace(
                    '\\',
                    '/',
                    ltrim(substr($absolute, \strlen($base)), '\\/')
                );

                if (!$this->isSafeSubPath($subPath)) {
                    continue;
                }

                $entries[] = $this->pathEntry($absolute, $subPath, $destination, 0);
            }
        }

        return $entries;
    }

    /**
     * Files sitting at the top of the old root that no product-file row names.
     *
     * Reported so the merchant can see what the move leaves behind, and never moved: a file
     * nothing records is as likely to be the store's own as a stray customer upload.
     *
     * @return list<array<string, mixed>>
     */
    private function orphanEntries(string $sourceReal, string $sourceRelative): array
    {
        $named = [];

        foreach ($this->productFileEntries($sourceRelative, '') as $entry) {
            if ($entry['source'] !== '') {
                $named[$this->pathKey((string) $entry['source'])] = true;
            }
        }

        $entries = [];

        foreach ((array) scandir($sourceReal) as $name) {
            $name     = (string) $name;
            $absolute = $sourceReal . \DIRECTORY_SEPARATOR . $name;

            if (
                \in_array($name, ['.', '..'], true)
                || \in_array(strtolower($name), self::IGNORED_NAMES, true)
                || !is_file($absolute)
                || isset($named[$this->pathKey($absolute)])
            ) {
                continue;
            }

            $entries[] = $this->entry(self::STATE_ORPHAN, $name, $absolute, '', '', 0);
        }

        return $entries;
    }

    /**
     * An entry for a file that has a destination, stated as movable or already present.
     *
     * @return array<string, mixed>
     */
    private function pathEntry(string $absolute, string $subPath, string $destination, int $rowId): array
    {
        $targetRelative = $destination . '/' . $subPath;
        $placed         = $destination !== '' && is_file(JPATH_ROOT . '/' . $targetRelative);

        return $this->entry(
            $placed ? self::STATE_PRESENT : self::STATE_MOVABLE,
            basename($subPath),
            $absolute,
            $destination === '' ? '' : \dirname(JPATH_ROOT . '/' . $targetRelative),
            $targetRelative,
            $rowId
        );
    }

    /** @return array<string, mixed> */
    private function entry(
        string $state,
        string $name,
        string $source,
        string $targetDirectory,
        string $targetRelative,
        int $rowId
    ): array {
        return [
            'state'          => $state,
            'name'           => $name,
            'source'         => $source,
            'source_display' => $source === '' ? '' : $this->relative($source),
            'directory'      => $targetDirectory,
            'target'         => $targetRelative,
            'row_id'         => $rowId,
            'size'           => $source !== '' && is_file($source) ? (int) (filesize($source) ?: 0) : 0,
        ];
    }

    /**
     * Copy, verify, then unlink — a half-written destination is worse than an unmoved source,
     * and a source that outlives its copy is simply skipped by the next run.
     *
     * @param  array<string, mixed>  $entry
     * @param  list<string>          $notes
     *
     * @return 'moved'|'skipped'|'failed'
     */
    private function move(array $entry, string $root, array &$notes): string
    {
        $directory = (string) $entry['directory'];
        $source    = (string) $entry['source'];
        $name      = (string) $entry['name'];

        if ($directory === '' || !is_file($source)) {
            return 'failed';
        }

        if (!is_dir($directory) && !Folder::create($directory)) {
            return 'failed';
        }

        UploadHelper::ensureIndexHtml($directory);

        $real = realpath($directory);

        if ($real === false || !$this->isWithin($real, $root)) {
            return 'failed';
        }

        $destination = $real . \DIRECTORY_SEPARATOR . $name;

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
            // The file resolves from the destination now; the source is a duplicate the next run skips.
            $notes[] = (string) $entry['source_display'];
        }

        return 'moved';
    }

    /** Point a product-file row at where its file now lives. */
    private function repoint(array $entry): bool
    {
        $rowId  = (int) $entry['row_id'];
        $target = (string) $entry['target'];

        if ($rowId <= 0 || $target === '') {
            return false;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_productfiles'))
            ->set($db->quoteName('product_file_save_name') . ' = :target')
            ->where($db->quoteName('j2commerce_productfile_id') . ' = :id')
            ->bind(':target', $target)
            ->bind(':id', $rowId, ParameterType::INTEGER);

        try {
            $db->setQuery($query)->execute();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Every file beneath a directory, depth first.
     *
     * @return list<string>
     */
    private function filesUnder(string $base): array
    {
        $found = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isFile() && !\in_array(strtolower($item->getFilename()), self::IGNORED_NAMES, true)) {
                $found[] = $item->getPathname();
            }
        }

        return $found;
    }

    /** A relative path that stays beneath its root: no traversal, no absolute prefix. */
    private function isSafeSubPath(string $subPath): bool
    {
        if ($subPath === '' || preg_match('#^(?:/|[a-zA-Z]:)#', $subPath)) {
            return false;
        }

        foreach (explode('/', $subPath) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    /** @return string|null  Absolute resolved directory inside the site root, or null. */
    private function realDirectory(string $relative): ?string
    {
        if ($relative === '' || \in_array('..', explode('/', $relative), true)) {
            return null;
        }

        $real     = realpath(JPATH_ROOT . '/' . $relative);
        $rootReal = realpath(JPATH_ROOT);

        if ($real === false || $rootReal === false || !is_dir($real) || $real === $rootReal) {
            return null;
        }

        return $this->isWithin($real, $rootReal) ? $real : null;
    }

    /**
     * One comparable form for a path that two sources spell differently: a stored value is
     * joined with '/', while a directory read resolves with the platform separator, so on
     * Windows the same file arrives as both `.../media/downloads/x.pdf` and
     * `...\media\downloads\x.pdf`. Comparing those raw reported every named file as an orphan.
     */
    private function pathKey(string $path): string
    {
        $resolved = realpath($path);

        return strtolower(str_replace('\\', '/', $resolved !== false ? $resolved : $path));
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
}
