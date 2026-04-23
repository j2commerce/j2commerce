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

use J2Commerce\Component\J2commercemigrator\Administrator\Service\AdapterRegistry;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\ConnectionManager;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\MigrationEngine;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\PreflightAnalyzer;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\RunRepository;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\SchemaIntrospector;
use J2Commerce\Component\J2commercemigrator\Administrator\Helper\MigrationLogger;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

/**
 * Namespaced AJAX endpoint: index.php?option=com_j2commercemigrator&task=api.run&action=...
 * All responses are JSON. All requests require core.admin + CSRF token.
 */
class ApiController extends BaseController
{
    public function run(): void
    {
        $app = Factory::getApplication();

        // CSRF check
        if (!Session::checkToken('get') && !Session::checkToken('post')) {
            $this->sendJson(['error' => Text::_('JINVALID_TOKEN')]);
            return;
        }

        $user = $app->getIdentity();

        if (!$user || !$user->authorise('core.admin', 'com_j2commercemigrator')) {
            $this->sendJson(['error' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]);
            return;
        }

        $input  = $app->getInput();
        $action = $input->getCmd('action', '');

        try {
            $db      = $this->getDatabase();
            $logger  = new MigrationLogger();
            $connMgr = new ConnectionManager($app, $db);
            $registry = new AdapterRegistry();

            $connectionActions = ['connection.verify', 'connection.clear', 'connection.get', 'plugins.list'];

            if (!in_array($action, $connectionActions, true) && !$connMgr->isReady()) {
                $this->sendJson(['error' => Text::_('COM_J2COMMERCEMIGRATOR_ERR_SESSION_EXPIRED')]);
                return;
            }

            $result = match ($action) {
                'connection.verify'         => $this->handleConnectionVerify($connMgr, $input),
                'connection.clear'          => $this->handleConnectionClear($connMgr),
                'connection.get'            => $this->handleConnectionGet($connMgr),
                'plugins.list'              => $this->handlePluginsList($registry),
                'migrate.audit'             => $this->handleAudit($registry, $connMgr, $db, $logger, $input),
                'migrate.runTier'           => $this->handleRunTier($registry, $connMgr, $db, $logger, $input),
                'migrate.getStatus'         => $this->handleGetStatus($registry, $connMgr, $db, $logger, $input),
                'migrate.resetTier'         => $this->handleResetTier($registry, $db, $logger, $input),
                'migrate.getTableCount'     => $this->handleGetTableCount($registry, $connMgr, $db, $logger, $input),
                'migrate.migrateTable'      => $this->handleMigrateTable($registry, $connMgr, $db, $logger, $input),
                'migrate.normalizeStatuses' => $this->handleNormalizeStatuses($db, $logger),
                'migrate.discover'          => $this->handleDiscover($registry, $connMgr, $db),
                default                     => ['error' => "Unknown action: {$action}"],
            };

            $this->sendJson($result);
        } catch (\Throwable $e) {
            if (defined('JDEBUG') && JDEBUG) {
                $this->sendJson(['error' => $e->getMessage()]);
            } else {
                $this->sendJson(['error' => Text::_('COM_J2COMMERCEMIGRATOR_ERR_GENERIC')]);
            }
        }
    }

    private function handleConnectionVerify(ConnectionManager $connMgr, $input): array
    {
        if ($input->getMethod() !== 'POST') {
            return ['ok' => false, 'category' => 'post_required'];
        }

        $creds = [
            'mode'     => $input->get('mode', 'A', 'cmd'),
            'host'     => $input->get('host', '', 'string'),
            'port'     => $input->getInt('port', 3306),
            'database' => $input->get('database', '', 'string'),
            'username' => $input->get('username', '', 'string'),
            'password' => $input->get('password', '', 'raw'),
            'prefix'   => $input->get('prefix', 'jos_', 'string'),
            'ssl'      => $input->getBool('ssl', false),
            'ssl_ca'   => $input->get('ssl_ca', '', 'string'),
        ];

        return $connMgr->verify($creds);
    }

    private function handleConnectionClear(ConnectionManager $connMgr): array
    {
        $connMgr->clear();
        return ['ok' => true];
    }

    private function handleConnectionGet(ConnectionManager $connMgr): array
    {
        return [
            'ok'           => true,
            'status'       => $connMgr->getStatus(),
            'pdoAvailable' => extension_loaded('pdo_mysql'),
        ];
    }

    private function handlePluginsList(AdapterRegistry $registry): array
    {
        $adapters = [];

        foreach ($registry->getAll() as $adapter) {
            $info       = $adapter->getSourceInfo();
            $adapters[] = [
                'key'         => $adapter->getKey(),
                'title'       => $info->title,
                'description' => $info->description,
                'icon'        => $info->icon,
                'author'      => $info->author,
                'version'     => $info->version,
            ];
        }

        return ['ok' => true, 'adapters' => $adapters];
    }

