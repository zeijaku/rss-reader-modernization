<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('APP_TOTP_SECRET_KEY_ID=test-key');
putenv('APP_TOTP_SECRET_KEY_B64=AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=');
putenv('APP_TOTP_ISSUER=iGuguru-Test');
putenv('DB_DRIVER=mysql');
putenv('DB_HOST=test');
putenv('DB_NAME=test');
putenv('DB_USER=test');
putenv('DB_PASSWORD=test');

require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/auth_totp.php';

$results = [];
$check = static function (bool $condition, string $message) use (&$results): void {
    $results[] = [$condition, $message];
};

$secret = '12345678901234567890';
$now = 1788512430; // aligned to a 30-second TOTP step
$current = auth_totp_code_at($secret, $now);
$previous = auth_totp_code_at($secret, $now - 30);
$tooOld = auth_totp_code_at($secret, $now - 60);
$next = auth_totp_code_at($secret, $now + 30);
$tooFuture = auth_totp_code_at($secret, $now + 60);

$check(auth_totp_match_step($secret, $current, $now, 1) === auth_totp_step_at($now), 'current TOTP step is accepted');
$check(auth_totp_match_step($secret, $previous, $now, 1) === auth_totp_step_at($now - 30), 'immediately previous TOTP step is accepted for clock skew');
$check(auth_totp_match_step($secret, $next, $now, 1) === auth_totp_step_at($now + 30), 'immediately next TOTP step is accepted for clock skew');
$check(auth_totp_match_step($secret, $tooOld, $now, 1) === null, 'TOTP code two steps old is rejected as expired');
$check(auth_totp_match_step($secret, $tooFuture, $now, 1) === null, 'TOTP code two steps ahead is rejected');
$check(auth_totp_match_step($secret, '12 3456', $now, 1) === null, 'non-six-digit TOTP input is rejected');

$passed = 0;
foreach ($results as [$ok, $message]) {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if ($ok) { $passed++; }
}
$failed = count($results) - $passed;
echo "RESULT: PASS {$passed} / FAIL {$failed} / SKIP 0" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
