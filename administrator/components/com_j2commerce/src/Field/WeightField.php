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
 * Weight field - provides a dropdown of enabled weight classes from the database.
 *
 * @since  6.0.7
 */
class WeightField extends ListField
{
    protected $type = 'Weight';

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
                    $db->quoteName('j2commerce_weight_id', 'value'),
                    $db->quoteName('weight_title', 'text'),
                ])
                ->from($db->quoteName('#__j2commerce_weights'))
                ->where($db->quoteName('enabled') . ' = 1')
                ->order($db->quoteName('weight_title') . ' ASC');

            // Apply unit filter if specified (e.g. filter_units="lb,kg")
            if ($this->filterUnits !== '') {
                $units  = array_map('trim', explode(',', $this->filterUnits));
                $quoted = array_map([$db, 'quote'], $units);
                $query->where($db->quoteName('weight_unit') . ' IN (' . implode(',', $quoted) . ')');
            }

            $db->setQuery($query);
            $weights = $db->loadObjectList();

            if ($weights) {
                foreach ($weights as $weight) {
                    $options[] = HTMLHelper::_('select.option', $weight->value, $weight->text);
                }
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_J2COMMERCE_ERROR_LOADING_WEIGHTS', $e->getMessage()),
                'error'
            );
        }

        return $options;
    }
}
