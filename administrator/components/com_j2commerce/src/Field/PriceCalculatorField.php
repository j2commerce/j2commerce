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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * PriceCalculator field - provides a dropdown of available pricing calculators.
 *
 * Pricing calculators are loaded from plugins via the ProductHelper::getPricingCalculators()
 * method, which fires the onJ2CommerceGetPricingCalculators event to collect calculators
 * from enabled J2Commerce plugins.
 *
 * @since  6.0.7
 */
class PriceCalculatorField extends ListField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  6.0.7
     */
    protected $type = 'PriceCalculator';

    /**
     * Method to get the field options.
     *
     * @return  array  The field option objects.
     *
     * @since   6.0.7
     */
    public function getOptions(): array
    {
        $options = parent::getOptions();

        try {
            // Load pricing calculators from the ProductHelper
            // Returns associative array: ['calculator_key' => 'Calculator Label', ...]
            $calculators = J2CommerceHelper::product()->getPricingCalculators();

            foreach ($calculators as $value => $text) {
                $options[] = HTMLHelper::_('select.option', $value, $text);
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_J2COMMERCE_ERROR_LOADING_PRICING_CALCULATORS', $e->getMessage()),
                'error'
            );
        }

        return $options;
    }
}
