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

use J2Commerce\Component\J2commerce\Administrator\Helper\ComponentParamsHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Uri\Uri;
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
        $app->setHeader('X-Content-Type-Options', 'nosniff', true);

        $params   = ComponentHelper::getParams('com_j2commerce');
        $queueKey = $params->get('queue_key', '');

        if (empty($queueKey)) {
            $this->respond(503, 'ERROR: Queue key not configured');
        }

        // A bracketed parameter makes the filter hand back an array, so the type is checked
        // before it reaches hash_equals().
        $secret = $app->getInput()->getString('cron_secret', '');

        if (!\is_string($secret) || !hash_equals((string) $queueKey, $secret)) {
            $this->respond(403, 'ERROR: Invalid cron secret');
        }

        $command = $app->getInput()->getString('command', '');
        $command = \is_string($command) ? trim(strtolower($command)) : '';

        if ($command === '') {
            $this->respond(501, 'ERROR: No command specified');
        }

        // Record last trigger
        $tz          = $app->get('offset');
        $nowDate     = Factory::getDate('now', $tz);
        $lastTrigger = json_encode([
            'date'    => $nowDate->toSql(),
            'command' => $command,
            'url'     => Uri::getInstance()->toString(['scheme', 'host', 'port', 'path']),
            'ip'      => $app->getInput()->server->getString('REMOTE_ADDR', ''),
            'success' => true,
        ]);

        try {
            ComponentParamsHelper::set('cron_last_trigger', $lastTrigger);
        } catch (\Throwable) {
            // Non-fatal — a missing trigger record must not break the cron run
        }

        // Dispatch the cron event — any plugin can subscribe to onJ2CommerceProcessCron
        $event = new Event('onJ2CommerceProcessCron', ['command' => $command]);
        $app->getDispatcher()->dispatch('onJ2CommerceProcessCron', $event);

        $this->respond(200, htmlspecialchars($command, ENT_QUOTES, 'UTF-8') . ' OK');
    }

    /**
     * close() is a bare exit(), so queued headers are only written if sendHeaders() runs first.
     */
    private function respond(int $status, string $message): void
    {
        $app = Factory::getApplication();

        $app->setHeader('status', (string) $status);
        $app->sendHeaders();

        echo $message;

        $app->close($status === 200 ? 0 : $status);
    }
}
