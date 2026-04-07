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

use J2Commerce\Component\J2commerce\Administrator\Helper\CategoryHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Form\Field\SpacerField;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/**
 * Custom field type to render J2Commerce category settings accordion.
 *
 * @since  6.1.0
 */
class Categoryj2commerceField extends SpacerField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  6.1.0
     */
    protected $type = 'Categoryj2commerce';

    /**
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     *
     * @since   6.1.0
     */
    protected function getInput()
    {
        return $this->getLabel();
    }

    /**
     * Method to get the field label markup.
     *
     * @return  string  The field label markup.
     *
     * @since   6.1.0
     */
    protected function getLabel()
    {
        // Get the category data from the form
        $category = $this->form->getData() ?? new \stdClass();

        // Form prefix for field names
        $formPrefix = 'jform[params][j2commerce]';

        // Gather all J2Commerce category app data from plugins
        $pluginApps = J2CommerceHelper::plugin()->eventWithAppData(
            'AfterDisplayCategoryForm',
            [$this, $category, $formPrefix]
        );

        // Get core J2Commerce category settings
        $coreApps = CategoryHelper::getCoreCategoryFormData($category, $formPrefix);

        // Merge core apps with plugin apps
        $apps = array_merge($coreApps, $pluginApps);

        // Sort apps by ordering
        usort($apps, function ($a, $b) {
            return ($a['ordering'] ?? 100) <=> ($b['ordering'] ?? 100);
        });

        if (empty($apps)) {
            return '<div class="alert alert-info">' . Text::_('COM_J2COMMERCE_CATEGORY_TAB_NO_APPS') . '</div>';
        }

        // Render the accordion
        $html   = [];
        $html[] = '<div class="accordion" id="j2commerce-category-accordion">';

        foreach ($apps as $index => $app) {
            $element     = $app['element'] ?? '';
            $nameKey     = $app['name'] ?? 'PLG_J2COMMERCE_' . strtoupper($element);
            $description = $app['description'] ?? ($nameKey . '_DESCRIPTION');
            $imagePath   = $app['image'] ?? J2CommerceHelper::getAppImagePath($element);
            $collapseId  = 'category-app-collapse-' . htmlspecialchars($element, ENT_QUOTES, 'UTF-8');
            $headingId   = 'category-heading-' . htmlspecialchars($element, ENT_QUOTES, 'UTF-8');

            // First accordion item is open by default for better UX
            $isFirst       = $index === 0;
            $buttonClass   = $isFirst ? 'accordion-button' : 'accordion-button collapsed';
            $collapseClass = $isFirst ? 'accordion-collapse collapse show' : 'accordion-collapse collapse';
            $ariaExpanded  = $isFirst ? 'true' : 'false';

            $html[] = '<div class="accordion-item">';
            $html[] = '    <h2 class="accordion-header" id="' . $headingId . '">';
            $html[] = '        <button class="' . $buttonClass . '"';
            $html[] = '                type="button"';
            $html[] = '                data-bs-toggle="collapse"';
            $html[] = '                data-bs-target="#' . $collapseId . '"';
            $html[] = '                aria-expanded="' . $ariaExpanded . '"';
            $html[] = '                aria-controls="' . $collapseId . '">';
            $html[] = '            <div class="d-block d-lg-flex align-items-center">';
            $html[] = '                <div class="flex-shrink-0">';
            $html[] = '                    <span class="d-none d-lg-inline-block d-md-block">';
            $html[] = '                        <img src="' . htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') . '"';
            $html[] = '                             alt="' . htmlspecialchars(Text::_($nameKey), ENT_QUOTES, 'UTF-8') . '"';
            $html[] = '                             class="me-2 img-fluid j2commerce-app-image"';
            $html[] = '                        >';
            $html[] = '                    </span>';
            $html[] = '                </div>';
            $html[] = '                <div class="flex-grow-1 ms-lg-3 mt-0 mt-lg-0">';
            $html[] = '                    <div>' . htmlspecialchars(Text::_($nameKey), ENT_QUOTES, 'UTF-8') . '</div>';
            $html[] = '                    <div class="small d-none d-md-block text-muted">';
            $html[] = '                        ' . htmlspecialchars(Text::_($description), ENT_QUOTES, 'UTF-8');
            $html[] = '                    </div>';
            $html[] = '                </div>';
            $html[] = '            </div>';
            $html[] = '        </button>';
            $html[] = '    </h2>';
            $html[] = '    <div id="' . $collapseId . '"';
            $html[] = '         class="' . $collapseClass . '"';
            $html[] = '         aria-labelledby="' . $headingId . '"';
            $html[] = '         data-bs-parent="#j2commerce-category-accordion">';
            $html[] = '        <div class="accordion-body">';

            // Render form or HTML
            if (!empty($app['form_xml']) && file_exists($app['form_xml'])) {
                $form = \Joomla\CMS\Form\Form::getInstance(
                    'j2commerce.category.' . $element,
                    $app['form_xml'],
                    ['control' => $formPrefix . '[params]']
                );

                if ($form) {
                    if (!empty($app['data'])) {
                        $form->bind($app['data']);
                    }
                    $html[] = $form->renderFieldset('basic');
                }
            } elseif (!empty($app['html'])) {
                $html[] = $app['html'];
            }

            $html[] = '        </div>';
            $html[] = '    </div>';
            $html[] = '</div>';
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }
}
