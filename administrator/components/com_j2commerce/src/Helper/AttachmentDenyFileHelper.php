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
use Joomla\CMS\Factory;
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

    /**
     * Marker the per-file download rules carry. Deliberately shares no text with MARKER:
     * those rules go into directories the store already owned, and ownsTree() must keep
     * answering no for them however many rule files this component has left there.
     *
     * @since  6.6.0
     */
    public const DOWNLOAD_MARKER = 'J2Commerce download file rules';

    /** Characters of names per FilesMatch pattern, well inside both the PCRE and the config-line ceilings. */
    private const MAX_PATTERN_LENGTH = 1000;

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
     * Deny direct web access to named files only, in a directory the store points at
     * rather than one this component laid out.
     *
     * The payloads above cannot be used there. They deny a whole tree, and the directory a
     * downloadable file lands in is routinely the configured product-image directory — the
     * one the storefront serves every product image from. So these rules name the files
     * individually and leave everything else in the directory served.
     *
     * Carries DOWNLOAD_MARKER rather than MARKER: a rule file written here identifies its
     * own rules for rewriting without telling ownsTree() that J2Commerce owns a merchant's
     * directory. No README either — that text describes an order-upload tree. An existing
     * rule file J2Commerce did not write is still left alone by writeDenyFile().
     *
     * @param   list<string>  $names  Base names, relative to $dir.
     *
     * @since  6.6.0
     */
    public static function writeDownloadFileDeny(string $dir, array $names, ?callable $trace = null): void
    {
        $usable = [];

        foreach ($names as $name) {
            // A quote, an angle bracket or a control character would break out of the
            // directive it is written into, and none is a name this component stores.
            if ($name === '' || preg_match('#["<>\r\n]|[\x00-\x1F]#', $name)) {
                self::warn(
                    $trace,
                    'no rule written for a file name that cannot be quoted: ' . $name,
                    'No deny rule was written for ' . $dir . '/' . $name
                        . ' because its name cannot be expressed in a rule file.'
                );

                continue;
            }

            $usable[$name] = $name;
        }

        if ($usable === []) {
            return;
        }

        // Sorted so a directory whose file set has not changed is rewritten to the
        // same bytes, and writeDenyFile() can skip it.
        ksort($usable);

        self::writeDenyFile($dir . '/.htaccess', self::fileHtaccess($usable), true, $trace, self::DOWNLOAD_MARKER);
        self::writeDenyFile($dir . '/web.config', self::fileWebConfig($usable), true, $trace, self::DOWNLOAD_MARKER);

        self::reportUnreadRules($dir, $usable, $trace);
    }

    /**
     * Name the directories where the rules just written are read by nothing.
     *
     * Both payloads are configuration for two web servers. A site served by nginx, by Caddy,
     * or by an Apache whose vhost declines the directives gets a rule file that changes
     * nothing, and until now got no indication of it. The tree-wide writer ships a README
     * carrying the equivalent snippet; the per-file writer deliberately ships no README, so
     * this is the only place that guidance can come from.
     *
     * Not reported for the attachment tree, which carries that README already, and not
     * reported when the server is unnamed — a CLI install or a scheduled task states nothing
     * about how the site is served, and guessing there would put a message in front of every
     * cron run for no gain.
     *
     * @param  array<string, string>  $names
     */
    private static function reportUnreadRules(string $dir, array $names, ?callable $trace): void
    {
        static $reported = [];

        if (isset($reported[$dir]) || self::serverReadsDenyFiles() || self::isAttachmentTree($dir)) {
            return;
        }

        $reported[$dir] = true;

        $site     = rtrim(str_replace('\\', '/', JPATH_SITE), '/');
        $absolute = str_replace('\\', '/', $dir);
        $location = str_starts_with($absolute, $site . '/') ? substr($absolute, \strlen($site)) : $absolute;

        // The pattern names the files rather than the directory: this is routinely the
        // directory the storefront serves its product images from, and denying the whole of
        // it would take those with it. Quoted in the snippet because a stored name may carry
        // a space, which ends the directive where it stands otherwise.
        $pattern = implode('|', array_map(
            static fn (string $name): string => preg_quote($name, '#'),
            array_values($names)
        ));

        self::warn(
            $trace,
            'rules written for a server that reads neither file: ' . $dir,
            'The downloadable files in ' . $dir . ' are denied direct web access by an .htaccess and a '
                . 'web.config, and this server reports itself as one that reads neither. Add the equivalent '
                . 'rule to your server configuration — for nginx or Caddy: location ~ "^'
                . $location . '/(' . $pattern . ')$" { deny all; return 403; } — then request one of those '
                . 'files in a browser and check you get 403 rather than the file. An Apache told not to '
                . 'read .htaccess reports itself the same as one that does, so that check is the only proof '
                . 'either way.'
        );
    }

    /**
     * Whether the running web server reads one of the two payloads. An unnamed server is
     * treated as reading them: it is as likely to be a console run as a server that does not.
     */
    private static function serverReadsDenyFiles(): bool
    {
        $software = strtolower((string) ($_SERVER['SERVER_SOFTWARE'] ?? ''));

        if ($software === '') {
            return true;
        }

        // LiteSpeed reads .htaccess; IIS reads web.config.
        foreach (['apache', 'litespeed', 'microsoft-iis'] as $reader) {
            if (str_contains($software, $reader)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Params are read here rather than through ConfigHelper::getAttachmentPath(): the
     * installer require_once's this class before the PSR-4 map for the namespace exists,
     * so it must not reach for a sibling helper.
     */
    private static function isAttachmentTree(string $dir): bool
    {
        $configured = trim(
            str_replace('\\', '/', (string) ComponentHelper::getParams('com_j2commerce')->get('attachmentfolderpath', '')),
            '/'
        );

        $root = @realpath(JPATH_SITE . '/' . ($configured !== '' ? $configured : self::defaultPath()));

        return $root !== false && str_starts_with($dir . \DIRECTORY_SEPARATOR, $root . \DIRECTORY_SEPARATOR);
    }

    /** @param  array<string, string>  $names */
    private static function fileHtaccess(array $names): string
    {
        $out = '# ' . self::DOWNLOAD_MARKER . "\n"
            . "# Deny direct web access to the downloadable files stored here. Downloads are\n"
            . "# streamed by PHP. Every other file in this directory is left served.\n";

        foreach (self::patternChunks($names) as $pattern) {
            // (?i) because a case-insensitive filesystem will serve Ebook.PDF against a
            // rule naming ebook.pdf, and IIS matches <location path> that way regardless.
            $out .= '<FilesMatch "(?i)^(?:' . $pattern . ')$">' . "\n"
                . "    <IfModule mod_authz_core.c>\n"
                . "        Require all denied\n"
                . "    </IfModule>\n"
                . "\n"
                . "    <IfModule !mod_authz_core.c>\n"
                . "        Order allow,deny\n"
                . "        Deny from all\n"
                . "    </IfModule>\n"
                . "</FilesMatch>\n";
        }

        return $out;
    }

    /**
     * Split the names across as many patterns as it takes to keep each one short.
     *
     * A store can record more downloadable files in one directory than a single
     * alternation will hold: past a few thousand characters PCRE refuses to compile the
     * pattern, and Apache answers a directive it cannot parse by failing the whole
     * directory — which here is the one the storefront serves its images from.
     *
     * @param   array<string, string>  $names
     *
     * @return  list<string>
     */
    private static function patternChunks(array $names): array
    {
        $chunks  = [];
        $current = [];
        $length  = 0;

        foreach ($names as $name) {
            $quoted = preg_quote($name, '#');

            if ($current !== [] && $length + \strlen($quoted) > self::MAX_PATTERN_LENGTH) {
                $chunks[] = implode('|', $current);
                $current  = [];
                $length   = 0;
            }

            $current[] = $quoted;
            $length += \strlen($quoted) + 1;
        }

        if ($current !== []) {
            $chunks[] = implode('|', $current);
        }

        return $chunks;
    }

    /** @param  array<string, string>  $names */
    private static function fileWebConfig(array $names): string
    {
        $locations = '';

        foreach ($names as $name) {
            // ENT_SUBSTITUTE: without it a name that is not valid UTF-8 encodes to the empty
            // string, and an empty path attribute widens the deny to the whole directory.
            $locations .= '    <location path="' . htmlspecialchars($name, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8') . '">' . "\n"
                . "        <system.webServer>\n"
                . "            <security>\n"
                . "                <authorization>\n"
                . '                    <remove users="*" roles="" verbs="" />' . "\n"
                . '                    <add accessType="Deny" users="*" />' . "\n"
                . "                </authorization>\n"
                . "            </security>\n"
                . "        </system.webServer>\n"
                . "    </location>\n";
        }

        return '<?xml version="1.0" encoding="utf-8"?>' . "\n"
            . '<!-- ' . self::DOWNLOAD_MARKER . ': downloads are streamed by PHP. Other files here are left served. -->' . "\n"
            . "<configuration>\n"
            . $locations
            . "</configuration>\n";
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
    private static function writeDenyFile(
        string $path,
        string $contents,
        bool $owned,
        ?callable $trace,
        string $marker = self::MARKER
    ): void {
        $exists   = file_exists($path);
        $existing = $exists ? (string) @file_get_contents($path) : '';

        if ($exists && !str_contains($existing, $marker)) {
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

        // The per-file rules are rewritten on every product save; leave the untouched ones alone.
        if ($existing === $contents) {
            return;
        }

        // Locked because the per-file rules are rewritten on every save: this serialises
        // two saves writing the same directory. It does not order this against the web
        // server, which reads the file without a lock — only a temp file and a rename
        // would close that, and a torn read fails the directory closed either way.
        if (@file_put_contents($path, $contents, LOCK_EX) === false) {
            // Surfaced beyond the install trace: a failed deny-file write leaves the tree readable over HTTP.
            self::warn($trace, 'failed to write ' . $path, 'Failed to write ' . $path);
        }
    }

    /**
     * The one place a protection failure is reported, so every caller reaches the same
     * three surfaces. Public because DownloadHelper decides some of these on its own.
     *
     * The category is the one the system plugin registers a logger for, and ERROR is inside
     * the priorities that logger records without Site Debug — at WARNING on a category no
     * logger claims, every line written here went nowhere. The install trace only exists
     * while the installer is running, and nobody reads a log to find out whether an ordinary
     * save worked, so the message is also put in front of whoever performed it.
     */
    public static function warn(?callable $trace, string $traceLine, string $logLine): void
    {
        if ($trace !== null) {
            $trace('ENSURE FILES FOLDER: ' . $traceLine);
        }

        Log::add($logLine, Log::ERROR, 'com_j2commerce');

        Factory::getApplication()->enqueueMessage($logLine, 'warning');
    }

    /**
     * Drop the per-file rules from a directory that no longer holds a recorded download.
     * Only a file carrying DOWNLOAD_MARKER is removed, so a tree-wide pair or a ruleset
     * the site wrote itself is never touched.
     *
     * @since  6.6.0
     */
    public static function removeDownloadFileDeny(string $dir, ?callable $trace = null): void
    {
        foreach (['/.htaccess', '/web.config'] as $file) {
            $path = $dir . $file;

            if (!is_file($path) || !str_contains((string) @file_get_contents($path), self::DOWNLOAD_MARKER)) {
                continue;
            }

            if (!@unlink($path)) {
                self::warn(
                    $trace,
                    'failed to remove ' . $path,
                    'Failed to remove ' . $path . ', which no longer names a stored downloadable file.'
                );
            }
        }
    }
}
