<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Controller;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\QueueHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Plugin\PluginHelper as JoomlaPluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\Event as GenericEvent;

class QueuesController extends AdminController
{
    protected $text_prefix = 'COM_J2COMMERCE';

    public function getModel($name = 'Queue', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    public function retryFailed(): bool
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.edit.state', 'com_j2commerce')) {
            $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=queues', false));
            return false;
        }

        $cid = (array) $this->input->get('cid', [], 'int');
        $cid = array_filter($cid);

        if (empty($cid)) {
            $this->app->enqueueMessage(Text::_('JLIB_HTML_PLEASE_MAKE_A_SELECTION_FROM_THE_LIST'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=queues', false));
            return false;
        }

        $count = QueueHelper::retryItems($cid);
        $this->app->enqueueMessage(Text::sprintf('COM_J2COMMERCE_QUEUE_N_ITEMS_RETRIED', $count));
        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=queues', false));
        return true;
    }

    public function purgeCompleted(): bool
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.delete', 'com_j2commerce')) {
            $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=queues', false));
            return false;
        }

        $count = QueueHelper::purgeCompleted(30);
        $this->app->enqueueMessage(Text::sprintf('COM_J2COMMERCE_QUEUE_N_ITEMS_PURGED', $count));
        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=queues', false));
        return true;
    }

    public function purgeDead(): bool
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.delete', 'com_j2commerce')) {
            $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=queues', false));
            return false;
        }

        $count = QueueHelper::purgeDead();
        $this->app->enqueueMessage(Text::sprintf('COM_J2COMMERCE_QUEUE_N_ITEMS_PURGED', $count));
        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=queues', false));
        return true;
    }

    public function processNow(): bool
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.edit.state', 'com_j2commerce')) {
            $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=queues', false));
            return false;
        }

        $filters   = $this->input->get('filter', [], 'array');
        $queueType = trim($filters['queue_type'] ?? '');

        if ($queueType === '') {
            $queueType = $this->app->getUserState('com_j2commerce.queues.filter.queue_type', '');
        }

        $redirectUrl = Route::_(
            'index.php?option=com_j2commerce&view=queues'
            . ($queueType !== '' ? '&filter_queue_type=' . rawurlencode($queueType) : ''),
            false
        );

        if ($queueType === '') {
            $this->app->enqueueMessage(Text::_('COM_J2COMMERCE_QUEUE_NO_QUEUE_TYPE_SELECTED'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=queues', false));
            return false;
        }

        QueueHelper::releaseStale(30);

        $items = QueueHelper::claimBatch($queueType, 10);

        if (empty($items)) {
            $this->app->enqueueMessage(Text::sprintf('COM_J2COMMERCE_QUEUE_NO_PENDING_ITEMS', $queueType), 'info');
            $this->setRedirect($redirectUrl);
            return true;
        }

        $db         = Factory::getContainer()->get(DatabaseInterface::class);
        $startedAt  = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $startMs    = (int) (microtime(true) * 1000);
        $logStatus  = 'running';
        $itemsTotal = \count($items);

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__j2commerce_queue_logs'))
            ->columns($db->quoteName(['queue_type', 'started_at', 'status', 'items_total']))
            ->values(':queue_type, :started_at, :status, :items_total')
            ->bind(':queue_type', $queueType)
            ->bind(':started_at', $startedAt)
            ->bind(':status', $logStatus)
            ->bind(':items_total', $itemsTotal, ParameterType::INTEGER);

        $db->setQuery($query)->execute();
        $logId = (int) $db->insertid();

        JoomlaPluginHelper::importPlugin('j2commerce');

        $dispatcher = $this->app->getDispatcher();
        $success    = 0;
        $failed     = 0;
        $skipped    = 0;
        $details    = [];

        foreach ($items as $item) {
            $queueId      = (int) $item->j2commerce_queue_id;
            $processEvent = new GenericEvent('onJ2CommerceQueueProcess', ['item' => $item]);
            $dispatcher->dispatch('onJ2CommerceQueueProcess', $processEvent);

            $currentItem = QueueHelper::getQueueById($queueId);

            if ($currentItem === null || \in_array($currentItem->status, ['completed', 'failed', 'dead'], true)) {
                if ($currentItem !== null && $currentItem->status === 'completed') {
                    $success++;
                } else {
                    $failed++;
                }

                $detail = [
                    'id'          => $queueId,
                    'status'      => $currentItem->status ?? 'deleted',
                    'relation_id' => $currentItem->relation_id ?? '',
                    'item_type'   => $currentItem->item_type ?? '',
                ];

                if (!empty($currentItem->error_message)) {
                    $detail['error'] = $currentItem->error_message;
                }

                $details[] = $detail;
                continue;
            }

            QueueHelper::fail($queueId, 'No handler processed this item');
            $failed++;
            $details[] = [
                'id'          => $queueId,
                'status'      => 'failed',
                'relation_id' => $item->relation_id ?? '',
                'item_type'   => $item->item_type ?? '',
                'error'       => 'No handler processed this item',
            ];
        }

        $endMs       = (int) (microtime(true) * 1000);
        $durationMs  = $endMs - $startMs;
        $finishedAt  = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $finalStatus = 'completed';
        $detailsJson = json_encode($details);

        $query = $db->getQuery(true)
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
        $deleteQuery     = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_queues'))
            ->where($db->quoteName('status') . ' = :status')
            ->bind(':status', $completedStatus);
        $db->setQuery($deleteQuery)->execute();

        $this->app->enqueueMessage(Text::sprintf('COM_J2COMMERCE_QUEUE_N_ITEMS_PROCESSED', $success));
        $this->setRedirect($redirectUrl);
        return true;
    }
}
