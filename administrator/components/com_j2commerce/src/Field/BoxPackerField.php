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

\defined('_JEXEC') or die;

class BoxPackerField extends FormField
{
    protected $type = 'BoxPacker';

    protected function getInput(): string
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseScript(
            'com_j2commerce.boxpacker-field',
            'media/com_j2commerce/js/administrator/boxpacker-field.js',
            [],
            ['defer' => true]
        );
        $wa->registerAndUseScript(
            'com_j2commerce.boxpacker-preview',
            'media/com_j2commerce/js/administrator/boxpacker-preview.js',
            [],
            ['defer' => true]
        );
        $wa->registerAndUseStyle('j2commerce-admin-css', 'media/com_j2commerce/css/administrator/j2commerce_admin.css');

        Text::script('COM_J2COMMERCE_BOXPACKER_PREVIEW_ITEM_NAME');
        Text::script('COM_J2COMMERCE_BOXPACKER_PREVIEW_NO_ITEMS');
        Text::script('COM_J2COMMERCE_BOXPACKER_PREVIEW_RUNNING');
        Text::script('COM_J2COMMERCE_BOXPACKER_PREVIEW_ERROR');
        Text::script('COM_J2COMMERCE_BOXPACKER_PREVIEW_RUN');
        Text::script('COM_J2COMMERCE_BOXPACKER_PREVIEW_UNKNOWN_ERROR');
        Text::script('COM_J2COMMERCE_BOXPACKER_PREVIEW_BOXES_NEEDED');
        Text::script('COM_J2COMMERCE_BOXPACKER_PREVIEW_PER_ITEM_MODE');
        Text::script('COM_J2COMMERCE_BOXPACKER_PREVIEW_BOX_N');
        Text::script('COM_J2COMMERCE_BOXPACKER_PREVIEW_WEIGHT_USED');
        Text::script('COM_J2COMMERCE_BOXPACKER_PREVIEW_WEIGHT');
        Text::script('COM_J2COMMERCE_BOXPACKER_PREVIEW_VOLUME_USED');
        Text::script('COM_J2COMMERCE_BOXPACKER_PREVIEW_UNPACKED_MSG');

        HTMLHelper::_('bootstrap.modal', '#boxpacker3dModal');

        $boxes = [];
        if (!empty($this->value)) {
            $decoded = \is_string($this->value) ? json_decode($this->value, true) : (array) $this->value;
            $boxes   = \is_array($decoded) ? $decoded : [];
        }

        $fieldName = $this->name;
        $fieldId   = htmlspecialchars($this->id, ENT_COMPAT, 'UTF-8');
        $token     = Session::getFormToken();

        $html = '<div class="j2commerce-boxpacker-field" id="' . $fieldId . '_container" data-field-name="' . htmlspecialchars($fieldName, ENT_COMPAT, 'UTF-8') . '" data-token="' . $token . '">';

        // Hidden input to store JSON value
        $html .= '<input type="hidden" name="' . htmlspecialchars($fieldName, ENT_COMPAT, 'UTF-8') . '" id="' . $fieldId . '" value="' . htmlspecialchars(json_encode($boxes), ENT_COMPAT, 'UTF-8') . '" />';

        // Box definition table
        $showCommonBoxes = ((string) $this->element['show_common_boxes'] ?? '') === 'true';

        $html .= '<div class="d-flex justify-content-between align-items-center mb-2">';
        $html .= '<strong>' . Text::_('COM_J2COMMERCE_BOXPACKER_BOX_NAME') . '</strong>';
        $html .= '<div class="btn-group">';
        if ($showCommonBoxes) {
            $html .= '<button type="button" class="btn btn-sm btn-outline-primary btn-load-common-boxes"><i class="fa-solid fa-boxes-stacked"></i> ' . Text::_('COM_J2COMMERCE_BOXPACKER_LOAD_COMMON_BOXES') . '</button>';
        }
        $html .= '<button type="button" class="btn btn-sm btn-success btn-add-box"><i class="fa-solid fa-plus"></i> ' . Text::_('COM_J2COMMERCE_BOXPACKER_ADD_BOX') . '</button>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div class="table-responsive">';
        $html .= '<table class="table boxpacker-boxes-table">';
        $html .= '<thead><tr>';
        $html .= '<th scope="col"><small>' . Text::_('COM_J2COMMERCE_BOXPACKER_BOX_NAME') . '</small></th>';
        $html .= '<th scope="col"><small>' . Text::_('COM_J2COMMERCE_BOXPACKER_OUTER_LENGTH') . '</small></th>';
        $html .= '<th scope="col"><small>' . Text::_('COM_J2COMMERCE_BOXPACKER_OUTER_WIDTH') . '</small></th>';
        $html .= '<th scope="col"><small>' . Text::_('COM_J2COMMERCE_BOXPACKER_OUTER_HEIGHT') . '</small></th>';
        $html .= '<th scope="col"><small>' . Text::_('COM_J2COMMERCE_BOXPACKER_INNER_LENGTH') . '</small></th>';
        $html .= '<th scope="col"><small>' . Text::_('COM_J2COMMERCE_BOXPACKER_INNER_WIDTH') . '</small></th>';
        $html .= '<th scope="col"><small>' . Text::_('COM_J2COMMERCE_BOXPACKER_INNER_HEIGHT') . '</small></th>';
        $html .= '<th scope="col"><small>' . Text::_('COM_J2COMMERCE_BOXPACKER_BOX_WEIGHT') . '</small></th>';
        $html .= '<th scope="col"><small>' . Text::_('COM_J2COMMERCE_BOXPACKER_MAX_WEIGHT') . '</small></th>';
        $html .= '<th scope="col"><span class="visually-hidden">' . Text::_('COM_J2COMMERCE_ACTIONS') . '</span></th>';
        $html .= '</tr></thead>';
        $html .= '<tbody class="boxpacker-boxes-body">';

