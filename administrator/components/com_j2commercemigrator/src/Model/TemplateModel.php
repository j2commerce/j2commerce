<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\Model;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commercemigrator\Administrator\Helper\MigrationLogger;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\SubTemplateMigrator;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Template model — wraps SubTemplateMigrator for the Templates wizard step.
 */
class TemplateModel extends BaseDatabaseModel
{
    private function migrator(): SubTemplateMigrator
    {
        return new SubTemplateMigrator(new MigrationLogger());
    }

    /** Lists frontend templates that still contain J2Store references. */
    public function discover(): array
    {
        return $this->migrator()->discover();
    }

    /** Returns a per-file replacement report for a specific template. */
    public function analyze(string $template): array
    {
        return $this->migrator()->analyze($template);
    }

    /** Rewrites J2Store references in the given template (optionally backing up first). */
    public function migrate(string $template, bool $backup = true): array
    {
        return $this->migrator()->migrate($template, $backup);
    }

    /** Enumerates template override directories that contain J2Store layouts. */
    public function discoverTemplateOverrides(): array
    {
        return $this->migrator()->discoverTemplateOverrides();
    }

    /** Migrates selected template override files to J2Commerce namespace. */
    public function migrateTemplateOverrides(string $template, array $selectedFiles, bool $backup = true): array
    {
        return $this->migrator()->migrateTemplateOverrides($template, $selectedFiles, $backup);
    }

    /** Applies a user-supplied single-line pattern replacement to a specific file. */
    public function applyManualReplacement(string $filePath, int $lineNumber, string $oldPattern, string $newPattern): array
    {
        return $this->migrator()->applyManualReplacement($filePath, $lineNumber, $oldPattern, $newPattern);
    }

    /** Re-seeds a target template file from its plugin source (full reset for a single file). */
    public function remigrateFromPlugin(string $targetPath, bool $isLayoutFile): array
    {
        return $this->migrator()->remigrateFromPlugin($targetPath, $isLayoutFile);
    }
}
