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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Uri\Uri;

// No direct access
\defined('_JEXEC') or die;

/**
 * Image Helper class for J2Commerce
 *
 * Provides image URL handling and validation functionality.
 * Determines whether images are local or served from a CDN,
 * and ensures proper URL formatting for image paths.
 *
 * @since  6.0.0
 */
class ImageHelper
{
    /**
     * Singleton instance
     *
     * @var   ImageHelper|null
     * @since 6.0.0
     */
    private static ?ImageHelper $instance = null;

    private static ?array $imageUploadParams = null;

    /**
     * Get the singleton instance
     *
     * @return  ImageHelper
     *
     * @since   6.0.0
     */
    public static function getInstance(): ImageHelper
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Determine if the given image URL is local (host matches current site) or from a CDN (different host).
     *
     * A URL is considered local if:
     * - It's a relative path (not a full URL)
     * - It's a full URL but the host matches the current site's host
     *
     * @param   string  $imageUrl  The image URL to check
     *
     * @return  bool  True if local, false if from a CDN
     *
     * @since   6.0.0
     */
    public static function isLocalImage(string $imageUrl): bool
    {
        // Empty URLs are treated as local (no-op)
        if (empty($imageUrl)) {
            return true;
        }

        // If it's a relative path, it's definitely local
        if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return true;
        }

        // Parse the image URL to get its host
        $imageHost = parse_url($imageUrl, PHP_URL_HOST);

        // Get the current site's domain
        $siteHost = parse_url(Uri::root(), PHP_URL_HOST);

        // Handle edge case where either host is null
        if ($imageHost === null || $siteHost === null) {
            return true;
        }

