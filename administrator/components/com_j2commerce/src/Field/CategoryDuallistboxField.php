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

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * CategoryDuallistbox field - dual listbox interface for selecting Joomla article categories.
 *
 * @since  6.0.7
 */
class CategoryDuallistboxField extends ListField
{
    protected $type = 'CategoryDuallistbox';

    public function getOptions(): array
    {
        $options = parent::getOptions();

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('id', 'value'),
                    $db->quoteName('title', 'text'),
                    $db->quoteName('level'),
                ])
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
                ->where($db->quoteName('published') . ' = 1')
                ->order($db->quoteName('lft') . ' ASC');

            $db->setQuery($query);
            $categories = $db->loadObjectList();

            if ($categories) {
                foreach ($categories as $category) {
                    $indent    = str_repeat('— ', max(0, (int) $category->level - 1));
                    $options[] = HTMLHelper::_('select.option', $category->value, $indent . $category->text);
                }
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_J2COMMERCE_ERROR_LOADING_CATEGORIES', $e->getMessage()),
                'error'
            );
        }

        return $options;
    }

    protected function getInput(): string
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseScript('com_j2commerce.vendor.dual-listbox', 'media/com_j2commerce/vendor/dual-listbox/js/dual-listbox.js', [], ['defer' => true]);
        $wa->registerAndUseStyle('com_j2commerce.vendor.dual-listbox.css', 'media/com_j2commerce/vendor/dual-listbox/css/dual-listbox.css');

        $options        = $this->getOptions();
        $selectedValues = $this->processValue($this->value);

        $class = $this->element['class'] ? (string) $this->element['class'] : 'form-select';
        $size  = $this->element['size'] ? (int) $this->element['size'] : 10;

        $attributes = [
            'id="' . htmlspecialchars($this->id, ENT_COMPAT, 'UTF-8') . '"',
            'name="' . htmlspecialchars($this->name, ENT_COMPAT, 'UTF-8') . '[]"',
            'class="' . htmlspecialchars($class . ' category-duallistbox', ENT_COMPAT, 'UTF-8') . '"',
            'multiple="multiple"',
            'size="' . $size . '"',
        ];

        if ((string) $this->element['disabled'] === 'true') {
            $attributes[] = 'disabled="disabled"';
        }

        if ((string) $this->element['readonly'] === 'true') {
            $attributes[] = 'readonly="readonly"';
        }

        if ((string) $this->element['required'] === 'true') {
            $attributes[] = 'required="required"';
            $attributes[] = 'aria-required="true"';
        }

        $html   = [];
        $html[] = '<div class="dual-listbox-container" id="dual-listbox-container-' . $this->id . '">';
        $html[] = '<select ' . implode(' ', $attributes) . '>';

        foreach ($options as $option) {
            $selected = \in_array((string) $option->value, $selectedValues, true) ? ' selected="selected"' : '';
            $html[]   = '<option value="' . htmlspecialchars((string) $option->value, ENT_COMPAT, 'UTF-8') . '"' . $selected . '>';
            $html[]   = htmlspecialchars((string) $option->text, ENT_COMPAT, 'UTF-8');
            $html[]   = '</option>';
        }

        $html[] = '</select>';
        $html[] = '</div>';
        $html[] = $this->getInitScript($selectedValues);

        return implode('', $html);
    }

    protected function processValue($value): array
    {
        if (empty($value)) {
            return [];
        }

        if (\is_array($value)) {
            return array_filter($value);
        }

        if (\is_string($value)) {
            return array_filter(array_map('trim', explode(',', $value)));
        }

        return [];
    }

    protected function getInitScript(array $selected): string
    {
        $selectedJson        = json_encode($selected);
        $availableLabel      = Text::_('COM_J2COMMERCE_DUALLISTBOX_AVAILABLE');
        $selectedLabel       = Text::_('COM_J2COMMERCE_DUALLISTBOX_SELECTED');
        $searchPlaceholder   = Text::_('COM_J2COMMERCE_DUALLISTBOX_SEARCH');
        $addButtonText       = Text::_('COM_J2COMMERCE_DUALLISTBOX_BUTTON_ADD');
        $addAllButtonText    = Text::_('COM_J2COMMERCE_DUALLISTBOX_BUTTON_ADDALL');
        $removeButtonText    = Text::_('COM_J2COMMERCE_DUALLISTBOX_BUTTON_REMOVE');
        $removeAllButtonText = Text::_('COM_J2COMMERCE_DUALLISTBOX_BUTTON_REMOVEALL');

        return <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof DualListbox !== 'undefined') {
        const selectElement = document.getElementById('{$this->id}');
        if (selectElement) {
            const dualListbox = new DualListbox(selectElement, {
                availableTitle: '{$availableLabel}',
                selectedTitle: '{$selectedLabel}',
                searchPlaceholder: '{$searchPlaceholder}',
                addButtonText: '{$addButtonText}',
                addAllButtonText: '{$addAllButtonText}',
                removeButtonText: '{$removeButtonText}',
                removeAllButtonText: '{$removeAllButtonText}',
                showAddAllButton: true,
                showRemoveAllButton: true,
                showSearchFilter: true,
                moveOnSelect: false,
                sortable: false
            });
            const selected = {$selectedJson};
            if (selected && selected.length > 0) {
                selectElement.value = selected;
                dualListbox.redraw();
            }
        }
    } else {
        console.error('DualListbox library not loaded');
    }
});
</script>
JS;
    }
}
