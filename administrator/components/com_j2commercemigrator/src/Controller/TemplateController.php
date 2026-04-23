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

use J2Commerce\Component\J2commercemigrator\Administrator\Service\SubTemplateMigrator;
use J2Commerce\Component\J2commercemigrator\Administrator\Helper\MigrationLogger;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Template migration: discoverSubtemplates, analyzeSubtemplates, migrateSubtemplates,
 * discoverTemplateOverrides, migrateTemplateOverrides, applyManualReplacement,
 * remigrateFromPlugin.
 */
class TemplateController extends BaseController
{
    protected $default_view = 'templates';

    public function display($cachable = false, $urlparams = []): static
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || !$user->authorise('core.manage', 'com_j2commercemigrator')) {
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'error');
            $this->setRedirect(Route::_('index.php', false));
            return $this;
        }

        return parent::display($cachable, $urlparams);
    }

    /**
     * GET: lists frontend templates that reference J2Store namespaces.
     */
    public function discoverSubtemplates(): void
    {
        $this->enforceAcl();

        try {
            $logger  = new MigrationLogger();
            $service = new SubTemplateMigrator($this->getDatabase(), $logger);
            $this->sendJson($service->discover());
        } catch (\Throwable $e) {
            $this->handleError('TemplateController::discoverSubtemplates', $e);
        }
    }

    /**
     * GET: per-file replacement report for the specified template.
     */
    public function analyzeSubtemplates(): void
    {
        $this->enforceAcl();

        try {
            $input   = Factory::getApplication()->getInput();
            $tpl     = $input->getString('template', '');

            $logger  = new MigrationLogger();
            $service = new SubTemplateMigrator($this->getDatabase(), $logger);
            $this->sendJson($service->analyze($tpl));
        } catch (\Throwable $e) {
            $this->handleError('TemplateController::analyzeSubtemplates', $e);
        }
    }

    /**
     * POST: rewrites J2Store references in the specified template in-place (with backup).
     */
    public function migrateSubtemplates(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $input   = Factory::getApplication()->getInput();
            $tpl     = $input->getString('template', '');
            $backup  = $input->getBool('backup', true);

            $logger  = new MigrationLogger();
            $service = new SubTemplateMigrator($this->getDatabase(), $logger);
            $this->sendJson($service->migrate($tpl, $backup));
        } catch (\Throwable $e) {
            $this->handleError('TemplateController::migrateSubtemplates', $e);
        }
    }

    /**
     * GET: enumerates template override directories that contain J2Store paths.
     */
    public function discoverTemplateOverrides(): void
    {
        $this->enforceAcl();

        try {
            $logger  = new MigrationLogger();
            $service = new SubTemplateMigrator($this->getDatabase(), $logger);
            $this->sendJson($service->discoverTemplateOverrides());
        } catch (\Throwable $e) {
            $this->handleError('TemplateController::discoverTemplateOverrides', $e);
        }
    }

    /**
     * POST: ports override files between J2Store and J2Commerce namespaces.
     */
    public function migrateTemplateOverrides(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $input   = Factory::getApplication()->getInput();
            $dirs    = $input->get('dirs', [], 'array');
            $backup  = $input->getBool('backup', true);

            $logger  = new MigrationLogger();
            $service = new SubTemplateMigrator($this->getDatabase(), $logger);
            $this->sendJson($service->migrateTemplateOverrides($dirs, $backup));
        } catch (\Throwable $e) {
            $this->handleError('TemplateController::migrateTemplateOverrides', $e);
        }
    }

    /**
     * POST: applies a user-specified single-line rewrite to a template file.
     */
    public function applyManualReplacement(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $input  = Factory::getApplication()->getInput();
            $file   = $input->getString('file', '');
            $search = $input->getString('search', '');
            $replace = $input->getString('replace', '');

            $logger  = new MigrationLogger();
            $service = new SubTemplateMigrator($this->getDatabase(), $logger);
            $this->sendJson($service->applyManualReplacement($file, $search, $replace));
        } catch (\Throwable $e) {
            $this->handleError('TemplateController::applyManualReplacement', $e);
        }
    }

    /**
     * POST: re-seeds a template file from the originating plugin source.
     */
    public function remigrateFromPlugin(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $input  = Factory::getApplication()->getInput();
            $file   = $input->getString('file', '');
            $plugin = $input->getCmd('plugin', '');

            $logger  = new MigrationLogger();
            $service = new SubTemplateMigrator($this->getDatabase(), $logger);
            $this->sendJson($service->remigrateFromPlugin($file, $plugin));
        } catch (\Throwable $e) {
            $this->handleError('TemplateController::remigrateFromPlugin', $e);
        }
    }

    private function enforceAcl(): void
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || !$user->authorise('core.manage', 'com_j2commercemigrator')) {
            $this->sendJson(['success' => false, 'error' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'category' => 'forbidden']);
        }
    }

    private function enforceToken(): void
    {
        if (!Session::checkToken('post')) {
            $this->sendJson(['success' => false, 'error' => Text::_('JINVALID_TOKEN'), 'category' => 'csrf']);
        }
    }

    private function handleError(string $context, \Throwable $e): void
    {
        (new MigrationLogger())->error($context . ': ' . $e->getMessage());

        if (\defined('JDEBUG') && JDEBUG) {
            $this->sendJson(['error' => $e->getMessage()]);
        } else {
            $this->sendJson(['error' => Text::_('COM_J2COMMERCEMIGRATOR_ERR_GENERIC')]);
        }
    }

    private function sendJson(array $data): void
    {
        if (!array_key_exists('success', $data)) {
            if (array_key_exists('error', $data)) {
                $normalized = ['success' => false, 'error' => $data['error']];

                if (array_key_exists('category', $data)) {
                    $normalized['category'] = $data['category'];
                }

                $data = $normalized;
            } else {
                $data = ['success' => true, 'data' => $data];
            }
        }

        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $app->close();
    }
}
