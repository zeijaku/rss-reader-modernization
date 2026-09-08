<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('APP_TOTP_SECRET_KEY_ID=test-key');
putenv('APP_TOTP_SECRET_KEY_B64=AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=');
putenv('DB_DRIVER=mysql');
putenv('DB_HOST=test');
putenv('DB_NAME=test');
putenv('DB_USER=test');
putenv('DB_PASSWORD=test');

require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/auth_totp.php';

$checks = [];
$check = static function (bool $condition, string $message) use (&$checks): void {
    $checks[] = [$condition, $message];
};

$secret = random_bytes(20);
$envelope = auth_totp_encrypt_secret(77, $secret);
$check(auth_totp_decrypt_secret(77, $envelope) === $secret, 'valid encrypted TOTP secret decrypts in its owner context');

$parts = explode('.', $envelope);
$tamperedCipher = $parts;
$tamperedCipher[3] = substr($tamperedCipher[3], 0, -1) . ($tamperedCipher[3][-1] === 'A' ? 'B' : 'A');
try {
    auth_totp_decrypt_secret(77, implode('.', $tamperedCipher));
    $check(false, 'tampered TOTP ciphertext is rejected');
} catch (Throwable) {
    $check(true, 'tampered TOTP ciphertext is rejected');
}

try {
    auth_totp_decrypt_secret(78, $envelope);
    $check(false, 'TOTP envelope cannot be moved to another user context');
} catch (Throwable) {
    $check(true, 'TOTP envelope cannot be moved to another user context');
}

$wrongKeyId = $parts;
$wrongKeyId[1] = 'other-key';
try {
    auth_totp_decrypt_secret(77, implode('.', $wrongKeyId));
    $check(false, 'unknown TOTP key id fails closed');
} catch (Throwable) {
    $check(true, 'unknown TOTP key id fails closed');
}

foreach (['', 'v1', 'v1.test-key.bad.bad', 'v2.test-key.bad.bad', 'v1.test-key.@@.@@'] as $bad) {
    try {
        auth_totp_decrypt_secret(77, $bad);
        $check(false, 'malformed encrypted TOTP material is rejected');
    } catch (Throwable) {
        $check(true, 'malformed encrypted TOTP material is rejected');
    }
}

$now = 1788512430;
$current = auth_totp_code_at($secret, $now);
$previous = auth_totp_code_at($secret, $now - 30);
$tooOld = auth_totp_code_at($secret, $now - 60);
$check(auth_totp_match_step($secret, $current, $now, 1) !== null, 'current TOTP code remains valid');
$check(auth_totp_match_step($secret, $previous, $now, 1) !== null, 'one previous TOTP step remains valid for clock skew');
$check(auth_totp_match_step($secret, $tooOld, $now, 1) === null, 'two-step-old TOTP code remains expired');
foreach (['12345', '1234567', '123 456', '１２３４５６', "123456\0"] as $badCode) {
    $check(auth_totp_match_step($secret, $badCode, $now, 1) === null, 'non-canonical TOTP input is rejected');
}

$passed = 0;
foreach ($checks as [$ok, $message]) {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if ($ok) {
        $passed++;
    }
}
$failed = count($checks) - $passed;
echo "RESULT: PASS {$passed} / FAIL {$failed} / SKIP 0" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
