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

$userId = app_session_user_id();
$state = isset($_GET['state']) && is_string($_GET['state']) ? $_GET['state'] : '';
$code = isset($_GET['code']) && is_string($_GET['code']) ? $_GET['code'] : '';
$providerError = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : '';

if ($userId === null || $providerError !== '' || $state === '' || $code === '') {
    unset($_SESSION['mail_google_oauth']);
    header('Location: ./?mail_oauth=error', true, 303);
    exit;
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
} catch (PDOException $exception) {
    header('Location: ./?mail_oauth=migration', true, 303);
    exit;
} catch (Throwable $exception) {
    // OAuth codes and tokens must never be written to logs.
    error_log('Gmail OAuth callback failed: ' . $exception::class);
    header('Location: ./?mail_oauth=error', true, 303);
    exit;
}
