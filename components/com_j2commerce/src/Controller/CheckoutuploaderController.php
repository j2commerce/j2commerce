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

use Joomla\CMS\Helper\MediaHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
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
     * Handle checkout file upload.
     *
     * @return  void
     *
     * @since   6.2.0
     */
    public function upload(): void
    {
        if (!Session::checkToken('request')) {
            $this->sendJson(false, 'Invalid security token');
            return;
        }

        // Verify active checkout session (cart with items)
        $session = $this->app->getSession();
        $cartId  = $session->get('j2commerce.cart_id', 0);

        if (empty($cartId)) {
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

        // Force directory to checkout-uploads only (security: prevent path manipulation)
        $directory = $input->getString('path', 'images/checkout-uploads');
        $directory = preg_replace('/[^a-zA-Z0-9\/_-]/', '', $directory);

        if (!str_starts_with($directory, 'images/checkout-uploads')) {
            $directory = 'images/checkout-uploads';
        }

        $uploadPath = JPATH_ROOT . '/' . $directory;

        if (!is_dir($uploadPath)) {
            Folder::create($uploadPath);
        }

        // Ensure path is within site root
        $realUpload = realpath($uploadPath);
        $realRoot   = realpath(JPATH_ROOT);

        if ($realUpload === false || $realRoot === false || !str_starts_with($realUpload, $realRoot)) {
            $this->sendJson(false, 'Access denied');
            return;
        }

        $extension = strtolower(File::getExt($file['name']));
        $safeName  = File::makeSafe($file['name']);
        $baseName  = File::stripExt($safeName);
        $fileName  = $baseName . '_' . uniqid() . '.' . $extension;
        $filePath  = $uploadPath . '/' . $fileName;

        if (!File::upload($file['tmp_name'], $filePath)) {
            $this->sendJson(false, 'Failed to save file');
            return;
        }

        $relativePath = $directory . '/' . $fileName;
        $fileSize     = filesize($filePath) ?: 0;

        $this->sendJson(true, '', [
            'name' => $file['name'],
            'path' => $relativePath,
            'url'  => Uri::root() . $relativePath,
            'size' => $fileSize,
        ]);
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
