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

use J2Commerce\Component\J2commerce\Administrator\Helper\ConfigHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\UploadHelper;
use Joomla\CMS\Helper\MediaHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

\defined('_JEXEC') or die;

/**
 * Frontend controller for checkout file uploads (multiuploader custom field).
 *
 * @since  6.2.0
 */
class CheckoutuploaderController extends BaseController
{
    /**
     * Handle checkout file upload — stores under files/com_j2commerce/tmp/{cart_id}/
     * with randomized filename + DB-tracked mangled token.
     *
     * @since  6.2.0
     */
    public function upload(): void
    {
        if (!Session::checkToken('request')) {
            $this->sendJson(false, 'Invalid security token');
            return;
        }

        $session = $this->app->getSession();
        $cartId  = (int) $session->get('j2commerce.cart_id', 0);

        if ($cartId <= 0) {
            $this->sendJson(false, 'No active checkout session');
            return;
        }

        $input = $this->app->getInput();
        $file  = $input->files->get('file', [], 'array');

        if (empty($file['name'])) {
            $this->sendJson(false, 'No file uploaded');
            return;
        }

        if (!(new MediaHelper())->canUpload($file)) {
            $this->sendJson(false, 'File type not allowed');
            return;
        }

        $attachmentRoot = ConfigHelper::getAttachmentAbsolutePath();

        if ($attachmentRoot === null) {
            $this->sendJson(false, 'Upload storage unavailable');
            return;
        }

        $uploadPath = $attachmentRoot . '/tmp/' . $cartId;

        if (!is_dir($uploadPath) && !Folder::create($uploadPath)) {
            $this->sendJson(false, 'Failed to prepare storage');
            return;
        }

        $realUpload = realpath($uploadPath);

        if ($realUpload === false || !str_starts_with($realUpload, $attachmentRoot)) {
            $this->sendJson(false, 'Access denied');
            return;
        }

        $extension   = strtolower(File::getExt($file['name']));
        $savedName   = UploadHelper::randomToken() . ($extension !== '' ? '.' . $extension : '');
        $mangledName = UploadHelper::randomToken();
        $filePath    = $uploadPath . '/' . $savedName;

        if (!File::upload($file['tmp_name'], $filePath)) {
            $this->sendJson(false, 'Failed to save file');
            return;
        }

        $fileSize = filesize($filePath) ?: 0;
        $mimeType = $this->resolveMimeType($filePath, $file);
        $userId   = (int) ($this->app->getIdentity()->id ?? 0);

        $stored = UploadHelper::createPendingUpload(
            $cartId,
            (string) $file['name'],
            $mangledName,
            $savedName,
            $mimeType,
            (int) $fileSize,
            $userId
        );

        if (!$stored) {
            @unlink($filePath);
            $this->sendJson(false, 'Failed to persist upload metadata');
            return;
        }

        $this->sendJson(true, '', [
            'name'         => $file['name'],
            'mangled_name' => $mangledName,
            'size'         => $fileSize,
        ]);
    }

    /** Resolve a MIME type for the uploaded file, with safe fallback. */
    private function resolveMimeType(string $filePath, array $file): string
    {
        if (\function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo !== false) {
                $mime = (string) @finfo_file($finfo, $filePath);
                @finfo_close($finfo);

                if ($mime !== '') {
                    return $mime;
                }
            }
        }

        return (string) ($file['type'] ?? 'application/octet-stream');
    }

    /**
     * Send JSON response and close.
     *
     * @param   bool    $success  Success flag.
     * @param   string  $message  Message string.
     * @param   array   $data     Response data.
     *
     * @return  void
     *
     * @since   6.2.0
     */
    private function sendJson(bool $success, string $message = '', array $data = []): void
    {
        $response = ['success' => $success];

        if ($message !== '') {
            $response['message'] = $message;
        }

        if (!empty($data)) {
            $response['data'] = $data;
        }

        @ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
        $this->app->close();
    }
}
