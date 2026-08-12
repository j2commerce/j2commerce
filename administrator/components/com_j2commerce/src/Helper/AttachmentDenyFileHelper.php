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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Log\Log;

/**
 * Deny-file payloads and ownership rules for the customer-upload storage tree, shared
 * by the installer and by ConfigHelper::getAttachmentAbsolutePath() so the two surfaces
 * cannot disagree. The installer require_once's this file at its call site because the
 * PSR-4 map for this namespace is built before the component exists on a fresh install.
 *
 * @since  6.5.0
 */
final class AttachmentDenyFileHelper
{
    /** Storage root of an install that predates the file_path-derived default below. */
    public const DEFAULT_PATH = 'files/com_j2commerce';

    /** Marker both deny payloads carry, identifying a file as one J2Commerce wrote. */
    public const MARKER = 'J2Commerce file storage';

    /** Heading identifying a README as one J2Commerce wrote. */
    public const README_HEADING = 'J2Commerce Customer Upload Storage';

    private const HTACCESS = <<<'HTACCESS'
# J2Commerce file storage
# Disable directory browsing
Options -Indexes

# Deny direct web access to every file in this tree. Downloads are streamed by PHP.
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>

<IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
</IfModule>

# Belt and braces: never hand off an executable here, even if the rules above are
# overridden by a vhost that disallows this directive scope.
<FilesMatch "\.(php|phtml|phar|pl|py|jsp|asp|aspx|sh|cgi|exe|bat)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>

    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>
HTACCESS;

    private const WEB_CONFIG = <<<'WEBCONFIG'
<?xml version="1.0" encoding="utf-8"?>
<!-- J2Commerce file storage: deny direct web access. Downloads are streamed by PHP. -->
<configuration>
    <system.webServer>
        <directoryBrowse enabled="false" />
        <handlers accessPolicy="None" />
        <security>
            <authorization>
                <remove users="*" roles="" verbs="" />
                <add accessType="Deny" users="*" />
            </authorization>
        </security>
    </system.webServer>
</configuration>
WEBCONFIG;

    /**
     * Storage root used when the component names no path of its own: a com_j2commerce
     * folder inside the file storage location Joomla itself was configured with — the
     * com_media 'file_path' param behind Global Configuration → Media, 'files' by default —
     * so a site that moved that location is followed here rather than hard-coded past. An
     * install already holding the legacy tree keeps it: a site that moved file_path after
     * uploads existed must not lose sight of them.
     *
     * A file_path carrying a traversal segment or a drive letter is not resolvable inside
     * the site root, so the legacy default is used rather than handing the callers a path
     * their own confinement tests would reject, leaving the site nowhere to store.
     *
     * @since  6.5.0
     */
    public static function defaultPath(): string
    {
        if (is_dir(JPATH_ROOT . '/' . self::DEFAULT_PATH)) {
            return self::DEFAULT_PATH;
        }

        $configured = (string) ComponentHelper::getParams('com_media')->get('file_path', 'files');
        $configured = trim(str_replace('\\', '/', $configured), '/');
        $segments   = array_filter(
            explode('/', $configured),
            static fn (string $segment): bool => $segment !== '' && $segment !== '.'
        );

        if ($segments === [] || \in_array('..', $segments, true) || preg_match('#^[a-zA-Z]:#', $configured)) {
            return self::DEFAULT_PATH;
        }

        return implode('/', $segments) . '/com_j2commerce';
    }

