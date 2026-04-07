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
 * Manufacturer Country field - provides a dropdown of countries that have manufacturers.
 *
 * Only shows countries that are linked to at least one manufacturer via their address.
 *
 * @since  6.0.6
 */
class ManufacturerCountryField extends ListField
{
    protected $type = 'ManufacturerCountry';

    public function getOptions(): array
    {
        $options = parent::getOptions();

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            // Get distinct countries that have manufacturers via their addresses
            $query = $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('c.j2commerce_country_id', 'value'))
                ->select($db->quoteName('c.country_name', 'text'))
                ->from($db->quoteName('#__j2commerce_manufacturers', 'm'))
                ->join(
                    'INNER',
                    $db->quoteName('#__j2commerce_addresses', 'a'),
                    $db->quoteName('a.j2commerce_address_id') . ' = ' . $db->quoteName('m.address_id')
                )
                ->join(
                    'INNER',
                    $db->quoteName('#__j2commerce_countries', 'c'),
                    $db->quoteName('c.j2commerce_country_id') . ' = ' . $db->quoteName('a.country_id')
                )
                ->where($db->quoteName('c.enabled') . ' = 1')
                ->where($db->quoteName('a.country_id') . ' IS NOT NULL')
                ->where($db->quoteName('a.country_id') . ' != ' . $db->quote(''))
                ->order($db->quoteName('c.country_name') . ' ASC');

            $db->setQuery($query);
            $countries = $db->loadObjectList();

            if ($countries) {
                foreach ($countries as $country) {
                    $options[] = HTMLHelper::_('select.option', $country->value, $country->text);
                }
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_J2COMMERCE_ERROR_LOADING_COUNTRIES', $e->getMessage()),
                'error'
            );
        }

        return $options;
    }
}
