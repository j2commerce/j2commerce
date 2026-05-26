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

use J2Commerce\Component\J2commerce\Administrator\Helper\OrderUploadHelper;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

/**
 * Stream customer-uploaded order files to admins.
 *
 * @since  6.3.0
 */
class OrderfileController extends BaseController
{
    /** GET task=orderfile.download&file={mangled}&{token}=1 */
    public function download(): void
    {
        $app = Factory::getApplication();

        if (!Session::checkToken('get')) {
            $this->fail($app, 403, 'COM_J2COMMERCE_ORDER_FILE_ACCESS_DENIED');
        }

        $user = $app->getIdentity();

        if (!$user || !$user->authorise('core.edit', 'com_j2commerce')) {
            $this->fail($app, 403, 'COM_J2COMMERCE_ORDER_FILE_ACCESS_DENIED');
        }

        $mangled = trim((string) $app->getInput()->getString('file', ''));
        $upload  = OrderUploadHelper::getAttachedByMangled($mangled);

        if ($upload === null) {
            $this->fail($app, 404, 'COM_J2COMMERCE_ORDER_FILE_NOT_FOUND');
        }

        $path = OrderUploadHelper::resolveOrderFilePath(
            (string) $upload->order_id,
            (string) $upload->saved_name
        );

        if ($path === null) {
            $this->fail($app, 404, 'COM_J2COMMERCE_ORDER_FILE_NOT_FOUND');
        }

        $fileSize = filesize($path);

        if ($fileSize === false) {
            $this->fail($app, 404, 'COM_J2COMMERCE_ORDER_FILE_NOT_FOUND');
        }

        $downloadName = $this->sanitizeDownloadFilename((string) $upload->original_name, (string) $upload->saved_name);
        $mimeType     = (string) ($upload->mime_type ?: 'application/octet-stream');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $app->setHeader('Content-Type', $mimeType, true);
        $app->setHeader('Content-Disposition', 'attachment; filename="' . $downloadName . '"', true);
        $app->setHeader('Content-Length', (string) $fileSize, true);
        $app->setHeader('X-Content-Type-Options', 'nosniff', true);
        $app->setHeader('Cache-Control', 'private, no-store, max-age=0', true);
        $app->sendHeaders();

        readfile($path);
        $app->close();
    }

    private function fail(CMSApplicationInterface $app, int $status, string $messageKey): never
    {
        $app->enqueueMessage(Text::_($messageKey), 'error');
        $app->setHeader('status', (string) $status);
        $app->sendHeaders();
        $app->close();
    }

    /** Strip CR/LF and quote chars so the value is safe inside a Content-Disposition header. */
    private function sanitizeDownloadFilename(string $original, string $fallback): string
    {
        $clean = trim((string) preg_replace('/[\r\n"\\\\]+/', '', $original));

        if ($clean === '') {
            $clean = $fallback;
        }

        return $clean !== '' ? $clean : 'download';
    }
}
