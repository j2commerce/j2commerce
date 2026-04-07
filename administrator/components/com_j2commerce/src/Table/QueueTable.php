<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class QueueTable extends Table
{
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__j2commerce_queues', 'j2commerce_queue_id', $db);
    }

    public function check(): bool
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());

            return false;
        }

        if (!isset($this->priority) || $this->priority === '') {
            $this->priority = 0;
        }

        if (empty($this->status)) {
            $this->status = 'pending';
        }

        if (!isset($this->attempt_count) || $this->attempt_count === '') {
            $this->attempt_count = 0;
        }

        if (empty($this->max_attempts)) {
            $this->max_attempts = 10;
        }

        if (empty($this->item_type)) {
            $this->item_type = 'order';
        }

        if (\is_array($this->queue_data) || \is_object($this->queue_data)) {
            $this->queue_data = json_encode($this->queue_data);
        }

        if (\is_array($this->params) || \is_object($this->params)) {
            $this->params = json_encode($this->params);
        }

        if (empty($this->created_on)) {
            $this->created_on = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        }

        $this->modified_on = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        return true;
    }
}
