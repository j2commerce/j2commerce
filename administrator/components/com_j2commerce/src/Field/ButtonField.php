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

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

/**
 * Button field - renders a button element for UI interaction.
 *
 * Supports onclick handlers and custom CSS classes.
 * This is purely a UI element - no hidden input is rendered.
 *
 * @since  6.0.7
 */
class ButtonField extends FormField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  6.0.7
     */
    protected $type = 'Button';

    /**
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     *
     * @since   6.0.7
     */
    protected function getInput(): string
    {
        // Get attributes from XML
        $label   = (string) $this->element['label'] ?: '';
        $class   = (string) $this->element['class'] ?: 'btn btn-primary';
        $onclick = (string) $this->element['onclick'] ?: '';

        // Translate the label if it's a language constant
        $buttonText = Text::_($label);

        // Build button attributes
        $buttonId      = htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8');
        $buttonClass   = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
        $buttonOnclick = htmlspecialchars($onclick, ENT_QUOTES, 'UTF-8');

        // Build the button HTML
        $html = '<button type="button" id="' . $buttonId . '" class="' . $buttonClass . '"';

        if (!empty($onclick)) {
            $html .= ' onclick="' . $buttonOnclick . '"';
        }

        $html .= '>' . htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8') . '</button>';

        return $html;
    }
}