    private function handleAudit(AdapterRegistry $registry, ConnectionManager $connMgr, $db, MigrationLogger $logger, $input): array
    {
        $adapterKey = $input->getCmd('adapter', '');
        $adapter    = $registry->get($adapterKey);

        if (!$adapter) {
            return ['error' => "Unknown adapter: {$adapterKey}"];
        }

        $engine = new MigrationEngine($db, $logger, $connMgr->getReader());
        return $engine->audit($adapter);
    }

    private function handleRunTier(AdapterRegistry $registry, ConnectionManager $connMgr, $db, MigrationLogger $logger, $input): array
    {
        $adapterKey   = $input->getCmd('adapter', '');
        $tier         = $input->getInt('tier', 0);
        $batchSize    = $input->getInt('batch_size', 200);
        $conflictMode = $input->getCmd('conflict_mode', 'skip');
        $adapter      = $registry->get($adapterKey);

        if (!$adapter) {
            return ['error' => "Unknown adapter: {$adapterKey}"];
        }

        $engine = new MigrationEngine($db, $logger, $connMgr->getReader());
        return $engine->runTier($adapter, $tier, $batchSize, $conflictMode);
    }

    private function handleGetStatus(AdapterRegistry $registry, ConnectionManager $connMgr, $db, MigrationLogger $logger, $input): array
    {
        $adapterKey = $input->getCmd('adapter', '');
        $adapter    = $registry->get($adapterKey);

        if (!$adapter) {
            return ['error' => "Unknown adapter: {$adapterKey}"];
        }

        $engine = new MigrationEngine($db, $logger, $connMgr->getReader());
        return $engine->getProgress($adapter);
    }

    private function handleResetTier(AdapterRegistry $registry, $db, MigrationLogger $logger, $input): array
    {
        $adapterKey = $input->getCmd('adapter', '');
        $tier       = $input->getInt('tier', 0);
        $adapter    = $registry->get($adapterKey);

        if (!$adapter) {
            return ['error' => "Unknown adapter: {$adapterKey}"];
        }

        $engine = new MigrationEngine($db, $logger);
        return $engine->resetTier($adapter, $tier);
    }

    private function handleGetTableCount(AdapterRegistry $registry, ConnectionManager $connMgr, $db, MigrationLogger $logger, $input): array
    {
        $adapterKey  = $input->getCmd('adapter', '');
        $sourceTable = $input->getString('source_table', '');
        $adapter     = $registry->get($adapterKey);

        if (!$adapter) {
            return ['error' => "Unknown adapter: {$adapterKey}"];
        }

        $tableMap = $adapter->getTableMap();

        if (!isset($tableMap[$sourceTable])) {
            return ['error' => "Unknown table: {$sourceTable}"];
        }

        $reader      = $connMgr->getReader();
        $sourceCount = $reader->count($sourceTable);

        return [
            'source_table' => $sourceTable,
            'target_table' => $tableMap[$sourceTable],
            'source_count' => $sourceCount,
        ];
    }

    private function handleMigrateTable(AdapterRegistry $registry, ConnectionManager $connMgr, $db, MigrationLogger $logger, $input): array
    {
        $adapterKey   = $input->getCmd('adapter', '');
        $sourceTable  = $input->getString('source_table', '');
        $batchSize    = $input->getInt('batch_size', 200);
        $conflictMode = $input->getCmd('conflict_mode', 'skip');
        $offset       = $input->getInt('offset', 0);
        $adapter      = $registry->get($adapterKey);

        if (!$adapter) {
            return ['error' => "Unknown adapter: {$adapterKey}"];
        }

        $engine = new MigrationEngine($db, $logger, $connMgr->getReader());
        return $engine->migrateOneTable($adapter, $sourceTable, $batchSize, $conflictMode, $offset);
    }

    private function handleNormalizeStatuses($db, MigrationLogger $logger): array
    {
        $engine = new MigrationEngine($db, $logger);
        return $engine->normalizeOrderStatusCssClasses();
    }

    private function handleDiscover(AdapterRegistry $registry, ConnectionManager $connMgr, $db): array
    {
        $adapterKey = Factory::getApplication()->getInput()->getCmd('adapter', '');
        $adapter    = $registry->get($adapterKey);

        if (!$adapter) {
            return ['error' => "Unknown adapter: {$adapterKey}"];
        }

        $introspector = new SchemaIntrospector($db);
        return $introspector->discover($adapter, $connMgr->getReader());
    }

    private function sendJson(array $data): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $app->close();
    }
}
