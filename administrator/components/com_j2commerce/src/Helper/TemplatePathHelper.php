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

use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;

// No direct access
\defined('_JEXEC') or die;

/**
 * Resolves stored template file references against the directory that owns them.
 *
 * Both the email and the invoice pipeline include their resolved template, so the
 * resolution is shared here rather than restated per helper.
 */
class TemplatePathHelper
{
    /**
     * Template file extensions the include sinks accept. Not a code-execution control — the
     * sinks include, so a .html template runs PHP too. Containment is the realpath test below.
     */
    public const ALLOWED_EXTENSIONS = ['html', 'php'];

    /** Inline image references the email pipeline embeds. */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif'];

    /**
     * Resolve $relFile beneath $root.
     *
     * @param   string    $root        Absolute directory the reference is allowed to resolve within
     * @param   string    $relFile     Stored reference, relative to $root
     * @param   string[]  $extensions  Extensions the reference must carry
     *
     * @return  string|null  The resolved absolute path, or null when it does not resolve to an
     *                       accepted file inside $root
     *
     * @since   6.0.0
     */
    public static function confine(string $root, string $relFile, array $extensions = self::ALLOWED_EXTENSIONS): ?string
    {
        if ($relFile === '' || str_contains($relFile, '..') || str_contains($relFile, "\0")) {
            return null;
        }

        if (!\in_array(strtolower(File::getExt($relFile)), $extensions, true)) {
            return null;
        }

        $realRoot = realpath($root);
        $realFile = realpath(Path::clean($root . '/' . $relFile));

        if ($realRoot === false || $realFile === false) {
            return null;
        }

        // realpath() has resolved every segment, so a prefix test now settles containment
        if (!str_starts_with($realFile, $realRoot . \DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realFile;
    }
}
