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
 * therefore re-checks each hop itself with redirect-following turned off. Each hop resolves the
 * host's A and AAAA records once, requires every address to be public, and connects to the
 * address that was checked rather than letting the transport resolve the name a second time.
 *
 * Twin of com_j2commercemigrator's Service\RemoteUrlGuard, which is rewired to this class once
 * the minimum supported J2Commerce version ships it (6.6.3).
 */
final class RemoteUrlGuard
{
    public const MAX_REDIRECTS = 3;

    private const ALLOWED_SCHEMES = ['http', 'https'];

    private const ALLOWED_PORTS = [80, 443, 8080, 8443];

    /** A well-formed http(s) URL on a web port whose host resolves only to public addresses. */
    public static function isAllowed(string $url): bool
    {
        return self::target($url) !== null;
    }

    /** isAllowed() plus a WARNING log naming the URL that was refused. */
    public static function assertAllowed(string $url, string $context = ''): bool
    {
        return self::checkedTarget($url, $context) !== null;
    }

    /**
     * GET $url. Returns the body on a 200, or null on refusal, transport error, any non-200 status,
     * or a body larger than $maxBytes (0 = no limit), which is enforced while the body is received.
     */
    public static function fetch(string $url, int $timeout = 30, string $context = '', int $maxBytes = 0): ?string
    {
        $response = self::request('get', $url, $timeout, $context, $maxBytes);

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
        $response = self::request('head', $url, $timeout, $context, 0);

        if ($response === null) {
            return null;
        }

        $headers = [];

        foreach ($response->getHeaders() as $name => $values) {
            $headers[strtolower((string) $name)] = (string) ($values[0] ?? '');
        }

        return $headers;
    }

    private static function request(string $method, string $url, int $timeout, string $context, int $maxBytes): ?ResponseInterface
    {
        $next = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $target = self::checkedTarget($next, $context);

            if ($target === null) {
                return null;
            }

            // CURLOPT_RESOLVE connects to the address checked above instead of a second lookup. An IP
            // literal is never looked up, and curl cannot parse an IPv6 literal in that option anyway.
            $curl = filter_var($target['host'], FILTER_VALIDATE_IP) === false
                ? [CURLOPT_RESOLVE => [$target['host'] . ':' . $target['port'] . ':' . $target['address']]]
                : [];

            if ($maxBytes > 0) {
                $curl[CURLOPT_MAXFILESIZE_LARGE] = $maxBytes;
            }

            try {
                // follow_location off: CurlTransport chases redirects by default, which would walk
                // past the per-hop check. Curl only: the stream transport cannot take a fixed address.
                $http     = (new HttpFactory())->getHttp(['follow_location' => false, 'transport.curl' => $curl], ['curl']);
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

    /** @return array{host: string, port: int, address: string}|null */
    private static function checkedTarget(string $url, string $context): ?array
    {
        $target = self::target($url);

        if ($target === null) {
            Log::add(
                'Refused to fetch "' . $url . '"' . ($context !== '' ? ' (' . $context . ')' : '')
                . ': the address is not a public http(s) destination.',
                Log::WARNING,
                'com_j2commerce'
            );
        }

        return $target;
    }

    /**
     * The host, port and address to connect to, or null when the URL is not http(s), not on a web
     * port, or any address its host resolves to is not globally routable.
     *
     * @return array{host: string, port: int, address: string}|null
     */
    private static function target(string $url): ?array
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (!filter_var($url, FILTER_VALIDATE_URL) || !\in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return null;
        }

        $host = trim((string) (parse_url($url, PHP_URL_HOST) ?? ''), '[]');
        $port = (int) (parse_url($url, PHP_URL_PORT) ?? ($scheme === 'https' ? 443 : 80));

        if ($host === '' || strcasecmp($host, 'localhost') === 0 || !\in_array($port, self::ALLOWED_PORTS, true)) {
            return null;
        }

        $isLiteral = filter_var($host, FILTER_VALIDATE_IP) !== false;

        // curl reads shorthand IPv4 forms (2130706433, 0x7f.1, 127.1, 0177.0.0.1) as an address and
        // connects without consulting CURLOPT_RESOLVE, so only the canonical literal is accepted.
        if (!$isLiteral && preg_match('/^(?:0x[0-9a-f]*|\d+)(?:\.(?:0x[0-9a-f]*|\d+)){0,3}\.?$/i', $host)) {
            return null;
        }

        $addresses = $isLiteral ? [$host] : self::addresses($host);

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (!self::isGlobal($address)) {
                return null;
            }
        }

        return [
            'host'    => $host,
            'port'    => $port,
            'address' => str_contains($addresses[0], ':') ? '[' . $addresses[0] . ']' : $addresses[0],
        ];
    }

    private static function isGlobal(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
            return false;
        }

        $packed = (string) inet_pton($address);

        // Multicast (224.0.0.0/4, ff00::/8), the deprecated IPv4-compatible ::/96 and the
        // IPv4-translated ::ffff:0:0/96 pass the range flag but are never a web destination.
        if ((\strlen($packed) === 4 && (\ord($packed[0]) & 0xF0) === 0xE0)
            || (\strlen($packed) === 16 && (
                $packed[0] === "\xff"
                || str_starts_with($packed, str_repeat("\x00", 12))
                || str_starts_with($packed, str_repeat("\x00", 8) . "\xff\xff\x00\x00")
            ))
        ) {
            return false;
        }

        // NAT64 prefixes carry an IPv4 destination: the local-use 64:ff9b:1::/48 is never global,
        // and the well-known 64:ff9b::/96 is only as global as the IPv4 address it embeds.
        if (str_starts_with($packed, "\x00\x64\xff\x9b\x00\x01")) {
            return false;
        }

        if (str_starts_with($packed, "\x00\x64\xff\x9b" . str_repeat("\x00", 8))) {
            return self::isGlobal((string) inet_ntop(substr($packed, 12)));
        }

        return true;
    }

    /** @return list<string>  Every A and AAAA address of $host. */
    private static function addresses(string $host): array
    {
        $addresses = [];

        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? '';

            if ($address !== '') {
                $addresses[] = $address;
            }
        }

        // dns_get_record() skips the hosts file, which the system resolver still honors.
        return $addresses ?: (@gethostbynamel($host) ?: []);
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
