<?php

declare(strict_types=1);

define('APP_MAIL_GOOGLE_OAUTH_CLIENT_ID', 'test-client.apps.googleusercontent.com');
define('APP_MAIL_GOOGLE_OAUTH_CLIENT_SECRET', 'test-client-secret');
define('APP_MAIL_GOOGLE_OAUTH_REDIRECT_URI', 'https://reader.example.test/mail_oauth_google.php');
define('APP_MAIL_GOOGLE_OAUTH_ALLOWED_EMAIL', '');

function api_error(string $code, string $message, int $status): array
{
    return ['status' => $status, 'body' => ['ok' => false, 'error' => ['code' => $code, 'message' => $message]]];
}

require_once dirname(__DIR__) . '/app/mail/mail_error.php';
require_once dirname(__DIR__) . '/app/mail/mail_google_oauth.php';
require_once dirname(__DIR__) . '/app/mail/mail_api.php';

$results = [];
$check = static function (bool $condition, string $message) use (&$results): void {
    $results[] = [$condition, $message];
};

$credentialCases = [
    'key_missing' => 'mail_credential_key_missing',
    'key_invalid' => 'mail_credential_key_invalid',
    'key_mismatch' => 'mail_credential_key_mismatch',
    'envelope_invalid' => 'mail_credential_data_invalid',
    'decrypt_failed' => 'mail_credential_decrypt_failed',
    'smtp_key_mismatch' => 'mail_smtp_credential_key_mismatch',
    'smtp_decrypt_failed' => 'mail_smtp_credential_decrypt_failed',
];
foreach ($credentialCases as $reason => $expected) {
    $exception = new AppMailCredentialException($reason);
    $check(mail_credential_failure_code($exception) === $expected, "credential {$reason} has a stable public code");
    $check($exception->getMessage() === 'Mail credential operation failed.', "credential {$reason} hides internal/provider text");
}

$oauthCases = [
    'state_missing' => 'mail_oauth_state_missing',
    'state_expired' => 'mail_oauth_state_expired',
    'state_mismatch' => 'mail_oauth_state_mismatch',
    'authorization_denied' => 'mail_oauth_access_denied',
    'authorization_code_invalid' => 'mail_oauth_authorization_code_invalid',
    'offline_authorization_missing' => 'mail_oauth_offline_access_missing',
    'scope_missing' => 'mail_oauth_scope_missing',
    'refresh_token_expired' => 'mail_oauth_reconnect_required',
    'google_timeout' => 'mail_oauth_google_timeout',
    'google_tls_failed' => 'mail_oauth_google_tls_failed',
    'response_invalid' => 'mail_oauth_response_invalid',
    'client_invalid' => 'mail_oauth_configuration_invalid',
];
foreach ($oauthCases as $reason => $expected) {
    $exception = new AppMailGoogleOAuthException($reason);
    $check(mail_google_oauth_failure_code($exception) === $expected, "OAuth {$reason} has a stable public code");
    $details = mail_public_error_details($expected);
    $check(is_array($details) && $details['message'] !== '' && $details['status'] >= 400, "OAuth {$reason} has actionable public details");
}

$providerCases = [
    ['invalid_grant', 'refresh_token', 'refresh_token_expired'],
    ['invalid_grant', 'authorization_code', 'authorization_code_invalid'],
    ['access_denied', 'authorization_code', 'authorization_denied'],
    ['invalid_client', 'refresh_token', 'client_invalid'],
    ['temporarily_unavailable', 'refresh_token', 'google_unavailable'],
];
foreach ($providerCases as [$providerCode, $operation, $expected]) {
    $check(
        mail_google_oauth_provider_error_reason($providerCode, $operation) === $expected,
        "provider {$providerCode} is classified for {$operation}"
    );
}

$providerSecret = 'provider-description-must-not-leak';
$caught = null;
try {
    mail_google_oauth_json_response([
        'ok' => false,
        'status' => 400,
        'body' => json_encode([
            'error' => 'invalid_grant',
            'error_description' => $providerSecret,
        ], JSON_THROW_ON_ERROR),
    ], 'refresh_token');
} catch (AppMailGoogleOAuthException $exception) {
    $caught = $exception;
}
$check($caught instanceof AppMailGoogleOAuthException, 'provider failure raises the typed OAuth exception');
$check($caught?->reason() === 'refresh_token_expired', 'invalid refresh grant requires Gmail reconnection');
$check(!str_contains((string) $caught?->getMessage(), $providerSecret), 'provider descriptions never reach the public exception message');

$apiFailure = api_mail_error_from_code('mail_credential_key_mismatch');
$check(($apiFailure['status'] ?? 0) === 409, 'credential key mismatch is returned as a conflict, not an unknown error');
$check(
    ($apiFailure['body']['error']['code'] ?? '') === 'mail_credential_key_mismatch',
    'API preserves the granular credential reason code'
);
$check(
    str_contains(api_mail_sent_save_message('sent_save_uncertain'), '自動再試行しません'),
    'uncertain Sent-folder saves warn against duplicate retries'
);

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
