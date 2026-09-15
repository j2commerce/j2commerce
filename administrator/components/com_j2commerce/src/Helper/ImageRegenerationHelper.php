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

use J2Commerce\Component\J2commerce\Administrator\Service\RemoteImageDownloader;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

class ImageRegenerationHelper
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    private const SIZES = ['thumbs', 'tiny'];

    /** 'both' makes each source's thumbnail and tiny image in one pass: one read, one download, one row write. */
    private const ALLOWED_SCOPES = ['thumbs', 'tiny', 'both'];

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    public function countTotal(string $scope): int
    {
        $this->assertScope($scope);

        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__j2commerce_productimages'));
        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    public function processBatch(string $scope, int $offset, int $limit): array
    {
        $this->assertScope($scope);

        $params  = ComponentHelper::getParams('com_j2commerce');
        $targets = [];

        foreach ($scope === 'both' ? self::SIZES : [$scope] as $size) {
            [$width, $height] = $this->dimensionsFor($size, $params);

            $targets[$size] = $this->columnsFor($size) + [
                'processor' => $this->processorFor($size, $params),
                'width'     => $width,
                'height'    => $height,
                'settings'  => $width . 'x' . $height . 'q' . (int) $params->get($size === 'thumbs' ? 'image_thumb_quality' : 'image_tiny_quality', 80),
            ];
        }

        $remote = (int) $params->get('image_generate_remote', 0) === 1 ? [
            'dir'         => $this->remoteDirectory(),
            'onlyChanged' => (int) $params->get('image_remote_only_changed', 1) === 1,
        ] : null;

        $columns = [
            'j2commerce_productimage_id',
            'product_id',
            'main_image',
            'main_image_alt',
            'additional_images',
            'additional_images_alt',
            'thumb_image_alt',
            'tiny_image_alt',
            'additional_thumb_images_alt',
            'additional_tiny_images_alt',
        ];

        foreach ($targets as $target) {
            $columns[] = $target['image_col'];
            $columns[] = $target['additional_col'];
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName($columns))
            ->from($this->db->quoteName('#__j2commerce_productimages'))
            ->order($this->db->quoteName('j2commerce_productimage_id') . ' ASC')
            ->setLimit($limit, $offset);
        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList();

        $processed = 0;
        $generated = 0;
        $skipped   = 0;
        $failed    = 0;
        $errors    = [];

        foreach ($rows as $row) {
            $processed++;

            $productId   = (int) $row->product_id;
            $mainResults = $this->regenerateSource($row->main_image, $targets, $remote);
            $sourceItems = $this->decodeJsonField($row->additional_images);
            $itemResults = [];

            foreach ($sourceItems as $key => $sourcePath) {
                $itemResults[$key] = $this->regenerateSource(\is_string($sourcePath) ? $sourcePath : null, $targets, $remote);
            }

            $values = [];

            foreach ($targets as $size => $target) {
                $this->tally($mainResults[$size], $productId, $size, $generated, $skipped, $failed, $errors);
                $values[$target['image_col']] = $mainResults[$size]['value'] ?? ($row->{$target['image_col']} ?? '');

                $existing    = $this->decodeJsonField($row->{$target['additional_col']});
                $derivatives = [];

                foreach ($itemResults as $key => $results) {
                    $this->tally($results[$size], $productId, $size, $generated, $skipped, $failed, $errors);
                    $derivatives[$key] = $results[$size]['value'] ?? ($existing[$key] ?? '');
                }

                $values[$target['additional_col']] = $this->encodeJsonField($derivatives);
            }

            $altKeysMatch = $this->altKeysMatchImages($row, array_keys($sourceItems));

            if (!$altKeysMatch) {
                $errors[] = [
                    'status'    => 'skipped',
                    'productId' => $productId,
                    'size'      => '',
                    'message'   => 'additional image alt text is keyed differently from the additional images, so it was not copied to the thumbnail and tiny images',
                ];
            }

            $this->updateRow((int) $row->j2commerce_productimage_id, $values + $this->copiedAltText($row, array_keys($sourceItems), $altKeysMatch));
        }

        return [
            'processed' => $processed,
            'generated' => $generated,
            'skipped'   => $skipped,
            'failed'    => $failed,
            'errors'    => $errors,
        ];
    }

    private function assertScope(string $scope): void
    {
        if (!\in_array($scope, self::ALLOWED_SCOPES, true)) {
            throw new \InvalidArgumentException('Invalid regeneration scope.');
        }
    }

    /** @return array{image_col: string, additional_col: string} */
    private function columnsFor(string $size): array
    {
        return match ($size) {
            'thumbs' => ['image_col' => 'thumb_image', 'additional_col' => 'additional_thumb_images'],
            'tiny'   => ['image_col' => 'tiny_image', 'additional_col' => 'additional_tiny_images'],
        };
    }

    /** @return array{0: int, 1: int} */
    private function dimensionsFor(string $size, Registry $params): array
    {
        return $size === 'thumbs'
            ? [(int) $params->get('image_thumb_width', 300), (int) $params->get('image_thumb_height', 300)]
            : [(int) $params->get('image_tiny_width', 100), (int) $params->get('image_tiny_height', 100)];
    }

    private function processorFor(string $size, Registry $params): ImageProcessorHelper
    {
        $qualityKey = $size === 'thumbs' ? 'image_thumb_quality' : 'image_tiny_quality';

        return new ImageProcessorHelper(
            (int) $params->get('image_webp_quality', 80),
            (int) $params->get($qualityKey, 80)
        );
    }

    /**
     * Every requested size of one source image. The source is validated, resolved, and — when it is
     * remote — checked and downloaded once, however many sizes are made from it.
     *
     * @return array<string, array{status: string, value: ?string, error: ?string}>  Keyed by size.
     */
    private function regenerateSource(?string $rawSource, array $targets, ?array $remote): array
    {
        if ($rawSource === null || trim($rawSource) === '') {
            return $this->sameResult($targets, 'skipped', 'no image path is set for this image');
        }

        $source = $this->stripSiteRoot($this->stripJoomlaImageMeta(trim($rawSource)));

        if (RemoteImageDownloader::isRemote($source)) {
            return $remote !== null
                ? $this->regenerateRemote($source, $targets, $remote)
                : $this->sameResult($targets, 'skipped', $source . ': external URL (turn on Generate From Remote)');
        }

        $clean     = ltrim($source, '/');
        $extension = strtolower(pathinfo($clean, PATHINFO_EXTENSION));

        if (!\in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return $this->sameResult($targets, 'skipped', $clean . ': unsupported file type');
        }

        if (\in_array(basename(\dirname($clean)), self::SIZES, true)) {
            return $this->sameResult($targets, 'skipped', $clean . ': already a derivative image');
        }

        $absolute = $this->resolveAndConfine($clean);

        if ($absolute === null) {
            return $this->sameResult($targets, 'skipped', $clean . ': source file not found');
        }

        $webpBasename = File::stripExt(basename($absolute)) . '.webp';
        $results      = [];

        foreach ($targets as $size => $target) {
            $targetDir = \dirname($absolute) . '/' . $size . '/';

            if (!is_dir($targetDir)) {
                Folder::create($targetDir);
            }

            if (!$target['processor']->createThumbnail($absolute, $targetDir . $webpBasename, $target['width'], $target['height']) || !is_file($targetDir . $webpBasename)) {
                Log::add('J2Commerce image regeneration failed for ' . $clean . ' (' . $size . ')', Log::WARNING, 'com_j2commerce');
                $results[$size] = ['status' => 'failed', 'value' => null, 'error' => $clean . ': regeneration failed'];

                continue;
            }

            $results[$size] = $this->storedResult('generated', $clean, $size, $targetDir, $webpBasename, $target);
        }

        return $results;
    }

    /**
     * Derivatives of an externally hosted image under {dir}/{host}/{size}/, named
     * {name}-{url hash}-{version}.webp. The version covers the remote server's validator (or the
     * downloaded bytes when it sends none) plus that size's settings, so an unchanged image is
     * recognized by its file name alone, and a changed one gets a new name that no browser or
     * proxy has cached. The source stays on the external URL. One HEAD request and at most one
     * download serve every size.
     *
     * @param   array{dir: string, onlyChanged: bool}  $remote
     *
     * @return array<string, array{status: string, value: ?string, error: ?string}>  Keyed by size.
     */
    private function regenerateRemote(string $url, array $targets, array $remote): array
    {
        $absoluteUrl = RemoteImageDownloader::absolute($url);
        $urlPath     = (string) (parse_url($absoluteUrl, PHP_URL_PATH) ?? '');
        $host        = (string) preg_replace('/[^a-z0-9.-]/', '', strtolower((string) (parse_url($absoluteUrl, PHP_URL_HOST) ?? '')));

        if (trim($host, '.') === '') {
            return $this->sameResult($targets, 'failed', $url . ': invalid URL');
        }

        if (\in_array(basename(\dirname($urlPath)), self::SIZES, true)) {
            return $this->sameResult($targets, 'skipped', $url . ': already a derivative image');
        }

        // The URL hash keeps two sources that share a file name on one host apart. Dots and spaces
        // become hyphens so the name is a single segment ahead of the hash and version.
        $name        = trim((string) preg_replace('/[.\s]+/', '-', File::makeSafe(File::stripExt(basename($urlPath)))), '-');
        $prefix      = ($name ?: 'image') . '-' . substr(sha1($absoluteUrl), 0, 16);
        $relativeDir = $remote['dir'] . '/' . $host;
        $validator   = RemoteImageDownloader::validator($absoluteUrl, 20, 'image regeneration');
        $results     = [];
        $pending     = [];

        foreach ($targets as $size => $target) {
            $basename = $validator !== null ? $prefix . '-' . $this->versionCode($validator, $target['settings']) . '.webp' : null;

            if ($basename !== null && $remote['onlyChanged'] && is_file($this->remoteTargetDir($relativeDir, $size) . $basename)) {
                $results[$size] = $this->storedResult('unchanged', $relativeDir . '/' . $basename, $size, $this->remoteTargetDir($relativeDir, $size), $basename, $target, $url . ': unchanged on the remote server since it was last generated, so the existing local image was kept');
            } else {
                $pending[$size] = $basename;
            }
        }

        if ($pending === []) {
            return $results;
        }

        $tmpFile = RemoteImageDownloader::toTempFile($absoluteUrl, 20, 'image regeneration');

        if ($tmpFile === null) {
            foreach (array_keys($pending) as $size) {
                $results[$size] = ['status' => 'failed', 'value' => null, 'error' => $url . ': download failed or not a JPEG, PNG, GIF or WebP image'];
            }

            return $results;
        }

        try {
            // No validator from the server: the downloaded bytes are the version.
            $contentHash = $validator === null ? 'sha1:' . (string) sha1_file($tmpFile) : '';

            foreach ($pending as $size => $basename) {
                $target    = $targets[$size];
                $targetDir = $this->remoteTargetDir($relativeDir, $size);
                $basename ??= $prefix . '-' . $this->versionCode($contentHash, $target['settings']) . '.webp';

                if ($validator === null && $remote['onlyChanged'] && is_file($targetDir . $basename)) {
                    $results[$size] = $this->storedResult('unchanged', $relativeDir . '/' . $basename, $size, $targetDir, $basename, $target, $url . ': unchanged on the remote server since it was last generated, so the existing local image was kept');

                    continue;
                }

                if (!is_dir($targetDir)) {
                    Folder::create($targetDir);
                }

                if (!$target['processor']->createThumbnail($tmpFile, $targetDir . $basename, $target['width'], $target['height']) || !is_file($targetDir . $basename)) {
                    Log::add('J2Commerce image regeneration failed for ' . $url . ' (' . $size . ')', Log::WARNING, 'com_j2commerce');
                    $results[$size] = ['status' => 'failed', 'value' => null, 'error' => $url . ': regeneration failed'];

                    continue;
                }

                $this->removeOtherVersions($targetDir, $prefix, $basename);
                $results[$size] = $this->storedResult('generated', $relativeDir . '/' . $basename, $size, $targetDir, $basename, $target);
            }
        } finally {
            @unlink($tmpFile);
        }

        return $results;
    }

    private function remoteTargetDir(string $relativeDir, string $size): string
    {
        return JPATH_ROOT . '/' . $relativeDir . '/' . $size . '/';
    }

    private function versionCode(string $validator, string $settings): string
    {
        return substr(sha1($validator . '|' . $settings), 0, 8);
    }

    /** @return array<string, array{status: string, value: null, error: ?string}> */
    private function sameResult(array $targets, string $status, ?string $error): array
    {
        return array_fill_keys(array_keys($targets), ['status' => $status, 'value' => null, 'error' => $error]);
    }

    /**
     * A derivative that exists on disk, as the value stored in the image column.
     *
     * @param   string  $sourceRelativePath  The source (local) or the derivative's own path (remote); only its folder is used.
     *
     * @param   ?string  $note  Reason reported for a derivative that was kept rather than generated.
     *
     * @return array{status: string, value: string, error: ?string}
     */
    private function storedResult(string $status, string $sourceRelativePath, string $size, string $targetDir, string $basename, array $target, ?string $note = null): array
    {
        $dimensions = @getimagesize($targetDir . $basename);

        return [
            'status' => $status,
            'value'  => $this->buildStoredValue(
                $sourceRelativePath,
                $size,
                $basename,
                $dimensions ? (int) $dimensions[0] : $target['width'],
                $dimensions ? (int) $dimensions[1] : $target['height']
            ),
            'error' => $note,
        ];
    }

    /** Earlier versions of the same source: exactly {prefix}-{8 hex version}.webp, nothing broader. */
    private function removeOtherVersions(string $targetDir, string $prefix, string $keep): void
    {
        $pattern = '/^' . preg_quote($prefix, '/') . '-[0-9a-f]{8}\.webp$/';

        foreach (glob($targetDir . $prefix . '-*.webp') ?: [] as $file) {
            if (basename($file) !== $keep && preg_match($pattern, basename($file)) === 1) {
                @unlink($file);
            }
        }
    }

    /** First configured product image directory plus /remote; never a path that climbs out of the site root. */
    private function remoteDirectory(): string
    {
        $base = trim((string) (ConfigHelper::getImageDirectoryPaths(['images/products'])[0] ?? ''), '/');

        if ($base === '' || preg_match('#(^|/)\.\.(/|$)#', $base) === 1) {
            $base = 'images/products';
        }

        return $base . '/remote';
    }

    private function tally(array $result, int $productId, string $size, int &$generated, int &$skipped, int &$failed, array &$errors): void
    {
        match ($result['status']) {
            'generated'            => $generated++,
            'skipped', 'unchanged' => $skipped++,
            'failed'               => $failed++,
        };

        if ($result['error'] !== null) {
            $errors[] = ['status' => $result['status'], 'productId' => $productId, 'size' => $size, 'message' => $result['error']];
        }
    }

    private function stripJoomlaImageMeta(string $path): string
    {
        $hashPos = strpos($path, '#joomlaImage://');

        return $hashPos !== false ? substr($path, 0, $hashPos) : $path;
    }

    private function stripSiteRoot(string $path): string
    {
        $root = rtrim(Uri::root(), '/') . '/';

        while ($root !== '/' && str_starts_with($path, $root)) {
            $path = substr($path, \strlen($root));
        }

        return $path;
    }

    /** Realpath-confine a repo-relative path to JPATH_ROOT; null if missing or escapes the root. */
    private function resolveAndConfine(string $relativePath): ?string
    {
        $real = realpath(JPATH_ROOT . '/' . $relativePath);

        if ($real === false) {
            return null;
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', JPATH_ROOT), '/') . '/';
        $normalizedReal = str_replace('\\', '/', $real);

        return str_starts_with($normalizedReal, $normalizedRoot) ? $normalizedReal : null;
    }

    /**
     * Joomla's default local media adapter aliases the "images" root as "local-images"
     * in the #joomlaImage:// metadata fragment.
     */
    private function buildStoredValue(string $sourceRelativePath, string $size, string $webpBasename, int $width, int $height): string
    {
        $sourceDir = \dirname($sourceRelativePath);
        $sourceDir = $sourceDir === '.' ? '' : $sourceDir . '/';

        $relativeTarget  = $sourceDir . $size . '/' . $webpBasename;
        $adapterRelative = str_starts_with($relativeTarget, 'images/')
            ? substr($relativeTarget, \strlen('images/'))
            : $relativeTarget;

        return $relativeTarget . '#joomlaImage://local-images/' . $adapterRelative . '?width=' . $width . '&height=' . $height;
    }

    private function decodeJsonField(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * JSON for an image or alt text column: always an object keyed by the image's position, in
     * display order, so a row's images, derivatives and alt text share one shape and one key set
     * (j2commerce/j2commerce#2384). An empty set is {}.
     */
    private function encodeJsonField(array $data): string
    {
        return json_encode($data, JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /**
     * Whether every additional-image alt key belongs to an additional image. Alt text keyed from 0
     * against images keyed from 1 (or the reverse) would copy each alt onto the neighboring image.
     * A subset is fine: an image with no alt key keeps its existing derivative alt text.
     *
     * @param   array<int|string>  $imageKeys
     */
    private function altKeysMatchImages(object $row, array $imageKeys): bool
    {
        $altKeys = array_keys($this->decodeJsonField($row->additional_images_alt));

        return array_diff($altKeys, $imageKeys) === [];
    }

    /**
     * Thumbnail and tiny alt text copied from the main and additional image alt text, for both
     * sizes on every run. An empty source alt keeps the derivative's existing alt text instead of
     * blanking it. When the additional alt keys do not match the image keys, the additional alt
     * columns are left untouched and only the main alt text is copied.
     *
     * @param   array<int|string>  $keys  Additional-image keys, so each alt stays on its image's key.
     *
     * @return array<string, string>
     */
    private function copiedAltText(object $row, array $keys, bool $copyAdditional = true): array
    {
        $mainAlt    = trim((string) ($row->main_image_alt ?? ''));
        $sourceAlts = $this->decodeJsonField($row->additional_images_alt);
        $columns    = [];

        foreach (['thumb_image_alt' => 'additional_thumb_images_alt', 'tiny_image_alt' => 'additional_tiny_images_alt'] as $altCol => $additionalAltCol) {
            $columns[$altCol] = $mainAlt !== '' ? $mainAlt : (string) ($row->{$altCol} ?? '');

            if (!$copyAdditional) {
                continue;
            }

            $existing = $this->decodeJsonField($row->{$additionalAltCol});
            $alts     = [];

            foreach ($keys as $key) {
                $alt        = \is_string($sourceAlts[$key] ?? null) ? trim($sourceAlts[$key]) : '';
                $alts[$key] = $alt !== '' ? $alt : (\is_string($existing[$key] ?? null) ? $existing[$key] : '');
            }

            $columns[$additionalAltCol] = $this->encodeJsonField($alts);
        }

        return $columns;
    }

    /** @param  array<string, string>  $columns  Column => value; names come only from columnsFor() and copiedAltText(). */
    private function updateRow(int $productImageId, array $columns): void
    {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__j2commerce_productimages'))
            ->where($this->db->quoteName('j2commerce_productimage_id') . ' = :id')
            ->bind(':id', $productImageId, ParameterType::INTEGER);

        $index = 0;

        // bind() takes its value by reference, so bind the array element, never the loop variable.
        foreach (array_keys($columns) as $column) {
            $placeholder = ':value' . $index++;
            $query->set($this->db->quoteName($column) . ' = ' . $placeholder)->bind($placeholder, $columns[$column]);
        }

        $this->db->setQuery($query);
        $this->db->execute();
    }
}
