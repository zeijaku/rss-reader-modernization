<?php

declare(strict_types=1);

require_once __DIR__ . '/feed/feed_http_headers.php';
require_once __DIR__ . '/feed/feed_retry.php';

/**
 * SB-09 safe outbound HTTP fetcher.
 *
 * Security properties:
 * - only http/https
 * - only default ports (80 for http, 443 for https)
 * - every resolved address must be publicly routable
 * - DNS result is pinned to cURL with CURLOPT_RESOLVE
 * - redirects are followed manually and revalidated
 * - TLS peer/hostname verification remains enabled
 * - fixed application User-Agent
 * - bounded response body and timeouts
 */

/** @return array<string,mixed> */
function app_fetch_result(
    bool $ok,
    string $url,
    int $status = 0,
    string $body = '',
    string $errorCode = '',
    string $errorMessage = '',
    ?string $etag = null,
    ?string $lastModified = null,
    bool $notModified = false,
    ?string $retryAfter = null
): array {
    return [
        'ok' => $ok,
        'url' => $url,
        'status' => $status,
        'body' => $body,
        'etag' => $etag,
        'last_modified' => $lastModified,
        'not_modified' => $notModified,
        'retry_after' => $retryAfter,
        'error_code' => $errorCode,
        'error_message' => $errorMessage,
    ];
}

/** Return true when an IP address belongs to the supplied CIDR block. */
function app_ip_in_cidr(string $ip, string $cidr): bool
{
    [$network, $prefixText] = array_pad(explode('/', $cidr, 2), 2, '');
    if ($network === '' || $prefixText === '' || preg_match('/\A\d{1,3}\z/D', $prefixText) !== 1) {
        return false;
    }

    $packedIp = @inet_pton($ip);
    $packedNetwork = @inet_pton($network);
    if (!is_string($packedIp) || !is_string($packedNetwork) || strlen($packedIp) !== strlen($packedNetwork)) {
        return false;
    }

    $bits = strlen($packedIp) * 8;
    $prefix = (int) $prefixText;
    if ($prefix < 0 || $prefix > $bits) {
        return false;
    }

    $wholeBytes = intdiv($prefix, 8);
    $remainingBits = $prefix % 8;
    if ($wholeBytes > 0 && substr($packedIp, 0, $wholeBytes) !== substr($packedNetwork, 0, $wholeBytes)) {
        return false;
    }
    if ($remainingBits === 0) {
        return true;
    }

    $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
    return (ord($packedIp[$wholeBytes]) & $mask) === (ord($packedNetwork[$wholeBytes]) & $mask);
}

function app_is_public_ip(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return false;
    }

    // PHP's NO_PRIV_RANGE / NO_RES_RANGE filters do not reject every IANA
    // special-purpose range on every supported PHP build (for example TEST-NET,
    // benchmark, shared-address and multicast ranges). Keep the built-in check
    // as a first line, then explicitly deny non-global ranges used by SSRF
    // bypasses. The policy intentionally prefers a false negative over allowing
    // a request to a special-use address.
    if (filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) === false) {
        return false;
    }

    $blockedCidrs = str_contains($ip, ':')
        ? [
            '::/128',           // unspecified
            '::1/128',          // loopback
            '::ffff:0:0/96',    // IPv4-mapped
            '64:ff9b::/96',     // IPv4/IPv6 translation well-known prefix
            '64:ff9b:1::/48',   // local-use translation prefix
            '100::/64',         // discard-only
            '2001::/23',        // IETF protocol assignments / special use
            '2001:db8::/32',    // documentation
            '2002::/16',        // deprecated 6to4
            'fc00::/7',         // unique local
            'fe80::/10',        // link local
            'ff00::/8',         // multicast
        ]
        : [
            '0.0.0.0/8',        // current network / unspecified
            '10.0.0.0/8',       // RFC1918
            '100.64.0.0/10',    // shared address space
            '127.0.0.0/8',      // loopback
            '169.254.0.0/16',   // link local / cloud metadata
            '172.16.0.0/12',    // RFC1918
            '192.0.0.0/24',     // IETF protocol assignments
            '192.0.2.0/24',     // TEST-NET-1
            '192.168.0.0/16',   // RFC1918
            '198.18.0.0/15',    // benchmark testing
            '198.51.100.0/24',  // TEST-NET-2
            '203.0.113.0/24',   // TEST-NET-3
            '224.0.0.0/4',      // multicast
            '240.0.0.0/4',      // reserved / limited broadcast
        ];

    foreach ($blockedCidrs as $cidr) {
        if (app_ip_in_cidr($ip, $cidr)) {
            return false;
        }
    }

    return true;
}

