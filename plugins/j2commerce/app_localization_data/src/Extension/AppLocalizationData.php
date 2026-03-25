<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_app_localization_data
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\AppLocalizationData\Extension;

use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class AppLocalizationData extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    protected $autoloadLanguage = true;

    private const VALID_TABLES = ['countries', 'zones', 'lengths', 'weights'];

    public static function getSubscribedEvents(): array
    {
        return [
            'onAjaxApp_localization_data' => 'handleAjax',
        ];
    }

    public function handleAjax(AjaxEvent $event): void
    {
        $app = $this->getApplication();

        // Admin-only endpoint
        if (!$app->isClient('administrator')) {
            $event->addResult(json_encode(['success' => false, 'message' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]));

            return;
        }

        // CSRF validation
        if (!Session::checkToken('request')) {
            $event->addResult(json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]));

            return;
        }

        // ACL check — only super admins can reset localization data
        if (!$app->getIdentity()->authorise('core.admin', 'com_j2commerce')) {
            $event->addResult(json_encode(['success' => false, 'message' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]));

            return;
        }

        $task = $app->getInput()->getCmd('task', '');

        $result = match ($task) {
            'insertTableValues' => $this->insertTableValues(),
            default => [
                'success' => false,
                'message' => Text::_('PLG_J2COMMERCE_APP_LOCALIZATION_DATA_ERR_INVALID_TASK'),
            ],
        };

        $event->addResult(json_encode($result));
    }

    private function insertTableValues(): array
    {
        $tableName = $this->getApplication()->getInput()->getCmd('table', '');

        Log::add('Localization data reset requested for table: ' . $tableName, Log::DEBUG, 'com_j2commerce');

        if ($tableName === 'metrics') {
            return $this->resetMetrics();
        }

        if (!$this->isValidTable($tableName)) {
            return [
                'success' => false,
                'message' => Text::_('PLG_J2COMMERCE_APP_LOCALIZATION_DATA_ERR_INVALID_TABLE'),
            ];
        }

        return $this->resetTable($tableName);
    }

    /** Resets both lengths and weights in a single transaction. */
    private function resetMetrics(): array
    {
        $db = $this->getDatabase();

        try {
            $db->transactionStart();

            $this->deleteAndReinsert($db, 'lengths');
            $this->deleteAndReinsert($db, 'weights');

            $db->transactionCommit();

            return [
                'success' => true,
                'message' => Text::_('PLG_J2COMMERCE_APP_LOCALIZATION_DATA_MSG_SUCCESS'),
            ];
        } catch (\Throwable $e) {
            try {
                $db->transactionRollback();
            } catch (\Throwable) {
            }

            Log::add('Localization data reset (metrics) failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');

            return [
                'success' => false,
                'message' => Text::_('PLG_J2COMMERCE_APP_LOCALIZATION_DATA_ERR_INSERTION_ERROR'),
            ];
        }
    }

    private function resetTable(string $tableName): array
    {
        $db = $this->getDatabase();

        try {
            $db->transactionStart();

            $this->deleteAndReinsert($db, $tableName);

            $db->transactionCommit();

            return [
                'success' => true,
                'message' => Text::_('PLG_J2COMMERCE_APP_LOCALIZATION_DATA_MSG_SUCCESS'),
            ];
        } catch (\Throwable $e) {
            try {
                $db->transactionRollback();
            } catch (\Throwable) {
            }

            Log::add('Localization data reset (' . $tableName . ') failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');

            return [
                'success' => false,
                'message' => Text::_('PLG_J2COMMERCE_APP_LOCALIZATION_DATA_ERR_INSERTION_ERROR'),
            ];
        }
    }

    /** @throws \Throwable on any SQL failure (caller must handle transaction) */
    private function deleteAndReinsert(DatabaseDriver $db, string $tableName): void
    {
        // DELETE instead of TRUNCATE — TRUNCATE is DDL and auto-commits, breaking transactions
        $db->setQuery('DELETE FROM ' . $db->quoteName('#__j2commerce_' . $tableName));
        $db->execute();

        $this->insertFromSqlFile($db, $tableName);
    }

    private function insertFromSqlFile(DatabaseDriver $db, string $tableName): void
    {
        $sqlFile = JPATH_ADMINISTRATOR . '/components/com_j2commerce/sql/install/mysql/' . $tableName . '.sql';

        if (!file_exists($sqlFile)) {
            throw new \RuntimeException('SQL install file not found: ' . $tableName . '.sql');
        }

        $sqlContent = file_get_contents($sqlFile);

        if ($sqlContent === false) {
            throw new \RuntimeException('Failed to read SQL install file: ' . $tableName . '.sql');
        }

        $queries = DatabaseDriver::splitSql($sqlContent);

        foreach ($queries as $query) {
            $query = trim($query);

            if ($query === '' || str_starts_with($query, '--')) {
                continue;
            }

            $db->setQuery($query);
            $db->execute();
        }
    }

    private function isValidTable(string $tableName): bool
    {
        return \in_array($tableName, self::VALID_TABLES, true);
    }
}
