<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

final class QueueHelper
{
    private static ?DatabaseInterface $db = null;

    private static function db(): DatabaseInterface
    {
        return self::$db ??= Factory::getContainer()->get(DatabaseInterface::class);
    }

    public static function enqueue(
        string $queueType,
        string $relationId,
        array $data,
        string $itemType = 'order',
        int $priority = 0,
        int $maxAttempts = 10
    ): int {
        $db        = self::db();
        $now       = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $queueData = json_encode($data, JSON_THROW_ON_ERROR);
        $status    = 'pending';
        $attempts  = 0;

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__j2commerce_queues'))
            ->columns($db->quoteName([
                'relation_id', 'queue_type', 'item_type', 'queue_data',
                'priority', 'status', 'attempt_count', 'max_attempts',
                'created_on', 'modified_on',
            ]))
            ->values(':relation_id, :queue_type, :item_type, :queue_data, :priority, :status, :attempt_count, :max_attempts, :created_on, :modified_on')
            ->bind(':relation_id', $relationId)
            ->bind(':queue_type', $queueType)
            ->bind(':item_type', $itemType)
            ->bind(':queue_data', $queueData)
            ->bind(':priority', $priority, ParameterType::INTEGER)
            ->bind(':status', $status)
            ->bind(':attempt_count', $attempts, ParameterType::INTEGER)
            ->bind(':max_attempts', $maxAttempts, ParameterType::INTEGER)
            ->bind(':created_on', $now)
            ->bind(':modified_on', $now);

        $db->setQuery($query)->execute();

        return (int) $db->insertid();
    }

    public static function claimBatch(string $queueType, int $limit = 10, ?string $lockId = null): array
    {
        $db         = self::db();
        $lockId     = $lockId ?? uniqid('queue_', true);
        $now        = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $status     = 'pending';
        $processing = 'processing';

        $selectQuery = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_queue_id'))
            ->from($db->quoteName('#__j2commerce_queues'))
            ->where($db->quoteName('queue_type') . ' = :queue_type')
            ->where($db->quoteName('status') . ' = :status')
            ->where('(' . $db->quoteName('next_attempt_at') . ' IS NULL OR ' . $db->quoteName('next_attempt_at') . ' <= :now)')
            ->order($db->quoteName('priority') . ' DESC')
            ->order($db->quoteName('created_on') . ' ASC')
            ->bind(':queue_type', $queueType)
            ->bind(':status', $status)
            ->bind(':now', $now)
            ->setLimit($limit);

        $db->setQuery($selectQuery);
        $ids = $db->loadColumn();

        if (empty($ids)) {
            return [];
        }

        $updateQuery = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_queues'))
            ->set($db->quoteName('status') . ' = :processing')
            ->set($db->quoteName('locked_at') . ' = :locked_at')
            ->set($db->quoteName('locked_by') . ' = :locked_by')
            ->set($db->quoteName('modified_on') . ' = :modified_on')
            ->whereIn($db->quoteName('j2commerce_queue_id'), $ids)
            ->bind(':processing', $processing)
            ->bind(':locked_at', $now)
            ->bind(':locked_by', $lockId)
            ->bind(':modified_on', $now);

        $db->setQuery($updateQuery)->execute();

        $fetchQuery = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_queues'))
            ->whereIn($db->quoteName('j2commerce_queue_id'), $ids);

        $db->setQuery($fetchQuery);

        return $db->loadObjectList() ?: [];
    }

