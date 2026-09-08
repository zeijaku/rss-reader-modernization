<?php

declare(strict_types=1);

final class AppTotpException extends RuntimeException {}
const AUTH_STEP_UP_TIMEOUT = 300;
const AUTH_PASSWORD_MAX_LENGTH = 72;

$GLOBALS['totp_enabled'] = true;
$GLOBALS['password_ok'] = true;
$GLOBALS['totp_ok'] = true;
$GLOBALS['recovery_ok'] = true;
$GLOBALS['account_blocked'] = false;
$GLOBALS['factor_blocked'] = false;
$GLOBALS['stepup_valid'] = false;
$GLOBALS['account_failures'] = 0;
$GLOBALS['account_successes'] = 0;
$GLOBALS['factor_failures'] = 0;
$GLOBALS['factor_successes'] = 0;
$GLOBALS['grants'] = [];
$GLOBALS['clears'] = 0;
$GLOBALS['disable_result'] = ['ok' => true];
$GLOBALS['cookie_clears'] = 0;
$GLOBALS['session_logins'] = 0;
$GLOBALS['previous_tokens'] = [];
$GLOBALS['csrf'] = str_repeat('b', 64);
$GLOBALS['recovery_status'] = ['configured' => true, 'unused' => 8];
$_SERVER['REMOTE_ADDR'] = '198.51.100.24';

function api_success(array $data = [], int $status = 200): array { return ['status' => $status, 'body' => ['ok' => true, 'data' => $data]]; }
function api_error(string $code, string $message, int $status): array { return ['status' => $status, 'body' => ['ok' => false, 'error' => ['code' => $code, 'message' => $message]]]; }
function api_validation_error(string $message): array { return api_error('validation_error', $message, 422); }
function account_settings_current_password_is_valid(string $password): bool { return $password !== '' && strlen($password) <= AUTH_PASSWORD_MAX_LENGTH && !str_contains($password, "\0"); }
function api_account_settings_rate_status(int $userId): array { return ['blocked' => $GLOBALS['account_blocked'], 'retry_after' => 0]; }
function auth_2fa_throttle_status(int $userId, string $ip): array { return ['blocked' => $GLOBALS['factor_blocked'], 'retry_after' => 0]; }
function auth_totp_status(int $userId): array { return ['configured' => true, 'enabled' => $GLOBALS['totp_enabled']]; }
function auth_step_up_verify_password(int $userId, string $password): bool { return $GLOBALS['password_ok']; }
function auth_totp_verify_enabled_code(int $userId, string $code): bool { return $GLOBALS['totp_ok']; }
function auth_recovery_code_consume(int $userId, string $code): bool { return $GLOBALS['recovery_ok']; }
function auth_recovery_code_status(int $userId): array { return $GLOBALS['recovery_status']; }
function api_account_settings_record_failure(int $userId): void { $GLOBALS['account_failures']++; }
function api_account_settings_record_success(int $userId): void { $GLOBALS['account_successes']++; }
function auth_2fa_throttle_record_failure(int $userId, string $ip): void { $GLOBALS['factor_failures']++; }
function auth_2fa_throttle_record_success(int $userId, string $ip): void { $GLOBALS['factor_successes']++; }
function app_session_step_up_grant(int $userId, string $method): void { $GLOBALS['grants'][] = [$userId, $method]; $GLOBALS['stepup_valid'] = true; }
function app_session_step_up_clear(): void { $GLOBALS['clears']++; $GLOBALS['stepup_valid'] = false; }
function app_session_step_up_is_valid(): bool { return $GLOBALS['stepup_valid']; }
function account_security_disable_totp(int $userId): array { return $GLOBALS['disable_result']; }
function persistent_login_clear_cookie(): void { $GLOBALS['cookie_clears']++; }
function app_csrf_current_token(): ?string { return str_repeat('a', 64); }
function app_session_login(int $userId): void { $GLOBALS['session_logins']++; $GLOBALS['stepup_valid'] = false; $GLOBALS['csrf'] = str_repeat('c', 64); }
function app_csrf_allow_previous_token(string $token, int $graceSeconds = 300): void { $GLOBALS['previous_tokens'][] = $token; }
function app_csrf_token(): string { return $GLOBALS['csrf']; }