        foreach ($boxes as $i => $box) {
            $box = (array) $box;
            $html .= self::renderBoxRow($i, $box);
        }

        $html .= '</tbody></table></div>';

        if (empty($boxes)) {
            $html .= '<p class="text-muted boxpacker-no-boxes">' . Text::_('COM_J2COMMERCE_BOXPACKER_NO_BOXES') . '</p>';
        }

        // === Packing Preview Section ===
        $html .= '<hr class="my-4">';
        $html .= '<div class="j2commerce-boxpacker-preview">';
        $html .= '<h5>' . Text::_('COM_J2COMMERCE_BOXPACKER_PREVIEW_TITLE') . '</h5>';

        // Sample items table
        $html .= '<div class="d-flex justify-content-between align-items-center mb-2">';
        $html .= '<strong>' . Text::_('COM_J2COMMERCE_BOXPACKER_PREVIEW_SAMPLE_ITEMS') . '</strong>';
        $html .= '<button type="button" class="btn btn-sm btn-outline-primary btn-add-test-item"><i class="fa-solid fa-plus"></i> ' . Text::_('COM_J2COMMERCE_BOXPACKER_PREVIEW_ADD_ITEM') . '</button>';
        $html .= '</div>';

        $html .= '<div class="table-responsive">';
        $html .= '<table class="table table-sm preview-items-table">';
        $html .= '<thead><tr>';
        $html .= '<th>' . Text::_('COM_J2COMMERCE_BOXPACKER_PREVIEW_DESCRIPTION') . '</th>';
        $html .= '<th>' . Text::_('COM_J2COMMERCE_BOXPACKER_PREVIEW_LENGTH') . '</th>';
        $html .= '<th>' . Text::_('COM_J2COMMERCE_BOXPACKER_PREVIEW_WIDTH') . '</th>';
        $html .= '<th>' . Text::_('COM_J2COMMERCE_BOXPACKER_PREVIEW_HEIGHT') . '</th>';
        $html .= '<th>' . Text::_('COM_J2COMMERCE_BOXPACKER_PREVIEW_WEIGHT') . '</th>';
        $html .= '<th>' . Text::_('COM_J2COMMERCE_BOXPACKER_PREVIEW_QTY') . '</th>';
        $html .= '<th><span class="visually-hidden">' . Text::_('COM_J2COMMERCE_ACTIONS') . '</span></th>';
        $html .= '</tr></thead>';
        $html .= '<tbody class="preview-items-body"></tbody>';
        $html .= '</table></div>';

        $html .= '<button type="button" class="btn btn-primary btn-preview-packing mb-3">';
        $html .= '<i class="fa-solid fa-box-open"></i> ' . Text::_('COM_J2COMMERCE_BOXPACKER_PREVIEW_RUN');
        $html .= '</button>';

        // Results container (populated by JS)
        $html .= '<div class="preview-results"></div>';
        $html .= '</div>'; // .j2commerce-boxpacker-preview

        $html .= '</div>'; // .j2commerce-boxpacker-field

        return $html;
    }

    private static function renderBoxRow(int $index, array $box): string
    {
        $fields = ['name', 'outer_length', 'outer_width', 'outer_height', 'inner_length', 'inner_width', 'inner_height', 'box_weight', 'max_weight'];
        $row    = '<tr data-row-index="' . $index . '">';

        foreach ($fields as $field) {
            $val  = htmlspecialchars((string) ($box[$field] ?? ''), ENT_COMPAT, 'UTF-8');
            $type = $field === 'name' ? 'text' : 'number';
            $step = $field === 'name' ? '' : ' step="0.1" min="0"';
            $cls  = 'form-control form-control-sm';
            $row .= '<td><input type="' . $type . '" class="' . $cls . '" data-box-field="' . $field . '" value="' . $val . '"' . $step . '></td>';
        }

        $row .= '<td><button type="button" class="btn btn-sm btn-danger btn-remove-box" title="' . Text::_('COM_J2COMMERCE_BOXPACKER_REMOVE_BOX') . '"><i class="fa-solid fa-times"></i></button></td>';
        $row .= '</tr>';

        return $row;
    }

    protected function getLabel()
    {
        return parent::getLabel();
    }
}