    /**
     * A tree is J2Commerce's when this request created it, when it is the default path
     * (ours by construction — Joomla ships nothing there), or when it already carries a
     * file J2Commerce wrote. Never keyed on directory names, so a pre-existing folder an
     * administrator points the config at (e.g. images) is never claimed.
     */
    public static function ownsTree(string $root, bool $createdNow, bool $isDefaultPath): bool
    {
        if ($createdNow || $isDefaultPath) {
            return true;
        }

        // The README heading, and the marker both deny payloads carry.
        $evidence = [
            '/README.md'  => self::README_HEADING,
            '/.htaccess'  => self::MARKER,
            '/web.config' => self::MARKER,
        ];

        foreach ($evidence as $file => $needle) {
            $path = $root . $file;

            if (is_file($path) && str_contains((string) @file_get_contents($path), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Write the .htaccess + web.config deny pair into an upload tree root, and the README
     * that documents them. All three come from here so a tree the runtime creates carries
     * the same guidance as one the installer created, and so the nginx snippet in the README
     * always names $relative — the path this tree actually sits at, which since 6.5.0 is not
     * necessarily the legacy default.
     */
    public static function writeDenyPair(string $root, string $relative, bool $owned, ?callable $trace = null): void
    {
        self::writeDenyFile($root . '/.htaccess', self::HTACCESS, $owned, $trace);
        self::writeDenyFile($root . '/web.config', self::WEB_CONFIG, $owned, $trace);
        self::writeReadme($root, $relative, $owned, $trace);
    }

    /**
     * Written only into an owned tree — marking a foreign directory as J2Commerce's would
     * make ownsTree() claim it on every later run. An existing README is replaced only when
     * it carries our heading, so a stale path in the nginx snippet is corrected while a
     * site's own README is left alone.
     */
    private static function writeReadme(string $root, string $relative, bool $owned, ?callable $trace): void
    {
        $path   = $root . '/README.md';
        $exists = file_exists($path);

        if ($exists && !str_contains((string) @file_get_contents($path), self::README_HEADING)) {
            return;
        }

        if (!$exists && !$owned) {
            return;
        }

        if (@file_put_contents($path, self::readme($relative)) === false) {
            self::warn($trace, 'failed to write ' . $path, 'Failed to write ' . $path);
        }
    }

    private static function readme(string $relative): string
    {
        $heading  = self::README_HEADING;
        $location = '/' . trim(str_replace('\\', '/', $relative), '/');

        return <<<README
# {$heading}

This directory holds customer-supplied files attached to orders (product-option uploads and checkout uploads).

- `tmp/{cart_id}/` — uploads bound to in-progress carts; cleaned by the `j2commerce.cleanupOrderUploads` scheduled task once `expires_on` passes.
- `orders/{order_id}/` — uploads attached to a placed order; cleaned by the same task per configured retention.

## Web access

Nothing in this tree is meant to be fetched by URL. Files are streamed by PHP after an
authorisation check — `OrderfileController` for admin order attachments, `MyprofileController`
for a customer's own downloads.

- `.htaccess` denies every request under this tree on Apache (`Require all denied`, with the
  pre-2.4 `Order allow,deny` form for older servers), and separately blocks executable
  extensions in case the blanket rule is overridden by the vhost.
- `web.config` denies every request under this tree on IIS and disables handlers.

Both files only take effect if the web server is configured to honour them — Apache needs
`AllowOverride` to permit `Limit`/`AuthConfig` in this path, and IIS needs the URL
Authorization feature installed. Verify by requesting a known filename in a browser: you
should get 403, not the file.

## Nginx equivalent

Nginx reads neither file. If your site is served by Nginx, add this to your server block,
adjusting the location for any prefix your site is installed under:

```nginx
location ~ ^{$location} { deny all; return 403; }
```

Do not store anything in this tree manually — admin order views look up files by
`#__j2commerce_uploads` row, not by filesystem scan.
README;
    }

    /**
     * A deny file replaces only a file carrying the marker, and is created only in an owned
     * tree: refusing to replace a foreign ruleset or to create one in a foreign directory is
     * what makes clobbering a site's own content impossible whatever the path resolves to.
     */
    private static function writeDenyFile(string $path, string $contents, bool $owned, ?callable $trace): void
    {
        $exists = file_exists($path);

        if ($exists && !str_contains((string) @file_get_contents($path), self::MARKER)) {
            self::warn(
                $trace,
                'left existing non-J2Commerce file in place: ' . $path,
                'An existing ' . basename($path) . ' that J2Commerce did not write was left untouched at '
                    . $path . '. The upload storage tree may not be protected from direct web access.'
            );

            return;
        }

        if (!$exists && !$owned) {
            self::warn(
                $trace,
                'not creating ' . basename($path) . ' in a directory J2Commerce does not own: ' . $path,
                'No ' . basename($path) . ' was written at ' . $path . ' because the configured attachment '
                    . 'folder is a pre-existing directory J2Commerce does not own. The upload storage tree '
                    . 'may not be protected from direct web access.'
            );

            return;
        }

        if (@file_put_contents($path, $contents) === false) {
            // Surfaced beyond the install trace: a failed deny-file write leaves the tree readable over HTTP.
            self::warn($trace, 'failed to write ' . $path, 'Failed to write ' . $path);
        }
    }

    private static function warn(?callable $trace, string $traceLine, string $logLine): void
    {
        if ($trace !== null) {
            $trace('ENSURE FILES FOLDER: ' . $traceLine);
        }

        Log::add($logLine, Log::WARNING, 'j2commerce');
    }
}
