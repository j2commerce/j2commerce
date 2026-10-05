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

use J2Commerce\Component\J2commerce\Administrator\Helper\FilterSourceHelper;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * Where a filter group's values come from: J2Commerce (entered by hand) or a registered plugin
 * source. A stored source whose plugin is gone stays selectable, labelled as missing, so saving
 * the form never silently moves the group to another source.
 *
 * @since  6.6.4
 */
class FiltersourceField extends ListField
{
    protected $type = 'Filtersource';

    protected function getOptions(): array
    {
        $options = parent::getOptions();
        $sources = FilterSourceHelper::sources();
        $current = (string) $this->value;

        if ($current !== '' && !isset($sources[$current])) {
            $sources[$current] = FilterSourceHelper::label($current);
        }

        foreach ($sources as $key => $label) {
            $options[] = HTMLHelper::_('select.option', $key, $label);
        }

        return $options;
    }
}
