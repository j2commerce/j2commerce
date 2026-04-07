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

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

// No direct access
\defined('_JEXEC') or die;

/**
 * Version Helper class for J2Commerce
 *
 * Provides version information and constants for the J2Commerce component.
 * This is a static helper class that manages version-related functionality.
 *
 * @since  6.0.0
 */
class VersionHelper
{
    /**
     * Flag indicating if version constants have been loaded
     *
     * @var   bool
     * @since 6.0.0
     */
    private static bool $loaded = false;

    /**
     * Singleton instance
     *
     * @var VersionHelper|null
     * @since 6.0.0
     */
    protected static ?VersionHelper $instance = null;

    /**
     * Database instance
     *
     * @var   DatabaseInterface|null
     * @since 6.0.0
     */
    private static ?DatabaseInterface $db = null;

    /**
     * Get the singleton instance of VersionHelper
     *
     * @return VersionHelper The VersionHelper instance
     * @since 6.0.0
     */
    public static function getInstance(): VersionHelper
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Get the database instance
     *
     * @return  DatabaseInterface
     *
     * @since   6.0.0
     */
    private static function getDatabase(): DatabaseInterface
    {
        if (self::$db === null) {
            self::$db = Factory::getContainer()->get(DatabaseInterface::class);
        }

        return self::$db;
    }

    /**
     * Load J2Commerce version constants
     *
     * Populates global constants holding the J2Commerce component version.
     * If version.php exists in the component directory, it will be loaded.
     * Otherwise, default values are defined.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public static function loadVersionDefines(): void
    {
        if (self::$loaded) {
            return;
        }

        $versionFile = JPATH_ADMINISTRATOR . '/components/com_j2commerce/version.php';

        if (file_exists($versionFile)) {
            require_once $versionFile;
        }

        // Define defaults if not already defined
        if (!\defined('J2COMMERCE_VERSION')) {
            \define('J2COMMERCE_VERSION', 'dev');
        }

        if (!\defined('J2COMMERCE_PRO')) {
            \define('J2COMMERCE_PRO', false);
        }

        if (!\defined('J2COMMERCE_DATE')) {
            $date = new Date();
            \define('J2COMMERCE_DATE', $date->format('Y-m-d'));
        }

        if (!\defined('J2COMMERCE_EXTRA_VERSION')) {
            \define('J2COMMERCE_EXTRA_VERSION', '');
        }

        self::$loaded = true;
    }

    /**
     * Get the J2Commerce version string
     *
     * @return  string  The version string
     *
     * @since   6.0.0
     */
    public static function getVersion(): string
    {
        self::loadVersionDefines();

        return \defined('J2COMMERCE_VERSION') ? J2COMMERCE_VERSION : 'dev';
    }

    /**
     * Get the full version string including extra version info
     *
     * @return  string  The full version string (e.g., "6.0.0 Beta 1")
     *
     * @since   6.0.0
     */
    public static function getFullVersion(): string
    {
        self::loadVersionDefines();

        $version = self::getVersion();
        $extra   = \defined('J2COMMERCE_EXTRA_VERSION') ? J2COMMERCE_EXTRA_VERSION : '';

        if (!empty($extra)) {
            return $version . ' ' . $extra;
        }

        return $version;
    }

    /**
     * Check if this is the Pro version
     *
     * @return  bool  True if Pro version, false otherwise
     *
     * @since   6.0.0
     */
    public static function isPro(): bool
    {
        self::loadVersionDefines();

        if (\defined('J2COMMERCE_PRO')) {
            return (bool) J2COMMERCE_PRO;
        }

        return false;
    }

    /**
     * Get the release date
     *
     * @return  string  The release date in Y-m-d format
     *
     * @since   6.0.0
     */
    public static function getDate(): string
    {
        self::loadVersionDefines();

        return \defined('J2COMMERCE_DATE') ? J2COMMERCE_DATE : (new Date())->format('Y-m-d');
    }

    /**
     * Get version information from the component manifest
     *
     * Reads version information directly from the j2commerce.xml manifest file.
     *
     * @return  array{version: string, date: string, author: string, authorEmail: string, authorUrl: string}
     *
     * @since   6.0.0
     */
    public static function getManifestInfo(): array
    {
        $manifestFile = JPATH_ADMINISTRATOR . '/components/com_j2commerce/j2commerce.xml';

        $info = [
            'version'     => '',
            'date'        => '',
            'author'      => '',
            'authorEmail' => '',
            'authorUrl'   => '',
        ];

        if (!file_exists($manifestFile)) {
            return $info;
        }

        $xml = simplexml_load_string(file_get_contents($manifestFile));

        if ($xml === false) {
            return $info;
        }

        $info['version']     = (string) ($xml->version ?? '');
        $info['date']        = (string) ($xml->creationDate ?? '');
        $info['author']      = (string) ($xml->author ?? '');
        $info['authorEmail'] = (string) ($xml->authorEmail ?? '');
        $info['authorUrl']   = (string) ($xml->authorUrl ?? '');

        return $info;
    }

    /**
     * Get the installed version from the database
     *
     * Queries the Joomla extensions table to get the currently installed version.
     *
     * @return  string  The installed version or empty string if not found
     *
     * @since   6.0.0
     */
    public static function getInstalledVersion(): string
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $element = 'com_j2commerce';
        $type    = 'component';

        $query->select($db->quoteName('manifest_cache'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = :element')
            ->where($db->quoteName('type') . ' = :type')
            ->bind(':element', $element)
            ->bind(':type', $type);

        $db->setQuery($query);
        $manifestCache = $db->loadResult();

        if (empty($manifestCache)) {
            return '';
        }

        $manifest = json_decode($manifestCache);

        if ($manifest === null || !isset($manifest->version)) {
            return '';
        }

        return (string) $manifest->version;
    }

    /**
     * Compare two version strings
     *
     * @param   string  $version1  First version to compare
     * @param   string  $version2  Second version to compare
     * @param   string  $operator  Comparison operator (<, <=, >, >=, ==, !=)
     *
     * @return  bool  Result of the comparison
     *
     * @since   6.0.0
     */
    public static function compareVersions(string $version1, string $version2, string $operator = '=='): bool
    {
        return version_compare($version1, $version2, $operator);
    }

    /**
     * Check if the current version is at least a specific version
     *
     * @param   string  $requiredVersion  The minimum required version
     *
     * @return  bool  True if current version >= required version
     *
     * @since   6.0.0
     */
    public static function isAtLeast(string $requiredVersion): bool
    {
        $currentVersion = self::getVersion();

        return self::compareVersions($currentVersion, $requiredVersion, '>=');
    }

    /**
     * Get complete version information as an array
     *
     * @return  array{version: string, fullVersion: string, date: string, isPro: bool, installedVersion: string}
     *
     * @since   6.0.0
     */
    public static function getVersionInfo(): array
    {
        self::loadVersionDefines();

        return [
            'version'          => self::getVersion(),
            'fullVersion'      => self::getFullVersion(),
            'date'             => self::getDate(),
            'isPro'            => self::isPro(),
            'installedVersion' => self::getInstalledVersion(),
        ];
    }

    /**
     * Reset the helper state (useful for testing)
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public static function reset(): void
    {
        self::$loaded = false;
        self::$db     = null;
    }
}
