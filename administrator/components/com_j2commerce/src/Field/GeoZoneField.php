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
 * GeoZone field - provides a dropdown of enabled geozones from the database.
 *
 * @since  6.0.7
 */
class GeoZoneField extends ListField
{
    protected $type = 'GeoZone';

    public function getOptions(): array
    {
        $options = parent::getOptions();

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('j2commerce_geozone_id', 'value'),
                    $db->quoteName('geozone_name', 'text'),
                ])
                ->from($db->quoteName('#__j2commerce_geozones'))
                ->where($db->quoteName('enabled') . ' = 1')
                ->order($db->quoteName('geozone_name') . ' ASC');

            $db->setQuery($query);
            $geozones = $db->loadObjectList();

            if ($geozones) {
                foreach ($geozones as $geozone) {
                    $options[] = HTMLHelper::_('select.option', $geozone->value, $geozone->text);
                }
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_J2COMMERCE_ERROR_LOADING_GEOZONES', $e->getMessage()),
                'error'
            );
        }

        return $options;
    }
}
