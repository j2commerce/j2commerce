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

use J2Commerce\Component\J2commerce\Administrator\Helper\CartHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ConfigHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\CustomFieldHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\UploadHelper;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Helper\MediaHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Filesystem\Exception\FilesystemException;
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

        // Resolve the caller's own cart the way the rest of the cart path does — by user
        // id, then session id, then signed cookie. Never create one from an upload.
        $cart   = CartHelper::getInstance()->getCart(0, false);
        $cartId = (int) ($cart->j2commerce_cart_id ?? 0);

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

        // The field's own configured extension/size limits are published to Uppy as
        // data-* attributes; re-read and apply them here so the browser is not the
        // only thing enforcing them. The id is on the upload URL the render step builds,
        // so a request without one is not a shape this route serves — answering it with
        // com_media's global configuration alone would be the opposite of re-reading.
        $fieldOptions = CustomFieldHelper::multiuploaderOptions($input->getInt('customfield_id', 0));

        if ($fieldOptions === null) {
            $this->sendJson(false, Text::_('COM_J2COMMERCE_CHECKOUT_UPLOAD_FIELD_UNKNOWN'));
            return;
        }

        $fieldError = CustomFieldHelper::validateMultiuploaderFile($fieldOptions, $file);

        if ($fieldError !== null) {
            $this->sendJson(false, $fieldError);
            return;
        }

        // Rolling-hour throttle counted over stored rows — a session counter would
        // reset itself on every new visit.
        if (UploadHelper::hasExceededHourlyLimit()) {
            $this->sendJson(false, Text::_('COM_J2COMMERCE_UPLOAD_RATE_LIMITED'));
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

        UploadHelper::ensureIndexHtml($uploadPath);

        $realUpload = realpath($uploadPath);

        if ($realUpload === false || !str_starts_with($realUpload, $attachmentRoot)) {
            $this->sendJson(false, 'Access denied');
            return;
        }

        $extension   = strtolower(File::getExt($file['name']));
        $savedName   = UploadHelper::randomToken() . ($extension !== '' ? '.' . $extension : '');
        $mangledName = UploadHelper::randomToken();
        $filePath    = $uploadPath . '/' . $savedName;

        // canUpload() settles the extension and the sniffed MIME but never reads the bytes,
        // so a polyglot carrying PHP under an allowed extension passes it. isSafeFile()
        // is the core scanner for that: chunked rather than whole-file, and it also catches
        // the phar stub and a forbidden extension buried in any dot segment. The two content
        // options are off because a customer's .txt or .zip legitimately trips them — a
        // short tag is any '<?', and the archive test is a raw string search for '.php'.
        if (!InputFilter::isSafeFile($file, ['shorttag_in_content' => false, 'fobidden_ext_in_content' => false])) {
            $this->sendJson(false, Text::_('COM_J2COMMERCE_UPLOAD_FILE_PHP_TAGS'));
            return;
        }

        // Framework File::upload() returns true or throws — it never returns false, so the
        // client's JSON contract depends on catching rather than on testing the result.
        try {
            File::upload($file['tmp_name'], $filePath);
        } catch (FilesystemException $e) {
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
