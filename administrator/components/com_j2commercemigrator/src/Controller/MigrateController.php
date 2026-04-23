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

use J2Commerce\Component\J2commercemigrator\Administrator\Service\AdapterRegistry;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\ConnectionManager;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\J2CoreMigrator;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\MigrationEngine;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\PostMigrationNormalizer;
use J2Commerce\Component\J2commercemigrator\Administrator\Helper\MigrationLogger;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

/**
 * Migration wizard actions: audit, runTier, migrateTable, getTableCount, resetTier,
 * getStatus, normalizeOrderStatuses, normalizeDates, auditCore, runCoreTier, resetCoreTier.
 */
class MigrateController extends BaseController
{
    protected $default_view = 'migrate';

    public function display($cachable = false, $urlparams = []): static
    {
        $this->enforceAcl();
        return parent::display($cachable, $urlparams);
    }

    /**
     * GET: source + target row counts per tier table for the selected adapter.
     */
    public function audit(): void
    {
        $this->enforceAcl();

        try {
            [$engine, $adapter] = $this->makeEngine();

            if ($adapter === null) {
                $this->sendJson(['error' => Text::_('COM_J2COMMERCEMIGRATOR_ERR_GENERIC')]);
                return;
            }

            $this->sendJson($engine->audit($adapter));
        } catch (\Throwable $e) {
            $this->handleError('MigrateController::audit', $e);
        }
    }

