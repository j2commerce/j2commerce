<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Downloads an externally hosted image (CDN, subdomain, another site) into a temporary file so
 * the local image pipeline can resize it, and reports the remote server's version of it so a
 * caller can tell whether a local derivative is stale without downloading the image again.
 */
final class RemoteImageDownloader
{
    public const MAX_BYTES = 50 * 1024 * 1024;

    private const RASTER_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];

    public static function isRemote(string $path): bool
    {
        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//');
    }

    /** A protocol-relative URL (//host/path) as https. */
    public static function absolute(string $url): string
    {
        return str_starts_with($url, '//') ? 'https:' . $url : $url;
    }

    /**
     * What the remote server says identifies this version of the image, from a HEAD request:
     * the ETag, else Last-Modified plus Content-Length. Null when the server sends neither, does
     * not answer HEAD, or the URL is refused — the caller then has to download to compare.
     */
    public static function validator(string $url, int $timeout = 30, string $context = ''): ?string
    {
        $headers = RemoteUrlGuard::head(self::absolute($url), $timeout, $context);

        if ($headers === null) {
            return null;
        }

        $etag = trim($headers['etag'] ?? '');

        if ($etag !== '') {
            return 'etag:' . $etag;
        }

        $lastModified = trim($headers['last-modified'] ?? '');

        return $lastModified !== '' ? 'modified:' . $lastModified . '|' . trim($headers['content-length'] ?? '') : null;
    }

    /**
     * Temp-file path holding the image, or null when the URL is refused, the download fails, or
     * the body is not a JPEG, PNG, GIF or WebP image. The type is read from the bytes, never from
     * the URL, so an SVG or any non-image payload is rejected whatever it is named. The caller
     * owns the returned file and must unlink it.
     */
    public static function toTempFile(string $url, int $timeout = 30, string $context = ''): ?string
    {
        $data = RemoteUrlGuard::fetch(self::absolute($url), $timeout, $context);

        if ($data === null || \strlen($data) < 100 || \strlen($data) > self::MAX_BYTES) {
            return null;
        }

        $info = @getimagesizefromstring($data);

        if ($info === false || !\in_array($info[2], self::RASTER_TYPES, true)) {
            return null;
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'j2img_');

        if ($tmpFile === false) {
            return null;
        }

        if (file_put_contents($tmpFile, $data) === false) {
            @unlink($tmpFile);

            return null;
        }

        return $tmpFile;
    }
}
