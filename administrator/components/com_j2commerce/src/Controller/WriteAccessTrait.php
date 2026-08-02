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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;

/**
 * Requires a j2commerce.edit* action for everything a controller does except reading.
 *
 * The dispatcher enforces core.manage and the views enforce the matching j2commerce.view*
 * action, but nothing has ever enforced an edit-level action outside orders — there was no
 * such action to name until j2commerce.editproducts and j2commerce.editsetup existed.
 *
 * The list is of things that only READ, and everything else needs the action. That direction
 * is deliberate: a task added to one of these controllers later is gated by default instead
 * of being silently exempt, which is the failure mode that leaves a fix looking complete.
 * ProductsController alone carries roughly thirty tasks, most of them AJAX writes.
 *
 * Matching is against the method the task map resolves to, not the task string, so the
 * aliases FormController registers — apply, save2new, save2copy all resolving to save — are
 * covered without naming each one.
 */
trait WriteAccessTrait
{
    /**
     * Methods that only read. Anything else on the controller requires $writeAction.
     *
     * A method rather than a property on purpose: a class that overrides a trait property
     * with a different initial value is a fatal at composition time, which php -l cannot
     * see because it is a binding error rather than a parse error.
     */
    protected function readTasks(): array
    {
        return ['display'];
    }

    public function execute($task)
    {
        $resolved = $this->taskMap[strtolower((string) $task)] ?? ($this->taskMap['__default'] ?? null);

        // A null resolution is an unknown task; let parent::execute() raise its own 404.
        if (
            $resolved !== null
            && !\in_array($resolved, $this->readTasks(), true)
            && !J2CommerceHelper::canAccess($this->writeAction)
        ) {
            throw new NotAllowed(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        return parent::execute($task);
    }
}
