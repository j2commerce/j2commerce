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

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\Event;

/**
 * Cron controller for external cron job triggers.
 *
 * Validates requests against the queue_key stored in component config,
 * then dispatches onJ2CommerceProcessCron so any plugin can handle the command.
 *
 * cPanel/sPanel cron URL format:
 *   /index.php?option=com_j2commerce&task=cron.execute&command=COMMAND&cron_secret=QUEUE_KEY
 *
 * @since  6.0.7
 */
class CronController extends BaseController
{
    public function execute($task): static
    {
        $this->doExecute();

        return $this;
    }

    private function doExecute(): void
    {
        $app = Factory::getApplication();

        // Prevent caching (SiteGround SuperCache, etc.)
        $app->setHeader('X-Cache-Control', 'False', true);
        $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);

        $params   = ComponentHelper::getParams('com_j2commerce');
        $queueKey = $params->get('queue_key', '');

        if (empty($queueKey)) {
            $app->setHeader('status', '503');
            echo 'ERROR: Queue key not configured';
            $app->close(503);
        }

        $secret = $app->getInput()->get('cron_secret', '', 'raw');

        if (!hash_equals($queueKey, $secret)) {
            $app->setHeader('status', '403');
            echo 'ERROR: Invalid cron secret';
            $app->close(403);
        }

        $command = trim(strtolower($app->getInput()->get('command', '', 'raw')));

        if ($command === '') {
            $app->setHeader('status', '501');
            echo 'ERROR: No command specified';
            $app->close(501);
        }

        // Record last trigger
        $tz          = $app->get('offset');
        $nowDate     = Factory::getDate('now', $tz);
        $lastTrigger = json_encode([
            'date'    => $nowDate->toSql(),
            'command' => $command,
            'url'     => $_SERVER['REQUEST_URI'] ?? '',
            'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
            'success' => true,
        ]);

        $this->saveConfigValue('cron_last_trigger', $lastTrigger);

        // Dispatch the cron event — any plugin can subscribe to onJ2CommerceProcessCron
        $event = new Event('onJ2CommerceProcessCron', ['command' => $command]);
        $app->getDispatcher()->dispatch('onJ2CommerceProcessCron', $event);

        echo "{$command} OK";
        $app->close();
    }

    private function saveConfigValue(string $key, string $value): void
    {
        try {
            $params = ComponentHelper::getParams('com_j2commerce');
            $params->set($key, $value);

            $db         = Factory::getContainer()->get(DatabaseInterface::class);
            $paramsJson = $params->toString();

            $query = $db->createQuery()
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('params') . ' = :params')
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_j2commerce'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->bind(':params', $paramsJson);

            $db->setQuery($query)->execute();
        } catch (\Throwable) {
            // Non-fatal — don't break the cron run
        }
    }
}
