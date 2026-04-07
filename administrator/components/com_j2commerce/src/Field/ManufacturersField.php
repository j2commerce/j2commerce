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
 * Manufacturers field - provides a dropdown of enabled manufacturers from the database.
 *
 * @since  6.0.7
 */
class ManufacturersField extends ListField
{
    protected $type = 'Manufacturers';

    public function getOptions(): array
    {
        $options = parent::getOptions();

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('m.j2commerce_manufacturer_id', 'value'),
                    $db->quoteName('a.company', 'text'),
                ])
                ->from($db->quoteName('#__j2commerce_manufacturers', 'm'))
                ->join('INNER', $db->quoteName('#__j2commerce_addresses', 'a') . ' ON ' . $db->quoteName('m.address_id') . ' = ' . $db->quoteName('a.j2commerce_address_id'))
                ->where($db->quoteName('m.enabled') . ' = 1')
                ->order($db->quoteName('a.company') . ' ASC');

            $db->setQuery($query);
            $manufacturers = $db->loadObjectList();

            if ($manufacturers) {
                foreach ($manufacturers as $manufacturer) {
                    $text = trim((string) $manufacturer->text);
                    if (empty($text)) {
                        $text = Text::_('COM_J2COMMERCE_MANUFACTURER_UNNAMED');
                    }
                    $options[] = HTMLHelper::_('select.option', $manufacturer->value, $text);
                }
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_J2COMMERCE_ERROR_LOADING_MANUFACTURERS', $e->getMessage()),
                'error'
            );
        }

        return $options;
    }
}
