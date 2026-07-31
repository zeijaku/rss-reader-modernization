<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('LOGIN_RATE_WINDOW=300');
putenv('LOGIN_RATE_MAX_PAIR=3');
putenv('LOGIN_RATE_MAX_IP=6');
putenv('LOGIN_RATE_BLOCK_SECONDS=120');

require $root . '/app/common/common_conf.php';
require $root . '/app/login_throttle.php';

$dir = login_throttle_prepare_directory();
foreach (glob($dir . '/*.json') ?: [] as $file) { @unlink($file); }

$failures = [];
function tcheck(bool $condition, string $message): void
{
    global $failures;
    if ($condition) echo "PASS: {$message}\n";
    else { $failures[] = $message; echo "FAIL: {$message}\n"; }
}

$identity = str_repeat('b', 64);
$ip = '203.0.113.10';
$now = 1000000;

tcheck(login_throttle_status($identity, $ip, $now)['blocked'] === false, 'fresh login is not blocked');
login_throttle_record_failure($identity, $ip, $now);
login_throttle_record_failure($identity, $ip, $now + 1);
tcheck(login_throttle_status($identity, $ip, $now + 2)['blocked'] === false, 'below pair threshold remains allowed');
login_throttle_record_failure($identity, $ip, $now + 2);
$status = login_throttle_status($identity, $ip, $now + 3);
tcheck($status['blocked'] === true && $status['retry_after'] > 0, 'pair threshold triggers temporary block');
tcheck(login_throttle_status($identity, $ip, $now + 130)['blocked'] === false, 'block expires automatically');

login_throttle_record_failure($identity, $ip, $now + 200);
login_throttle_record_success($identity, $ip);
tcheck(login_throttle_status($identity, $ip, $now + 201)['blocked'] === false, 'successful login clears pair failure state');

foreach (glob($dir . '/*.json') ?: [] as $file) { @unlink($file); }

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " throttle checks failed.\n");
    exit(1);
}

echo "All login throttle checks passed.\n";
