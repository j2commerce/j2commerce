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

/**
 * Lifecycle classification select. Options come from OrderStatusHelper so the edit form, the
 * list mapping screen and the Table validation all read one declaration of the value set.
 */
class OrderstatustypeField extends ListField
{
    protected $type = 'Orderstatustype';

    public function getOptions(): array
    {
        return array_merge(parent::getOptions(), OrderStatusHelper::getTypeOptions());
    }
}
