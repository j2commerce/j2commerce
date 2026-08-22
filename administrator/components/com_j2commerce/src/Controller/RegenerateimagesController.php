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

use J2Commerce\Component\J2commerce\Administrator\Helper\ImageRegenerationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

class RegenerateimagesController extends BaseController
{
    private const ALLOWED_SCOPES = ['thumbs', 'tiny'];

    public function scan(): void
    {
        if (!$this->authorize()) {
            return;
        }

        $scope = $this->requireScope();

        if ($scope === null) {
            return;
        }

        $total = $this->getRegenerationHelper()->countTotal($scope);

        $this->sendJson(true, '', ['scope' => $scope, 'total' => $total]);
    }

    public function run(): void
    {
        if (!$this->authorize()) {
            return;
        }

        $scope = $this->requireScope();

        if ($scope === null) {
            return;
        }

        $input  = $this->app->getInput();
        $offset = max(0, $input->getInt('offset', 0));
        $limit  = max(1, min(25, $input->getInt('limit', 10)));

        try {
            $result = $this->getRegenerationHelper()->processBatch($scope, $offset, $limit);
        } catch (\Throwable $e) {
            Log::add('J2Commerce image regeneration batch failed: ' . $e->getMessage(), Log::ERROR, 'j2commerce');
            $this->sendJson(false, Text::_('COM_J2COMMERCE_ERR_GENERIC'));

            return;
        }

        $total      = $this->getRegenerationHelper()->countTotal($scope);
        $nextOffset = $offset + $result['processed'];

        $this->sendJson(true, '', [
            'scope'      => $scope,
            'total'      => $total,
            'offset'     => $offset,
            'nextOffset' => $nextOffset,
            'processed'  => $result['processed'],
            'generated'  => $result['generated'],
            'skipped'    => $result['skipped'],
            'failed'     => $result['failed'],
            'errors'     => $result['errors'],
            'done'       => $nextOffset >= $total,
        ]);
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

        if (!$user->authorise('core.admin', 'com_j2commerce')) {
            $this->sendJson(false, Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'));

            return false;
        }

        return true;
    }

    private function requireScope(): ?string
    {
        $scope = $this->app->getInput()->getCmd('scope', '');

        if (!\in_array($scope, self::ALLOWED_SCOPES, true)) {
            $this->sendJson(false, Text::_('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_ERR_INVALID_SCOPE'));

            return null;
        }

        return $scope;
    }

    private function getRegenerationHelper(): ImageRegenerationHelper
    {
        return new ImageRegenerationHelper(Factory::getContainer()->get(DatabaseInterface::class));
    }

    private function sendJson(bool $success, string $message = '', mixed $data = null): void
    {
        $this->app->getDocument()->setMimeEncoding('application/json');
        echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
        $this->app->close();
    }
}
