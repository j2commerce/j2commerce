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

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use Joomla\CMS\Form\FormField;

/**
 * Variant Price field - displays a price input with currency symbol prefix.
 *
 * This field renders an input-group with the store's currency symbol
 * prepended to the price input field, matching the main product pricing UI.
 *
 * @since  6.0.0
 */
class VariantPriceField extends FormField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $type = 'VariantPrice';

    /**
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     *
     * @since   6.0.0
     */
    protected function getInput(): string
    {
        // Get currency symbol from helper
        $currencyHelper = new CurrencyHelper();
        $currencySymbol = $currencyHelper->getSymbol();

        // Format value to match the currency's configured decimal places
        $decimals = CurrencyHelper::getDecimalPlace();
        $value    = number_format((float) ($this->value ?? 0), $decimals, '.', '');

        // Build attributes
        $attributes = [];
        $attributes[] = 'type="text"';
        $attributes[] = 'name="' . htmlspecialchars($this->name, ENT_COMPAT, 'UTF-8') . '"';
        $attributes[] = 'id="' . htmlspecialchars($this->id, ENT_COMPAT, 'UTF-8') . '"';
        $attributes[] = 'value="' . htmlspecialchars($value, ENT_COMPAT, 'UTF-8') . '"';
        $attributes[] = 'class="form-control' . ($this->class ? ' ' . htmlspecialchars($this->class, ENT_COMPAT, 'UTF-8') : '') . '"';
        $attributes[] = 'inputmode="decimal"';

        if ($this->readonly) {
            $attributes[] = 'readonly';
        }

        if ($this->disabled) {
            $attributes[] = 'disabled';
        }

        if ($this->required) {
            $attributes[] = 'required';
        }

        if ($this->hint) {
            $attributes[] = 'placeholder="' . htmlspecialchars($this->hint, ENT_COMPAT, 'UTF-8') . '"';
        }

        // Build the input-group HTML
        $html = '<div class="input-group">';
        $html .= '<span class="input-group-text">' . htmlspecialchars($currencySymbol, ENT_COMPAT, 'UTF-8') . '</span>';
        $html .= '<input ' . implode(' ', $attributes) . '>';
        $html .= '</div>';

        return $html;
    }
}
