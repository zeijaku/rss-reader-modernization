<?php

declare(strict_types=1);

final class AppTotpException extends RuntimeException {}

$GLOBALS['totp_status'] = ['configured' => true, 'enabled' => false];
$GLOBALS['enable_result'] = ['ok' => true];
$GLOBALS['enable_throw'] = null;
$GLOBALS['throttle'] = ['blocked' => false, 'retry_after' => 0];
$GLOBALS['failures'] = 0;
$GLOBALS['successes'] = 0;
$GLOBALS['remember_revokes'] = 0;
$GLOBALS['cookie_clears'] = 0;
$_SERVER['REMOTE_ADDR'] = '192.0.2.44';

function api_success(array $data = [], int $status = 200): array { return ['status' => $status, 'body' => ['ok' => true, 'data' => $data]]; }
function api_error(string $code, string $message, int $status): array { return ['status' => $status, 'body' => ['ok' => false, 'error' => ['code' => $code, 'message' => $message]]]; }
function api_validation_error(string $message): array { return api_error('validation_error', $message, 422); }
function auth_totp_status(int $userId): array { return $GLOBALS['totp_status']; }
function auth_totp_begin_enrollment(int $userId): array { return ['ok' => true]; }
function auth_totp_pending_provisioning(int $userId): array { return ['ok' => true, 'secret' => str_repeat('A', 32), 'otpauth_uri' => 'otpauth://totp/test']; }
function auth_totp_enable_with_code(int $userId, string $code): array
{
    if ($GLOBALS['enable_throw'] instanceof Throwable) { throw $GLOBALS['enable_throw']; }
    return $GLOBALS['enable_result'];
}
function auth_2fa_throttle_status(int $userId, string $ipAddress): array { return $GLOBALS['throttle']; }
function auth_2fa_throttle_record_failure(int $userId, string $ipAddress): void { $GLOBALS['failures']++; }
function auth_2fa_throttle_record_success(int $userId, string $ipAddress): void { $GLOBALS['successes']++; }
function remember_token_revoke_user(int $userId): int { $GLOBALS['remember_revokes']++; return 2; }
function persistent_login_clear_cookie(): bool { $GLOBALS['cookie_clears']++; return true; }

require_once dirname(__DIR__) . '/app/api/account_totp.php';

$failed = 0;
$check = static function (bool $condition, string $message) use (&$failed): void {
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) { $failed++; }
};

$response = api_account_totp_confirm(0, ['code' => '123456']);
$check($response['status'] === 401, 'confirmation requires an authenticated user');

$response = api_account_totp_confirm(1, ['code' => '12345']);
$check($response['status'] === 422 && ($response['body']['error']['code'] ?? '') === 'validation_error', 'confirmation requires exactly six digits');
$check($GLOBALS['failures'] === 0, 'format validation does not consume a TOTP failure bucket');

$GLOBALS['totp_status'] = ['configured' => false, 'enabled' => false];
$response = api_account_totp_confirm(1, ['code' => '123456']);
$check($response['status'] === 409 && ($response['body']['error']['code'] ?? '') === 'totp_not_pending', 'unconfigured account cannot confirm enrollment');

$GLOBALS['totp_status'] = ['configured' => true, 'enabled' => true];
$response = api_account_totp_confirm(1, ['code' => '123456']);
$check($response['status'] === 409 && ($response['body']['error']['code'] ?? '') === 'totp_already_enabled', 'already-enabled account cannot confirm enrollment again');

$GLOBALS['totp_status'] = ['configured' => true, 'enabled' => false];
$GLOBALS['throttle'] = ['blocked' => true, 'retry_after' => 120];
$response = api_account_totp_confirm(1, ['code' => '123456']);
$check($response['status'] === 429 && ($response['body']['error']['code'] ?? '') === 'totp_throttled', 'confirmation obeys the dedicated 2FA rate limit');

$GLOBALS['throttle'] = ['blocked' => false, 'retry_after' => 0];
$GLOBALS['enable_result'] = ['ok' => false, 'reason' => 'invalid_code'];
$response = api_account_totp_confirm(1, ['code' => '111111']);
$check($response['status'] === 403 && ($response['body']['error']['code'] ?? '') === 'totp_code_invalid', 'wrong code is rejected generically');
$check($GLOBALS['failures'] === 1, 'wrong code records one dedicated 2FA failure');
$encoded = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$check(is_string($encoded) && !str_contains($encoded, '111111'), 'wrong code is never reflected in the API response');

$GLOBALS['enable_result'] = ['ok' => true];
$response = api_account_totp_confirm(1, ['code' => '654321']);
$check($response['status'] === 200 && ($response['body']['data']['state'] ?? '') === 'enabled', 'valid code enables 2FA');
$check($GLOBALS['successes'] === 1, 'successful confirmation clears the user/IP factor bucket');
$check($GLOBALS['remember_revokes'] === 1, 'enabling 2FA revokes pre-existing Remember Tokens');
$check($GLOBALS['cookie_clears'] === 1, 'enabling 2FA clears the current Remember cookie');
$encoded = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$check(is_string($encoded) && !str_contains($encoded, '654321') && !str_contains($encoded, 'secret'), 'success response does not expose the code or provisioning secret');

$response = api_account_totp_dispatch('account.totp.confirm', 1, ['code' => '654321']);
$check($response['status'] === 200, 'TOTP dispatcher routes the confirmation action');

$GLOBALS['enable_throw'] = new AppTotpException('private-key-detail');
$response = api_account_totp_confirm(1, ['code' => '222222']);
$encoded = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$check($response['status'] === 503 && is_string($encoded) && !str_contains($encoded, 'private-key-detail') && !str_contains($encoded, '222222'), 'crypto failure stays generic and does not expose sensitive material');

exit($failed === 0 ? 0 : 1);
