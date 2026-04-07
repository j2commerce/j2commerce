<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Site AJAX controller for J2Commerce.
 *
 * Handles AJAX requests for dynamic form field population on the frontend.
 *
 * @since  6.0.7
 */
class AjaxController extends BaseController
{
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

        $countryId      = $app->getInput()->getInt('country_id', 0);
        $selectedZoneId = $app->getInput()->getInt('zone_id', 0);

        $html = '<option value="">' . Text::sprintf('COM_J2COMMERCE_SELECT_PLACEHOLDER', Text::_('COM_J2COMMERCE_ZONE')) . '</option>';

        if ($countryId > 0) {
            try {
                $db    = Factory::getContainer()->get(DatabaseInterface::class);
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
            } catch (\RuntimeException $e) {
                // Database error — return just the default option
            }
        }

        $app->setHeader('Content-Type', 'text/html; charset=utf-8');
        echo $html;
        $app->close();
    }

    /**
     * AJAX: Get countries list.
     *
     * Returns HTML <option> elements for the country dropdown.
     *
     * @return  void
     *
     * @since   6.0.7
     */
    public function getCountries(): void
    {
        $app = Factory::getApplication();

        $selectedCountryId = $app->getInput()->getInt('country_id', 0);

        $html = '<option value="">' . Text::sprintf('COM_J2COMMERCE_SELECT_PLACEHOLDER', Text::_('COM_J2COMMERCE_COUNTRY')) . '</option>';

        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true);

            $query->select($db->quoteName(['j2commerce_country_id', 'country_name']))
                ->from($db->quoteName('#__j2commerce_countries'))
                ->where($db->quoteName('enabled') . ' = 1')
                ->order($db->quoteName('country_name') . ' ASC');

            $db->setQuery($query);
            $countries = $db->loadObjectList();

            if ($countries) {
                foreach ($countries as $country) {
                    $selected = ($country->j2commerce_country_id == $selectedCountryId) ? ' selected="selected"' : '';
                    $html .= '<option value="' . (int) $country->j2commerce_country_id . '"' . $selected . '>'
                        . htmlspecialchars($country->country_name, ENT_QUOTES, 'UTF-8')
                        . '</option>';
                }
            }
        } catch (\RuntimeException $e) {
            // Database error — return just the default option
        }

        $app->setHeader('Content-Type', 'text/html; charset=utf-8');
        echo $html;
        $app->close();
    }

    /**
     * AJAX: Lookup country and zone IDs from country code and state name.
     *
     * Used by address autocomplete plugins to map Google Places data to J2Commerce IDs.
     *
     * @return  void
     *
     * @since   6.0.21
     */
    public function lookupCountryZone(): void
    {
        $app = Factory::getApplication();

        $countryCode = $app->getInput()->getString('country_code', '');
        $stateName   = $app->getInput()->getString('state_name', '');

        $result = ['country_id' => 0, 'zone_id' => 0];

        if (empty($countryCode)) {
            $app->setHeader('Content-Type', 'application/json');
            echo json_encode($result);
            $app->close();
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            // Get country ID
            $query = $db->getQuery(true)
                ->select($db->quoteName('j2commerce_country_id'))
                ->from($db->quoteName('#__j2commerce_countries'))
                ->where($db->quoteName('country_isocode_2') . ' = :code')
                ->where($db->quoteName('enabled') . ' = 1')
                ->bind(':code', strtoupper(trim($countryCode)));

            $db->setQuery($query);
            $countryId = (int) $db->loadResult();

            if (!$countryId) {
                $app->setHeader('Content-Type', 'application/json');
                echo json_encode($result);
                $app->close();
            }

            $result['country_id'] = $countryId;

            if (empty($stateName)) {
                $app->setHeader('Content-Type', 'application/json');
                echo json_encode($result);
                $app->close();
            }

            // Try to match zone
            $zoneId = $this->matchZone($db, $countryId, trim($stateName));
            if ($zoneId) {
                $result['zone_id'] = $zoneId;
            }
        } catch (\RuntimeException $e) {
            // Database error — return default result
        }

        $app->setHeader('Content-Type', 'application/json');
        echo json_encode($result);
        $app->close();
    }

    /**
     * Match a state name to a zone ID using tiered matching.
     *
     * @param   DatabaseInterface  $db         Database instance
     * @param   int                $countryId  Country ID
     * @param   string             $stateName  State name to match
     *
     * @return  int  Zone ID or 0 if not found
     *
     * @since   6.0.21
     */
    private function matchZone(DatabaseInterface $db, int $countryId, string $stateName): int
    {
        // Tier 1: Exact zone_name match
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_zone_id'))
            ->from($db->quoteName('#__j2commerce_zones'))
            ->where($db->quoteName('country_id') . ' = :countryId')
            ->where($db->quoteName('zone_name') . ' = :stateName')
            ->where($db->quoteName('enabled') . ' = 1')
            ->bind(':countryId', $countryId, ParameterType::INTEGER)
            ->bind(':stateName', $stateName);

        $db->setQuery($query);
        $result = $db->loadResult();
        if ($result) {
            return (int) $result;
        }

        // Tier 2: zone_code abbreviation match
        $code  = strtoupper($stateName);
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_zone_id'))
            ->from($db->quoteName('#__j2commerce_zones'))
            ->where($db->quoteName('country_id') . ' = :countryId')
            ->where($db->quoteName('zone_code') . ' = :code')
            ->where($db->quoteName('enabled') . ' = 1')
            ->bind(':countryId', $countryId, ParameterType::INTEGER)
            ->bind(':code', $code);

        $db->setQuery($query);
        $result = $db->loadResult();
        if ($result) {
            return (int) $result;
        }

        // Tier 3: LIKE partial match
        $likePattern = '%' . $db->escape($stateName, true) . '%';
        $query       = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_zone_id'))
            ->from($db->quoteName('#__j2commerce_zones'))
            ->where($db->quoteName('country_id') . ' = :countryId')
            ->where($db->quoteName('zone_name') . ' LIKE :pattern')
            ->where($db->quoteName('enabled') . ' = 1')
            ->bind(':countryId', $countryId, ParameterType::INTEGER)
            ->bind(':pattern', $likePattern);

        $db->setQuery($query);
        $result = $db->loadResult();

        return $result ? (int) $result : 0;
    }
}
