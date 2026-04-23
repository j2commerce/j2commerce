<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\Helper;

use Joomla\CMS\Log\Log;

class MigrationLogger
{
    private const CATEGORY = 'com_j2commercemigrator';

    public function __construct()
    {
        Log::addLogger(
            ['text_file' => 'com_j2commercemigrator.log'],
            Log::ALL,
            [self::CATEGORY]
        );
    }

    public function info(string $message): void
    {
        Log::add($message, Log::INFO, self::CATEGORY);
    }

    public function warning(string $message): void
    {
        Log::add($message, Log::WARNING, self::CATEGORY);
    }

    public function error(string $message, ?\Throwable $throwable = null): void
    {
        if ($throwable !== null) {
            $message .= '. ' . $throwable->getMessage();
            Log::add($message . "\n" . $throwable->getTraceAsString(), Log::ERROR, self::CATEGORY);
            return;
        }

        Log::add($message, Log::ERROR, self::CATEGORY);
    }

    public function tierStart(int $tier, string $name): void
    {
        $this->info("=== TIER {$tier} START: {$name} ===");
    }

    public function tierEnd(int $tier, string $name, int $totalMigrated): void
    {
        $this->info("=== TIER {$tier} END: {$name} — {$totalMigrated} records migrated ===");
    }

    public function rowError(string $table, int|string $pk, string $message): void
    {
        $this->error("ROW ERROR [{$table}] PK={$pk}: {$message}");
    }
}
