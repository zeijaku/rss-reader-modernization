<?php

declare(strict_types=1);

final class AppTotpException extends RuntimeException {}
$GLOBALS['provisioning_result'] = ['ok' => true, 'secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', 'otpauth_uri' => 'otpauth://totp/iGuguru%3Auser-1?secret=JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP&issuer=iGuguru&algorithm=SHA1&digits=6&period=30'];
$GLOBALS['provisioning_throw'] = null;
$GLOBALS['totp_status'] = ['configured' => false, 'enabled' => false];
$GLOBALS['totp_begin'] = ['ok' => true];

function api_success(array $data = [], int $status = 200): array { return ['status' => $status, 'body' => ['ok' => true, 'data' => $data]]; }
function api_error(string $code, string $message, int $status): array { return ['status' => $status, 'body' => ['ok' => false, 'error' => ['code' => $code, 'message' => $message]]]; }
function auth_totp_status(int $userId): array { return $GLOBALS['totp_status']; }
function auth_totp_begin_enrollment(int $userId): array { return $GLOBALS['totp_begin']; }
function auth_totp_pending_provisioning(int $userId): array
{
    if ($GLOBALS['provisioning_throw'] instanceof Throwable) { throw $GLOBALS['provisioning_throw']; }
    return $GLOBALS['provisioning_result'];
}

require_once dirname(__DIR__) . '/app/api/account_totp.php';

$failures = 0;
$check = static function (bool $condition, string $message) use (&$failures): void {
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) { $failures++; }
};

$response = api_account_totp_provisioning(0);
$check($response['status'] === 401, 'provisioning requires an authenticated user id');

$response = api_account_totp_provisioning(1);
$check($response['status'] === 200 && ($response['body']['data']['state'] ?? '') === 'pending', 'pending provisioning returns success state');
$check(($response['body']['data']['secret'] ?? '') === 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', 'authenticated owner receives Base32 setup key');
$check(str_starts_with((string) ($response['body']['data']['otpauth_uri'] ?? ''), 'otpauth://totp/'), 'authenticated owner receives otpauth URI');

$GLOBALS['provisioning_result'] = ['ok' => false, 'reason' => 'not_configured'];
$response = api_account_totp_provisioning(1);
$check($response['status'] === 409 && ($response['body']['error']['code'] ?? '') === 'totp_not_pending', 'unconfigured account cannot request provisioning');

$GLOBALS['provisioning_result'] = ['ok' => false, 'reason' => 'already_enabled'];
$response = api_account_totp_provisioning(1);
$check($response['status'] === 409 && ($response['body']['error']['code'] ?? '') === 'totp_already_enabled', 'enabled account cannot request provisioning');

$GLOBALS['provisioning_result'] = ['ok' => true, 'secret' => 'BAD', 'otpauth_uri' => 'otpauth://totp/bad'];
$response = api_account_totp_provisioning(1);
$check($response['status'] === 503 && ($response['body']['error']['code'] ?? '') === 'totp_unavailable', 'malformed provisioning material fails closed');

$GLOBALS['provisioning_throw'] = new AppTotpException('do-not-expose-key-details');
$response = api_account_totp_provisioning(1);
$encoded = json_encode($response, JSON_UNESCAPED_SLASHES);
$check($response['status'] === 503 && is_string($encoded) && !str_contains($encoded, 'do-not-expose-key-details'), 'crypto errors return a generic browser response');
$GLOBALS['provisioning_throw'] = null;

$GLOBALS['provisioning_result'] = ['ok' => true, 'secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', 'otpauth_uri' => 'otpauth://totp/iGuguru%3Auser-1?secret=JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP&issuer=iGuguru&algorithm=SHA1&digits=6&period=30'];
$response = api_account_totp_dispatch('account.totp.provisioning', 1, []);
$check($response['status'] === 200, 'TOTP dispatcher routes provisioning action');

exit($failures === 0 ? 0 : 1);