/** @return list<string> */
function app_resolve_host_ips(string $host): array
{
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        return [$host];
    }

    $ips = [];
    if (function_exists('dns_get_record')) {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ip']) && is_string($record['ip'])) {
                    $ips[] = $record['ip'];
                }
                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }
    }

    if ($ips === [] && function_exists('gethostbynamel')) {
        $legacy = @gethostbynamel($host);
        if (is_array($legacy)) {
            foreach ($legacy as $ip) {
                if (is_string($ip)) {
                    $ips[] = $ip;
                }
            }
        }
    }

    return array_values(array_unique($ips));
}

/**
 * @param callable(string):list<string>|null $resolver
 * @return array{ok:bool,url:string,scheme:string,host:string,port:int,ips:list<string>,error_code:string,error_message:string}
 */
function app_validate_fetch_target(string $url, ?callable $resolver = null): array
{
    $normalized = app_validate_feed_url($url);
    if ($normalized === null) {
        return [
            'ok' => false,
            'url' => '',
            'scheme' => '',
            'host' => '',
            'port' => 0,
            'ips' => [],
            'error_code' => 'invalid_url',
            'error_message' => 'Feed URL is invalid.',
        ];
    }

    $parts = parse_url($normalized);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
        $host = substr($host, 1, -1);
    }
    $defaultPort = $scheme === 'https' ? 443 : 80;
    $port = isset($parts['port']) ? (int) $parts['port'] : $defaultPort;

    if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
        return [
            'ok' => false,
            'url' => $normalized,
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'ips' => [],
            'error_code' => 'port_not_allowed',
            'error_message' => 'Only the default HTTP/HTTPS ports are allowed.',
        ];
    }

    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        $ips = [$host];
    } else {
        $resolve = $resolver ?? 'app_resolve_host_ips';
        $ips = $resolve($host);
    }
    if (!is_array($ips) || $ips === []) {
        return [
            'ok' => false,
            'url' => $normalized,
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'ips' => [],
            'error_code' => 'dns_failed',
            'error_message' => 'Feed host could not be resolved.',
        ];
    }

    $cleanIps = [];
    foreach ($ips as $ip) {
        if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            continue;
        }
        if (!app_is_public_ip($ip)) {
            return [
                'ok' => false,
                'url' => $normalized,
                'scheme' => $scheme,
                'host' => $host,
                'port' => $port,
                'ips' => [],
                'error_code' => 'non_public_address',
                'error_message' => 'Feed host resolves to a non-public address.',
            ];
        }
        $cleanIps[] = $ip;
    }

    $cleanIps = array_values(array_unique($cleanIps));
    if ($cleanIps === []) {
        return [
            'ok' => false,
            'url' => $normalized,
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'ips' => [],
            'error_code' => 'dns_failed',
            'error_message' => 'Feed host did not resolve to a usable address.',
        ];
    }

    return [
        'ok' => true,
        'url' => $normalized,
        'scheme' => $scheme,
        'host' => $host,
        'port' => $port,
        'ips' => $cleanIps,
        'error_code' => '',
        'error_message' => '',
    ];
}

function app_remove_dot_segments(string $path): string
{
    $segments = explode('/', $path);
    $output = [];
    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($output);
            continue;
        }
        $output[] = $segment;
    }
    return '/' . implode('/', $output);
}

