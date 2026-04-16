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

class MultiImageUploaderField extends FormField
{
    protected $type = 'MultiImageUploader';

    protected function getInput(): string
    {
        $this->loadAssets();

        $componentParams = ComponentHelper::getParams('com_j2commerce');

        $options = [
            'maxFileSize'       => ((int) ($this->element['max_file_size'] ?? 0) ?: $componentParams->get('image_max_file_size', 10)) * 1024 * 1024,
            'allowedFileTypes'  => ['image/*'],
            'enableCompression' => (bool) ($this->element['client_compression'] ?? $componentParams->get('image_client_compression', 1)),
            'enableImageEditor' => true,
            'autoThumbnail'     => (bool) ($this->element['auto_thumbnail'] ?? $componentParams->get('image_auto_thumbnail', 1)),
            'directory'         => (string) ($this->element['directory'] ?? 'images'),
            'multiple'          => (bool) ($this->element['multiple'] ?? true),
            'endpoint'          => Uri::base() . 'index.php?option=com_j2commerce&task=multiimageuploader.upload&format=json',
        ];

        $value = $this->value;
        if (\is_string($value) && !empty($value)) {
            $value = json_decode($value, true) ?: [];
        }
        if (!\is_array($value)) {
            $value = [];
        }

        return LayoutHelper::render(
            'field.multiimageuploader',
            [
                'id'       => $this->id,
                'name'     => $this->name,
                'value'    => $value,
                'options'  => $options,
                'required' => $this->required,
                'disabled' => $this->disabled,
                'readonly' => $this->readonly,
            ],
            JPATH_ADMINISTRATOR . '/components/com_j2commerce/layouts'
        );
    }

    protected function loadAssets(): void
    {
        static::loadAssetsStatic();
    }

    public static function loadAssetsStatic(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();

        $wa->registerAndUseStyle(
            'com_j2commerce.vendor.uppy.css',
            'media/com_j2commerce/vendor/uppy/css/uppy.min.css',
            ['version' => 'auto']
        );

        $wa->registerAndUseStyle(
            'com_j2commerce.multiimageuploader.css',
            'media/com_j2commerce/css/administrator/multiimageuploader.css',
            ['version' => 'auto']
        );

        $wa->registerAndUseScript(
            'com_j2commerce.vendor.uppy',
            'media/com_j2commerce/vendor/uppy/js/uppy.min.js',
            ['version' => 'auto'],
            ['defer'   => true]
        );

        $wa->registerAndUseScript(
            'com_j2commerce.multiimageuploader.js',
            'media/com_j2commerce/js/administrator/multiimageuploader.js',
            ['version' => 'auto'],
            ['defer'   => true, 'type' => 'module'],
            ['com_j2commerce.vendor.uppy']
        );

        // JS language strings used by multiimageuploader.js
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_DRAG_DROP_NOTE');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_UPLOAD_ERROR');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_UPLOAD_SUCCESS');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_ALT_TEXT_PLACEHOLDER');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_ALT_TEXT');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_DROP_HERE_OR');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_BROWSE');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_UPLOAD_COMPLETE');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_UPLOAD_PAUSED');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_REMOVE_FILE');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_EDIT_FILE');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_CONFIRM_DELETE');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_CONFIRM_DELETE_SHARED');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_DELETE_FAILED');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_SELECT_IMAGE');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_DELETE_FROM_SERVER');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_MOVE_LEFT');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_MOVE_RIGHT');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_NEW_FOLDER_PROMPT');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_INVALID_FOLDER_NAME');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_CONFIRM_DELETE_FOLDER');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_CANNOT_DELETE_ROOT');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_ADD_PRODUCT_IMAGES');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_ADD_PRODUCT_FILES');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_ACCEPTED_FORMATS');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_ACCEPTED_FILE_FORMATS');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_ADD_MEDIA');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_DONE');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_REMOVE_SELECTED');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_SELECT_FILE');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_AUTO_THUMBNAIL_CHECKBOX');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_IMAGE_FILTER_PLACEHOLDER');
        Text::script('COM_J2COMMERCE_MULTIIMAGEUPLOADER_NEW_FOLDER');
    }
}
