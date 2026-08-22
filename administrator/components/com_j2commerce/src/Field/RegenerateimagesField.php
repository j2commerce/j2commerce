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

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

class RegenerateimagesField extends FormField
{
    protected $type = 'Regenerateimages';

    protected function getInput(): string
    {
        static::loadAssetsStatic();

        $endpoint  = Uri::base() . 'index.php?option=com_j2commerce&task=regenerateimages.';
        $csrfToken = Session::getFormToken();

        $html   = [];
        $html[] = '<div class="j2c-regenerate-images" '
            . 'data-endpoint="' . htmlspecialchars($endpoint, ENT_QUOTES, 'UTF-8') . '" '
            . 'data-csrf-token="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">';

        $html[] = '<p class="text-body-secondary">' . htmlspecialchars(Text::_('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_DESC'), ENT_QUOTES, 'UTF-8') . '</p>';

        $html[] = '<div class="d-flex flex-wrap gap-2">';
        $html[] = '<button type="button" class="btn btn-outline-primary" data-j2c-regen="thumbs">'
            . htmlspecialchars(Text::_('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_THUMBS_BUTTON'), ENT_QUOTES, 'UTF-8') . '</button>';
        $html[] = '<button type="button" class="btn btn-outline-primary" data-j2c-regen="tiny">'
            . htmlspecialchars(Text::_('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_TINY_BUTTON'), ENT_QUOTES, 'UTF-8') . '</button>';
        $html[] = '</div>';

        $html[] = '</div>';

        return implode('', $html);
    }

    public static function loadAssetsStatic(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        HTMLHelper::_('bootstrap.modal');

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseScript(
            'com_j2commerce.admin.regenerateimages',
            'media/com_j2commerce/js/administrator/regenerateimages.js',
            [],
            ['defer' => true]
        );

        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_THUMBS_BUTTON');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_TINY_BUTTON');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_MODAL_TITLE');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_SCANNING');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_PROGRESS');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_PROGRESS_LABEL');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_SUMMARY');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_COMPLETE');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_CANCELLED');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_ERROR');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_ERRORS_MORE');
        Text::script('JCANCEL');
        Text::script('JCLOSE');
    }
}
