<?php

declare(strict_types=1);

define('APP_RESPONSE_FORMAT', 'json');

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/api.php';

function api_emit(array $response): never
{
    $status = isset($response['status']) ? (int) $response['status'] : 500;
    $body = isset($response['body']) && is_array($response['body'])
        ? $response['body']
        : ['ok' => false, 'error' => ['code' => 'internal_error', 'message' => 'Internal server error.']];

    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    app_send_no_store_headers();
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
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
