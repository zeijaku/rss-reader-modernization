<?php

declare(strict_types=1);

define('APP_RESPONSE_FORMAT', 'json');

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/calendar_color.php';

/** @param array<string,mixed> $body */
function calendar_color_emit(int $status, array $body): never
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
function calendar_color_success(array $data = [], int $status = 200): never
{
    calendar_color_emit($status, ['ok' => true, 'data' => $data]);
}

function calendar_color_error(string $code, string $message, int $status): never
{
    calendar_color_emit($status, ['ok' => false, 'error' => ['code' => $code, 'message' => $message]]);
}

function calendar_color_positive_int(mixed $value): ?int
{
    return app_validate_positive_int($value);
}

function calendar_color_request_content_length(): ?int
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
    calendar_color_error('method_not_allowed', 'POST is required.', 405);
}

$userId = app_session_user_id();
if ($userId === null) {
    calendar_color_error('unauthenticated', 'Authentication is required.', 401);
}

$csrfToken = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : null;
if (!app_csrf_is_valid($csrfToken)) {
    calendar_color_error('csrf_invalid', 'CSRF validation failed.', 403);
}

$contentLength = calendar_color_request_content_length();
if ($contentLength !== null && $contentLength > APP_API_MAX_REQUEST_BYTES) {
    calendar_color_error('request_too_large', 'Request body is too large.', 413);
}

$action = isset($_POST['action']) && is_string($_POST['action']) ? trim($_POST['action']) : '';
if (!in_array($action, ['calendar.color.list', 'calendar.color.create', 'calendar.color.update'], true)) {
    calendar_color_error('unknown_action', 'Unknown API action.', 400);
}

// The authenticated user id above is the only ownership source. Release the
// file-session lock before DB work just like the main API endpoint does.
app_session_release();

try {
    if ($action === 'calendar.color.list') {
        $year = calendar_validate_year($_POST['calendar_year'] ?? null);
        $month = calendar_validate_month($_POST['calendar_month'] ?? null);
        if ($year === null || $month === null) {
            calendar_color_error('validation_error', 'Calendar color month is invalid.', 422);
        }
        calendar_color_success([
            'year' => $year,
            'month' => $month,
            'colors' => calendar_event_color_month_list($userId, $year, $month),
        ]);
    }

    $title = calendar_validate_event_title($_POST['calendar_event_title'] ?? null);
    $note = calendar_validate_event_note($_POST['calendar_event_note'] ?? '');
    $range = calendar_validate_event_range(
        $_POST['calendar_event_start_date'] ?? null,
        $_POST['calendar_event_end_date'] ?? null
    );
    $color = calendar_event_color_validate($_POST['calendar_event_color'] ?? null);
    if ($title === null || $note === null || $range === null || $color === null) {
        calendar_color_error('validation_error', 'Calendar event settings are invalid.', 422);
    }

    if ($action === 'calendar.color.create') {
        $eventId = calendar_event_color_create($userId, $title, $range[0], $range[1], $note, $color);
        calendar_color_success(['event_id' => $eventId, 'color' => $color], 201);
    }

    $eventId = calendar_color_positive_int($_POST['event_id'] ?? null);
    if ($eventId === null) {
        calendar_color_error('validation_error', 'event_id must be a positive integer.', 422);
    }
    if (!calendar_event_color_update($userId, $eventId, $title, $range[0], $range[1], $note, $color)) {
        calendar_color_error('not_found', 'Calendar event was not found.', 404);
    }
    calendar_color_success(['event_id' => $eventId, 'color' => $color]);
} catch (LengthException|InvalidArgumentException $exception) {
    calendar_color_error('validation_error', $exception->getMessage(), 422);
} catch (PDOException $exception) {
    error_log('Calendar color API failed: ' . $exception->getMessage());
    calendar_color_error('calendar_color_unavailable', 'Calendar color migration is required.', 503);
} catch (Throwable $exception) {
    error_log('Calendar color API failed: ' . $exception->getMessage());
    calendar_color_error('internal_error', 'Calendar color operation failed.', 500);
}
