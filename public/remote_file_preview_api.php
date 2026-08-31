<?php

declare(strict_types=1);

define('APP_RESPONSE_FORMAT', 'json');

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/api.php';
require_once dirname(__DIR__) . '/app/remote_file/remote_bootstrap.php';

/** @param array<string,mixed> $body */
function remote_preview_emit(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cross-Origin-Resource-Policy: same-origin');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
    app_send_no_store_headers();
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

app_session_start();
app_send_private_no_store_headers();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    remote_preview_emit(405, ['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'GET is required.']]);
}
$userId = app_session_user_id();
if ($userId === null) {
    remote_preview_emit(401, ['ok' => false, 'error' => ['code' => 'unauthenticated', 'message' => 'Authentication is required.']]);
}
$connectionId = app_validate_positive_int($_GET['remote_connection_id'] ?? null);
$path = remote_path_normalize_relative($_GET['path'] ?? null);
$mode = app_validate_enum($_GET['mode'] ?? '', ['text', 'csv']);
if ($connectionId === null || $path === null || $path === '/' || $mode === null) {
    remote_preview_emit(404, ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'File not found.']]);
}
app_session_release();

$tempPath = null;
try {
    $preview = remote_service_prepare_preview($userId, $connectionId, $path);
    $tempPath = $preview['path'];
    if ($mode === 'text' && $preview['kind'] === 'text') {
        $data = user_file_preview_text($preview['row'], $tempPath);
        @unlink($tempPath);
        $tempPath = null;
        remote_preview_emit(200, ['ok' => true, 'data' => ['text' => $data]]);
    }
    if ($mode === 'csv' && $preview['kind'] === 'csv') {
        $data = user_file_preview_csv($preview['row'], $tempPath);
        @unlink($tempPath);
        $tempPath = null;
        remote_preview_emit(200, ['ok' => true, 'data' => ['csv' => $data]]);
    }
    @unlink($tempPath);
    $tempPath = null;
    remote_preview_emit(404, ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'File not found.']]);
} catch (UserFilePreviewException $exception) {
    if (is_string($tempPath)) {
        @unlink($tempPath);
        $tempPath = null;
    }
    $status = in_array($exception->errorCode, ['preview_encoding_unsupported', 'preview_record_too_large'], true) ? 422 : 500;
    remote_preview_emit($status, ['ok' => false, 'error' => ['code' => $exception->errorCode, 'message' => 'Remote preview is not available for this file.']]);
} catch (Throwable $exception) {
    if (is_string($tempPath)) {
        @unlink($tempPath);
        $tempPath = null;
    }
    $response = remote_api_failure('file.preview', $userId, $exception);
    remote_preview_emit($response['status'], $response['body']);
}
