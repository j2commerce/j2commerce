<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks;

use J2Commerce\Component\J2commerce\Administrator\SetupGuide\AbstractSetupCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\SetupCheckResult;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

class CartCleanupTaskCheck extends AbstractSetupCheck
{
    private const TASK_TYPE = 'j2commerce.clearOutdatedCarts';

    public function getId(): string
    {
        return 'cart_cleanup_task';
    }

    public function getGroup(): string
    {
        return 'system_requirements';
    }

    public function getGroupOrder(): int
    {
        return 200;
    }

    public function isDismissible(): bool
    {
        return false;
    }

    public function getLabel(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_CART_CLEANUP_TASK');
    }

    public function getDescription(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_CART_CLEANUP_TASK_DESC');
    }

    public function check(): SetupCheckResult
    {
        $task = $this->getTaskRow();

        if ($task === null) {
            return new SetupCheckResult('fail', Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_CART_CLEANUP_TASK_MISSING'));
        }

        if ((int) $task->state !== 1) {
            return new SetupCheckResult(
                'fail',
                Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_CART_CLEANUP_TASK_DISABLED'),
                ['taskId' => (int) $task->id]
            );
        }

        if ((int) $task->times_failed > 0) {
            return new SetupCheckResult(
                'fail',
                Text::sprintf('COM_J2COMMERCE_SETUP_GUIDE_CHECK_CART_CLEANUP_TASK_FAILING', (int) $task->times_failed),
                ['taskId' => (int) $task->id]
            );
        }

        return new SetupCheckResult('pass', Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_CART_CLEANUP_TASK_PASS'));
    }

    public function getActions(): array
    {
        $result = $this->check();

        if ($result->status === 'pass') {
            return [];
        }

        if (empty($result->data['taskId'])) {
            return [[
                'action' => 'create_scheduled_task',
                'label'  => 'COM_J2COMMERCE_SETUP_GUIDE_ACTION_CREATE_TASK',
                'params' => [],
            ]];
        }

        return [[
            'action' => 'enable_scheduled_task',
            'label'  => 'COM_J2COMMERCE_SETUP_GUIDE_ACTION_ENABLE_TASK',
            'params' => ['taskId' => $result->data['taskId']],
        ]];
    }

    public function getDetailView(): string
    {
        $result = $this->check();
        $task   = $this->getTaskRow();

        $html = '<h5>' . $this->getLabel() . '</h5>'
            . '<p>' . $result->message . '</p>';

        if ($task !== null) {
            $editUrl   = 'index.php?option=com_scheduler&task=task.edit&id=' . (int) $task->id;
            $lastRun   = $task->last_execution !== null ? htmlspecialchars($task->last_execution, ENT_QUOTES, 'UTF-8') : Text::_('JNONE');

            $html .= '<p class="small text-body-secondary mb-2">'
                . Text::sprintf('COM_J2COMMERCE_SETUP_GUIDE_CHECK_CART_CLEANUP_TASK_LAST_RUN', $lastRun)
                . '</p>'
                . '<a href="' . $editUrl . '" class="btn btn-outline-primary w-100 mb-2">'
                . Text::_('COM_J2COMMERCE_SETUP_GUIDE_ACTION_MANAGE_TASK')
                . '</a>';
        }

        return $html;
    }

    private function getTaskRow(): ?object
    {
        $db   = $this->getDatabase();
        $type = self::TASK_TYPE;

        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'state', 'times_failed', 'last_execution', 'next_execution']))
            ->from($db->quoteName('#__scheduler_tasks'))
            ->where($db->quoteName('type') . ' = :type')
            ->bind(':type', $type);

        return $db->setQuery($query)->loadObject() ?: null;
    }
}
