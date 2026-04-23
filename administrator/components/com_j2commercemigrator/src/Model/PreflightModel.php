<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\Model;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commercemigrator\Administrator\Service\AdapterRegistry;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\ConnectionManager;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\PreflightAnalyzer;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\PreflightRepository;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;

/**
 * Preflight analysis model — delegates to PreflightAnalyzer and persists
 * results to #__j2commerce_migrator_preflight via the analyzer's runId parameter.
 */
class PreflightModel extends BaseDatabaseModel
{
    private function analyzer(): PreflightAnalyzer
    {
        $db      = $this->getDatabase();
        $app     = Factory::getApplication();
        $connMgr = new ConnectionManager($app, $db);

        return new PreflightAnalyzer($db, $connMgr->getReader());
    }

    /** Runs all preflight checks for the given adapter. Pass $runId to persist results. */
    public function analyzeAll(string $adapterKey, ?int $runId = null): array
    {
        $adapter = (new AdapterRegistry())->get($adapterKey);

        if (!$adapter) {
            return ['error' => "Unknown adapter: {$adapterKey}"];
        }

        return $this->analyzer()->analyzeAll($adapter, $runId);
    }

    /** Runs preflight for a single tier. Pass $runId to persist results. */
    public function analyzeTier(string $adapterKey, int $tier, ?int $runId = null): array
    {
        $adapter = (new AdapterRegistry())->get($adapterKey);

        if (!$adapter) {
            return ['error' => "Unknown adapter: {$adapterKey}"];
        }

        return $this->analyzer()->analyzeTier($adapter, $tier, $runId);
    }

    /** Returns all persisted preflight rows for a run. */
    public function getByRun(int $runId): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        return (new PreflightRepository($db))->getByRun($runId);
    }

    /** Returns pass/warn/fail/skip counts for a run. */
    public function getStatusSummary(int $runId): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        return (new PreflightRepository($db))->getStatusSummary($runId);
    }
}
