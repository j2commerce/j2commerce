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
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Error-log model — wraps ErrorRepository and exposes filtered/paginated access.
 */
class ErrorsModel extends BaseDatabaseModel
{
    private function repo(): ErrorRepository
    {
        return new ErrorRepository($this->getDatabase());
    }

    /** Returns paginated errors for the specified run. */
    public function getByRun(int $runId, int $limit = 200, int $offset = 0): array
    {
        return $this->repo()->getByRun($runId, $limit, $offset);
    }

    /** Returns the total error count for a run (for pagination). */
    public function countByRun(int $runId): int
    {
        return $this->repo()->countByRun($runId);
    }

    /** Records a new error entry and returns the new PK. */
    public function record(
        int $runId,
        string $adapter,
        string $sourceTable,
        ?int $sourceId,
        ?string $errorCode,
        string $errorMessage,
        ?string $context = null
    ): int {
        return $this->repo()->record($runId, $adapter, $sourceTable, $sourceId, $errorCode, $errorMessage, $context);
    }

    /** Deletes all error rows for a run (called on run deletion). */
    public function deleteByRun(int $runId): void
    {
        $this->repo()->deleteByRun($runId);
    }

    /**
     * Returns a summary of errors grouped by source table for the given run.
     * Useful for the run detail page aggregation panel.
     */
    public function getSummaryByTable(int $runId): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('source_table'),
                'COUNT(*) AS ' . $db->quoteName('error_count'),
            ])
            ->from($db->quoteName('#__j2commerce_migrator_errors'))
            ->where($db->quoteName('run_id') . ' = :run_id')
            ->bind(':run_id', $runId, ParameterType::INTEGER)
            ->group($db->quoteName('source_table'))
            ->order('error_count DESC');

        return $db->setQuery($query)->loadObjectList() ?: [];
    }
}
