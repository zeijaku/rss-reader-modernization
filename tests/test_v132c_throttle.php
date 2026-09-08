<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');
putenv('DB_HOST=test');
putenv('DB_NAME=test');
putenv('DB_USER=test');
putenv('DB_PASSWORD=test');
putenv('AUTH_2FA_RATE_WINDOW=900');
putenv('AUTH_2FA_RATE_MAX_PAIR=3');
putenv('AUTH_2FA_RATE_MAX_IP=30');
putenv('AUTH_2FA_RATE_BLOCK_SECONDS=120');

require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/login_throttle.php';

$directory = login_throttle_directory();
if (is_dir($directory)) {
    foreach (glob($directory . '/*.json') ?: [] as $path) {
        @unlink($path);
    }
}

$results = [];
$check = static function (bool $condition, string $message) use (&$results): void {
    $results[] = [$condition, $message];
};

$userId = 42;
$ip = '192.0.2.32';
$now = 1788512400;
$identity = auth_2fa_throttle_identity($userId);
$check(strlen($identity) === 64 && ctype_xdigit($identity), '2FA throttle identity is a keyed non-plain digest');
$check(!auth_2fa_throttle_status($userId, $ip, $now)['blocked'], 'fresh 2FA throttle state is open');

auth_2fa_throttle_record_failure($userId, $ip, $now);
auth_2fa_throttle_record_failure($userId, $ip, $now + 1);
$check(!auth_2fa_throttle_status($userId, $ip, $now + 1)['blocked'], '2FA pair remains open below its threshold');
auth_2fa_throttle_record_failure($userId, $ip, $now + 2);
$status = auth_2fa_throttle_status($userId, $ip, $now + 2);
$check($status['blocked'] && $status['retry_after'] > 0, '2FA pair blocks at its dedicated threshold');

$passwordPairPath = login_throttle_path('pair', $identity . "\0" . $ip);
$factorPairPath = login_throttle_path('2fa-pair', $identity . "\0" . $ip);
$check(!is_file($passwordPairPath) && is_file($factorPairPath), '2FA failures use a separate namespace from password Login buckets');

auth_2fa_throttle_record_success($userId, $ip);
$check(!is_file($factorPairPath), 'successful 2FA clears only the user/IP pair bucket');
$check(is_file(login_throttle_path('2fa-ip', $ip)), 'successful 2FA preserves the IP-wide abuse history');

foreach (glob($directory . '/*.json') ?: [] as $path) {
    @unlink($path);
}

$passed = 0;
foreach ($results as [$ok, $message]) {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if ($ok) {
        $passed++;
    }
}
$failed = count($results) - $passed;
echo "RESULT: PASS {$passed} / FAIL {$failed} / SKIP 0" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