function app_resolve_redirect_url(string $baseUrl, string $location): ?string
{
    $location = trim($location);
    if ($location === '' || app_has_control_characters($location)) {
        return null;
    }

    if (preg_match('/^https?:\/\//i', $location) === 1) {
        return app_validate_feed_url($location);
    }
    // A URI scheme other than http/https must never be interpreted as a
    // relative path (e.g. javascript:, data:, file:).
    if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $location) === 1) {
        return null;
    }

    $base = parse_url($baseUrl);
    if (!is_array($base)) {
        return null;
    }
    $scheme = strtolower((string) ($base['scheme'] ?? ''));
    $host = (string) ($base['host'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        return null;
    }
    $hostForUrl = str_contains($host, ':') ? '[' . $host . ']' : $host;
    $origin = $scheme . '://' . $hostForUrl;
    if (isset($base['port'])) {
        $origin .= ':' . (int) $base['port'];
    }

    if (str_starts_with($location, '//')) {
        return app_validate_feed_url($scheme . ':' . $location);
    }

    $fragmentless = explode('#', $location, 2)[0];
    if ($fragmentless === '') {
        return null;
    }

    if (str_starts_with($fragmentless, '/')) {
        return app_validate_feed_url($origin . app_remove_dot_segments((string) parse_url($fragmentless, PHP_URL_PATH))
            . (($query = parse_url($fragmentless, PHP_URL_QUERY)) !== null ? '?' . $query : ''));
    }

    if (str_starts_with($fragmentless, '?')) {
        $basePath = (string) ($base['path'] ?? '/');
        return app_validate_feed_url($origin . ($basePath === '' ? '/' : $basePath) . $fragmentless);
    }

    $basePath = (string) ($base['path'] ?? '/');
    $directory = str_ends_with($basePath, '/') ? $basePath : dirname($basePath) . '/';
    $relativePath = (string) parse_url($fragmentless, PHP_URL_PATH);
    $query = parse_url($fragmentless, PHP_URL_QUERY);
    $resolved = $origin . app_remove_dot_segments($directory . $relativePath);
    if ($query !== null) {
        $resolved .= '?' . $query;
    }
    return app_validate_feed_url($resolved);
}

/**
 * 1回分のcURL取得。DNS pinning、TLS確認、本文上限は従来どおり維持する。
 *
 * @param array<string,mixed> $request
 * @return array<string,mixed>
 */
