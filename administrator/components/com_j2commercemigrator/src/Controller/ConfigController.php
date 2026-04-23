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

use J2Commerce\Component\J2commercemigrator\Administrator\Service\ConfigurationMigrator;
use J2Commerce\Component\J2commercemigrator\Administrator\Helper\MigrationLogger;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Configuration migration: analyzeConfig, migrateConfig.
 */
class ConfigController extends BaseController
{
    protected $default_view = 'config';

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
     * GET: compares J2Store component config against J2Commerce defaults, key by key.
     */
    public function analyzeConfig(): void
    {
        $this->enforceAcl();

        try {
            $logger  = new MigrationLogger();
            $service = new ConfigurationMigrator($this->getDatabase(), $logger);
            $this->sendJson($service->analyze());
        } catch (\Throwable $e) {
            $this->handleError('ConfigController::analyzeConfig', $e);
        }
    }

    /**
     * POST: applies per-key configuration choices selected by the store owner.
     */
    public function migrateConfig(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $input   = Factory::getApplication()->getInput();
            $choices = $input->get('choices', [], 'array');

            $logger  = new MigrationLogger();
            $service = new ConfigurationMigrator($this->getDatabase(), $logger);
            $this->sendJson($service->migrate($choices));
        } catch (\Throwable $e) {
            $this->handleError('ConfigController::migrateConfig', $e);
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
