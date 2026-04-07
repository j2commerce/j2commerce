<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\Database\ParameterType;

/**
 * Manufacturer item controller class.
 *
 * Handles single-item operations: edit, save, apply, cancel.
 * For bulk operations (publish, unpublish, delete, batch), see ManufacturersController.
 *
 * @since  6.0.6
 */
class ManufacturerController extends FormController
{
    /**
     * The URL option for the component.
     *
     * @var    string
     * @since  6.0.6
     */
    protected $option = 'com_j2commerce';

    /**
     * The URL view item variable.
     *
     * @var    string
     * @since  6.0.6
     */
    protected $view_item = 'manufacturer';

    /**
     * The URL view list variable.
     *
     * @var    string
     * @since  6.0.6
     */
    protected $view_list = 'manufacturers';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.6
     */
    protected $text_prefix = 'COM_J2COMMERCE_MANUFACTURER';

    /**
     * Method to edit an existing record.
     *
     * CRITICAL: We must explicitly set $urlVar to 'id' because Joomla's FormController
     * defaults to using the Table's primary key name (j2commerce_manufacturer_id) as the URL
     * parameter. Since our URLs use 'id' (standard Joomla convention), we override here.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable for the id.
     *
     * @return  boolean  True if access level check passes, false otherwise.
     *
     * @since   6.0.6
     */
    public function edit($key = null, $urlVar = 'id')
    {
        return parent::edit($key, $urlVar);
    }

    /**
     * Method to save a record.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable for the id.
     *
     * @return  boolean  True if successful, false otherwise.
     *
     * @since   6.0.6
     */
    public function save($key = null, $urlVar = 'id')
    {
        return parent::save($key, $urlVar);
    }

    /**
     * Method to cancel an edit.
     *
     * @param   string  $key  The name of the primary key of the URL variable.
     *
     * @return  boolean  True if access level checks pass, false otherwise.
     *
     * @since   6.0.6
     */
    public function cancel($key = 'id')
    {
        return parent::cancel($key);
    }

    /**
     * AJAX: Get zones for a given country.
     *
     * Returns HTML <option> elements for the zone dropdown.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    public function getZones(): void
    {
        $app = Factory::getApplication();

        // Get country ID from request
        $countryId      = $app->getInput()->getInt('country_id', 0);
        $selectedZoneId = $app->getInput()->getInt('zone_id', 0);

        // Build zone options HTML
        $html = '<option value="">' . Text::sprintf('COM_J2COMMERCE_SELECT_PLACEHOLDER', Text::_('COM_J2COMMERCE_ZONE')) . '</option>';

        if ($countryId > 0) {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);

            $query->select($db->quoteName(['j2commerce_zone_id', 'zone_name']))
                ->from($db->quoteName('#__j2commerce_zones'))
                ->where($db->quoteName('country_id') . ' = :country_id')
                ->where($db->quoteName('enabled') . ' = 1')
                ->bind(':country_id', $countryId, ParameterType::INTEGER)
                ->order($db->quoteName('zone_name') . ' ASC');

            $db->setQuery($query);
            $zones = $db->loadObjectList();

            if ($zones) {
                foreach ($zones as $zone) {
                    $selected = ($zone->j2commerce_zone_id == $selectedZoneId) ? ' selected="selected"' : '';
                    $html .= '<option value="' . (int) $zone->j2commerce_zone_id . '"' . $selected . '>'
                        . htmlspecialchars($zone->zone_name, ENT_QUOTES, 'UTF-8')
                        . '</option>';
                }
            }
        }

        // Output raw HTML (not JSON) for direct select population
        echo $html;
        $app->close();
    }
}
