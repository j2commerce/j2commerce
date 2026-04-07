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

use J2Commerce\Component\J2commerce\Administrator\Helper\CustomFieldHelper;
use Joomla\CMS\Form\FormField;

/**
 * Admin form field that renders the same telephone widget used on the frontend
 * (country flag dropdown + dial code + national number) so admin-saved values
 * stay consistent with frontend-edited values.
 *
 * XML usage:
 *   <field name="phone_1" type="phone" label="COM_J2COMMERCE_FIELD_PHONE_1" />
 *
 * Optional XML attributes:
 *   mode="all|selected|none"          default: all
 *   countries="US,GB,FR"              (comma-separated ISO2, used when mode=selected)
 *   default_iso="US"                  override the default country
 *   autocomplete="tel-national"
 *   placeholder="COM_J2COMMERCE_PHONE_NATIONAL_NUMBER"
 *
 * @since 6.1.5
 */
class PhoneField extends FormField
{
    protected $type = 'Phone';

    protected function getInput(): string
    {
        $mode = (string) ($this->element['mode'] ?? 'all');

        $allowedIso2   = null;
        $countriesAttr = (string) ($this->element['countries'] ?? '');
        if ($countriesAttr !== '') {
            $allowedIso2 = array_values(array_filter(array_map('trim', explode(',', $countriesAttr))));
        }

        $opts = [
            'required'     => (bool) $this->required,
            'autocomplete' => (string) ($this->element['autocomplete'] ?? 'tel-national'),
            'mode'         => $mode,
            'allowedIso2'  => $allowedIso2,
            'defaultIso'   => (string) ($this->element['default_iso'] ?? ''),
            'extraClass'   => (string) ($this->class ?? ''),
        ];

        $placeholder = (string) ($this->element['placeholder'] ?? '');
        if ($placeholder !== '') {
            $opts['placeholder'] = \Joomla\CMS\Language\Text::_($placeholder);
        }

        return CustomFieldHelper::renderPhoneWidget(
            (string) $this->value,
            $this->id,
            $this->name,
            $opts
        );
    }
}
