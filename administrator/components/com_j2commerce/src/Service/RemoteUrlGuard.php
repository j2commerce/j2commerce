<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Log\Log;
use Psr\Http\Message\ResponseInterface;

/**
 * One place that decides whether an outbound URL may be reached, and the fetch helper core and
 * extensions should use for any URL that did not originate on this site.
 *
 * Checking the host once is not enough: the transports Joomla ships follow redirects on their
 * own, so a host that passes here can hand back a 302 to somewhere that would not. Every request
 * therefore re-checks each hop itself with redirect-following turned off, and DNS is resolved
 * once per hop.
 *
 * Twin of com_j2commercemigrator's Service\RemoteUrlGuard, which is rewired to this class once
 * the minimum supported J2Commerce version ships it (6.6.3).
 */
final class RemoteUrlGuard
{
    public const MAX_REDIRECTS = 3;

    private const ALLOWED_SCHEMES = ['http', 'https'];

    /** A well-formed http(s) URL whose host does not resolve into a loopback, private or reserved range. */
    public static function isAllowed(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)
            || !\in_array(parse_url($url, PHP_URL_SCHEME), self::ALLOWED_SCHEMES, true)
        ) {
            return false;
        }

        $host = (string) (parse_url($url, PHP_URL_HOST) ?? '');

        if ($host === '' || strcasecmp($host, 'localhost') === 0) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($host);
        }

        // Every A record, not just the first one gethostbyname() happens to hand back:
        // a host that answers with one public and one private address is not public.
        $ips = @gethostbynamel($host);

        if ($ips === false || $ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /** isAllowed() plus a WARNING log naming the URL that was refused. */
    public static function assertAllowed(string $url, string $context = ''): bool
    {
        if (self::isAllowed($url)) {
            return true;
        }

        Log::add(
            'Refused to fetch "' . $url . '"' . ($context !== '' ? ' (' . $context . ')' : '')
            . ': the address is not a public http(s) destination.',
            Log::WARNING,
            'com_j2commerce'
        );

        return false;
    }

    /** GET $url. Returns the body on a 200, or null on refusal, transport error, or any non-200 status. */
    public static function fetch(string $url, int $timeout = 30, string $context = ''): ?string
    {
        $response = self::request('get', $url, $timeout, $context);

        return $response === null ? null : (string) $response->getBody();
    }

    /**
     * HEAD $url. Returns the final 200 response's headers keyed by lower-case name (first value
     * of each), or null on refusal, transport error, or any non-200 status — including servers
     * that do not answer HEAD at all.
     *
     * @return array<string, string>|null
     */
    public static function head(string $url, int $timeout = 30, string $context = ''): ?array
    {
        $response = self::request('head', $url, $timeout, $context);

        if ($response === null) {
            return null;
        }

        $headers = [];

        foreach ($response->getHeaders() as $name => $values) {
            $headers[strtolower((string) $name)] = (string) ($values[0] ?? '');
        }

        return $headers;
    }

    private static function request(string $method, string $url, int $timeout, string $context): ?ResponseInterface
    {
        // follow_location off is the whole point: CurlTransport and StreamTransport both
        // chase redirects by default, which would walk past the per-hop check below.
        $http = (new HttpFactory())->getHttp(['follow_location' => false], ['curl', 'stream']);
        $next = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (!self::assertAllowed($next, $context)) {
                return null;
            }

            try {
                $response = $method === 'head'
                    ? $http->head($next, [], $timeout)
                    : $http->get($next, [], $timeout);
            } catch (\Throwable) {
                return null;
            }

            $status = (int) $response->getStatusCode();

            if ($status === 200) {
                return $response;
            }

            if ($status < 300 || $status > 399) {
                return null;
            }

            $location = $response->getHeader('Location')[0] ?? '';

            if ($location === '') {
                return null;
            }

            // A relative Location is resolved against the hop it came from, and re-checked
            // on the next pass like any other destination.
            $next = self::resolve($next, (string) $location);
        }

        return null;
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private static function resolve(string $base, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }

        $parts  = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '')
            . (isset($parts['port']) ? ':' . $parts['port'] : '');

        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }

        $path = (string) ($parts['path'] ?? '/');

        return $origin . substr($path, 0, (int) strrpos($path, '/') + 1) . $location;
    }
}
