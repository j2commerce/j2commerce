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

use Joomla\CMS\Form\Field\CategoryField;

class RouterCategoryField extends CategoryField
{
    public $type = 'RouterCategory';

    public function __get($name)
    {
        if ($name === 'multiple') {
            return false;
        }

        return parent::__get($name);
    }

    public function setup(\SimpleXMLElement $element, $value, $group = null): bool
    {
        $element['multiple'] = 'false';

        return parent::setup($element, $value, $group);
    }
}
