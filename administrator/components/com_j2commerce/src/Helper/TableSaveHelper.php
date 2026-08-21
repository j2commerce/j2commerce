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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Table\Table;

// No direct access
\defined('_JEXEC') or die;

/**
 * Runs the check()/store() pair so a refusal is reported instead of discarded.
 *
 * Both methods return a boolean and record the reason via setError(). Called as
 * bare statements they lose it, and the caller carries on as though the row was
 * written.
 *
 * @since  6.0.0
 */
final class TableSaveHelper
{
    /** Validate then write. A failed check() stops the store that would follow it. */
    public static function save(Table $table, string $context): bool
    {
        if (!$table->check()) {
            return self::report($table, $context, 'validation');
        }

        return self::store($table, $context);
    }

    /** Write a table whose values were already validated, or that carries no check(). */
    public static function store(Table $table, string $context): bool
    {
        if (!$table->store()) {
            return self::report($table, $context, 'store');
        }

        return true;
    }

    /**
     * Enqueued only in the administrator, where it reaches someone who can act on it.
     * On the site it stays in the log — the driver's own text is not shopper-facing.
     */
    private static function report(Table $table, string $context, string $stage): bool
    {
        $error = $table->getError() ?: Text::_('COM_J2COMMERCE_ERR_GENERIC');

        Log::add(\sprintf('%s: %s failed: %s', $context, $stage, $error), Log::ERROR, 'com_j2commerce');

        $app = Factory::getApplication();

        if ($app->isClient('administrator')) {
            $app->enqueueMessage(Text::sprintf('COM_J2COMMERCE_ERR_RECORD_NOT_SAVED', $error), 'error');
        }

        return false;
    }
}
