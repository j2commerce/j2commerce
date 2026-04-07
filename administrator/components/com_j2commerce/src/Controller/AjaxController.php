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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Database\ParameterType;

/**
 * AJAX controller for J2Commerce.
 *
 * Handles AJAX requests for dynamic form field population.
 *
 * @since  6.0.7
 */
class AjaxController extends BaseController
{
    /**
     * AJAX: Get zones for a given country.
     *
     * Returns HTML <option> elements for the zone dropdown.
     * Used by ZoneField when `country_field` attribute is set.
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

        // Get selected country ID from request
        $selectedCountryId = $app->getInput()->getInt('country_id', 0);

        // Build country options HTML
        $html = '<option value="">' . Text::sprintf('COM_J2COMMERCE_SELECT_PLACEHOLDER', Text::_('COM_J2COMMERCE_COUNTRY')) . '</option>';

        $db    = Factory::getContainer()->get('DatabaseDriver');
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

        // Output raw HTML (not JSON) for direct select population
        echo $html;
        $app->close();
    }

    /**
     * AJAX: Regenerate the queue key.
     *
     * Generates a new queue key and saves it to the component params.
     * Returns JSON response with the new key.
     *
     * @return  void
     *
     * @since   6.0.7
     */
    public function regenerateQueuekey(): void
    {
        $app = Factory::getApplication();

        // Check for CSRF token
        if (!$this->checkToken('get')) {
            $this->sendJsonResponse(false, Text::_('JINVALID_TOKEN'), null);
            return;
        }

        try {
            // Generate new queue key
            $siteName    = $app->get('sitename', 'J2Commerce');
            $queueString = $siteName . time() . bin2hex(random_bytes(8));
            $queueKey    = md5($queueString);

            // Save to component params
            $db = Factory::getContainer()->get('DatabaseDriver');

            // Get the current params
            $params = ComponentHelper::getParams('com_j2commerce');

            // Set the queue_key
            $params->set('queue_key', $queueKey);

            // Convert to JSON
            $paramsJson = $params->toString();

            // Update the #__extensions table
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('params') . ' = :params')
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_j2commerce'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->bind(':params', $paramsJson);

            $db->setQuery($query);
            $db->execute();

            // Clear the component params cache
            ComponentHelper::getParams('com_j2commerce', true);

            $this->sendJsonResponse(true, Text::_('COM_J2COMMERCE_QUEUE_KEY_REGENERATED'), ['queue_key' => $queueKey]);
        } catch (\Exception $e) {
            $this->sendJsonResponse(false, Text::sprintf('COM_J2COMMERCE_ERROR_REGENERATING_QUEUE_KEY', $e->getMessage()), null);
        }
    }

    /**
     * Send a JSON response and close the application.
     *
     * @param   bool         $success  Whether the operation was successful.
     * @param   string       $message  The response message.
     * @param   array|null   $data     Optional data to include in the response.
     *
     * @return  void
     *
     * @since   6.0.7
     */
    private function sendJsonResponse(bool $success, string $message, ?array $data): void
    {
        $app = Factory::getApplication();

        $response = [
            'success' => $success,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        $app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode($response);
        $app->close();
    }
}