    /**
     * POST: migrates every table in the specified tier.
     */
    public function runTier(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $input        = Factory::getApplication()->getInput();
            $tier         = $input->getInt('tier', 0);
            $batchSize    = $input->getInt('batch_size', 200);
            $conflictMode = $input->getCmd('conflict_mode', 'skip');

            [$engine, $adapter] = $this->makeEngine();

            if ($adapter === null) {
                $this->sendJson(['error' => Text::_('COM_J2COMMERCEMIGRATOR_ERR_GENERIC')]);
                return;
            }

            $this->sendJson($engine->runTier($adapter, $tier, $batchSize, $conflictMode));
        } catch (\Throwable $e) {
            $this->handleError('MigrateController::runTier', $e);
        }
    }

    /**
     * POST: single-table paginated migration (browser loops until done).
     */
    public function migrateTable(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $input        = Factory::getApplication()->getInput();
            $sourceTable  = $input->getString('source_table', '');
            $batchSize    = $input->getInt('batch_size', 200);
            $conflictMode = $input->getCmd('conflict_mode', 'skip');
            $offset       = $input->getInt('offset', 0);

            [$engine, $adapter] = $this->makeEngine();

            if ($adapter === null) {
                $this->sendJson(['error' => Text::_('COM_J2COMMERCEMIGRATOR_ERR_GENERIC')]);
                return;
            }

            $this->sendJson($engine->migrateOneTable($adapter, $sourceTable, $batchSize, $conflictMode, $offset));
        } catch (\Throwable $e) {
            $this->handleError('MigrateController::migrateTable', $e);
        }
    }

    /**
     * GET: scalar row count for one source table.
     */
    public function getTableCount(): void
    {
        $this->enforceAcl();

        try {
            $app         = Factory::getApplication();
            $input       = $app->getInput();
            $sourceTable = $input->getString('source_table', '');
            $adapterKey  = $input->getCmd('adapter', '');

            $registry = new AdapterRegistry();
            $adapter  = $registry->get($adapterKey);

            if (!$adapter) {
                $this->sendJson(['error' => "Unknown adapter: {$adapterKey}"]);
                return;
            }

            $tableMap = $adapter->getTableMap();

            if (!isset($tableMap[$sourceTable])) {
                $this->sendJson(['error' => "Unknown table: {$sourceTable}"]);
                return;
            }

            $connMgr = new ConnectionManager($app, $this->getDatabase());
            $reader  = $connMgr->getReader();

            $this->sendJson([
                'success' => true,
                'data'    => [
                    'source_table' => $sourceTable,
                    'target_table' => $tableMap[$sourceTable],
                    'source_count' => $reader->count($sourceTable),
                ],
            ]);
        } catch (\Throwable $e) {
            $this->handleError('MigrateController::getTableCount', $e);
        }
    }

    /**
     * POST: TRUNCATE every target table in the specified tier.
     */
    public function resetTier(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $input      = Factory::getApplication()->getInput();
            $tier       = $input->getInt('tier', 0);
            $adapterKey = $input->getCmd('adapter', '');

            $registry = new AdapterRegistry();
            $adapter  = $registry->get($adapterKey);

            if (!$adapter) {
                $this->sendJson(['error' => "Unknown adapter: {$adapterKey}"]);
                return;
            }

            $logger = new MigrationLogger();
            $engine = new MigrationEngine($this->getDatabase(), $logger);

            $this->sendJson($engine->resetTier($adapter, $tier));
        } catch (\Throwable $e) {
            $this->handleError('MigrateController::resetTier', $e);
        }
    }

    /**
     * GET: live tier progress for the selected adapter.
     */
    public function getStatus(): void
    {
        $this->enforceAcl();

        try {
            [$engine, $adapter] = $this->makeEngine();

            if ($adapter === null) {
                $this->sendJson(['error' => Text::_('COM_J2COMMERCEMIGRATOR_ERR_GENERIC')]);
                return;
            }

            $this->sendJson($engine->getProgress($adapter));
        } catch (\Throwable $e) {
            $this->handleError('MigrateController::getStatus', $e);
        }
    }

    /**
     * POST: rewrites Bootstrap 2 order-status CSS classes to Bootstrap 5 badge classes.
     */
    public function normalizeOrderStatuses(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $logger = new MigrationLogger();
            $engine = new MigrationEngine($this->getDatabase(), $logger);
            $this->sendJson($engine->normalizeOrderStatusCssClasses());
        } catch (\Throwable $e) {
            $this->handleError('MigrateController::normalizeOrderStatuses', $e);
        }
    }

    /**
     * POST: replaces 0000-00-00 date defaults with NULL.
     */
    public function normalizeDates(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $logger     = new MigrationLogger();
            $normalizer = new PostMigrationNormalizer($this->getDatabase(), $logger);
            $this->sendJson($normalizer->normalizeDateColumnDefaults());
        } catch (\Throwable $e) {
            $this->handleError('MigrateController::normalizeDates', $e);
        }
    }

    /**
     * GET: row counts for Joomla core tiers (9–12).
     */
    public function auditCore(): void
    {
        $this->enforceAcl();

        try {
            $logger  = new MigrationLogger();
            $core    = new J2CoreMigrator($this->getDatabase(), $logger);
            $this->sendJson($core->audit());
        } catch (\Throwable $e) {
            $this->handleError('MigrateController::auditCore', $e);
        }
    }

    /**
     * POST: runs a Joomla core tier (9–12).
     */
    public function runCoreTier(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $input        = Factory::getApplication()->getInput();
            $tier         = $input->getInt('tier', 0);
            $batchSize    = $input->getInt('batch_size', 200);
            $conflictMode = $input->getCmd('conflict_mode', 'skip');

            $logger = new MigrationLogger();
            $app    = Factory::getApplication();
            $connMgr = new ConnectionManager($app, $this->getDatabase());
            $core   = new J2CoreMigrator($this->getDatabase(), $logger, $connMgr->getReader());

            $this->sendJson($core->runTier($tier, $batchSize, $conflictMode));
        } catch (\Throwable $e) {
            $this->handleError('MigrateController::runCoreTier', $e);
        }
    }

    /**
     * POST: truncates target tables for a Joomla core tier.
     */
    public function resetCoreTier(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $input  = Factory::getApplication()->getInput();
            $tier   = $input->getInt('tier', 0);
            $logger = new MigrationLogger();
            $core   = new J2CoreMigrator($this->getDatabase(), $logger);
            $this->sendJson($core->resetTier($tier));
        } catch (\Throwable $e) {
            $this->handleError('MigrateController::resetCoreTier', $e);
        }
    }

    private function makeEngine(): array
    {
        $app        = Factory::getApplication();
        $input      = $app->getInput();
        $adapterKey = $input->getCmd('adapter', '');

        $registry = new AdapterRegistry();
        $adapter  = $registry->get($adapterKey);

        if (!$adapter) {
            return [null, null];
        }

        $logger  = new MigrationLogger();
        $connMgr = new ConnectionManager($app, $this->getDatabase());
        $engine  = new MigrationEngine($this->getDatabase(), $logger, $connMgr->getReader());

        return [$engine, $adapter];
    }

    private function enforceAcl(): void
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || !$user->authorise('core.manage', 'com_j2commercemigrator')) {
            $this->sendJson(['error' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]);
        }
    }

    private function enforceToken(): void
    {
        if (!Session::checkToken('get') && !Session::checkToken('post')) {
            $this->sendJson(['error' => Text::_('JINVALID_TOKEN')]);
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
