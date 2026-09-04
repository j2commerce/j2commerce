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

\defined('_JEXEC') or die;

use Joomla\Filesystem\File;

/**
 * Joomla only deploys an extension's language files for locales whose core pack is already
 * installed -- Installer::parseLanguages() skips the rest. Installing a language pack after
 * J2Commerce therefore leaves those locales untranslated until the next reinstall.
 *
 * This replays that copy from the language folders each extension ships, using the same
 * source-to-client mapping the installer adapters pass to parseLanguages().
 */
class LanguageRepairHelper
{
    /** Glob patterns for extension language folders, mapped to the client base path Joomla installs them under. */
    private const SOURCE_PATTERNS = [
        'administrator/components/com_j2commerce/language' => JPATH_ADMINISTRATOR,
        'administrator/modules/mod_j2commerce_*/language'  => JPATH_ADMINISTRATOR,
        'plugins/j2commerce/*/language'                    => JPATH_ADMINISTRATOR,
        'plugins/*/j2commerce*/language'                   => JPATH_ADMINISTRATOR,
        'components/com_j2commerce/language'               => JPATH_SITE,
        'modules/mod_j2commerce_*/language'                => JPATH_SITE,
        'libraries/j2commerce*/language'                   => JPATH_SITE,
    ];

    /** Locale tags with at least one string file still missing, e.g. ['de-DE', 'fr-FR']. */
    public static function getMissingLocales(): array
    {
        $tags = array_keys(self::getPendingFiles());
        sort($tags);

        return $tags;
    }

    /** Copies every pending file into place. Returns the number copied. */
    public static function repair(): int
    {
        $copied = 0;

        foreach (self::getPendingFiles() as $files) {
            foreach ($files as $src => $dest) {
                if (File::copy($src, $dest)) {
                    $copied++;
                }
            }
        }

        return $copied;
    }

    /**
     * Files whose destination locale folder exists but whose file does not, keyed by locale
     * tag then source path. Existing files are left alone so a site's own edits survive.
     *
     * @return array<string, array<string, string>>
     */
    private static function getPendingFiles(): array
    {
        $pending = [];

        foreach (self::SOURCE_PATTERNS as $pattern => $clientPath) {
            foreach (glob(JPATH_ROOT . '/' . $pattern, GLOB_ONLYDIR) ?: [] as $sourceDir) {
                foreach (glob($sourceDir . '/*', GLOB_ONLYDIR) ?: [] as $tagDir) {
                    $tag = basename($tagDir);

                    // Mirror the installer: a locale is only serviced once its core pack is present.
                    if (!preg_match('/^[a-z]{2,3}-[A-Z]{2,4}$/D', $tag) || !is_dir($clientPath . '/language/' . $tag)) {
                        continue;
                    }

                    foreach (glob($tagDir . '/*.ini') ?: [] as $src) {
                        $dest = $clientPath . '/language/' . $tag . '/' . basename($src);

                        if (!is_file($dest)) {
                            $pending[$tag][$src] = $dest;
                        }
                    }
                }
            }
        }

        return $pending;
    }
}
