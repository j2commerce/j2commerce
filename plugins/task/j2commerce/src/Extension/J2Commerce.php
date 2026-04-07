<?php

declare(strict_types=1);

/**
 * @package     J2Commerce
 * @subpackage  plg_task_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Plugin\Task\J2Commerce\Extension;

use J2Commerce\Component\J2commerce\Administrator\Helper\QueueHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\Event as GenericEvent;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

final class J2Commerce extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use TaskPluginTrait;

    private const TASKS_MAP = [
        'j2commerce.removeNewOrders' => [
            'langConstPrefix' => 'PLG_TASK_J2COMMERCE_REMOVE_NEW_ORDERS',
            'method'          => 'removeNewOrders',
            'form'            => 'removeNewOrders',
        ],
        'j2commerce.processQueue' => [
            'langConstPrefix' => 'PLG_TASK_J2COMMERCE_PROCESS_QUEUE',
            'method'          => 'processQueue',
            'form'            => 'processQueue',
        ],
        'j2commerce.cleanupQueueLogs' => [
            'langConstPrefix' => 'PLG_TASK_J2COMMERCE_CLEANUP_QUEUE_LOGS',
            'method'          => 'cleanupQueueLogs',
            'form'            => 'cleanupQueueLogs',
        ],
        'j2commerce.updateCurrencyRates' => [
            'langConstPrefix' => 'PLG_TASK_J2COMMERCE_UPDATE_CURRENCY_RATES',
            'method'          => 'updateCurrencyRates',
            'form'            => 'updateCurrencyRates',
        ],
    ];

    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    private function removeNewOrders(ExecuteTaskEvent $event): int
    {
        $params        = $event->getArgument('params');
        $olderThanDays = (int) ($params->older_than_days ?? 30);
        $dryRun        = (int) ($params->dry_run ?? 1);

        $db    = $this->getDatabase();
        $query = $db->createQuery();
        $now   = $db->quote((new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'));

        $stateId = 5;
        $query->select($db->quoteName(['j2commerce_order_id', 'order_id']))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('order_state_id') . ' = :stateId')
            ->bind(':stateId', $stateId, ParameterType::INTEGER);

        if ($olderThanDays > 0) {
            $days = -1 * $olderThanDays;
            $query->where(
                $db->quoteName('created_on') . ' < ' . $query->dateAdd($now, $days, 'DAY')
            );
        }

        $db->setQuery($query);
        $orders = $db->loadObjectList();

        if (empty($orders)) {
            $this->logTask('No abandoned orders found matching criteria.');
            return Status::OK;
        }

        $orderIds    = array_column($orders, 'j2commerce_order_id');
        $orderVarIds = array_column($orders, 'order_id');
        $count       = \count($orderIds);

        $this->logTask(\sprintf(
            '%s %d abandoned order(s): %s',
            $dryRun ? '[DRY RUN] Would delete' : 'Deleting',
            $count,
            implode(', ', $orderVarIds)
        ));

        if ($dryRun) {
            $this->logTask('Dry run complete. No records were deleted.');
            return Status::OK;
        }

        // Build quoted value lists for IN clauses (values from our own trusted query)
        $quotedOrderVarIds = implode(',', array_map([$db, 'quote'], $orderVarIds));
        $quotedOrderIds    = implode(',', array_map('intval', $orderIds));

        // Fetch orderitem IDs before transaction so subquery bindings aren't lost
        $query = $db->createQuery()
            ->select($db->quoteName('j2commerce_orderitem_id'))
            ->from($db->quoteName('#__j2commerce_orderitems'))
            ->where($db->quoteName('order_id') . ' IN (' . $quotedOrderVarIds . ')');
        $orderitemIds = $db->setQuery($query)->loadColumn();

        $db->transactionStart();

        try {
            // Delete orderitemattributes first (FK: orderitem_id -> orderitems)
            if (!empty($orderitemIds)) {
                $quotedItemIds = implode(',', array_map('intval', $orderitemIds));
                $db->setQuery(
                    $db->createQuery()
                        ->delete($db->quoteName('#__j2commerce_orderitemattributes'))
                        ->where($db->quoteName('orderitem_id') . ' IN (' . $quotedItemIds . ')')
                )->execute();
            }

            // Delete from tables linked by order_id (varchar)
            $orderIdTables = [
                '#__j2commerce_orderitems',
                '#__j2commerce_orderinfos',
                '#__j2commerce_orderhistories',
                '#__j2commerce_ordershippings',
                '#__j2commerce_orderdiscounts',
                '#__j2commerce_orderfees',
                '#__j2commerce_orderdownloads',
                '#__j2commerce_ordertaxes',
            ];

            foreach ($orderIdTables as $table) {
                $db->setQuery(
                    $db->createQuery()
                        ->delete($db->quoteName($table))
                        ->where($db->quoteName('order_id') . ' IN (' . $quotedOrderVarIds . ')')
                )->execute();
            }

            // Delete the orders themselves
            $db->setQuery(
                $db->createQuery()
                    ->delete($db->quoteName('#__j2commerce_orders'))
                    ->where($db->quoteName('j2commerce_order_id') . ' IN (' . $quotedOrderIds . ')')
            )->execute();

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            $this->logTask(\sprintf('ERROR: %s', $e->getMessage()));
            return Status::KNOCKOUT;
        }

        $this->logTask(\sprintf('Successfully deleted %d abandoned order(s).', $count));

        return Status::OK;
    }

    private function processQueue(ExecuteTaskEvent $event): int
    {
        $params               = $event->getArgument('params');
        $queueType            = (string) ($params->queue_type ?? '');
        $batchSize            = (int) ($params->batch_size ?? 10);
        $releaseStaleMinutes  = (int) ($params->release_stale_minutes ?? 30);

        QueueHelper::releaseStale($releaseStaleMinutes);

        $items = QueueHelper::claimBatch($queueType ?: '', $batchSize);

        if (empty($items)) {
            $this->logTask('No pending queue items found.');
            return Status::OK;
        }

        $db        = $this->getDatabase();
        $startedAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $startMs   = (int) (microtime(true) * 1000);

        $logQueueType = $queueType ?: 'all';
        $logStatus    = 'running';
        $itemsTotal   = \count($items);

        $query = $db->createQuery()
            ->insert($db->quoteName('#__j2commerce_queue_logs'))
            ->columns($db->quoteName(['queue_type', 'started_at', 'status', 'items_total']))
            ->values(':queue_type, :started_at, :status, :items_total')
            ->bind(':queue_type', $logQueueType)
            ->bind(':started_at', $startedAt)
            ->bind(':status', $logStatus)
            ->bind(':items_total', $itemsTotal, ParameterType::INTEGER);

        $db->setQuery($query)->execute();
        $logId = (int) $db->insertid();

        $success  = 0;
        $failed   = 0;
        $skipped  = 0;
        $details  = [];

        $dispatcher = Factory::getApplication()->getDispatcher();

        foreach ($items as $item) {
            $queueId = (int) $item->j2commerce_queue_id;

            $processEvent = new GenericEvent('onJ2CommerceQueueProcess', ['item' => $item]);
            $dispatcher->dispatch('onJ2CommerceQueueProcess', $processEvent);

            $currentItem = QueueHelper::getQueueById($queueId);

            if ($currentItem === null || \in_array($currentItem->status, ['completed', 'failed', 'dead'], true)) {
                if ($currentItem !== null && $currentItem->status === 'completed') {
                    $success++;
                } else {
                    $failed++;
                }

                $details[] = ['id' => $queueId, 'status' => $currentItem->status ?? 'deleted'];
                continue;
            }

            QueueHelper::fail($queueId, 'No handler processed this item');
            $failed++;
            $details[] = ['id' => $queueId, 'status' => 'failed', 'error' => 'No handler processed this item'];
        }

        $endMs       = (int) (microtime(true) * 1000);
        $durationMs  = $endMs - $startMs;
        $finishedAt  = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $finalStatus = 'completed';
        $detailsJson = json_encode($details);

        $query = $db->createQuery()
            ->update($db->quoteName('#__j2commerce_queue_logs'))
            ->set($db->quoteName('finished_at') . ' = :finished_at')
            ->set($db->quoteName('duration_ms') . ' = :duration_ms')
            ->set($db->quoteName('items_total') . ' = :items_total')
            ->set($db->quoteName('items_success') . ' = :items_success')
            ->set($db->quoteName('items_failed') . ' = :items_failed')
            ->set($db->quoteName('items_skipped') . ' = :items_skipped')
            ->set($db->quoteName('status') . ' = :status')
            ->set($db->quoteName('details') . ' = :details')
            ->where($db->quoteName('j2commerce_queue_log_id') . ' = :log_id')
            ->bind(':finished_at', $finishedAt)
            ->bind(':duration_ms', $durationMs, ParameterType::INTEGER)
            ->bind(':items_total', $itemsTotal, ParameterType::INTEGER)
            ->bind(':items_success', $success, ParameterType::INTEGER)
            ->bind(':items_failed', $failed, ParameterType::INTEGER)
            ->bind(':items_skipped', $skipped, ParameterType::INTEGER)
            ->bind(':status', $finalStatus)
            ->bind(':details', $detailsJson)
            ->bind(':log_id', $logId, ParameterType::INTEGER);

        $db->setQuery($query)->execute();

        $completedStatus = 'completed';
        $deleteQuery     = $db->createQuery()
            ->delete($db->quoteName('#__j2commerce_queues'))
            ->where($db->quoteName('status') . ' = :status')
            ->bind(':status', $completedStatus);
        $db->setQuery($deleteQuery)->execute();
        $deleted = $db->getAffectedRows();

        $this->logTask(\sprintf(
            'Processed %d item(s): %d success, %d failed, %d skipped. Removed %d completed. Duration: %dms.',
            $itemsTotal,
            $success,
            $failed,
            $skipped,
            $deleted,
            $durationMs
        ));

        return Status::OK;
    }

    private function cleanupQueueLogs(ExecuteTaskEvent $event): int
    {
        $params                   = $event->getArgument('params');
        $olderThanDays            = (int) ($params->older_than_days ?? 90);
        $purgeCompletedQueueDays  = (int) ($params->purge_completed_queue_days ?? 30);
        $dryRun                   = (int) ($params->dry_run ?? 1);

        $db          = $this->getDatabase();
        $logCutoff   = (new \DateTimeImmutable("now -{$olderThanDays} days", new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $queueCutoff = (new \DateTimeImmutable("now -{$purgeCompletedQueueDays} days", new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $query = $db->createQuery()
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_queue_logs'))
            ->where($db->quoteName('created_on') . ' < :log_cutoff')
            ->bind(':log_cutoff', $logCutoff);
        $logCount = (int) $db->setQuery($query)->loadResult();

        $query = $db->createQuery()
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_queues'))
            ->where($db->quoteName('status') . ' = ' . $db->quote('completed'))
            ->where($db->quoteName('modified_on') . ' < :queue_cutoff')
            ->bind(':queue_cutoff', $queueCutoff);
        $queueCount = (int) $db->setQuery($query)->loadResult();

        $this->logTask(\sprintf(
            '%s %d log entries older than %d days and %d completed queue items older than %d days.',
            $dryRun ? '[DRY RUN] Would delete' : 'Deleting',
            $logCount,
            $olderThanDays,
            $queueCount,
            $purgeCompletedQueueDays
        ));

        if ($dryRun) {
            $this->logTask('Dry run complete. No records were deleted.');
            return Status::OK;
        }

        $db->setQuery(
            $db->createQuery()
                ->delete($db->quoteName('#__j2commerce_queue_logs'))
                ->where($db->quoteName('created_on') . ' < :log_cutoff')
                ->bind(':log_cutoff', $logCutoff)
        )->execute();

        QueueHelper::purgeCompleted($purgeCompletedQueueDays);

        $this->logTask(\sprintf(
            'Deleted %d log entries and %d completed queue items.',
            $logCount,
            $queueCount
        ));

        return Status::OK;
    }

    private function updateCurrencyRates(ExecuteTaskEvent $event): int
    {
        PluginHelper::importPlugin('j2commerce');

        $dispatcher  = Factory::getApplication()->getDispatcher();
        $updateEvent = new GenericEvent('onJ2CommerceUpdateCurrencies', []);
        $dispatcher->dispatch('onJ2CommerceUpdateCurrencies', $updateEvent);

        $result = $updateEvent->getArgument('result', null);

        if ($result === null) {
            $this->logTask('Currency updater plugin is not enabled or did not respond.');
            return Status::NO_RUN;
        }

        $this->logTask(\sprintf(
            'Updated %d rate(s), %d failed. %s',
            $result['updated'],
            $result['failed'],
            !empty($result['errors']) ? 'Errors: ' . implode('; ', $result['errors']) : ''
        ));

        return $result['failed'] > 0 && $result['updated'] === 0
            ? Status::KNOCKOUT
            : Status::OK;
    }
}
