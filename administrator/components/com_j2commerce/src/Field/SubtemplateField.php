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

use J2Commerce\Component\J2commerce\Administrator\Service\OverrideRegistry;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

class SubtemplateField extends ListField
{
    protected $type = 'Subtemplate';

    public function getOptions(): array
    {
        $options  = parent::getOptions();
        $excluded = $this->getExcludeList();

        if ($this->element['show_auto'] && !\in_array('auto', $excluded, true)) {
            $auto        = new \stdClass();
            $auto->value = 'auto';
            $auto->text  = Text::_('COM_J2COMMERCE_SUBTEMPLATE_AUTO');
            array_unshift($options, $auto);
        }

        $subtemplates = $this->getEnabledSubtemplates();

        foreach ($subtemplates as $sub) {
            if (\in_array($sub['element'], $excluded, true)) {
                continue;
            }

            $option = HTMLHelper::_('select.option', $sub['element'], $sub['translatedName']);

            if (!empty($sub['imagePath'])) {
                $option->image = $sub['imagePath'];
            }

            $options[] = $option;
        }

        return $options;
    }

    protected function getInput(): string
    {
        $subtemplates = $this->getEnabledSubtemplates();
        $excluded     = $this->getExcludeList();
        $hasImages    = false;

        foreach ($subtemplates as $sub) {
            if (\in_array($sub['element'], $excluded, true)) {
                continue;
            }

            if (!empty($sub['imagePath'])) {
                $hasImages = true;
                break;
            }
        }

        if (!$hasImages) {
            return parent::getInput();
        }

        $html = '<div class="j2commerce-subtemplate-field">';
        $html .= '<input type="hidden" name="' . $this->name . '" id="' . $this->id . '" value="' . htmlspecialchars((string) $this->value, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<div class="d-flex flex-wrap gap-3">';

        if ($this->element['show_auto'] && !\in_array('auto', $excluded, true)) {
            $isAutoSelected = ($this->value === 'auto' || $this->value === '');
            $autoClass      = $isAutoSelected ? ' border-primary' : '';
            $autoLabel      = htmlspecialchars(Text::_('COM_J2COMMERCE_SUBTEMPLATE_AUTO'), ENT_QUOTES, 'UTF-8');
            $html .= '<div class="j2commerce-subtemplate-option card text-center bg-body box-shadow-none border rounded-1 border-2' . $autoClass . '" '
                . 'data-value="auto" '
                . 'style="cursor:pointer;width:160px;transition:border-color 0.2s;" '
                . 'onclick="document.getElementById(\'' . $this->id . '\').value=\'auto\';'
                . 'this.parentNode.querySelectorAll(\'.card\').forEach(function(c){c.classList.remove(\'border-primary\',\'bg-light\');c.classList.add(\'border-secondary-subtle\');});'
                . 'this.classList.add(\'border-primary\',\'bg-light\');this.classList.remove(\'border-secondary-subtle\');">'
                . '<div class="card-body p-2 d-flex align-items-center justify-content-center" style="min-height:90px;">'
                . '<i class="fa-solid fa-wand-magic-sparkles fa-2x text-muted"></i>'
                . '</div>'
                . '<div class="card-body p-2">'
                . '<small class="fw-semibold lh-1 d-block">' . $autoLabel . '</small>'
                . '</div></div>';
        }

        foreach ($subtemplates as $sub) {
            if (\in_array($sub['element'], $excluded, true)) {
                continue;
            }

            $element       = htmlspecialchars($sub['element'], ENT_QUOTES, 'UTF-8');
            $name          = htmlspecialchars($sub['translatedName'], ENT_QUOTES, 'UTF-8');
            $isSelected    = ($this->value === $sub['element']);
            $selectedClass = $isSelected ? ' border-primary' : '';
            $thumbPath     = !empty($sub['imagePath'])
                ? str_replace($sub['element'] . '.webp', $sub['element'] . '_thumb.webp', $sub['imagePath'])
                : '';
            $imageSrc = $thumbPath ?: ($sub['imagePath'] ?? '');

            $html .= '<div class="j2commerce-subtemplate-option card text-center bg-body box-shadow-none border rounded-1 border-2' . $selectedClass . '" '
                . 'data-value="' . $element . '" '
                . 'style="cursor:pointer;width:160px;transition:border-color 0.2s;" '
                . 'onclick="document.getElementById(\'' . $this->id . '\').value=\'' . $element . '\';'
                . 'this.parentNode.querySelectorAll(\'.card\').forEach(function(c){c.classList.remove(\'border-primary\',\'bg-light\');c.classList.add(\'border-secondary-subtle\');});'
                . 'this.classList.add(\'border-primary\',\'bg-light\');this.classList.remove(\'border-secondary-subtle\');">';

            if (!empty($imageSrc)) {
                $html .= '<img src="' . htmlspecialchars($imageSrc, ENT_QUOTES, 'UTF-8') . '" '
                    . 'alt="' . $name . '" class="card-img-top p-2" style="max-height:90px;object-fit:contain;">';
            }

            $html .= '<div class="card-body p-2">'
                . '<small class="fw-semibold lh-1 d-block">' . $name . '</small>'
                . '</div>';
            $html .= '</div>';
        }

        $html .= '</div></div>';

        return $html;
    }

    private function getExcludeList(): array
    {
        $raw = (string) ($this->element['exclude'] ?? '');

        if ($raw === '') {
            return [];
        }

        return array_map('trim', explode(',', $raw));
    }

    private function getEnabledSubtemplates(): array
    {
        $subtemplates = OverrideRegistry::getInstalledSubtemplates();
        $lang         = Factory::getApplication()->getLanguage();
        $result       = [];

        foreach ($subtemplates as $sub) {
            if (!$sub['enabled']) {
                continue;
            }

            // Load the plugin's sys.ini so 3rd-party names are translated automatically
            $lang->load('plg_j2commerce_' . $sub['element'], JPATH_PLUGINS . '/j2commerce/' . $sub['element']);
            $lang->load('plg_j2commerce_' . $sub['element'], JPATH_ADMINISTRATOR);

            $sub['translatedName'] = Text::_($sub['name']);
            $result[]              = $sub;
        }

        return $result;
    }
}
