<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('APP_HTTP_CONNECT_TIMEOUT_MS=1500');
putenv('APP_HTTP_TIMEOUT_MS=5000');
putenv('APP_HTTP_MAX_REDIRECTS=3');
putenv('APP_HTTP_MAX_BYTES=1048576');
putenv('APP_HTTP_USER_AGENT=iGuguru-Test/1.0');
require $root . '/app/common/common_conf.php';
require $root . '/app/validation.php';
require $root . '/app/http_fetch.php';

$tests = 0;
$failures = [];
function fcheck(bool $condition, string $message): void
{
    global $tests, $failures;
    $tests++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) {
        $failures[] = $message;
    }
}

foreach (['127.0.0.1', '10.0.0.1', '172.16.0.1', '192.168.1.1', '169.254.169.254', '0.0.0.0', '::1', 'fc00::1', 'fe80::1'] as $ip) {
    fcheck(!app_is_public_ip($ip), "non-public IP rejected: {$ip}");
}
fcheck(app_is_public_ip('8.8.8.8'), 'public IPv4 accepted');
fcheck(app_is_public_ip('2001:4860:4860::8888'), 'public IPv6 accepted');

$publicResolver = static fn(string $host): array => ['93.184.216.34'];
$privateResolver = static fn(string $host): array => ['127.0.0.1'];
$mixedResolver = static fn(string $host): array => ['93.184.216.34', '10.0.0.5'];

$t = app_validate_fetch_target('https://example.com/feed', $publicResolver);
fcheck($t['ok'] === true && $t['port'] === 443 && $t['host'] === 'example.com', 'HTTPS public target passes with default port 443');
$t = app_validate_fetch_target('http://example.com/feed', $publicResolver);
fcheck($t['ok'] === true && $t['port'] === 80, 'HTTP public target passes with default port 80');
$t = app_validate_fetch_target('http://example.com:8080/feed', $publicResolver);
fcheck($t['ok'] === false && $t['error_code'] === 'port_not_allowed', 'non-default HTTP port is blocked');
$t = app_validate_fetch_target('https://example.com:8443/feed', $publicResolver);
fcheck($t['ok'] === false && $t['error_code'] === 'port_not_allowed', 'non-default HTTPS port is blocked');
$t = app_validate_fetch_target('http://127.0.0.1/feed', static fn(string $host): array => [$host]);
fcheck($t['ok'] === false && $t['error_code'] === 'non_public_address', 'direct loopback target is blocked');
$t = app_validate_fetch_target('http://localhost/feed', $privateResolver);
fcheck($t['ok'] === false && $t['error_code'] === 'non_public_address', 'localhost resolving to loopback is blocked');
$t = app_validate_fetch_target('https://mixed.example/feed', $mixedResolver);
fcheck($t['ok'] === false && $t['error_code'] === 'non_public_address', 'mixed public/private DNS answers fail closed');
$t = app_validate_fetch_target('https://unknown.example/feed', static fn(string $host): array => []);
fcheck($t['ok'] === false && $t['error_code'] === 'dns_failed', 'unresolved host is blocked');
fcheck(app_validate_fetch_target('ftp://example.com/feed', $publicResolver)['ok'] === false, 'non-http scheme is blocked before transport');
fcheck(app_validate_fetch_target('https://u:p@example.com/feed', $publicResolver)['ok'] === false, 'userinfo URL is blocked before transport');
$t = app_validate_fetch_target('https://[2001:4860:4860::8888]/feed', static fn(string $host): array => [$host]);
fcheck($t['ok'] === true && $t['host'] === '2001:4860:4860::8888', 'public IPv6 literal is accepted and normalized without brackets for pinning');
$t = app_validate_fetch_target('http://2130706433/feed', static fn(string $host): array => ['127.0.0.1']);
fcheck($t['ok'] === false && $t['error_code'] === 'non_public_address', 'alternative numeric loopback notation is blocked after resolution');

fcheck(app_resolve_redirect_url('https://example.com/a/b/feed.xml', '../next.xml') === 'https://example.com/a/next.xml', 'relative redirect is resolved safely');
fcheck(app_resolve_redirect_url('https://example.com/a/feed.xml', '/root.xml') === 'https://example.com/root.xml', 'root-relative redirect is resolved safely');
fcheck(app_resolve_redirect_url('https://example.com/a/feed.xml', '//other.example/x') === 'https://other.example/x', 'scheme-relative redirect inherits scheme then validates');
fcheck(app_resolve_redirect_url('https://example.com/a/feed.xml', 'javascript:alert(1)') === null, 'unsafe redirect scheme is rejected');

