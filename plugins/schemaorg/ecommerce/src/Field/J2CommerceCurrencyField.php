<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Schemaorg.ecommerce
 *
 * @copyright   (C) 2024–2026 J2Commerce, LLC All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Schemaorg\Ecommerce\Field;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Custom form field to display J2Commerce currency configuration.
 *
 *
 * @since  6.0.0
 */
class J2CommerceCurrencyField extends FormField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $type = 'J2CommerceCurrency';

    /**
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     *
     * @since   6.0.0
     */
    protected function getInput(): string
    {

        $currency = $this->getJ2CommerceCurrency();

        if ($currency === null) {
            return '<div class="alert alert-warning">'
                . Text::_('PLG_SCHEMAORG_ECOMMERCE_J2COMMERCE_NOT_CONFIGURED')
                . '</div>';
        }

        // Build the readonly input field
        $html = '<input type="text" '
            . 'id="' . $this->id . '" '
            . 'name="' . $this->name . '" '
            . 'value="' . htmlspecialchars($currency, ENT_COMPAT, 'UTF-8') . '" '
            . 'class="form-control" '
            . 'readonly="readonly" '
            . 'disabled="disabled" '
            . '/>';
        return $html;
    }

    /**
     * Get the currency code from J2Commerce configuration.
     *
     * @return  string|null  The currency code or null if J2Commerce is not available.
     *
     * @since   6.0.0
     */
    protected function getJ2CommerceCurrency(): ?string
    {
        if (!$this->isJ2CommerceAvailable()) {
            return null;
        }

        try {
            if ($this->isJ2CommerceAvailable()) {
                $params          = ComponentHelper::getParams('com_j2commerce');
                $defaultCurrency = $params->get('config_currency', 'USD');

                return $defaultCurrency;
            }

            return 'USD';
        } catch (\Exception $e) {
            return 'USD';
        }
    }

    /**
     * Check if J2Commerce is installed and enabled.
     *
     * @return  boolean  True if J2Commerce is available.
     *
     * @since   6.0.0
     */
    protected function isJ2CommerceAvailable(): bool
    {
        // Check if J2Commerce class exists
        if (ComponentHelper::isEnabled('com_j2commerce')) {
            return true;
        }

        return false;
    }
}
