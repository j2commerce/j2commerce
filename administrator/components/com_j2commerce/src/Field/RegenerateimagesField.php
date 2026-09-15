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

        $esc = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // The description is Joomla's inline help (renderfield prints it as {id}-desc), so it is referenced, not repeated.
        $describedBy = !empty($this->description) ? ' aria-describedby="' . $esc($this->id . '-desc') . '"' : '';

        // No name attribute: the choice drives the AJAX run and is never saved with the component options.
        $html[] = '<div class="input-group">';
        $html[] = '<select id="' . $esc($this->id) . '" class="form-select" data-j2c-regen-scope' . $describedBy . '>';
        $html[] = '<option value="">' . $esc(Text::_('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_SELECT_OPTION')) . '</option>';

        foreach (['thumbs' => 'THUMBS', 'tiny' => 'TINY', 'both' => 'BOTH'] as $scope => $labelKey) {
            $html[] = '<option value="' . $scope . '">' . $esc(Text::_('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_' . $labelKey . '_BUTTON')) . '</option>';
        }

        $html[] = '</select>';
        $html[] = '<button type="button" class="btn btn-primary" data-j2c-regen-start disabled>'
            . $esc(Text::_('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_START')) . '</button>';
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
        $wa->registerAndUseStyle('com_j2commerce.admin.regenerateimages', 'media/com_j2commerce/css/administrator/regenerateimages.css');

        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_THUMBS_BUTTON');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_TINY_BUTTON');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_MODAL_TITLE');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_SCANNING');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_PROGRESS');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_PROGRESS_COUNT');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_PROGRESS_LABEL');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_STATUS_HEADING');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_STATUS_SUCCESSFUL');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_STATUS_GENERATED');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_SIZE_THUMBS');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_SIZE_TINY');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_COMPLETE');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_CANCELLED');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_ERROR');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_CONFIRM');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_START');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_RESUME');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_RESUME_PROMPT');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_RESUME_NOTICE');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_START_OVER');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_DOWNLOAD_REPORT');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_KEEP_OPEN');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_REPORT_PRODUCT');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_STATUS_FAILED');
        Text::script('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_STATUS_SKIPPED');
        Text::script('JCANCEL');
        Text::script('JCLOSE');
    }
}
