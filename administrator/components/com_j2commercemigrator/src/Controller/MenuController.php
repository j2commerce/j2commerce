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

use J2Commerce\Component\J2commercemigrator\Administrator\Service\MenuMigrator;
use J2Commerce\Component\J2commercemigrator\Administrator\Helper\MigrationLogger;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Menu migration: getJ2StoreMenuItems, migrateSelectedMenus, migrateMenus,
 * rollbackMenus, createMenuItems.
 */
class MenuController extends BaseController
{
    protected $default_view = 'menus';

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
     * GET: lists J2Store frontend menu items available for migration.
     */
    public function getJ2StoreMenuItems(): void
    {
        $this->enforceAcl();

        try {
            $logger  = new MigrationLogger();
            $service = new MenuMigrator($this->getDatabase(), $logger);
            $this->sendJson($service->getJ2StoreMenuItems());
        } catch (\Throwable $e) {
            $this->handleError('MenuController::getJ2StoreMenuItems', $e);
        }
    }

    /**
     * POST: migrates store-owner-selected menu items to J2Commerce routes.
     */
    public function migrateSelectedMenus(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $input    = Factory::getApplication()->getInput();
            $selected = $input->get('selected', [], 'array');

            $logger  = new MigrationLogger();
            $service = new MenuMigrator($this->getDatabase(), $logger);
            $this->sendJson($service->migrateSelected($selected));
        } catch (\Throwable $e) {
            $this->handleError('MenuController::migrateSelectedMenus', $e);
        }
    }

    /**
     * POST: auto-migrates every safe J2Store menu item.
     */
    public function migrateMenus(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $logger  = new MigrationLogger();
            $service = new MenuMigrator($this->getDatabase(), $logger);
            $this->sendJson($service->migrate());
        } catch (\Throwable $e) {
            $this->handleError('MenuController::migrateMenus', $e);
        }
    }

    /**
     * POST: reverses menu migration — deletes created items and removes redirects.
     */
    public function rollbackMenus(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $logger  = new MigrationLogger();
            $service = new MenuMigrator($this->getDatabase(), $logger);
            $this->sendJson($service->rollback());
        } catch (\Throwable $e) {
            $this->handleError('MenuController::rollbackMenus', $e);
        }
    }

    /**
     * POST: creates canonical J2Commerce menu items.
     */
    public function createMenuItems(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $logger  = new MigrationLogger();
            $service = new MenuMigrator($this->getDatabase(), $logger);
            $this->sendJson($service->createMenuItems());
        } catch (\Throwable $e) {
            $this->handleError('MenuController::createMenuItems', $e);
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