        // Compare hosts (case-insensitive)
        return strcasecmp($siteHost, $imageHost) === 0;
    }

    /**
     * Get the full URL for an image, ensuring local images have the site root.
     *
     * For local images (relative paths), this prepends the site root URL.
     * For CDN images (full URLs to different hosts), returns as-is.
     * For local full URLs (same host), returns as-is.
     *
     * @param   string  $imagePath  The image path or URL
     *
     * @return  string  The full image URL, or empty string if no path provided
     *
     * @since   6.0.0
     */
    public static function getImageUrl(string $imagePath): string
    {
        // Return empty string for empty paths
        if (empty($imagePath)) {
            return '';
        }

        // Strip Joomla media field metadata (#joomlaImage://...) to get the clean path
        $parsed    = self::parseJoomlaImageUrl($imagePath);
        $cleanPath = $parsed['path'];

        // Check if this is a local image
        if (self::isLocalImage($cleanPath)) {
            // Local image - ensure it has the site root
            if (filter_var($cleanPath, FILTER_VALIDATE_URL)) {
                // Already a full local URL, return as-is
                return $cleanPath;
            }

            // Relative path - prepend site root and clean up leading slashes
            return Uri::root() . ltrim($cleanPath, '/');
        }

        // CDN image - return as-is
        return $cleanPath;
    }

    /**
     * Check if an image path is valid (non-empty and appears to be a valid file path or URL)
     *
     * @param   string  $imagePath  The image path to validate
     *
     * @return  bool  True if the path appears valid, false otherwise
     *
     * @since   6.0.0
     */
    public static function isValidImagePath(string $imagePath): bool
    {
        if (empty($imagePath)) {
            return false;
        }

        // Strip Joomla media field metadata before validating
        $parsed = self::parseJoomlaImageUrl($imagePath);
        $clean  = $parsed['path'];

        // Check if it's a valid URL
        if (filter_var($clean, FILTER_VALIDATE_URL)) {
            return true;
        }

        // Check if it looks like a file path with an image extension
        $extension       = strtolower(pathinfo($clean, PATHINFO_EXTENSION));
        $validExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico', 'avif'];

        return \in_array($extension, $validExtensions, true);
    }

    /**
     * Get the image extension from a path or URL
     *
     * @param   string  $imagePath  The image path or URL
     *
     * @return  string  The file extension (lowercase, without dot), or empty string if none
     *
     * @since   6.0.0
     */
    public static function getImageExtension(string $imagePath): string
    {
        if (empty($imagePath)) {
            return '';
        }

        // For URLs, parse the path component first to remove query strings
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            $parsedUrl = parse_url($imagePath, PHP_URL_PATH);
            $imagePath = $parsedUrl ?? $imagePath;
        }

        return strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
    }

    /**
     * Get the filename from an image path or URL (without extension)
     *
     * @param   string  $imagePath  The image path or URL
     *
     * @return  string  The filename without extension, or empty string if none
     *
     * @since   6.0.0
     */
    public static function getImageFilename(string $imagePath): string
    {
        if (empty($imagePath)) {
            return '';
        }

        // For URLs, parse the path component first to remove query strings
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            $parsedUrl = parse_url($imagePath, PHP_URL_PATH);
            $imagePath = $parsedUrl ?? $imagePath;
        }

        return pathinfo($imagePath, PATHINFO_FILENAME);
    }

    /**
     * Normalize an image path by cleaning up redundant slashes and dots
     *
     * @param   string  $imagePath  The image path to normalize
     *
     * @return  string  The normalized path
     *
     * @since   6.0.0
     */
    public static function normalizePath(string $imagePath): string
    {
        if (empty($imagePath)) {
            return '';
        }

        // Don't normalize full URLs
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            return $imagePath;
        }

        // Replace backslashes with forward slashes
        $path = str_replace('\\', '/', $imagePath);

        // Remove redundant slashes
        $path = preg_replace('#/+#', '/', $path) ?? $path;

        // Remove leading ./ if present
        if (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }

        return $path;
    }

    /**
     * Generate a placeholder image URL
     *
     * Returns an empty string placeholder. Override this method or use a plugin
     * to return actual placeholder images (e.g., from a service or local default).
     *
     * @param   int     $width   The desired width
     * @param   int     $height  The desired height
     * @param   string  $text    Optional text to display on placeholder
     *
     * @return  string  The placeholder image URL, or empty string for default behavior
     *
     * @since   6.0.0
     */
    public static function getPlaceholderUrl(int $width = 150, int $height = 150, string $text = ''): string
    {
        // Default implementation returns empty string
        // Extensions can override this via plugins to provide actual placeholders
        return '';
    }

    /**
     * Check if an image URL is from a known CDN
     *
     * @param   string  $imageUrl  The image URL to check
     *
     * @return  bool  True if the URL appears to be from a CDN
     *
     * @since   6.0.0
     */
    public static function isCdnImage(string $imageUrl): bool
    {
        // If it's local, it's not a CDN image
        if (self::isLocalImage($imageUrl)) {
            return false;
        }

        // It's a full URL to a different host - considered CDN
        return filter_var($imageUrl, FILTER_VALIDATE_URL) !== false;
    }

    /** Strips Joomla media field metadata, returns clean relative path + original dimensions. */
    private static function parseJoomlaImageUrl(string $imagePath): array
    {
        $result = ['path' => $imagePath, 'width' => 0, 'height' => 0];

        $hashPos = strpos($imagePath, '#joomlaImage://');

        if ($hashPos !== false) {
            $result['path'] = substr($imagePath, 0, $hashPos);
            $queryPos       = strpos($imagePath, '?', $hashPos);

            if ($queryPos !== false) {
                $queryString = html_entity_decode(substr($imagePath, $queryPos + 1), ENT_QUOTES, 'UTF-8');
                parse_str($queryString, $query);
                $result['width']  = (int) ($query['width'] ?? 0);
                $result['height'] = (int) ($query['height'] ?? 0);
            }
        }

        // Strip site root URL to get a relative path for file_exists() checks
        $root = Uri::root();

        if ($root !== '' && str_starts_with($result['path'], $root)) {
            $result['path'] = substr($result['path'], \strlen($root));
        }

        return $result;
    }

    private static function getImageUploadParams(): array
    {
        if (self::$imageUploadParams !== null) {
            return self::$imageUploadParams;
        }

        $params = ComponentHelper::getParams('com_j2commerce');

        self::$imageUploadParams = [
            'thumb_height' => (int) $params->get('image_thumb_height', 300),
            'thumb_width'  => (int) $params->get('image_thumb_width', 300),
            'tiny_height'  => (int) $params->get('image_tiny_height', 100),
            'tiny_width'   => (int) $params->get('image_tiny_width', 100),
        ];

        return self::$imageUploadParams;
    }

    private static function resolveImageVersion(string $imagePath, int $height): string
    {
        if ($height <= 0 || !self::isLocalImage($imagePath)) {
            return $imagePath;
        }

        $params     = self::getImageUploadParams();
        $normalized = self::normalizePath($imagePath);
        $dir        = \dirname($normalized);
        $file       = basename($normalized);

        if ($height <= $params['tiny_height']) {
            $candidate = $dir . '/tiny/' . $file;
            if (file_exists(JPATH_SITE . '/' . $candidate)) {
                return $candidate;
            }
        } elseif ($height <= $params['thumb_height']) {
            $candidate = $dir . '/thumbs/' . $file;
            if (file_exists(JPATH_SITE . '/' . $candidate)) {
                return $candidate;
            }
        }

        return $imagePath;
    }

    public static function getProductImage(
        string $imagePath,
        int $height = 0,
        string $output = 'html',
        int $width = 0,
        string $class = '',
        string $alt = '',
        bool $auto = false,
    ): string {
        if (empty($imagePath)) {
            return '';
        }

        $parsed = self::parseJoomlaImageUrl($imagePath);

        // Auto mode: fit within the requested width x height box preserving aspect ratio
        if ($auto && $parsed['width'] > 0 && $parsed['height'] > 0 && $width > 0 && $height > 0) {
            $ratio     = $parsed['width'] / $parsed['height'];
            $fitWidth  = (int) round($height * $ratio);
            $fitHeight = (int) round($width / $ratio);

            if ($fitWidth <= $width) {
                $width = $fitWidth;
            } else {
                $height = $fitHeight;
            }
        }

        $resolved = self::resolveImageVersion($parsed['path'], $height);
        $url      = self::getImageUrl($resolved);

        if ($output === 'raw') {
            return $url;
        }

        // Auto-calculate missing dimension from original aspect ratio to prevent CLS
        if (!$auto && $parsed['width'] > 0 && $parsed['height'] > 0) {
            $ratio = $parsed['width'] / $parsed['height'];

            if ($height > 0 && $width <= 0) {
                $width = (int) round($height * $ratio);
            } elseif ($width > 0 && $height <= 0) {
                $height = (int) round($width / $ratio);
            }
        }

        $attrs   = ['src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"'];
        $attrs[] = 'alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"';

        if ($height > 0) {
            $attrs[] = 'height="' . $height . '"';
        }
        if ($width > 0) {
            $attrs[] = 'width="' . $width . '"';
        }
        if ($class !== '') {
            $attrs[] = 'class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"';
        }

        $attrs[] = 'loading="lazy"';

        return '<img ' . implode(' ', $attrs) . '>';
    }

    /** Resolve a plugin logo/icon image from the media directory, checking j2commerce paths. */
    public static function getPluginImage(string $pluginName, string $imageName = ''): string
    {
        if (empty($pluginName)) {
            return '';
        }

        if ($imageName === '') {
            $imageName = $pluginName;
        }

        $extensions = ['webp', 'png', 'jpg'];
        $prefixes   = ['plg_j2commerce_'];

        foreach ($prefixes as $prefix) {
            foreach ($extensions as $ext) {
                $relPath = 'media/' . $prefix . $pluginName . '/images/' . $imageName . '.' . $ext;

                if (file_exists(JPATH_SITE . '/' . $relPath)) {
                    return $relPath;
                }
            }
        }

        return 'media/com_j2commerce/images/default_app_j2commerce.webp';
    }

    public static function getLanguageFlag(string $langCode): string
    {
        if (empty($langCode)) {
            return '';
        }

        $normalized = strtolower(str_replace('-', '_', $langCode));
        $relPath    = 'media/mod_languages/images/' . $normalized . '.gif';

        if (file_exists(JPATH_SITE . '/' . $relPath)) {
            return $relPath;
        }

        return '';
    }

    public static function reset(): void
    {
        self::$instance          = null;
        self::$imageUploadParams = null;
    }
}
