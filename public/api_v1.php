<?php

declare(strict_types=1);

define('APP_RESPONSE_FORMAT', 'json');

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/api.php';
require_once dirname(__DIR__) . '/app/camera_video.php';

// V1.9-C R2: Mail dependencies are loaded at the API boundary so the V1.8
// bootstrap/api core does not need to be rewritten just to enable Mail Widget.
$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

if (!defined('APP_MAIL_CREDENTIAL_KEY_ID')) {
    define('APP_MAIL_CREDENTIAL_KEY_ID', app_env('APP_MAIL_CREDENTIAL_KEY_ID', 'primary'));
}
if (!defined('APP_MAIL_CREDENTIAL_KEY_B64')) {
    define('APP_MAIL_CREDENTIAL_KEY_B64', app_env('APP_MAIL_CREDENTIAL_KEY_B64', ''));
}
if (!defined('APP_MAIL_IMAP_TIMEOUT_SECONDS')) {
    define('APP_MAIL_IMAP_TIMEOUT_SECONDS', max(2, min(30, (int) app_env('APP_MAIL_IMAP_TIMEOUT_SECONDS', '5'))));
}

require_once dirname(__DIR__) . '/app/mail/mail_crypto.php';
require_once dirname(__DIR__) . '/app/mail/mail_target.php';
require_once dirname(__DIR__) . '/app/mail/mail_account.php';
require_once dirname(__DIR__) . '/app/mail/mail_client.php';
require_once dirname(__DIR__) . '/app/mail/mail_service.php';
require_once dirname(__DIR__) . '/app/mail/mail_api.php';
require_once dirname(__DIR__) . '/app/mail/mail_widget.php';

function api_emit(array $response): never
{
    $status = isset($response['status']) ? (int) $response['status'] : 500;
    $body = isset($response['body']) && is_array($response['body'])
        ? $response['body']
        : ['ok' => false, 'error' => ['code' => 'internal_error', 'message' => 'Internal server error.']];

    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    if (app_session_is_authenticated()) {
        $csrfToken = app_csrf_current_token();
        if ($csrfToken !== null) {
            header('X-CSRF-Token: ' . $csrfToken);
        }
    }
    app_send_no_store_headers();
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

/** @return array{status:int,body:array<string,mixed>} */
function api_mail_account_dispatch(string $action, int $userId, array $input): array
{
    return match ($action) {
        'mail.account.list' => api_mail_account_list($userId, $input),
        'mail.account.create' => api_mail_account_create($userId, $input),
        'mail.account.update' => api_mail_account_update($userId, $input),
        'mail.account.delete' => api_mail_account_delete($userId, $input),
        'mail.account.test' => api_mail_account_test($userId, $input),
        default => api_error('unknown_action', 'Unknown API action.', 400),
    };
}

/**
 * Account credential changes rotate the authenticated session and issue a new
 * CSRF token, so only those actions must keep the file-backed session open.
 */
function api_action_requires_open_session(string $action): bool
{
    return in_array($action, [
        'account.email.update',
        'account.password.update',
    ], true);
}

app_session_start();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    api_emit(api_error('method_not_allowed', 'POST is required.', 405));
}

$userId = app_session_user_id();
if ($userId === null) {
    api_emit(api_error('unauthenticated', 'Authentication is required.', 401));
}

$csrfToken = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : null;
if (!app_csrf_is_valid($csrfToken)) {
    api_emit(api_error('csrf_invalid', 'CSRF validation failed.', 403));
}

$action = isset($_POST['action']) && is_string($_POST['action']) ? trim($_POST['action']) : '';
if ($action === '' || strlen($action) > 64 || preg_match('/^[a-z]+(?:\.[a-z]+)+$/', $action) !== 1) {
    api_emit(api_error('invalid_request', 'A valid action is required.', 400));
}

try {
    // V1.17.1-A/E: authentication and CSRF are already fixed above. Release
    // the file-session lock before DB/outbound I/O so parallel Dashboard
    // requests do not queue behind a slow RSS, Mail, Weather, or other
    // external fetch. Keep the release inside the API exception boundary so a
    // rare session_write_close() failure still returns the normal JSON error.
    if (!api_action_requires_open_session($action)) {
        app_session_release();
    }

    if (str_starts_with($action, 'camera.widget.')) {
        api_emit(camera_video_api_dispatch($action, $userId, $_POST));
    }
    if (str_starts_with($action, 'mail.account.')) {
        api_emit(api_mail_account_dispatch($action, $userId, $_POST));
    }
    if (str_starts_with($action, 'mail.widget.')) {
        api_emit(api_mail_widget_dispatch($action, $userId, $_POST));
    }
    api_emit(api_dispatch($action, $userId, $_POST));
} catch (Throwable $exception) {
    try {
        $reference = bin2hex(random_bytes(6));
    } catch (Throwable) {
        $reference = substr(hash('sha256', uniqid('', true)), 0, 12);
    }

    error_log(sprintf(
        'API exception ref=%s action=%s user_id=%d [%s] at %s:%d: %s',
        $reference,
        $action,
        $userId,
        $exception::class,
        $exception->getFile(),
        $exception->getLine(),
        $exception->getMessage()
    ));

    api_emit(api_error('internal_error', 'Internal server error. Reference: ' . $reference, 500));
}
