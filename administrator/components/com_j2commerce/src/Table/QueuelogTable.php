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

class QueuelogTable extends Table
{
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__j2commerce_queue_logs', 'j2commerce_queue_log_id', $db);
    }

    public function check(): bool
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());

            return false;
        }

        if (!isset($this->items_total) || $this->items_total === '') {
            $this->items_total = 0;
        }

        if (!isset($this->items_success) || $this->items_success === '') {
            $this->items_success = 0;
        }

        if (!isset($this->items_failed) || $this->items_failed === '') {
            $this->items_failed = 0;
        }

        if (!isset($this->items_skipped) || $this->items_skipped === '') {
            $this->items_skipped = 0;
        }

        if (empty($this->status)) {
            $this->status = 'running';
        }

        if (empty($this->started_at)) {
            $this->started_at = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        }

        if (empty($this->created_on)) {
            $this->created_on = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        }

        return true;
    }
}
