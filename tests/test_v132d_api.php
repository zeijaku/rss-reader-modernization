<?php

declare(strict_types=1);

final class AppTotpException extends RuntimeException {}

const AUTH_RECOVERY_CODE_COUNT = 10;
$GLOBALS['totp_status'] = ['configured' => true, 'enabled' => true];
$GLOBALS['verify_result'] = true;
$GLOBALS['verify_throw'] = null;
$GLOBALS['replace_result'] = ['ok' => true, 'codes' => []];
$GLOBALS['replace_calls'] = 0;
$GLOBALS['throttle'] = ['blocked' => false, 'retry_after' => 0];
$GLOBALS['failures'] = 0;
$GLOBALS['successes'] = 0;
$_SERVER['REMOTE_ADDR'] = '198.51.100.77';

$sampleCodes = [];
for ($i = 0; $i < 10; $i++) {
    $sampleCodes[] = 'ABCD-EFGH-JKLM-' . str_pad((string) (2345 + $i), 4, '2', STR_PAD_LEFT);
}
// Keep every character inside the Recovery Code alphabet.
$sampleCodes = [
    'ABCD-EFGH-JKLM-NPQR', 'BCDE-FGHJ-KLMN-PQRS', 'CDEF-GHJK-LMNP-QRST',
    'DEFG-HJKL-MNPQ-RSTU', 'EFGH-JKLM-NPQR-STUV', 'FGHJ-KLMN-PQRS-TUVW',
    'GHJK-LMNP-QRST-UVWX', 'HJKL-MNPQ-RSTU-VWXY', 'JKLM-NPQR-STUV-WXYZ',
    'KLMN-PQRS-TUVW-XYZ2',
];
$GLOBALS['replace_result']['codes'] = $sampleCodes;

function api_success(array $data = [], int $status = 200): array { return ['status' => $status, 'body' => ['ok' => true, 'data' => $data]]; }
function api_error(string $code, string $message, int $status): array { return ['status' => $status, 'body' => ['ok' => false, 'error' => ['code' => $code, 'message' => $message]]]; }
function api_validation_error(string $message): array { return api_error('validation_error', $message, 422); }
function auth_totp_status(int $userId): array { return $GLOBALS['totp_status']; }
function auth_totp_begin_enrollment(int $userId): array { return ['ok' => true]; }
function auth_totp_pending_provisioning(int $userId): array { return ['ok' => true, 'secret' => str_repeat('A', 32), 'otpauth_uri' => 'otpauth://totp/test']; }
function auth_totp_enable_with_code(int $userId, string $code): array { return ['ok' => true]; }
function auth_totp_verify_enabled_code(int $userId, string $code): bool
{
    if ($GLOBALS['verify_throw'] instanceof Throwable) { throw $GLOBALS['verify_throw']; }
    return (bool) $GLOBALS['verify_result'];
}
function auth_recovery_code_replace(int $userId): array { $GLOBALS['replace_calls']++; return $GLOBALS['replace_result']; }
function auth_2fa_throttle_status(int $userId, string $ipAddress): array { return $GLOBALS['throttle']; }
function auth_2fa_throttle_record_failure(int $userId, string $ipAddress): void { $GLOBALS['failures']++; }
function auth_2fa_throttle_record_success(int $userId, string $ipAddress): void { $GLOBALS['successes']++; }
function remember_token_revoke_user(int $userId): int { return 0; }
function persistent_login_clear_cookie(): bool { return true; }

// V1.32-H adds Security Activity side effects. These earlier focused fixtures
// remain scoped to their factor/Step-up API behavior; the H/current tests
// assert the audit-log contract itself.
function auth_audit_log_record(string $event, string $result, ?int $userId = null, mixed $identity = null, ?string $method = null): bool { return true; }

require_once dirname(__DIR__) . '/app/api/account_totp.php';

$failed = 0;
$check = static function (bool $condition, string $message) use (&$failed): void {
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) { $failed++; }
};

$response = api_account_totp_recovery_generate(0, ['code' => '123456']);
$check($response['status'] === 401, 'Recovery Code generation requires an authenticated user');

$response = api_account_totp_recovery_generate(1, ['code' => '12345']);
$check($response['status'] === 422 && ($response['body']['error']['code'] ?? '') === 'validation_error', 'generation requires exactly six TOTP digits');
$check($GLOBALS['failures'] === 0 && $GLOBALS['replace_calls'] === 0, 'format failure does not consume a factor bucket or replace codes');

$GLOBALS['totp_status'] = ['configured' => false, 'enabled' => false];
$response = api_account_totp_recovery_generate(1, ['code' => '123456']);
$check($response['status'] === 409 && ($response['body']['error']['code'] ?? '') === 'totp_not_enabled', 'Recovery Codes cannot be generated before 2FA is enabled');

$GLOBALS['totp_status'] = ['configured' => true, 'enabled' => true];
$GLOBALS['throttle'] = ['blocked' => true, 'retry_after' => 120];
$response = api_account_totp_recovery_generate(1, ['code' => '123456']);
$check($response['status'] === 429 && ($response['body']['error']['code'] ?? '') === 'totp_throttled', 'generation obeys the existing second-factor rate limit');

$GLOBALS['throttle'] = ['blocked' => false, 'retry_after' => 0];
$GLOBALS['verify_result'] = false;
$response = api_account_totp_recovery_generate(1, ['code' => '111111']);
$encoded = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$check($response['status'] === 403 && ($response['body']['error']['code'] ?? '') === 'totp_code_invalid', 'wrong Authenticator code is rejected');
$check($GLOBALS['failures'] === 1 && $GLOBALS['replace_calls'] === 0, 'wrong Authenticator code records a failure and does not replace Recovery Codes');
$check(is_string($encoded) && !str_contains($encoded, '111111'), 'wrong Authenticator code is never reflected in the API response');

$GLOBALS['verify_result'] = true;
$response = api_account_totp_recovery_generate(1, ['code' => '654321']);
$body = $response['body']['data'] ?? [];
$encoded = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$check($response['status'] === 200 && ($body['state'] ?? '') === 'enabled', 'valid Authenticator code generates a Recovery Code set');
$check(is_array($body['recovery_codes'] ?? null) && count($body['recovery_codes']) === 10 && ($body['remaining'] ?? null) === 10, 'generation returns ten plaintext codes exactly once');
$check($GLOBALS['replace_calls'] === 1 && $GLOBALS['successes'] === 1, 'successful generation replaces the set once and clears the factor failure bucket');
$check(is_string($encoded) && !str_contains($encoded, '654321'), 'successful response never reflects the Authenticator code');

$response = api_account_totp_dispatch('account.totp.recovery.generate', 1, ['code' => '654321']);
$check($response['status'] === 200, 'TOTP dispatcher routes Recovery Code generation');

$GLOBALS['verify_throw'] = new AppTotpException('private-secret-detail');
$response = api_account_totp_recovery_generate(1, ['code' => '222222']);
$encoded = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$check($response['status'] === 503 && is_string($encoded) && !str_contains($encoded, 'private-secret-detail') && !str_contains($encoded, '222222'), 'TOTP verification failure stays generic and does not expose sensitive input');

exit($failed === 0 ? 0 : 1);