$calls = [];
$transport = static function (array $request) use (&$calls): array {
    $calls[] = $request;
    if ($request['url'] === 'https://feed.example/start') {
        return ['ok' => true, 'status' => 302, 'body' => '', 'location' => '/final', 'error_code' => '', 'error_message' => ''];
    }
    return ['ok' => true, 'status' => 200, 'body' => '<rss>ok</rss>', 'location' => null, 'error_code' => '', 'error_message' => ''];
};
$resolver = static fn(string $host): array => $host === 'feed.example' ? ['93.184.216.34'] : ['8.8.8.8'];
$r = app_safe_http_fetch('https://feed.example/start', $resolver, $transport);
fcheck($r['ok'] === true && $r['url'] === 'https://feed.example/final' && $r['body'] === '<rss>ok</rss>', 'manual redirect succeeds after revalidation');
fcheck(count($calls) === 2, 'manual redirect performs exactly two transport hops');
fcheck(($calls[0]['ip'] ?? '') === '93.184.216.34', 'validated DNS IP is pinned into transport request');
fcheck(($calls[0]['host'] ?? '') === 'feed.example' && ($calls[0]['port'] ?? 0) === 443, 'transport retains original hostname and TLS port');
fcheck(($calls[0]['user_agent'] ?? '') === 'iGuguru-Test/1.0', 'fixed application User-Agent is passed to transport');
fcheck(($calls[0]['max_bytes'] ?? 0) === 1048576, 'configured response size limit is passed to transport');
fcheck(($calls[0]['connect_timeout_ms'] ?? 0) === 1500 && ($calls[0]['total_timeout_ms'] ?? 0) === 5000, 'configured connect/total timeouts are passed to transport');

$calls = [];
$transportPrivateRedirect = static function (array $request) use (&$calls): array {
    $calls[] = $request;
    return ['ok' => true, 'status' => 302, 'body' => '', 'location' => 'http://internal.example/secret', 'error_code' => '', 'error_message' => ''];
};
$resolverPrivateRedirect = static fn(string $host): array => $host === 'internal.example' ? ['10.1.2.3'] : ['93.184.216.34'];
$r = app_safe_http_fetch('https://feed.example/start', $resolverPrivateRedirect, $transportPrivateRedirect);
fcheck($r['ok'] === false && $r['error_code'] === 'non_public_address', 'redirect to private address is blocked');
fcheck(count($calls) === 1, 'private redirect is rejected before second outbound transport call');

$redirectCount = 0;
$endless = static function (array $request) use (&$redirectCount): array {
    $redirectCount++;
    return ['ok' => true, 'status' => 302, 'body' => '', 'location' => '/loop' . $redirectCount, 'error_code' => '', 'error_message' => ''];
};
$r = app_safe_http_fetch('https://feed.example/start', $publicResolver, $endless);
fcheck($r['ok'] === false && $r['error_code'] === 'too_many_redirects', 'redirect count is bounded');
fcheck($redirectCount === APP_HTTP_MAX_REDIRECTS + 1, 'redirect bound includes initial request and configured follow attempts');

$statusTransport = static fn(array $request): array => ['ok' => true, 'status' => 404, 'body' => 'not found', 'location' => null, 'error_code' => '', 'error_message' => ''];
$r = app_safe_http_fetch('https://feed.example/missing', $publicResolver, $statusTransport);
fcheck($r['ok'] === false && $r['error_code'] === 'http_status' && $r['status'] === 404, 'non-2xx HTTP status is rejected');

$emptyTransport = static fn(array $request): array => ['ok' => true, 'status' => 200, 'body' => '', 'location' => null, 'error_code' => '', 'error_message' => ''];
$r = app_safe_http_fetch('https://feed.example/empty', $publicResolver, $emptyTransport);
fcheck($r['ok'] === false && $r['error_code'] === 'empty_response', 'empty 2xx body is rejected');

$largeTransport = static fn(array $request): array => ['ok' => false, 'status' => 200, 'body' => '', 'location' => null, 'error_code' => 'response_too_large', 'error_message' => 'too large'];
$r = app_safe_http_fetch('https://feed.example/large', $publicResolver, $largeTransport);
fcheck($r['ok'] === false && $r['error_code'] === 'response_too_large', 'transport body limit failure is propagated');

$transportError = static fn(array $request): array => ['ok' => false, 'status' => 0, 'body' => '', 'location' => null, 'error_code' => 'transport_error', 'error_message' => 'timeout'];
$r = app_safe_http_fetch('https://feed.example/timeout', $publicResolver, $transportError);
fcheck($r['ok'] === false && $r['error_code'] === 'transport_error', 'transport timeout/error is not treated as success');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . "/{$tests} SB-09 fetch checks failed.\n");
    exit(1);
}

echo "All {$tests} SB-09 safe-fetch checks passed.\n";