    public static function complete(int $queueId): void
    {
        $db     = self::db();
        $now    = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $status = 'completed';

        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_queues'))
                ->set($db->quoteName('status') . ' = :status')
                ->set($db->quoteName('processed_at') . ' = :processed_at')
                ->set($db->quoteName('locked_at') . ' = NULL')
                ->set($db->quoteName('locked_by') . ' = NULL')
                ->set($db->quoteName('modified_on') . ' = :modified_on')
                ->where($db->quoteName('j2commerce_queue_id') . ' = :id')
                ->bind(':status', $status)
                ->bind(':processed_at', $now)
                ->bind(':modified_on', $now)
                ->bind(':id', $queueId, ParameterType::INTEGER)
        )->execute();
    }

    public static function fail(int $queueId, string $errorMessage): void
    {
        $db = self::db();

        $item = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['attempt_count', 'max_attempts']))
                ->from($db->quoteName('#__j2commerce_queues'))
                ->where($db->quoteName('j2commerce_queue_id') . ' = :id')
                ->bind(':id', $queueId, ParameterType::INTEGER)
        )->loadObject();

        if ($item === null) {
            return;
        }

        $attemptCount = (int) $item->attempt_count + 1;
        $maxAttempts  = (int) $item->max_attempts;
        $now          = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($attemptCount >= $maxAttempts) {
            $status          = 'dead';
            $nextAttemptAt   = null;
        } else {
            $status        = 'failed';
            $nextAttemptAt = self::calculateBackoff($attemptCount)->format('Y-m-d H:i:s');
        }

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_queues'))
            ->set($db->quoteName('status') . ' = :status')
            ->set($db->quoteName('attempt_count') . ' = :attempt_count')
            ->set($db->quoteName('error_message') . ' = :error_message')
            ->set($db->quoteName('next_attempt_at') . ' = :next_attempt_at')
            ->set($db->quoteName('processed_at') . ' = :processed_at')
            ->set($db->quoteName('locked_at') . ' = NULL')
            ->set($db->quoteName('locked_by') . ' = NULL')
            ->set($db->quoteName('modified_on') . ' = :modified_on')
            ->where($db->quoteName('j2commerce_queue_id') . ' = :id')
            ->bind(':status', $status)
            ->bind(':attempt_count', $attemptCount, ParameterType::INTEGER)
            ->bind(':error_message', $errorMessage)
            ->bind(':next_attempt_at', $nextAttemptAt)
            ->bind(':processed_at', $now)
            ->bind(':modified_on', $now)
            ->bind(':id', $queueId, ParameterType::INTEGER);

        $db->setQuery($query)->execute();
    }

    public static function releaseStale(int $minutes = 30): int
    {
        $db        = self::db();
        $cutoff    = (new \DateTimeImmutable("-{$minutes} minutes"))->format('Y-m-d H:i:s');
        $now       = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $status    = 'processing';
        $newStatus = 'pending';

        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_queues'))
                ->set($db->quoteName('status') . ' = :new_status')
                ->set($db->quoteName('locked_at') . ' = NULL')
                ->set($db->quoteName('locked_by') . ' = NULL')
                ->set($db->quoteName('modified_on') . ' = :modified_on')
                ->where($db->quoteName('status') . ' = :status')
                ->where($db->quoteName('locked_at') . ' <= :cutoff')
                ->bind(':new_status', $newStatus)
                ->bind(':modified_on', $now)
                ->bind(':status', $status)
                ->bind(':cutoff', $cutoff)
        )->execute();

        return $db->getAffectedRows();
    }

    public static function purgeCompleted(int $days = 30): int
    {
        $db     = self::db();
        $cutoff = (new \DateTimeImmutable("-{$days} days"))->format('Y-m-d H:i:s');
        $status = 'completed';

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_queues'))
                ->where($db->quoteName('status') . ' = :status')
                ->where($db->quoteName('processed_at') . ' <= :cutoff')
                ->bind(':status', $status)
                ->bind(':cutoff', $cutoff)
        )->execute();

        return $db->getAffectedRows();
    }

    public static function retryItems(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $db       = self::db();
        $now      = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $pending  = 'pending';
        $attempts = 0;

        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_queues'))
                ->set($db->quoteName('status') . ' = :pending')
                ->set($db->quoteName('attempt_count') . ' = :attempts')
                ->set($db->quoteName('next_attempt_at') . ' = NULL')
                ->set($db->quoteName('error_message') . ' = NULL')
                ->set($db->quoteName('processed_at') . ' = NULL')
                ->set($db->quoteName('locked_at') . ' = NULL')
                ->set($db->quoteName('locked_by') . ' = NULL')
                ->set($db->quoteName('modified_on') . ' = :modified_on')
                ->whereIn($db->quoteName('j2commerce_queue_id'), array_map('intval', $ids))
                ->bind(':pending', $pending)
                ->bind(':attempts', $attempts, ParameterType::INTEGER)
                ->bind(':modified_on', $now)
        )->execute();

        return $db->getAffectedRows();
    }

    public static function purgeDead(): int
    {
        $db   = self::db();
        $dead = 'dead';

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_queues'))
                ->where($db->quoteName('status') . ' = :status')
                ->bind(':status', $dead)
        )->execute();

        return $db->getAffectedRows();
    }

    public static function retryDead(string $queueType): int
    {
        $db       = self::db();
        $now      = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $dead     = 'dead';
        $pending  = 'pending';
        $attempts = 0;

        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_queues'))
                ->set($db->quoteName('status') . ' = :pending')
                ->set($db->quoteName('attempt_count') . ' = :attempts')
                ->set($db->quoteName('next_attempt_at') . ' = NULL')
                ->set($db->quoteName('error_message') . ' = NULL')
                ->set($db->quoteName('processed_at') . ' = NULL')
                ->set($db->quoteName('locked_at') . ' = NULL')
                ->set($db->quoteName('locked_by') . ' = NULL')
                ->set($db->quoteName('modified_on') . ' = :modified_on')
                ->where($db->quoteName('queue_type') . ' = :queue_type')
                ->where($db->quoteName('status') . ' = :dead')
                ->bind(':pending', $pending)
                ->bind(':attempts', $attempts, ParameterType::INTEGER)
                ->bind(':modified_on', $now)
                ->bind(':queue_type', $queueType)
                ->bind(':dead', $dead)
        )->execute();

        return $db->getAffectedRows();
    }

    public static function getStats(): array
    {
        $db = self::db();

        $rows = $db->setQuery(
            $db->getQuery(true)
                ->select([
                    $db->quoteName('queue_type'),
                    $db->quoteName('status'),
                    'COUNT(*) AS ' . $db->quoteName('cnt'),
                ])
                ->from($db->quoteName('#__j2commerce_queues'))
                ->group($db->quoteName('queue_type'))
                ->group($db->quoteName('status'))
                ->order($db->quoteName('queue_type') . ' ASC')
        )->loadObjectList() ?: [];

        $stats = [];

        foreach ($rows as $row) {
            $stats[$row->queue_type][$row->status] = (int) $row->cnt;
        }

        return $stats;
    }

    public static function getQueueById(int $queueId): ?object
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->createQuery()
            ->select('*')
            ->from($db->quoteName('#__j2commerce_queues'))
            ->where($db->quoteName('j2commerce_queue_id') . ' = :id')
            ->bind(':id', $queueId, ParameterType::INTEGER);

        return $db->setQuery($query)->loadObject();
    }

    public static function calculateBackoff(int $attemptCount): \DateTimeImmutable
    {
        $seconds = min((int) (2 ** $attemptCount) * 60, 86400);

        return new \DateTimeImmutable("+{$seconds} seconds");
    }
}
