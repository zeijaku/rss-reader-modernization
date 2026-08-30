<?php

declare(strict_types=1);

define('APP_RESPONSE_FORMAT', 'json');

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/user_file.php';

/** @param array<string,mixed> $body */
function file_upload_emit(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
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
function file_upload_success(array $data, int $status = 201): never
{
    file_upload_emit($status, ['ok' => true, 'data' => $data]);
}

function file_upload_error(string $code, string $message, int $status): never
{
    file_upload_emit($status, ['ok' => false, 'error' => ['code' => $code, 'message' => $message]]);
}

function file_upload_request_content_length(): ?int
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
    file_upload_error('method_not_allowed', 'POST is required.', 405);
}

$userId = app_session_user_id();
if ($userId === null) {
    file_upload_error('unauthenticated', 'Authentication is required.', 401);
}

$csrfToken = null;
if (isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])) {
    $csrfToken = $_POST['csrf_token'];
} elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN']) && is_string($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
}
if (!app_csrf_is_valid($csrfToken)) {
    file_upload_error('csrf_invalid', 'CSRF validation failed.', 403);
}

$contentLength = file_upload_request_content_length();
if ($contentLength !== null && $contentLength > APP_FILE_UPLOAD_MAX_REQUEST_BYTES) {
    file_upload_error('request_too_large', 'Upload request is too large.', 413);
}

$file = $_FILES['file'] ?? null;
if (!is_array($file)) {
    // PHP can clear both POST and FILES when post_max_size is exceeded. If a
    // body length exists but no multipart file survived parsing, fail closed.
    if ($contentLength !== null && $contentLength > 0 && $_FILES === []) {
        file_upload_error('upload_invalid', 'Upload payload could not be parsed.', 400);
    }
    file_upload_error('file_required', 'A file is required.', 422);
}

// The authenticated session user is the only owner source. Do not accept a
// user id from the request, and release the file-session lock before disk/DB IO.
app_session_release();

try {
    $metadata = user_file_store_upload($userId, $file);
    file_upload_success(['file' => $metadata]);
} catch (UserFileUploadException $exception) {
    file_upload_error($exception->errorCode, $exception->getMessage(), $exception->httpStatus);
} catch (PDOException $exception) {
    error_log('File upload metadata insert failed: ' . $exception->getMessage());
    file_upload_error('file_library_unavailable', 'File Library migration is required.', 503);
} catch (Throwable $exception) {
    error_log('File upload failed: ' . $exception->getMessage());
    file_upload_error('internal_error', 'File upload failed.', 500);
}
