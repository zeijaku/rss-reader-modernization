<?php

declare(strict_types=1);

define('APP_RESPONSE_FORMAT', 'json');

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/api.php';
require_once dirname(__DIR__) . '/app/remote_file/remote_bootstrap.php';

/** @param array<string,mixed> $body */
function remote_upload_emit(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cross-Origin-Resource-Policy: same-origin');
    if (app_session_is_authenticated()) {
        $token = app_csrf_current_token();
        if ($token !== null) {
            header('X-CSRF-Token: ' . $token);
        }
    }
    app_send_no_store_headers();
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

function remote_upload_content_length(): ?int
{
    $raw = $_SERVER['CONTENT_LENGTH'] ?? null;
    if (!is_string($raw) && !is_int($raw)) {
        return null;
    }
    $raw = trim((string) $raw);
    if ($raw === '' || preg_match('/\A\d{1,20}\z/D', $raw) !== 1) {
        return null;
    }
    return (int) $raw;
}

/** @return array{name:string,tmp_name:string,size:int} */
function remote_upload_validate_file(array $file): array
{
    foreach (['name', 'tmp_name', 'error', 'size'] as $required) {
        if (!array_key_exists($required, $file) || is_array($file[$required])) {
            throw new AppRemoteValidationException('invalid_transfer');
        }
    }
    $error = is_numeric($file['error']) ? (int) $file['error'] : -1;
    if ($error !== UPLOAD_ERR_OK) {
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new AppRemoteTransportException('transfer_too_large');
        }
        throw new AppRemoteValidationException('invalid_transfer');
    }
    $name = user_file_normalize_original_name($file['name']);
    $tmp = is_string($file['tmp_name']) ? $file['tmp_name'] : '';
    if ($name === null || $tmp === '' || !is_uploaded_file($tmp) || !is_file($tmp) || !is_readable($tmp)) {
        throw new AppRemoteValidationException('invalid_transfer');
    }
    $size = filesize($tmp);
    if (!is_int($size) || $size <= 0) {
        throw new AppRemoteValidationException('invalid_transfer');
    }
    if ($size > APP_REMOTE_TRANSFER_MAX_BYTES) {
        throw new AppRemoteTransportException('transfer_too_large');
    }
    return ['name' => $name, 'tmp_name' => $tmp, 'size' => $size];
}

app_session_start();
app_send_private_no_store_headers();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    remote_upload_emit(405, ['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'POST is required.']]);
}
$userId = app_session_user_id();
if ($userId === null) {
    remote_upload_emit(401, ['ok' => false, 'error' => ['code' => 'unauthenticated', 'message' => 'Authentication is required.']]);
}
$csrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : null;
if (!app_csrf_is_valid($csrf)) {
    remote_upload_emit(403, ['ok' => false, 'error' => ['code' => 'csrf_invalid', 'message' => 'CSRF validation failed.']]);
}
$contentLength = remote_upload_content_length();
if ($contentLength !== null && $contentLength > APP_REMOTE_UPLOAD_MAX_REQUEST_BYTES) {
    remote_upload_emit(413, ['ok' => false, 'error' => ['code' => 'request_too_large', 'message' => 'Upload request is too large.']]);
}
$connectionId = app_validate_positive_int($_POST['remote_connection_id'] ?? null);
$directory = remote_path_normalize_relative($_POST['path'] ?? '/');
$overwrite = ($_POST['overwrite'] ?? '0') === '1';
$file = $_FILES['file'] ?? null;
if ($connectionId === null || $directory === null || !is_array($file)) {
    remote_upload_emit(422, ['ok' => false, 'error' => ['code' => 'invalid_request', 'message' => 'Connection, path and file are required.']]);
}

try {
    $validated = remote_upload_validate_file($file);
    $remotePath = remote_path_child($directory, $validated['name']);
    if ($remotePath === null) {
        throw new AppRemoteValidationException('invalid_path');
    }
    $stream = @fopen($validated['tmp_name'], 'rb');
    if (!is_resource($stream)) {
        throw new AppRemoteValidationException('invalid_transfer');
    }
    app_session_release();
    try {
        remote_service_upload_stream($userId, $connectionId, $stream, $validated['size'], $remotePath, $overwrite);
    } finally {
        fclose($stream);
    }
    remote_upload_emit(201, ['ok' => true, 'data' => ['name' => $validated['name'], 'path' => $remotePath, 'size' => $validated['size']]]);
} catch (Throwable $exception) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        app_session_release();
    }
    $response = remote_api_failure('file.upload', $userId, $exception);
    remote_upload_emit($response['status'], $response['body']);
}
