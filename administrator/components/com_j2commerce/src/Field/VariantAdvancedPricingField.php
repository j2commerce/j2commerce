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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/**
 * Variant Advanced Pricing field - displays a button that opens the pricing modal.
 *
 * This field renders a "Set Additional Pricing" button that opens a Bootstrap modal
 * containing the product pricing iframe, similar to form_pricing.php.
 *
 * @since  6.0.0
 */
class VariantAdvancedPricingField extends FormField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $type = 'VariantAdvancedPricing';

    /**
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     *
     * @since   6.0.0
     */
    protected function getInput(): string
    {
        // Extract variant_id from field name pattern: ...variable][123][advanced_pricing]
        $variantId = 0;
        if (preg_match('/\[variable\]\[(\d+)\]/', $this->name, $matches)) {
            $variantId = (int) $matches[1];
        }

        // If no variant ID (new variant), show disabled button
        if ($variantId === 0) {
            return '<button type="button" class="btn btn-secondary btn-sm" disabled title="'
                . htmlspecialchars(Text::_('COM_J2COMMERCE_SAVE_VARIANT_FIRST'), ENT_COMPAT, 'UTF-8')
                . '">'
                . htmlspecialchars(Text::_('COM_J2COMMERCE_PRODUCT_ADDITIONAL_PRICING'), ENT_COMPAT, 'UTF-8')
                . '</button>';
        }

        // Build modal URL
        $basePath = rtrim(Uri::root(), '/') . '/administrator/';
        $modalUrl = $basePath . 'index.php?option=com_j2commerce&view=productprice&layout=productpricing&variant_id='
            . $variantId . '&tmpl=component';

        // Unique modal ID per variant
        $modalId = 'variantPriceModal_' . $variantId;

        // Build button HTML
        $buttonHtml = '<a href="' . htmlspecialchars($modalUrl, ENT_COMPAT, 'UTF-8') . '" '
            . 'class="btn btn-primary btn-sm" '
            . 'rel="noopener noreferrer" '
            . 'data-bs-toggle="modal" '
            . 'data-bs-target="#' . $modalId . '">'
            . htmlspecialchars(Text::_('COM_J2COMMERCE_PRODUCT_ADDITIONAL_PRICING'), ENT_COMPAT, 'UTF-8')
            . '</a>';

        // Build modal HTML using Joomla's Bootstrap helper
        $modalHtml = HTMLHelper::_(
            'bootstrap.renderModal',
            $modalId,
            [
                'url'        => $modalUrl,
                'title'      => Text::_('COM_J2COMMERCE_PRODUCT_ADDITIONAL_PRICING'),
                'height'     => '100%',
                'width'      => '100%',
                'modalWidth' => '95%',
                'bodyHeight' => '95%',
                'footer'     => '<button type="button" class="btn btn-primary" data-bs-dismiss="modal" aria-hidden="true">'
                    . Text::_('JLIB_HTML_BEHAVIOR_CLOSE') . '</button>',
            ]
        );

        return $buttonHtml . $modalHtml;
    }

    /**
     * Method to get the field label markup.
     *
     * @return  string  The field label markup.
     *
     * @since   6.0.0
     */
    protected function getLabel(): string
    {
        // Return standard label
        return parent::getLabel();
    }
}
