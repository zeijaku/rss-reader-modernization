<?php

declare(strict_types=1);

define('APP_RESPONSE_FORMAT', 'json');

require_once dirname(__DIR__) . '/app/bootstrap.php';

/** @param array<string,mixed> $body */
function calendar_source_emit(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    if (app_session_is_authenticated()) {
        $token = app_csrf_current_token();
        if ($token !== null) {
            header('X-CSRF-Token: ' . $token);
        }
    }
    app_send_no_store_headers();
    echo json_encode(
        $body,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    exit;
}

/** @param array<string,mixed> $data */
function calendar_source_success(array $data = [], int $status = 200): never
{
    calendar_source_emit($status, ['ok' => true, 'data' => $data]);
}

function calendar_source_error(string $code, string $message, int $status): never
{
    calendar_source_emit($status, ['ok' => false, 'error' => ['code' => $code, 'message' => $message]]);
}

function calendar_source_request_content_length(): ?int
{
    $raw = $_SERVER['CONTENT_LENGTH'] ?? null;
    if (!is_string($raw) && !is_int($raw)) {
        return null;
    }
    $raw = trim((string) $raw);
    if ($raw === '' || preg_match('/^[0-9]{1,20}$/', $raw) !== 1) {
        return null;
    }
    $length = (int) $raw;
    return $length >= 0 ? $length : null;
}

app_session_start();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    calendar_source_error('method_not_allowed', 'POST is required.', 405);
}

$userId = app_session_user_id();
if ($userId === null) {
    calendar_source_error('unauthenticated', 'Authentication is required.', 401);
}

$csrfToken = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : null;
if (!app_csrf_is_valid($csrfToken)) {
    calendar_source_error('csrf_invalid', 'CSRF validation failed.', 403);
}

$contentLength = calendar_source_request_content_length();
if ($contentLength !== null && $contentLength > APP_API_MAX_REQUEST_BYTES) {
    calendar_source_error('request_too_large', 'Request body is too large.', 413);
}

$action = isset($_POST['action']) && is_string($_POST['action']) ? trim($_POST['action']) : '';
if (!in_array($action, [
    'calendar.source.list',
    'calendar.source.create',
    'calendar.source.update',
    'calendar.source.delete',
], true)) {
    calendar_source_error('unknown_action', 'Unknown API action.', 400);
}

app_session_release();

try {
    if (!calendar_source_schema_ready(conn_db())) {
        calendar_source_error('calendar_source_unavailable', 'Calendar source migration is required.', 503);
    }

    if ($action === 'calendar.source.list') {
        calendar_source_success(['sources' => calendar_source_list($userId)]);
    }

    if ($action === 'calendar.source.create') {
        $name = calendar_source_validate_name($_POST['calendar_source_name'] ?? null);
        $color = calendar_source_validate_color($_POST['calendar_source_color'] ?? null);
        if ($name === null || $color === null) {
            calendar_source_error('validation_error', 'Calendar source settings are invalid.', 422);
        }
        $sourceId = calendar_source_create($userId, $name, $color);
        calendar_source_success([
            'source_id' => $sourceId,
            'sources' => calendar_source_list($userId),
        ], 201);
    }

    $sourceId = app_validate_positive_int($_POST['calendar_source_id'] ?? null);
    if ($sourceId === null) {
        calendar_source_error('validation_error', 'calendar_source_id must be a positive integer.', 422);
    }

    if ($action === 'calendar.source.update') {
        $name = calendar_source_validate_name($_POST['calendar_source_name'] ?? null);
        $color = calendar_source_validate_color($_POST['calendar_source_color'] ?? null);
        if ($name === null || $color === null) {
            calendar_source_error('validation_error', 'Calendar source settings are invalid.', 422);
        }
        if (!calendar_source_update($userId, $sourceId, $name, $color)) {
            calendar_source_error('not_found', 'Calendar source was not found.', 404);
        }
        calendar_source_success(['sources' => calendar_source_list($userId)]);
    }

    if (!calendar_source_delete($userId, $sourceId)) {
        calendar_source_error('not_found', 'Calendar source was not found.', 404);
    }
    calendar_source_success(['sources' => calendar_source_list($userId)]);
} catch (LengthException|InvalidArgumentException $exception) {
    calendar_source_error('validation_error', $exception->getMessage(), 422);
} catch (RuntimeException $exception) {
    error_log('Calendar source API failed: ' . $exception->getMessage());
    calendar_source_error('calendar_source_unavailable', 'Calendar source migration is required.', 503);
} catch (Throwable $exception) {
    error_log('Calendar source API failed: ' . $exception->getMessage());
    calendar_source_error('internal_error', 'Calendar source operation failed.', 500);
}
