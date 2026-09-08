<?php

declare(strict_types=1);

final class AppTotpException extends RuntimeException {}

$GLOBALS['totp_status'] = ['configured' => false, 'enabled' => false];
$GLOBALS['totp_begin'] = ['ok' => true, 'secret' => 'SHOULD-NOT-LEAK', 'otpauth_uri' => 'otpauth://SHOULD-NOT-LEAK'];
$GLOBALS['totp_begin_calls'] = 0;
$GLOBALS['totp_throw'] = null;

function api_success(array $data = [], int $status = 200): array
{
    return ['status' => $status, 'body' => ['ok' => true, 'data' => $data]];
}
function api_error(string $code, string $message, int $status): array
{
    return ['status' => $status, 'body' => ['ok' => false, 'error' => ['code' => $code, 'message' => $message]]];
}
function auth_totp_status(int $userId): array
{
    if ($GLOBALS['totp_throw'] instanceof Throwable) {
        throw $GLOBALS['totp_throw'];
    }
    return $GLOBALS['totp_status'];
}
function auth_totp_begin_enrollment(int $userId): array
{
    $GLOBALS['totp_begin_calls']++;
    if ($GLOBALS['totp_throw'] instanceof Throwable) {
        throw $GLOBALS['totp_throw'];
    }
    return $GLOBALS['totp_begin'];
}

require_once dirname(__DIR__) . '/app/api/account_totp.php';

$failures = 0;
$check = static function (bool $condition, string $message) use (&$failures): void {
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) {
        $failures++;
    }
};

$response = api_account_totp_begin(0);
$check($response['status'] === 401, 'invalid user id fails closed');

$GLOBALS['totp_status'] = ['configured' => true, 'enabled' => true];
$GLOBALS['totp_begin_calls'] = 0;
$response = api_account_totp_begin(1);
$check($response['status'] === 409 && ($response['body']['error']['code'] ?? '') === 'totp_already_enabled', 'enabled user cannot restart enrollment');
$check($GLOBALS['totp_begin_calls'] === 0, 'enabled state does not generate a new secret');

$GLOBALS['totp_status'] = ['configured' => true, 'enabled' => false];
$GLOBALS['totp_begin_calls'] = 0;
$response = api_account_totp_begin(1);
$check($response['status'] === 409 && ($response['body']['error']['code'] ?? '') === 'totp_enrollment_pending', 'pending enrollment is not silently replaced');
$check($GLOBALS['totp_begin_calls'] === 0, 'pending state does not generate another secret');

$GLOBALS['totp_status'] = ['configured' => false, 'enabled' => false];
$GLOBALS['totp_begin'] = ['ok' => true, 'secret' => 'SHOULD-NOT-LEAK', 'otpauth_uri' => 'otpauth://SHOULD-NOT-LEAK'];
$GLOBALS['totp_begin_calls'] = 0;
$response = api_account_totp_begin(1);
$encoded = json_encode($response, JSON_UNESCAPED_SLASHES);
$check($response['status'] === 200 && ($response['body']['data']['state'] ?? '') === 'pending', 'unconfigured user starts pending enrollment');
$check($GLOBALS['totp_begin_calls'] === 1, 'secret generation is called exactly once');
$check(is_string($encoded) && !str_contains($encoded, 'SHOULD-NOT-LEAK') && !str_contains($encoded, 'otpauth://'), 'API response never returns the secret or otpauth URI');

$GLOBALS['totp_begin'] = ['ok' => false, 'reason' => 'invalid_user'];
$response = api_account_totp_begin(1);
$check($response['status'] === 404, 'inactive/missing account fails closed');

$GLOBALS['totp_begin'] = ['ok' => true];
$GLOBALS['totp_throw'] = new AppTotpException('sensitive configuration detail');
$response = api_account_totp_begin(1);
$check($response['status'] === 503 && ($response['body']['error']['code'] ?? '') === 'totp_unavailable', 'TOTP crypto configuration errors return generic 503');
$encoded = json_encode($response);
$check(is_string($encoded) && !str_contains($encoded, 'sensitive configuration detail'), 'configuration exception details are not returned to browser');
$GLOBALS['totp_throw'] = null;

$response = api_account_totp_dispatch('account.totp.unknown', 1, []);
$check($response['status'] === 400 && ($response['body']['error']['code'] ?? '') === 'unknown_action', 'unknown TOTP action is rejected');

exit($failures === 0 ? 0 : 1);
