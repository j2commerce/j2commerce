<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Field\MultiImageUploaderField;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Uri\Uri;


$item = $displayData['product'];
$formPrefix = $displayData['form_prefix'] ?? 'jform[attribs][j2commerce]';

$base_path = JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product';
?>

<div class="j2commerce-product-images">
    <!-- Main/thumb image hidden inputs are created dynamically by Uppy's syncToFormInputs() -->

    <!-- MultiImageUploader Field -->
    <?php
    MultiImageUploaderField::loadAssetsStatic();

    // Build gallery data from existing productimages data
    $galleryData = [];
    $siteRoot = rtrim(Uri::root(), '/') . '/';

    // Strip repeated Uri::root() prefixes from a stored image path, returning a clean relative path.
    $stripBaseUrl = function (string $value) use ($siteRoot): string {
        // Remove all occurrences of the base URL (handles repeated corruption)
        while (str_starts_with($value, $siteRoot)) {
            $value = substr($value, strlen($siteRoot));
        }
        // Also handle without trailing slash
        $siteRootNoSlash = rtrim($siteRoot, '/');
        while (str_starts_with($value, $siteRootNoSlash)) {
            $value = ltrim(substr($value, strlen($siteRootNoSlash)), '/');
        }
        return $value;
    };

    // Format: "images/path/file.webp#joomlaImage://local-images/path/file.webp?width=700&height=700"
    $extractPath = function (string $imageValue) use ($stripBaseUrl): array {
        $path = $imageValue;
        $width = 0;
        $height = 0;

        // Split on # to get the raw path
        if (str_contains($imageValue, '#')) {
            $path = explode('#', $imageValue, 2)[0];
        }

        // Strip any absolute URL prefix (fixes corrupted DB values)
        $path = $stripBaseUrl($path);

        // Extract dimensions from the joomlaImage URL
        if (preg_match('/width=(\d+)/', $imageValue, $m)) {
            $width = (int) $m[1];
        }
        if (preg_match('/height=(\d+)/', $imageValue, $m)) {
            $height = (int) $m[1];
        }

        return ['path' => $path, 'width' => $width, 'height' => $height];
    };

    // Main image (first in the gallery)
    if (!empty($item->main_image)) {
        $info = $extractPath($item->main_image);

        // Extract thumb info
        $thumbInfo = ['path' => '', 'width' => 0, 'height' => 0];
        if (!empty($item->thumb_image)) {
            $thumbInfo = $extractPath($item->thumb_image);
        }

        // Extract tiny info
        $tinyInfo = ['path' => '', 'width' => 0, 'height' => 0];
        if (!empty($item->tiny_image)) {
            $tinyInfo = $extractPath($item->tiny_image);
        }

        $galleryData[] = [
            'name'         => basename($info['path']),
            'path'         => $info['path'],
            'url'          => $siteRoot . $info['path'],
            'thumb_url'    => !empty($thumbInfo['path']) ? $siteRoot . $thumbInfo['path'] : $siteRoot . $info['path'],
            'alt_text'     => $item->main_image_alt ?? '',
            'width'        => $info['width'],
            'height'       => $info['height'],
            'thumb_path'   => $thumbInfo['path'],
            'thumb_width'  => $thumbInfo['width'],
            'thumb_height' => $thumbInfo['height'],
            'tiny_path'    => $tinyInfo['path'],
            'tiny_url'     => !empty($tinyInfo['path']) ? $siteRoot . $tinyInfo['path'] : '',
            'tiny_width'   => $tinyInfo['width'],
            'tiny_height'  => $tinyInfo['height'],
        ];
    }

    // Decode additional image arrays (originals, thumbs, tinys)
    $decodeJsonField = function ($value): array {
        if (empty($value)) {
            return [];
        }
        if (\is_string($value)) {
            $decoded = json_decode($value, true);
            // Handle double-encoded JSON: the outer decode returns a string
            // that needs a second decode to get the actual array
            if (\is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }
            return \is_array($decoded) ? $decoded : [];
        }
        return (array) $value;
    };

    $addImages    = $decodeJsonField($item->additional_images ?? null);
    $addAlts      = $decodeJsonField($item->additional_images_alt ?? null);
    $addThumbs    = $decodeJsonField($item->additional_thumb_images ?? null);
    $addThumbAlts = $decodeJsonField($item->additional_thumb_images_alt ?? null);
    $addTinys     = $decodeJsonField($item->additional_tiny_images ?? null);
    $addTinyAlts  = $decodeJsonField($item->additional_tiny_images_alt ?? null);

    // Additional images (remaining gallery items)
    if (!empty($addImages)) {
        foreach ($addImages as $key => $imgValue) {
            if (empty($imgValue)) {
                continue;
            }
            $info = $extractPath($imgValue);

            $thumbInfo = ['path' => '', 'width' => 0, 'height' => 0];
            if (!empty($addThumbs[$key])) {
                $thumbInfo = $extractPath($addThumbs[$key]);
            }

            $tinyInfo = ['path' => '', 'width' => 0, 'height' => 0];
            if (!empty($addTinys[$key])) {
                $tinyInfo = $extractPath($addTinys[$key]);
            }

            $galleryData[] = [
                'name'         => basename($info['path']),
                'path'         => $info['path'],
                'url'          => $siteRoot . $info['path'],
                'thumb_url'    => !empty($thumbInfo['path']) ? $siteRoot . $thumbInfo['path'] : $siteRoot . $info['path'],
                'alt_text'     => $addAlts[$key] ?? '',
                'width'        => $info['width'],
                'height'       => $info['height'],
                'thumb_path'   => $thumbInfo['path'],
                'thumb_width'  => $thumbInfo['width'],
                'thumb_height' => $thumbInfo['height'],
                'tiny_path'    => $tinyInfo['path'],
                'tiny_url'     => !empty($tinyInfo['path']) ? $siteRoot . $tinyInfo['path'] : '',
                'tiny_width'   => $tinyInfo['width'],
                'tiny_height'  => $tinyInfo['height'],
            ];
        }
    }

    $layoutPath = JPATH_ADMINISTRATOR . '/components/com_j2commerce/layouts';
    ?>
    <div class="row">
        <div class="col-12">
            <fieldset id="fieldset-j2commerce-uppy-gallery" class="options-form">
                <legend><?php echo Text::_('COM_J2COMMERCE_PRODUCT_IMAGES'); ?></legend>
                <?php
                echo (new FileLayout('field.multiimageuploader', $layoutPath))->render([
                    'id'       => 'j2commerce-uppy-gallery',
                    'name'     => 'uppymedia_gallery',
                    'value'    => $galleryData,
                    'options'  => [
                        'maxFileSize'       => 10 * 1024 * 1024,
                        'allowedFileTypes'  => ['image/*'],
                        'enableCompression' => true,
                        'enableImageEditor' => true,
                        'autoThumbnail'     => true,
                        'directory'         => 'images',
                        'multiple'          => true,
                        'endpoint'          => 'index.php?option=com_j2commerce&task=multiimageuploader.upload&format=json',
                        'formPrefix'        => $formPrefix,
                    ],
                    'required' => false,
                    'disabled' => false,
                    'readonly' => false,
                ]);
                ?>
            </fieldset>
        </div>
    </div>

    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterProductImagesForm', array($this, $item, $formPrefix))->getArgument('html', ''); ?>

    <div class="alert alert-info mt-3">
        <h4 class="alert-heading"><?php echo Text::_('COM_J2COMMERCE_QUICK_HELP'); ?></h4>
        <p class="mb-1"><?php echo Text::_('COM_J2COMMERCE_FEATURE_AVAILABLE_IN_J2STORE_PRODUCT_LAYOUTS_AND_ARTICLES'); ?></p>
        <p><?php echo Text::_('COM_J2COMMERCE_PRODUCT_IMAGES_HELP_TEXT'); ?></p>
    </div>
</div>

