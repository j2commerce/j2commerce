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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

/**
 * Cron Last Hit field - displays the last cron job execution details.
 *
 * Shows when the cron job last ran, including date, URL, and IP address.
 * If no cron hit has been recorded, displays a "not found" message.
 *
 * @since  6.0.7
 */
class CronlasthitField extends FormField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  6.0.7
     */
    protected $type = 'Cronlasthit';

    /**
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     *
     * @since   6.0.7
     */
    protected function getInput(): string
    {
        // Get the cron last trigger value from component params
        $cronHit = $this->getCronLastTrigger();

        // Build the display message
        $note    = '';
        $success = false;

        if (empty($cronHit)) {
            $note  = Text::_('COM_J2COMMERCE_CRON_LAST_TRIGGER_NOT_FOUND');
            $class = 'alert-info';
        } elseif ($this->isJson($cronHit)) {
            $data = json_decode($cronHit);

            $date    = $data->date ?? '';
            $url     = $data->url ?? '';
            $ip      = $data->ip ?? '';
            $success = $data->success ?? false;

            $note  = Text::sprintf('COM_J2COMMERCE_CRON_LAST_TRIGGER_DETAILS', $date, $url, $ip);
            $class = 'alert-success';
        } else {
            // If it's not JSON, just display the raw value (could be a timestamp)
            $note  = htmlspecialchars($cronHit, ENT_QUOTES, 'UTF-8');
            $class = 'alert-info';
        }

        // Build the HTML output
        $html = <<<HTML
<div class="alert {$class} mt-n3 mb-0">
    <strong>{$note}</strong>
</div>
<input type="hidden" name="{$this->name}" id="{$this->id}" value=""/>
HTML;

        return $html;
    }

    /**
     * Get the cron last trigger value from the component params.
     *
     * @return  string  The cron last trigger value or empty string if not found.
     *
     * @since   6.0.7
     */
    private function getCronLastTrigger(): string
    {
        try {
            $params = ComponentHelper::getParams('com_j2commerce');

            return $params->get('cron_last_trigger', '');
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Check if a string is valid JSON.
     *
     * @param   string  $string  The string to check.
     *
     * @return  bool  True if valid JSON, false otherwise.
     *
     * @since   6.0.7
     */
    private function isJson(string $string): bool
    {
        if (empty($string)) {
            return false;
        }

        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
