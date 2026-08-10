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

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\GroupedlistField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;

/**
 * The filter values a product can carry, under the group that owns them.
 *
 * Grouped rather than flat because a value only means something inside its group — two groups
 * can both hold "Small" — and grouped because the same field serves the Products list filter
 * (multiple, fancy-select) and the batch control (single, plain select); `multiple` is the only
 * thing that differs between the two call sites.
 *
 * @since  6.5.1
 */
class ProductfilterField extends GroupedlistField
{
    protected $type = 'Productfilter';

    protected function getGroups(): array
    {
        // Options declared in the XML land under the integer key 0, which select.groupedlist
        // renders without an optgroup — which is what a leading "- Keep Current -" wants.
        $groups = parent::getGroups();

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['f.j2commerce_filter_id', 'f.filter_name', 'fg.group_name']))
            ->from($db->quoteName('#__j2commerce_filters', 'f'))
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_filtergroups', 'fg') . ' ON '
                . $db->quoteName('fg.j2commerce_filtergroup_id') . ' = ' . $db->quoteName('f.group_id')
            )
            ->where($db->quoteName('fg.enabled') . ' = 1')
            ->order([
                $db->quoteName('fg.ordering') . ' ASC',
                $db->quoteName('fg.group_name') . ' ASC',
                $db->quoteName('f.ordering') . ' ASC',
            ]);

        $db->setQuery($query);

        foreach ($db->loadObjectList() ?: [] as $row) {
            // Both grouped layouts render option text raw (option.text.toHtml => false), so a
            // name typed into the filter form is escaped here instead of at output.
            $groups[(string) $row->group_name][] = HTMLHelper::_(
                'select.option',
                (string) $row->j2commerce_filter_id,
                htmlspecialchars((string) $row->filter_name, ENT_QUOTES, 'UTF-8')
            );
        }

        return $groups;
    }
}
