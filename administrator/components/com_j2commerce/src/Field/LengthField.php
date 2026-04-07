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
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * Length field - provides a dropdown of enabled length classes from the database.
 *
 * @since  6.0.7
 */
class LengthField extends ListField
{
    protected $type = 'Length';

    protected string $filterUnits = '';

    public function setup(\SimpleXMLElement $element, $value, $group = null): bool
    {
        $result = parent::setup($element, $value, $group);

        if ($result) {
            $this->filterUnits = (string) ($this->element['filter_units'] ?? '');
        }

        return $result;
    }

    public function getOptions(): array
    {
        $options = parent::getOptions();

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('j2commerce_length_id', 'value'),
                    $db->quoteName('length_title', 'text'),
                ])
                ->from($db->quoteName('#__j2commerce_lengths'))
                ->where($db->quoteName('enabled') . ' = 1')
                ->order($db->quoteName('length_title') . ' ASC');

            // Apply unit filter if specified (e.g. filter_units="in,cm")
            if ($this->filterUnits !== '') {
                $units  = array_map('trim', explode(',', $this->filterUnits));
                $quoted = array_map([$db, 'quote'], $units);
                $query->where($db->quoteName('length_unit') . ' IN (' . implode(',', $quoted) . ')');
            }

            $db->setQuery($query);
            $lengths = $db->loadObjectList();

            if ($lengths) {
                foreach ($lengths as $length) {
                    $options[] = HTMLHelper::_('select.option', $length->value, $length->text);
                }
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_J2COMMERCE_ERROR_LOADING_LENGTHS', $e->getMessage()),
                'error'
            );
        }

        return $options;
    }
}
