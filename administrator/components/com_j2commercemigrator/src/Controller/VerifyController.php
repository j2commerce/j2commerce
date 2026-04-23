<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\Controller;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commercemigrator\Administrator\Service\VerificationService;
use J2Commerce\Component\J2commercemigrator\Administrator\Helper\MigrationLogger;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

/**
 * Post-migration verification: fans out to every integrity check and exports reports.
 */
class VerifyController extends BaseController
{
    protected $default_view = 'verify';

    public function display($cachable = false, $urlparams = []): static
    {
        $this->enforceAcl();
        return parent::display($cachable, $urlparams);
    }

    /**
     * GET: runs all post-migration integrity checks and returns a full report.
     */
    public function verify(): void
    {
        $this->enforceAcl();

        try {
            $logger  = new MigrationLogger();
            $service = new VerificationService($this->getDatabase(), $logger);
            $this->sendJson($service->runAll());
        } catch (\Throwable $e) {
            $this->handleError('VerifyController::verify', $e);
        }
    }

    /**
     * GET: exports the verification report as a downloadable JSON file.
     */
    public function exportReport(): void
    {
        $this->enforceAcl();

        try {
            $logger  = new MigrationLogger();
            $service = new VerificationService($this->getDatabase(), $logger);
            $report  = $service->runAll();

            $app = Factory::getApplication();
            $app->setHeader('Content-Type', 'application/json; charset=utf-8');
            $app->setHeader('Content-Disposition', 'attachment; filename="migration-verify-' . date('Y-m-d-His') . '.json"');
            echo json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $app->close();
        } catch (\Throwable $e) {
            $this->handleError('VerifyController::exportReport', $e);
        }
    }

    private function enforceAcl(): void
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || !$user->authorise('core.manage', 'com_j2commercemigrator')) {
            $this->sendJson(['error' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]);
        }
    }

    private function handleError(string $context, \Throwable $e): void
    {
        (new MigrationLogger())->error($context, $e->getMessage());

        if (\defined('JDEBUG') && JDEBUG) {
            $this->sendJson(['error' => $e->getMessage()]);
        } else {
            $this->sendJson(['error' => Text::_('COM_J2COMMERCEMIGRATOR_ERR_GENERIC')]);
        }
    }

    private function sendJson(array $data): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $app->close();
    }
}
