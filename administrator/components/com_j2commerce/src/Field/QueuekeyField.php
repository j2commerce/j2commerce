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
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * Queue Key field - displays the queue key with a regenerate button.
 *
 * The queue key is used for scheduled tasks and cron operations.
 * If no key exists, one is automatically generated.
 *
 * @since  6.0.7
 */
class QueuekeyField extends FormField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  6.0.7
     */
    protected $type = 'Queuekey';

    /**
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     *
     * @since   6.0.7
     */
    protected function getInput(): string
    {
        // Get the current queue key from the component params
        $queueKey = $this->getQueueKey();

        // If empty, generate a new one
        if (empty($queueKey)) {
            $queueKey = $this->generateQueueKey();
            $this->saveQueueKey($queueKey);
        }

        // Build the regenerate URL
        $ajaxUrl = 'index.php?option=com_j2commerce&task=ajax.regenerateQueuekey&format=json';

        // Get language strings
        $regenerateText = Text::_('COM_J2COMMERCE_STORE_REGENERATE');

        // Build the HTML output with vanilla JavaScript
        $html = <<<HTML
<div class="alert alert-success d-flex align-items-center gap-3 justify-content-between">
    <strong id="j2commerce_queue_key">{$queueKey}</strong>
    <button type="button" class="btn btn-success btn-sm" id="j2commerce_regenerate_queuekey">
        {$regenerateText}
    </button>
</div>
<input type="hidden" name="{$this->name}" id="{$this->id}" value="{$queueKey}"/>

<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    const regenerateBtn = document.getElementById('j2commerce_regenerate_queuekey');
    const queueKeyDisplay = document.getElementById('j2commerce_queue_key');
    const queueKeyInput = document.getElementById('{$this->id}');

    if (!regenerateBtn || !queueKeyDisplay) {
        return;
    }

    regenerateBtn.addEventListener('click', async function(e) {
        e.preventDefault();

        // Disable button during request
        regenerateBtn.disabled = true;
        regenerateBtn.classList.add('disabled');

        try {
            const url = '{$ajaxUrl}&' + Joomla.getOptions('csrf.token') + '=1';
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();

            if (data.success && data.data && data.data.queue_key) {
                queueKeyDisplay.textContent = data.data.queue_key;
                if (queueKeyInput) {
                    queueKeyInput.value = data.data.queue_key;
                }
                Joomla.renderMessages({success: [data.message || 'Queue key regenerated successfully']});
            } else {
                throw new Error(data.message || 'Failed to regenerate queue key');
            }
        } catch (error) {
            console.error('Error regenerating queue key:', error);
            Joomla.renderMessages({error: [error.message || 'Error regenerating queue key']});
        } finally {
            regenerateBtn.disabled = false;
            regenerateBtn.classList.remove('disabled');
        }
    });
});
</script>
HTML;

        return $html;
    }

    /**
     * Get the current queue key from the component params.
     *
     * @return  string  The queue key or empty string if not found.
     *
     * @since   6.0.7
     */
    private function getQueueKey(): string
    {
        try {
            $params = ComponentHelper::getParams('com_j2commerce');

            return $params->get('queue_key', '');
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Generate a new queue key.
     *
     * @return  string  The generated queue key.
     *
     * @since   6.0.7
     */
    private function generateQueueKey(): string
    {
        $app         = Factory::getApplication();
        $siteName    = $app->get('sitename', 'J2Commerce');
        $queueString = $siteName . time() . bin2hex(random_bytes(8));

        return md5($queueString);
    }

    /**
     * Save the queue key to the component params.
     *
     * @param   string  $queueKey  The queue key to save.
     *
     * @return  bool  True on success, false on failure.
     *
     * @since   6.0.7
     */
    private function saveQueueKey(string $queueKey): bool
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

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

            return true;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_J2COMMERCE_ERROR_SAVING_QUEUE_KEY', $e->getMessage()),
                'error'
            );
            return false;
        }
    }
}
