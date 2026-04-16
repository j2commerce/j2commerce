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
 * TemplateCustomField - displays HTML information about custom field shortcodes.
 *
 * @since  6.0.7
 */
class TemplateCustomField extends FormField
{
    protected $type = 'TemplateCustom';

    protected function getInput(): string
    {
        $html = [];

        $html[] = '<div class="template-custom-field-info py-5">';

        // Alert box for general information
        $html[] = '<div class="alert alert-info mb-4">';
        $html[] = '<h4 class="alert-heading">' . Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_TITLE') . '</h4>';
        $html[] = '<p class="mb-0">' . Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_DESCRIPTION') . '</p>';
        $html[] = '</div>';

        // Billing Custom Fields Section
        $html[] = '<fieldset class="adminform mb-4">';
        $html[] = '<legend class="text-primary">' . Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_BILLING_TITLE') . '</legend>';
        $html[] = '<p class="text-muted mb-3">' . Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_BILLING_DESCRIPTION') . '</p>';

        $html[] = '<div class="table-responsive">';
        $html[] = '<table class="table">';
        $html[] = '<thead>';
        $html[] = '<tr>';
        $html[] = '<th scope="col">' . Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_SHORTCODE') . '</th>';
        $html[] = '<th scope="col">' . Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_DESCRIPTION') . '</th>';
        $html[] = '<th scope="col">' . Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_EXAMPLE') . '</th>';
        $html[] = '</tr>';
        $html[] = '</thead>';
        $html[] = '<tbody>';

        $billingFields = [
            'company'     => Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_BILLING_COMPANY'),
            'tax_number'  => Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_BILLING_TAX_NUMBER'),
            'custom_note' => Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_BILLING_CUSTOM_NOTE'),
        ];

        foreach ($billingFields as $fieldName => $description) {
            $html[] = '<tr>';
            $html[] = '<td><code>[CUSTOM_BILLING_FIELD:' . strtoupper($fieldName) . ']</code></td>';
            $html[] = '<td>' . $description . '</td>';
            $html[] = '<td><small class="text-muted">' . Text::sprintf('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_EXAMPLE_OUTPUT', $fieldName) . '</small></td>';
            $html[] = '</tr>';
        }

        $html[] = '</tbody>';
        $html[] = '</table>';
        $html[] = '</div>';
        $html[] = '</fieldset>';

        // Shipping Custom Fields Section
        $html[] = '<fieldset class="adminform mb-4">';
        $html[] = '<legend class="text-success">' . Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_SHIPPING_TITLE') . '</legend>';
        $html[] = '<p class="text-muted mb-3">' . Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_SHIPPING_DESCRIPTION') . '</p>';

        $html[] = '<div class="table-responsive">';
        $html[] = '<table class="table table-striped table-hover">';
        $html[] = '<thead>';
        $html[] = '<tr>';
        $html[] = '<th scope="col">' . Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_SHORTCODE') . '</th>';
        $html[] = '<th scope="col">' . Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_DESCRIPTION') . '</th>';
        $html[] = '<th scope="col">' . Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_EXAMPLE') . '</th>';
        $html[] = '</tr>';
        $html[] = '</thead>';
        $html[] = '<tbody>';

        $shippingFields = [
            'delivery_instructions' => Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_SHIPPING_DELIVERY_INSTRUCTIONS'),
            'special_handling'      => Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_SHIPPING_SPECIAL_HANDLING'),
            'preferred_time'        => Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_SHIPPING_PREFERRED_TIME'),
        ];

        foreach ($shippingFields as $fieldName => $description) {
            $html[] = '<tr>';
            $html[] = '<td><code>[CUSTOM_SHIPPING_FIELD:' . strtoupper($fieldName) . ']</code></td>';
            $html[] = '<td>' . $description . '</td>';
            $html[] = '<td><small class="text-muted">' . Text::sprintf('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_EXAMPLE_OUTPUT', $fieldName) . '</small></td>';
            $html[] = '</tr>';
        }

        $html[] = '</tbody>';
        $html[] = '</table>';
        $html[] = '</div>';
        $html[] = '</fieldset>';

        // Payment Custom Fields Section
        $html[] = '<fieldset class="adminform mb-4">';
        $html[] = '<legend class="text-warning">' . Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_PAYMENT_TITLE') . '</legend>';
        $html[] = '<p class="text-muted mb-3">' . Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_PAYMENT_DESCRIPTION') . '</p>';

        $html[] = '<div class="table-responsive">';
        $html[] = '<table class="table">';
        $html[] = '<thead>';
        $html[] = '<tr>';
        $html[] = '<th scope="col">' . Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_SHORTCODE') . '</th>';
        $html[] = '<th scope="col">' . Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_DESCRIPTION') . '</th>';
        $html[] = '<th scope="col">' . Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_EXAMPLE') . '</th>';
        $html[] = '</tr>';
        $html[] = '</thead>';
        $html[] = '<tbody>';

        $paymentFields = [
            'po_number'         => Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_PAYMENT_PO_NUMBER'),
            'payment_reference' => Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_PAYMENT_REFERENCE'),
            'billing_contact'   => Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_PAYMENT_BILLING_CONTACT'),
        ];

        foreach ($paymentFields as $fieldName => $description) {
            $html[] = '<tr>';
            $html[] = '<td><code>[CUSTOM_PAYMENT_FIELD:' . strtoupper($fieldName) . ']</code></td>';
            $html[] = '<td>' . $description . '</td>';
            $html[] = '<td><small class="text-muted">' . Text::sprintf('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_EXAMPLE_OUTPUT', $fieldName) . '</small></td>';
            $html[] = '</tr>';
        }

        $html[] = '</tbody>';
        $html[] = '</table>';
        $html[] = '</div>';
        $html[] = '</fieldset>';

        // Usage notes section
        $html[] = '<div class="alert alert-warning">';
        $html[] = '<h5>' . Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_USAGE_TITLE') . '</h5>';
        $html[] = '<ul class="mb-0">';
        $html[] = '<li>' . Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_USAGE_NOTE_1') . '</li>';
        $html[] = '<li>' . Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_USAGE_NOTE_2') . '</li>';
        $html[] = '<li>' . Text::_('COM_J2COMMERCE_TEMPLATE_CUSTOM_FIELD_USAGE_NOTE_3') . '</li>';
        $html[] = '</ul>';
        $html[] = '</div>';

        $html[] = '</div>';

        return implode("\n", $html);
    }

    protected function getLabel(): string
    {
        return '';
    }
}
