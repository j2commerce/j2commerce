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

use J2Commerce\Component\J2commercemigrator\Administrator\Service\ErrorRepository;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\PreflightRepository;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\RunRepository;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Single-run detail model: load a run with its errors and preflight results.
 */
class RunModel extends BaseDatabaseModel
{
    protected function populateState(): void
    {
        $pk = Factory::getApplication()->getInput()->getInt('id', 0);
        $this->setState('run.id', $pk);
    }

    public function getItem(): ?object
    {
        $runId = (int) $this->getState('run.id', 0);

        if ($runId < 1) {
            return null;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_migrator_runs'))
            ->where($db->quoteName('j2commerce_migrator_run_id') . ' = :id')
            ->bind(':id', $runId, ParameterType::INTEGER);

        return $db->setQuery($query)->loadObject() ?: null;
    }

    /** Returns paginated errors for the current run. */
    public function getErrors(int $limit = 200, int $offset = 0): array
    {
        $runId = (int) $this->getState('run.id', 0);

        if ($runId < 1) {
            return [];
        }

        $db   = Factory::getContainer()->get(DatabaseInterface::class);
        $repo = new ErrorRepository($db);

        return $repo->getByRun($runId, $limit, $offset);
    }

    /** Returns all preflight results for the current run. */
    public function getPreflightResults(): array
    {
        $runId = (int) $this->getState('run.id', 0);

        if ($runId < 1) {
            return [];
        }

        $db   = Factory::getContainer()->get(DatabaseInterface::class);
        $repo = new PreflightRepository($db);

        return $repo->getByRun($runId);
    }

    /** Deletes a run record and its associated errors + preflight rows. */
    public function delete(int $runId): bool
    {
        $db       = $this->getDatabase();
        $errorRepo = new ErrorRepository($db);
        $preRepo   = new PreflightRepository($db);
        $runRepo   = new RunRepository($db);

        $errorRepo->deleteByRun($runId);
        $preRepo->deleteByRun($runId);
        $runRepo->delete($runId);

        return true;
    }
}
