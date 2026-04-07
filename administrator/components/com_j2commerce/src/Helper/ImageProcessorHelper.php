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

use enshrined\svgSanitize\Sanitizer;
use Joomla\CMS\Image\Image;

\defined('_JEXEC') or die;

class ImageProcessorHelper
{
    private int $webpQuality;
    private int $thumbQuality;

    public function __construct(int $webpQuality = 80, int $thumbQuality = 80)
    {
        $this->webpQuality  = max(1, min(100, $webpQuality));
        $this->thumbQuality = max(1, min(100, $thumbQuality));
    }

    public function convertToWebP(string $data, string $extension): string|false
    {
        if (!\function_exists('imagewebp')) {
            return false;
        }

        $image = @imagecreatefromstring($data);

        if ($image === false) {
            return false;
        }

        if (\in_array(strtolower($extension), ['png', 'gif'])) {
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
        }

        ob_start();
        $result   = imagewebp($image, null, $this->webpQuality);
        $webpData = ob_get_clean();

        imagedestroy($image);

        if (!$result || empty($webpData)) {
            return false;
        }

        return $webpData;
    }

    public function createThumbnail(
        string $sourcePath,
        string $thumbPath,
        int $width,
        int $height
    ): bool {
        try {
            if (strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) === 'svg') {
                $sanitized = self::sanitizeSvgFile($sourcePath);

                if ($sanitized === false) {
                    return false;
                }

                return file_put_contents($thumbPath, $sanitized) !== false;
            }

            $image = new Image($sourcePath);

            $originalWidth  = $image->getWidth();
            $originalHeight = $image->getHeight();

            if ($originalWidth <= $width && $originalHeight <= $height) {
                $newWidth  = $originalWidth;
                $newHeight = $originalHeight;
            } else {
                $ratio     = min($width / $originalWidth, $height / $originalHeight);
                $newWidth  = (int) round($originalWidth * $ratio);
                $newHeight = (int) round($originalHeight * $ratio);
            }

            $image->resize($newWidth, $newHeight, false, Image::SCALE_INSIDE);
            $image->toFile($thumbPath, IMAGETYPE_WEBP, ['quality' => $this->thumbQuality]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /** Resize and convert main image to WebP. Returns WebP data or false on failure. */
    public function processMainImage(string $sourcePath, int $maxDimension, bool $maintainRatio): string|false
    {
        if (strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) === 'svg') {
            return self::sanitizeSvgFile($sourcePath) ?: false;
        }

        if (!\function_exists('imagewebp') || $maxDimension < 1) {
            return false;
        }

        try {
            $image = new Image($sourcePath);
        } catch (\Exception $e) {
            return false;
        }

        $origWidth  = $image->getWidth();
        $origHeight = $image->getHeight();

        if ($maintainRatio) {
            if ($origWidth <= $maxDimension && $origHeight <= $maxDimension) {
                $newWidth  = $origWidth;
                $newHeight = $origHeight;
            } else {
                $ratio     = $maxDimension / max($origWidth, $origHeight);
                $newWidth  = (int) round($origWidth * $ratio);
                $newHeight = (int) round($origHeight * $ratio);
            }

            $image->resize($newWidth, $newHeight, false, Image::SCALE_INSIDE);

            return $this->imageToWebP($image);
        }

        // No maintain ratio: fit into square with white padding
        $ratio     = $maxDimension / max($origWidth, $origHeight);
        $scaledW   = (int) round($origWidth * $ratio);
        $scaledH   = (int) round($origHeight * $ratio);

        if ($origWidth <= $maxDimension && $origHeight <= $maxDimension) {
            $scaledW = $origWidth;
            $scaledH = $origHeight;
        }

        $image->resize($scaledW, $scaledH, false, Image::SCALE_INSIDE);

        $canvas = imagecreatetruecolor($maxDimension, $maxDimension);
        $white  = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        $offsetX = (int) round(($maxDimension - $scaledW) / 2);
        $offsetY = (int) round(($maxDimension - $scaledH) / 2);

        $scaledHandle = $image->getHandle();
        imagecopy($canvas, $scaledHandle, $offsetX, $offsetY, 0, 0, $scaledW, $scaledH);

        ob_start();
        $result   = imagewebp($canvas, null, $this->webpQuality);
        $webpData = ob_get_clean();

        imagedestroy($canvas);

        return ($result && !empty($webpData)) ? $webpData : false;
    }

    private function imageToWebP(Image $image): string|false
    {
        $handle = $image->getHandle();

        ob_start();
        $result   = imagewebp($handle, null, $this->webpQuality);
        $webpData = ob_get_clean();

        return ($result && !empty($webpData)) ? $webpData : false;
    }

    public function validateImage(string $data): bool
    {
        $trimmed = trim($data);

        if (str_starts_with($trimmed, '<svg') || str_starts_with($trimmed, '<?xml')) {
            $sanitizer = new Sanitizer();
            $clean     = $sanitizer->sanitize($data);

            return $clean !== false;
        }

        $imageInfo = @getimagesizefromstring($data);

        if ($imageInfo === false) {
            return false;
        }

        $validTypes = [
            IMAGETYPE_GIF,
            IMAGETYPE_JPEG,
            IMAGETYPE_PNG,
            IMAGETYPE_WEBP,
        ];

        if (\defined('IMAGETYPE_AVIF')) {
            $validTypes[] = IMAGETYPE_AVIF;
        }

        return \in_array($imageInfo[2], $validTypes);
    }

    /**
     * Sanitize an SVG file using Joomla's bundled enshrined/svg-sanitize library.
     * Returns sanitized SVG string, or false on failure.
     */
    public static function sanitizeSvgFile(string $filePath): string|false
    {
        $raw = file_get_contents($filePath);

        if ($raw === false) {
            return false;
        }

        $sanitizer = new Sanitizer();
        $clean     = $sanitizer->sanitize($raw);

        return \is_string($clean) && $clean !== '' ? $clean : false;
    }

    public function getImageDimensions(string $data): array|false
    {
        $imageInfo = @getimagesizefromstring($data);

        if ($imageInfo === false) {
            return false;
        }

        return [
            'width'  => $imageInfo[0],
            'height' => $imageInfo[1],
        ];
    }
}
