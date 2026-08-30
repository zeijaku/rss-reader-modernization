<?php

declare(strict_types=1);

define('APP_RESPONSE_FORMAT', 'json');

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/user_file.php';
require_once dirname(__DIR__) . '/app/file_library.php';
require_once dirname(__DIR__) . '/app/file_preview.php';

/** @param array<string,mixed> $body */
function file_preview_emit(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cross-Origin-Resource-Policy: same-origin');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
    app_send_no_store_headers();
    echo json_encode(
        $body,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    exit;
}

/** @param array<string,mixed> $data */
function file_preview_success(array $data): never
{
    file_preview_emit(200, ['ok' => true, 'data' => $data]);
}

function file_preview_error(string $code, string $message, int $status): never
{
    file_preview_emit($status, ['ok' => false, 'error' => ['code' => $code, 'message' => $message]]);
}

app_session_start();
app_send_private_no_store_headers();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    file_preview_error('method_not_allowed', 'GET is required.', 405);
}

$userId = app_session_user_id();
if ($userId === null) {
    file_preview_error('unauthenticated', 'Authentication is required.', 401);
}

$fileId = app_validate_positive_int($_GET['id'] ?? null);
$mode = app_validate_enum($_GET['mode'] ?? 'detail', ['detail', 'text', 'csv']);
if ($fileId === null || $mode === null) {
    file_preview_error('not_found', 'File not found.', 404);
}

app_session_release();

try {
    $row = user_file_library_find_owned($userId, $fileId);
    if ($row === null || !user_file_library_row_type_is_valid($row)) {
        file_preview_error('not_found', 'File not found.', 404);
    }

    $path = user_file_library_resolve_path($row);
    if ($path === null || !user_file_library_content_is_intact($row, $path)) {
        error_log('File Library preview validation failed for file_id=' . $fileId);
        file_preview_error('not_found', 'File not found.', 404);
    }

    if ($mode === 'text') {
        if (user_file_preview_kind($row) !== 'text') {
            file_preview_error('not_found', 'File not found.', 404);
        }
        try {
            $text = user_file_preview_text($row, $path);
            file_preview_success(['text' => $text]);
        } catch (UserFilePreviewException $exception) {
            if ($exception->errorCode === 'preview_encoding_unsupported') {
                file_preview_error('preview_encoding_unsupported', 'TXT preview requires UTF-8 text.', 422);
            }
            throw $exception;
        }
    }

    if ($mode === 'csv') {
        if (user_file_preview_kind($row) !== 'csv') {
            file_preview_error('not_found', 'File not found.', 404);
        }
        try {
            $csv = user_file_preview_csv($row, $path);
            file_preview_success(['csv' => $csv]);
        } catch (UserFilePreviewException $exception) {
            if ($exception->errorCode === 'preview_encoding_unsupported') {
                file_preview_error('preview_encoding_unsupported', 'CSV preview requires UTF-8 text.', 422);
            }
            if ($exception->errorCode === 'preview_record_too_large') {
                file_preview_error('preview_record_too_large', 'CSV record exceeds the preview limit.', 422);
            }
            throw $exception;
        }
    }

    $detail = user_file_preview_detail($row, $path);
    file_preview_success(['file' => $detail]);
} catch (Throwable $exception) {
    error_log('File Library preview endpoint failed: ' . $exception->getMessage());
    file_preview_error('internal_error', 'File preview failed.', 500);
}
