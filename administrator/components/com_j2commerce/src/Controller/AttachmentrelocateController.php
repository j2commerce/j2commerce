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

use J2Commerce\Component\J2commerce\Administrator\Model\AttachmentrelocateModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

\defined('_JEXEC') or die;

/**
 * Endpoints behind the Update Files control on the Options screen.
 *
 * Separate from the Options form on purpose: the form cannot save while the path it is
 * relocating away from is in the field, so the action cannot travel as a form submit.
 *
 * @since  6.6.2
 */
class AttachmentrelocateController extends BaseController
{
    /** Report what the move would touch, without moving any of it. */
    public function scan(): void
    {
        if (!$this->authorize()) {
            return;
        }

        try {
            $scan = $this->getRelocateModel()->scan();
        } catch (\Throwable $e) {
            Log::add('J2Commerce attachment relocate scan failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
            $this->sendJson(false, Text::_('COM_J2COMMERCE_ERR_GENERIC'));

            return;
        }

        $this->sendJson(true, '', [
            'eligible'    => $scan['eligible'],
            'owned'       => $scan['owned'],
            'source'      => $scan['source_display'],
            'destination' => $scan['destination_display'],
            'counts'      => $scan['counts'],
            'total'       => $scan['outstanding'],
        ]);
    }

    /** Move one batch, then report what is left. */
    public function run(): void
    {
        if (!$this->authorize()) {
            return;
        }

        $limit = $this->app->getInput()->getInt('limit', 10);

        try {
            $result = $this->getRelocateModel()->relocate($limit);
        } catch (\Throwable $e) {
            Log::add('J2Commerce attachment relocate batch failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
            $this->sendJson(false, Text::_('COM_J2COMMERCE_ERR_GENERIC'));

            return;
        }

        $this->sendJson(true, '', $result);
    }

    /** All three gates independently, per endpoint: CSRF, authentication, authorization. */
    private function authorize(): bool
    {
        if (!Session::checkToken('request')) {
            $this->sendJson(false, Text::_('JINVALID_TOKEN'));

            return false;
        }

        $user = $this->app->getIdentity();

        if ($user === null || $user->guest || (int) $user->id === 0) {
            $this->sendJson(false, Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'));

            return false;
        }

        // core.admin, not core.manage: this writes a component param and moves customer files.
        if (!$user->authorise('core.admin', 'com_j2commerce')) {
            $this->sendJson(false, Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'));

            return false;
        }

        return true;
    }

    private function getRelocateModel(): AttachmentrelocateModel
    {
        /** @var AttachmentrelocateModel $model */
        $model = $this->getModel('Attachmentrelocate', 'Administrator', ['ignore_request' => true]);

        return $model;
    }

    /** JSON exit for the AJAX tasks. close() is exit(), so the headers flush first. */
    private function sendJson(bool $success, string $message = '', mixed $data = null): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
        $this->app->setHeader('X-Content-Type-Options', 'nosniff', true);
        $this->app->sendHeaders();

        echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
        $this->app->close();
    }
}