function app_curl_single_hop(array $request): array
{
    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'status' => 0,
            'body' => '',
            'location' => null,
            'etag' => null,
            'last_modified' => null,
            'retry_after' => null,
            'error_code' => 'curl_unavailable',
            'error_message' => 'cURL extension is unavailable.',
        ];
    }

    $body = '';
    $tooLarge = false;
    $location = null;
    $etag = null;
    $lastModified = null;
    $retryAfter = null;
    $maxBytes = (int) $request['max_bytes'];

    $ch = curl_init();
    if ($ch === false) {
        return [
            'ok' => false,
            'status' => 0,
            'body' => '',
            'location' => null,
            'etag' => null,
            'last_modified' => null,
            'retry_after' => null,
            'error_code' => 'curl_init_failed',
            'error_message' => 'HTTP client initialization failed.',
        ];
    }

    $httpHeaders = ['Accept: application/rss+xml, application/atom+xml, application/xml, text/xml, */*;q=0.1'];
    foreach (($request['request_headers'] ?? []) as $header) {
        if (!is_string($header) || str_contains($header, "\r") || str_contains($header, "\n")) {
            continue;
        }
        if (str_starts_with($header, 'If-None-Match: ') || str_starts_with($header, 'If-Modified-Since: ')) {
            $httpHeaders[] = $header;
        }
    }

    $options = [
        CURLOPT_URL => $request['url'],
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_CONNECTTIMEOUT_MS => $request['connect_timeout_ms'],
        CURLOPT_TIMEOUT_MS => $request['total_timeout_ms'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => $request['user_agent'],
        CURLOPT_HTTPHEADER => $httpHeaders,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$location, &$etag, &$lastModified, &$retryAfter): int {
            $length = strlen($line);
            $trimmed = trim($line);
            if ($trimmed === '') {
                return $length;
            }
            if (str_starts_with(strtoupper($trimmed), 'HTTP/')) {
                $location = null;
                $etag = null;
                $lastModified = null;
                $retryAfter = null;
                return $length;
            }

            $separator = strpos($trimmed, ':');
            if ($separator === false) {
                return $length;
            }
            $name = strtolower(trim(substr($trimmed, 0, $separator)));
            $value = trim(substr($trimmed, $separator + 1));
            if ($name === 'location') {
                $location = $value;
            } elseif ($name === 'etag') {
                $etag = feed_clean_etag($value);
            } elseif ($name === 'last-modified') {
                $lastModified = feed_clean_last_modified($value);
            } elseif ($name === 'retry-after') {
                $retryAfter = feed_clean_retry_after($value);
            }
            return $length;
        },
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge, $maxBytes): int {
            if (strlen($body) + strlen($chunk) > $maxBytes) {
                $tooLarge = true;
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    ];

    if (filter_var($request['host'], FILTER_VALIDATE_IP) === false) {
        $resolveIp = str_contains($request['ip'], ':') ? '[' . $request['ip'] . ']' : $request['ip'];
        $options[CURLOPT_RESOLVE] = [$request['host'] . ':' . $request['port'] . ':' . $resolveIp];
    }

    curl_setopt_array($ch, $options);
    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    }

    $executed = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $errorNo = curl_errno($ch);
    $errorMessage = curl_error($ch);
    curl_close($ch);

    if ($tooLarge) {
        return [
            'ok' => false,
            'status' => $status,
            'body' => '',
            'location' => $location,
            'etag' => $etag,
            'last_modified' => $lastModified,
            'retry_after' => $retryAfter,
            'error_code' => 'response_too_large',
            'error_message' => 'Feed response exceeded the configured size limit.',
        ];
    }
    if ($executed === false || $errorNo !== 0) {
        $errorCode = 'transport_error';
        if (defined('CURLE_OPERATION_TIMEDOUT') && $errorNo === CURLE_OPERATION_TIMEDOUT) {
            $errorCode = 'timeout';
        } elseif (defined('CURLE_COULDNT_RESOLVE_HOST') && $errorNo === CURLE_COULDNT_RESOLVE_HOST) {
            $errorCode = 'dns_failed';
        } elseif ((defined('CURLE_SSL_CONNECT_ERROR') && $errorNo === CURLE_SSL_CONNECT_ERROR)
            || (defined('CURLE_PEER_FAILED_VERIFICATION') && $errorNo === CURLE_PEER_FAILED_VERIFICATION)
            || (defined('CURLE_SSL_CACERT') && $errorNo === CURLE_SSL_CACERT)
        ) {
            $errorCode = 'tls_error';
        }

        return [
            'ok' => false,
            'status' => $status,
            'body' => '',
            'location' => $location,
            'etag' => $etag,
            'last_modified' => $lastModified,
            'retry_after' => $retryAfter,
            'error_code' => $errorCode,
            'error_message' => $errorMessage !== '' ? $errorMessage : 'Feed request failed.',
        ];
    }

    return [
        'ok' => true,
        'status' => $status,
        'body' => $body,
        'location' => $location,
        'etag' => $etag,
        'last_modified' => $lastModified,
        'retry_after' => $retryAfter,
        'error_code' => '',
        'error_message' => '',
    ];
}

/**
 * @param callable(string):list<string>|null $resolver
 * @param callable(array):array|null $transport
 * @param array<string,mixed> $validators
 * @return array<string,mixed>
 */
