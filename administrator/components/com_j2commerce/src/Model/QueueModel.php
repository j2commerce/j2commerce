<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;

/**
 * Queue item model class.
 *
 * Handles single queue item operations: load, save, delete.
 * Used for background job management (emails, webhooks, inventory updates, etc.)
 *
 * @since  6.0.0
 */
class QueueModel extends AdminModel
{
    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  6.0.0
     */
    public $typeAlias = 'com_j2commerce.queue';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $text_prefix = 'COM_J2COMMERCE_QUEUE';

    /**
     * Method to auto-populate the model state.
     *
     * CRITICAL: Override required because parent AdminModel::populateState()
     * looks for a URL parameter matching table's primary key (j2commerce_queue_id),
     * but standard Joomla URLs use 'id'.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function populateState(): void
    {
        $app = Factory::getApplication();

        // Get the primary key from URL param 'id', NOT from table's column name
        $pk = $app->getInput()->getInt('id', 0);
        $this->setState($this->getName() . '.id', $pk);

        // Load the component parameters
        $params = ComponentHelper::getParams('com_j2commerce');
        $this->setState('params', $params);
    }

    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  Form|false  A Form object on success, false on failure
     *
     * @since   6.0.0
     */
    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_j2commerce.queue', 'queue', ['control' => 'jform', 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Method to get a table object.
     *
     * @param   string  $name     The table name. Optional.
     * @param   string  $prefix   The class prefix. Optional.
     * @param   array   $options  Configuration array for model. Optional.
     *
     * @return  Table  A Table object
     *
     * @since   6.0.0
     */
    public function getTable($name = 'Queue', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   6.0.0
     */
    protected function loadFormData(): mixed
    {
        $app  = Factory::getApplication();
        $data = $app->getUserState('com_j2commerce.edit.queue.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        return $data;
    }

    /**
     * Prepare and sanitize the table before saving.
     *
     * @param   Table  $table  The Table object.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function prepareTable($table): void
    {
        // Trim string fields
        if (!empty($table->relation_id)) {
            $table->relation_id = trim($table->relation_id);
        }

        if (!empty($table->queue_type)) {
            $table->queue_type = trim($table->queue_type);
        }

        if (!empty($table->status)) {
            $table->status = trim($table->status);
        }

        // Update modified timestamp
        $table->modified_on = date('Y-m-d H:i:s');

        // Set created timestamp for new records
        if (empty($table->j2commerce_queue_id) && empty($table->created_on)) {
            $table->created_on = date('Y-m-d H:i:s');
        }
    }

    /**
     * Add a new item to the queue.
     *
     * Convenience method for programmatic queue insertion.
     *
     * @param   string       $queueType   The type of queue item (e.g., 'email', 'webhook')
     * @param   string       $relationId  Related entity ID (e.g., order_id, customer_id)
     * @param   mixed        $data        Queue data (will be JSON encoded if array/object)
     * @param   int          $priority    Priority level (higher = processed first)
     * @param   string|null  $expired     Expiration datetime (null = no expiration)
     * @param   array        $params      Additional parameters
     *
     * @return  int|false  The queue ID on success, false on failure
     *
     * @since   6.0.0
     */
    public function addToQueue(
        string $queueType,
        string $relationId,
        mixed $data,
        int $priority = 0,
        ?string $expired = null,
        array $params = []
    ): int|false {
        $table = $this->getTable();

        $queueData = [
            'queue_type'   => $queueType,
            'relation_id'  => $relationId,
            'queue_data'   => \is_array($data) || \is_object($data) ? json_encode($data) : (string) $data,
            'params'       => !empty($params) ? json_encode($params) : '',
            'priority'     => $priority,
            'status'       => 'pending',
            'repeat_count' => 0,
            'expired'      => $expired,
            'created_on'   => date('Y-m-d H:i:s'),
            'modified_on'  => date('Y-m-d H:i:s'),
        ];

        if (!$table->bind($queueData)) {
            $this->setError($table->getError());

            return false;
        }

        if (!$table->check()) {
            $this->setError($table->getError());

            return false;
        }

        if (!$table->store()) {
            $this->setError($table->getError());

            return false;
        }

        return (int) $table->j2commerce_queue_id;
    }

    /**
     * Mark a queue item as completed.
     *
     * @param   int  $queueId  The queue item ID
     *
     * @return  bool  True on success
     *
     * @since   6.0.0
     */
    public function markCompleted(int $queueId): bool
    {
        return $this->updateStatus($queueId, 'completed');
    }

    /**
     * Mark a queue item as failed and increment retry count.
     *
     * @param   int  $queueId  The queue item ID
     *
     * @return  bool  True on success
     *
     * @since   6.0.0
     */
    public function markFailed(int $queueId): bool
    {
        $table = $this->getTable();

        if (!$table->load($queueId)) {
            return false;
        }

        $table->status       = 'failed';
        $table->repeat_count = (int) $table->repeat_count + 1;
        $table->modified_on  = date('Y-m-d H:i:s');

        return $table->store();
    }

    /**
     * Update the status of a queue item.
     *
     * @param   int     $queueId  The queue item ID
     * @param   string  $status   The new status
     *
     * @return  bool  True on success
     *
     * @since   6.0.0
     */
    public function updateStatus(int $queueId, string $status): bool
    {
        $table = $this->getTable();

        if (!$table->load($queueId)) {
            return false;
        }

        $table->status      = $status;
        $table->modified_on = date('Y-m-d H:i:s');

        return $table->store();
    }

    /**
     * Delete completed queue items older than specified days.
     *
     * Used for queue maintenance/cleanup.
     *
     * @param   int  $daysOld  Delete items older than this many days (default: 30)
     *
     * @return  int  Number of items deleted
     *
     * @since   6.0.0
     */
    public function purgeCompleted(int $daysOld = 30): int
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));

        $query->delete($db->quoteName('#__j2commerce_queues'))
            ->where($db->quoteName('status') . ' = :status')
            ->where($db->quoteName('modified_on') . ' < :cutoff')
            ->bind(':status', $completed = 'completed')
            ->bind(':cutoff', $cutoffDate);

        $db->setQuery($query);
        $db->execute();

        return $db->getAffectedRows();
    }
}
