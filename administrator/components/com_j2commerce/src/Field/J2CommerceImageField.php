<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Field;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

class J2CommerceImageField extends FormField
{
    protected $type = 'J2CommerceImage';

    protected function getInput(): string
    {
        $this->loadAssets();

        $componentParams = ComponentHelper::getParams('com_j2commerce');

        $multiple        = filter_var($this->element['multiple'] ?? 'false', \FILTER_VALIDATE_BOOLEAN);
        $max             = (int) ($this->element['max'] ?? 0);
        $thumbnailHeight = (int) ($this->element['thumbnail_height'] ?? 80);
        $directory       = (string) ($this->element['directory'] ?? 'images');
        $maxFileSize     = ((int) ($this->element['max_file_size'] ?? 0) ?: $componentParams->get('image_max_file_size', 10)) * 1024 * 1024;
        $compression     = (bool) ($this->element['client_compression'] ?? $componentParams->get('image_client_compression', 1));
        $autoThumbnail   = (bool) ($this->element['auto_thumbnail'] ?? $componentParams->get('image_auto_thumbnail', 1));
        $accept          = (string) ($this->element['accept'] ?? 'image/*');
        $previewStyle    = (string) ($this->element['preview_style'] ?? 'square');

        $options = [
            'maxFileSize'       => $maxFileSize,
            'allowedFileTypes'  => [$accept],
            'enableCompression' => $compression,
            'autoThumbnail'     => $autoThumbnail,
            'directory'         => $directory,
            'multiple'          => $multiple,
            'max'               => $max,
            'thumbnailHeight'   => $thumbnailHeight,
            'previewStyle'      => $previewStyle,
            'endpoint'          => Uri::base() . 'index.php?option=com_j2commerce&task=multiimageuploader.upload&format=json',
        ];

        // Normalize value: single mode = string, multi mode = JSON array
        $value = $this->value;

        if ($multiple) {
            if (\is_string($value) && !empty($value)) {
                $decoded = json_decode($value, true);
                $value   = \is_array($decoded) ? $decoded : [$value];
            }
            if (!\is_array($value)) {
                $value = [];
            }
        } else {
            if (\is_array($value)) {
                $value = $value[0] ?? '';
            }
            $value = (string) ($value ?? '');
        }

        return LayoutHelper::render(
            'field.j2commerceimage',
            [
                'id'       => $this->id,
                'name'     => $this->name,
                'value'    => $value,
                'options'  => $options,
                'multiple' => $multiple,
                'max'      => $max,
                'required' => $this->required,
                'disabled' => $this->disabled,
                'readonly' => $this->readonly,
            ],
            JPATH_ADMINISTRATOR . '/components/com_j2commerce/layouts'
        );
    }

    protected function loadAssets(): void
    {
        // Reuse Uppy bundle and multiimageuploader CSS (modal styles)
        MultiImageUploaderField::loadAssetsStatic();

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();

        $wa->registerAndUseStyle(
            'com_j2commerce.j2commerceimage.css',
            'media/com_j2commerce/css/administrator/j2commerceimage.css',
            ['version' => 'auto']
        );

        $wa->registerAndUseScript(
            'com_j2commerce.j2commerceimage.js',
            'media/com_j2commerce/js/administrator/j2commerceimage.js',
            ['version' => 'auto'],
            ['defer'   => true],
            ['com_j2commerce.vendor.uppy']
        );

        Text::script('COM_J2COMMERCE_IMAGE_CHOOSE');
        Text::script('COM_J2COMMERCE_IMAGE_CHOOSE_MULTIPLE');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_DRAG_DROP_NOTE');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_UPLOAD_ERROR');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_UPLOAD_SUCCESS');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_DROP_HERE_OR');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_BROWSE');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_UPLOAD_COMPLETE');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_UPLOAD_PAUSED');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_CONFIRM_DELETE');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_CONFIRM_DELETE_SHARED');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_DELETE_FAILED');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_DELETE_FROM_SERVER');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_SELECT_FILE');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_DONE');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_NEW_FOLDER_PROMPT');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_INVALID_FOLDER_NAME');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_CONFIRM_DELETE_FOLDER');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_IMAGE_FILTER_PLACEHOLDER');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_NEW_FOLDER');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_ADD_PRODUCT_IMAGES');
    }
}
