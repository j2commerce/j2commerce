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
 * Vendor Controller
 *
 * Handles single vendor item CRUD operations.
 * Most functionality is inherited from Joomla's FormController.
 *
 * @since  6.0.6
 */
class VendorController extends FormController
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.6
     */
    protected $text_prefix = 'COM_J2COMMERCE_VENDOR';

    /**
     * The primary key name for the table.
     * Required for J2Commerce tables which use j2commerce_*_id format.
     *
     * @var    string
     * @since  6.0.6
     */
    protected $key = 'j2commerce_vendor_id';

    public function edit($key = null, $urlVar = 'id')
    {
        return parent::edit($key, $urlVar);
    }

    public function save($key = null, $urlVar = 'id')
    {
        return parent::save($key, $urlVar);
    }

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
     * @since   6.0.7
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
