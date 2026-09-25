<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/api.php';
require_once dirname(__DIR__) . '/app/mail/mail_crypto.php';
require_once dirname(__DIR__) . '/app/mail/mail_target.php';
require_once dirname(__DIR__) . '/app/mail/mail_account.php';
require_once dirname(__DIR__) . '/app/mail/mail_google_oauth.php';

app_session_start();
app_send_private_no_store_headers();

function mail_oauth_callback_error_redirect(string $code, ?int $userId, string $exceptionClass): never
{
    $details = mail_public_error_details($code);
    $safeCode = $details !== null ? $details['code'] : 'mail_oauth_unavailable';
    try {
        $reference = bin2hex(random_bytes(6));
    } catch (Throwable) {
        $reference = substr(hash('sha256', uniqid('', true)), 0, 12);
    }
    error_log(sprintf(
        'Gmail OAuth callback failed ref=%s user_id=%d code=%s class=%s',
        $reference,
        max(0, (int) $userId),
        $safeCode,
        $exceptionClass
    ));
    $query = http_build_query([
        'mail_oauth' => 'error',
        'mail_oauth_reason' => $safeCode,
        'mail_oauth_ref' => $reference,
    ], '', '&', PHP_QUERY_RFC3986);
    header('Location: ./?' . $query, true, 303);
    exit;
}

$userId = app_session_user_id();
$state = isset($_GET['state']) && is_string($_GET['state']) ? $_GET['state'] : '';
$code = isset($_GET['code']) && is_string($_GET['code']) ? $_GET['code'] : '';
$providerError = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : '';

if ($userId === null) {
    app_session_mail_google_oauth_clear();
    mail_oauth_callback_error_redirect('mail_oauth_session_invalid', null, AppMailGoogleOAuthException::class);
}
if ($providerError !== '') {
    app_session_mail_google_oauth_clear();
    $providerException = new AppMailGoogleOAuthException(
        mail_google_oauth_callback_provider_reason($providerError)
    );
    mail_oauth_callback_error_redirect(
        mail_auth_failure_code($providerException),
        $userId,
        $providerException::class
    );
}
if ($state === '') {
    app_session_mail_google_oauth_clear();
    mail_oauth_callback_error_redirect('mail_oauth_state_missing', $userId, AppMailGoogleOAuthException::class);
}
if ($code === '') {
    app_session_mail_google_oauth_clear();
    mail_oauth_callback_error_redirect('mail_oauth_authorization_code_invalid', $userId, AppMailGoogleOAuthException::class);
}

try {
    $authorization = mail_google_oauth_complete($userId, $state, $code);
    try {
        mail_account_upsert_google_oauth(
            $userId,
            $authorization['email'],
            $authorization['refresh_token']
        );
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($authorization['refresh_token']);
        }
    }
    header('Location: ./?mail_oauth=success', true, 303);
    exit;
} catch (Throwable $exception) {
    // OAuth codes, tokens, provider descriptions, and credential envelopes
    // must never be written to logs or returned to the Browser.
    $failureCode = $exception instanceof AppMailCredentialException || $exception instanceof AppMailGoogleOAuthException
        ? mail_auth_failure_code($exception)
        : ($exception instanceof PDOException ? 'mail_storage_unavailable' : 'mail_oauth_unavailable');
    mail_oauth_callback_error_redirect($failureCode, $userId, $exception::class);
}
