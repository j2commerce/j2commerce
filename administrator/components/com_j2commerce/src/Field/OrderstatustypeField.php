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

use J2Commerce\Component\J2commerce\Administrator\Helper\OrderStatusHelper;
use Joomla\CMS\Form\Field\ListField;

/** Status Type select; multiple="true" switches to the fancy-select multi-pick. */
class OrderstatustypeField extends ListField
{
    protected $type = 'Orderstatustype';

    public function setup(\SimpleXMLElement $element, $value, $group = null)
    {
        $result = parent::setup($element, $value, $group);

        if ($result && $this->multiple && $this->layout === 'joomla.form.field.list') {
            $this->layout = 'joomla.form.field.list-fancy-select';
        }

        return $result;
    }

    public function getOptions(): array
    {
        return array_merge(parent::getOptions(), OrderStatusHelper::getTypeOptions(!$this->multiple));
    }
}