function app_safe_http_fetch(
    string $url,
    ?callable $resolver = null,
    ?callable $transport = null,
    array $validators = []
): array {
    if ($resolver === null && defined('APP_ENV') && APP_ENV === 'testing'
        && isset($GLOBALS['app_http_fetch_test_resolver']) && is_callable($GLOBALS['app_http_fetch_test_resolver'])) {
        $resolver = $GLOBALS['app_http_fetch_test_resolver'];
    }
    if ($transport === null && defined('APP_ENV') && APP_ENV === 'testing'
        && isset($GLOBALS['app_http_fetch_test_transport']) && is_callable($GLOBALS['app_http_fetch_test_transport'])) {
        $transport = $GLOBALS['app_http_fetch_test_transport'];
    }

    $currentUrl = $url;
    $transportFn = $transport ?? 'app_curl_single_hop';
    $maxRedirects = APP_HTTP_MAX_REDIRECTS;

    for ($hop = 0; $hop <= $maxRedirects; $hop++) {
        $target = app_validate_fetch_target($currentUrl, $resolver);
        if (($target['ok'] ?? false) !== true) {
            return app_fetch_result(
                false,
                (string) ($target['url'] ?? $currentUrl),
                0,
                '',
                (string) ($target['error_code'] ?? 'invalid_target'),
                (string) ($target['error_message'] ?? 'Feed target is not allowed.')
            );
        }

        $requestUrl = (string) $target['url'];
        $requestHeaders = feed_conditional_request_headers($validators, $requestUrl);
        $sentConditional = $requestHeaders !== [];

        // DNSで確認した公開IPへ固定して送信する。
        $ip = (string) $target['ips'][0];
        $response = $transportFn([
            'url' => $requestUrl,
            'host' => (string) $target['host'],
            'port' => (int) $target['port'],
            'ip' => $ip,
            'max_bytes' => APP_HTTP_MAX_BYTES,
            'connect_timeout_ms' => APP_HTTP_CONNECT_TIMEOUT_MS,
            'total_timeout_ms' => APP_HTTP_TIMEOUT_MS,
            'user_agent' => APP_HTTP_USER_AGENT,
            'request_headers' => $requestHeaders,
        ]);

        if (($response['ok'] ?? false) !== true) {
            return app_fetch_result(
                false,
                $requestUrl,
                (int) ($response['status'] ?? 0),
                '',
                (string) ($response['error_code'] ?? 'transport_error'),
                (string) ($response['error_message'] ?? 'Feed request failed.'),
                null,
                null,
                false,
                feed_clean_retry_after($response['retry_after'] ?? null)
            );
        }

        $status = (int) ($response['status'] ?? 0);
        $etag = feed_clean_etag($response['etag'] ?? null);
        $lastModified = feed_clean_last_modified($response['last_modified'] ?? null);
        $retryAfter = feed_clean_retry_after($response['retry_after'] ?? null);

        if (in_array($status, [301, 302, 303, 307, 308], true)) {
            if ($hop >= $maxRedirects) {
                return app_fetch_result(false, $requestUrl, $status, '', 'too_many_redirects', 'Too many redirects.');
            }
            $location = isset($response['location']) && is_string($response['location']) ? $response['location'] : '';
            $nextUrl = app_resolve_redirect_url($requestUrl, $location);
            if ($nextUrl === null) {
                return app_fetch_result(false, $requestUrl, $status, '', 'invalid_redirect', 'Redirect target is invalid.');
            }
            $currentUrl = $nextUrl;
            continue;
        }

        if ($status === 304) {
            if (!$sentConditional) {
                return app_fetch_result(false, $requestUrl, 304, '', 'unexpected_not_modified', 'Unexpected HTTP 304 response.');
            }
            return app_fetch_result(true, $requestUrl, 304, '', '', '', $etag, $lastModified, true);
        }

        if ($status < 200 || $status >= 300) {
            return app_fetch_result(false, $requestUrl, $status, '', 'http_status', 'Feed server returned an unsuccessful HTTP status.', null, null, false, $retryAfter);
        }

        $body = isset($response['body']) && is_string($response['body']) ? $response['body'] : '';
        if ($body === '') {
            return app_fetch_result(false, $requestUrl, $status, '', 'empty_response', 'Feed response was empty.');
        }

        return app_fetch_result(true, $requestUrl, $status, $body, '', '', $etag, $lastModified, false);
    }

    return app_fetch_result(false, $currentUrl, 0, '', 'too_many_redirects', 'Too many redirects.');
}
