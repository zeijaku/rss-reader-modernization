<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('APP_HTTP_MAX_REDIRECTS=3');
require $root . '/app/common/common_conf.php';
require $root . '/app/validation.php';
require $root . '/app/http_fetch.php';

$tests = 0;
$failures = [];
function sb14_ssrf_check(bool $condition, string $message): void
{
    global $tests, $failures;
    $tests++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) {
        $failures[] = $message;
    }
}

foreach ([
    '0.0.0.0',
    '10.0.0.1',
    '100.64.0.1',
    '127.0.0.1',
    '169.254.169.254',
    '172.31.255.255',
    '192.0.0.1',
    '192.0.2.1',
    '192.168.1.1',
    '198.18.0.1',
    '198.51.100.1',
    '203.0.113.1',
    '224.0.0.1',
    '239.255.255.255',
    '240.0.0.1',
    '255.255.255.255',
    '::',
    '::1',
    '::ffff:127.0.0.1',
    '64:ff9b::1',
    '100::1',
    '2001:db8::1',
    '2002::1',
    'fc00::1',
    'fd12:3456::1',
    'fe80::1',
    'ff02::1',
] as $ip) {
    sb14_ssrf_check(!app_is_public_ip($ip), "special/non-global address rejected: {$ip}");
}

foreach (['8.8.8.8', '1.1.1.1', '93.184.216.34', '2001:4860:4860::8888', '2606:4700:4700::1111'] as $ip) {
    sb14_ssrf_check(app_is_public_ip($ip), "representative globally-routable address accepted: {$ip}");
}

sb14_ssrf_check(app_ip_in_cidr('192.0.2.255', '192.0.2.0/24'), 'CIDR helper includes upper IPv4 boundary');
sb14_ssrf_check(!app_ip_in_cidr('192.0.3.0', '192.0.2.0/24'), 'CIDR helper excludes adjacent IPv4 network');
sb14_ssrf_check(app_ip_in_cidr('fdff:ffff::1', 'fc00::/7'), 'CIDR helper handles IPv6 ULA range');
sb14_ssrf_check(!app_ip_in_cidr('2001:4860::1', 'fc00::/7'), 'CIDR helper excludes public IPv6 from ULA');
sb14_ssrf_check(!app_ip_in_cidr('not-an-ip', '10.0.0.0/8'), 'CIDR helper rejects malformed IP');
sb14_ssrf_check(!app_ip_in_cidr('8.8.8.8', '10.0.0.0/999'), 'CIDR helper rejects invalid prefix length');

$publicResolver = static fn(string $host): array => ['93.184.216.34'];
$serverError = static fn(array $request): array => [
    'ok' => true,
    'status' => 500,
    'body' => 'internal error',
    'location' => null,
    'error_code' => '',
    'error_message' => '',
];
$result = app_safe_http_fetch('https://feed.example/error', $publicResolver, $serverError);
sb14_ssrf_check(($result['ok'] ?? true) === false && ($result['error_code'] ?? '') === 'http_status' && ($result['status'] ?? 0) === 500, 'HTTP 500 upstream response fails closed');

$privateRedirect = static fn(array $request): array => [
    'ok' => true,
    'status' => 302,
    'body' => '',
    'location' => 'http://169.254.169.254/latest/meta-data/',
    'error_code' => '',
    'error_message' => '',
];
$resolver = static function (string $host): array {
    return $host === 'feed.example' ? ['93.184.216.34'] : ['169.254.169.254'];
};
$result = app_safe_http_fetch('https://feed.example/start', $resolver, $privateRedirect);
sb14_ssrf_check(($result['ok'] ?? true) === false && ($result['error_code'] ?? '') === 'non_public_address', 'redirect to cloud metadata/link-local address is rejected before second fetch');

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d/%d SB-14 SSRF matrix checks failed.\n", count($failures), $tests));
    exit(1);
}

echo "All {$tests} SB-14 SSRF matrix checks passed.\n";