// V1.32-H adds Security Activity side effects. These earlier focused fixtures
// remain scoped to their factor/Step-up API behavior; the H/current tests
// assert the audit-log contract itself.
function auth_audit_log_record(string $event, string $result, ?int $userId = null, mixed $identity = null, ?string $method = null): bool { return true; }

require_once dirname(__DIR__) . '/app/api/account_security.php';

$failed = 0;
$check = static function (bool $condition, string $message) use (&$failed): void {
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) { $failed++; }
};

$response = api_account_security_step_up_verify(1, ['current_password' => '', 'factor_type' => 'totp', 'factor_code' => '123456']);
$check($response['status'] === 422, 'Step-up requires a current Password');

$GLOBALS['account_blocked'] = true;
$response = api_account_security_step_up_verify(1, ['current_password' => 'correct-password', 'factor_type' => 'totp', 'factor_code' => '123456']);
$check($response['status'] === 429, 'Step-up obeys the existing account/factor rate boundary');
$GLOBALS['account_blocked'] = false;

$GLOBALS['password_ok'] = false;
$response = api_account_security_step_up_verify(1, ['current_password' => 'wrong-password', 'factor_type' => 'totp', 'factor_code' => '123456']);
$check($response['status'] === 403 && ($response['body']['error']['code'] ?? '') === 'step_up_invalid', 'wrong Password is rejected generically');
$check($GLOBALS['account_failures'] === 1 && $GLOBALS['factor_failures'] === 0, 'wrong Password records only the Password-side failure');

$GLOBALS['password_ok'] = true;
$GLOBALS['totp_ok'] = false;
$response = api_account_security_step_up_verify(1, ['current_password' => 'correct-password', 'factor_type' => 'totp', 'factor_code' => '111111']);
$check($response['status'] === 403 && $GLOBALS['factor_failures'] === 1, 'wrong TOTP is rejected and recorded in the factor bucket');

$GLOBALS['totp_ok'] = true;
$response = api_account_security_step_up_verify(1, ['current_password' => 'correct-password', 'factor_type' => 'totp', 'factor_code' => '654321']);
$check($response['status'] === 200 && ($response['body']['data']['verified'] ?? false) === true, 'Password plus valid TOTP grants Step-up');
$check($GLOBALS['grants'][0] === [1, 'totp'], 'TOTP Step-up is bound to the current user and method');

$GLOBALS['stepup_valid'] = false;
$GLOBALS['recovery_ok'] = true;
$response = api_account_security_step_up_verify(1, ['current_password' => 'correct-password', 'factor_type' => 'recovery', 'factor_code' => 'ABCD-EFGH-JKLM-NPQR']);
$check($response['status'] === 200 && ($response['body']['data']['recovery_remaining'] ?? null) === 8, 'Recovery Code can satisfy Step-up and reports the reduced remaining count');
$check(end($GLOBALS['grants']) === [1, 'recovery'], 'Recovery Step-up records the factor method');

$GLOBALS['stepup_valid'] = false;
$response = api_account_security_totp_disable(1);
$check($response['status'] === 403 && ($response['body']['error']['code'] ?? '') === 'step_up_required', '2FA disable fails closed without recent Step-up');

$GLOBALS['stepup_valid'] = true;
$GLOBALS['disable_result'] = ['ok' => true];
$response = api_account_security_totp_disable(1);
$check($response['status'] === 200 && ($response['body']['data']['state'] ?? '') === 'unconfigured', 'recent Step-up permits 2FA disable');
$check($GLOBALS['cookie_clears'] === 1 && $GLOBALS['session_logins'] === 1, '2FA disable clears current Remember cookie and rotates the authenticated session');
$check(($response['body']['data']['csrf_token'] ?? '') === str_repeat('c', 64), '2FA disable returns the new CSRF token after Session rotation');

$response = api_account_security_dispatch('account.security.stepup.verify', 1, ['current_password' => 'correct-password', 'factor_type' => 'totp', 'factor_code' => '654321']);
$check($response['status'] === 200, 'Security dispatcher routes Step-up verification');

exit($failed === 0 ? 0 : 1);
