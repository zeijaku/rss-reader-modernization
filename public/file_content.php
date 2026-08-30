<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/user_file.php';
require_once dirname(__DIR__) . '/app/file_library.php';

function file_content_error(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    app_send_no_store_headers();
    echo $message;
    exit;
}

app_session_start();
app_send_private_no_store_headers();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    file_content_error(405, 'Method not allowed.');
}

$userId = app_session_user_id();
if ($userId === null) {
    file_content_error(401, 'Authentication is required.');
}

$fileId = app_validate_positive_int($_GET['id'] ?? null);
$mode = app_validate_enum($_GET['mode'] ?? 'download', ['view', 'thumb', 'download']);
if ($fileId === null || $mode === null) {
    file_content_error(404, 'File not found.');
}

app_session_release();

try {
    $row = user_file_library_find_owned($userId, $fileId);
    if ($row === null || !user_file_library_row_type_is_valid($row)) {
        file_content_error(404, 'File not found.');
    }

    $inline = $mode === 'view' || $mode === 'thumb';
    if ($inline && !user_file_library_is_inline_image($row)) {
        file_content_error(404, 'File not found.');
    }

    $path = user_file_library_resolve_path($row);
    if ($path === null || !user_file_library_content_is_intact($row, $path)) {
        error_log('File Library content validation failed for file_id=' . $fileId);
        file_content_error(404, 'File not found.');
    }

    $size = filesize($path);
    if (!is_int($size) || $size <= 0) {
        file_content_error(404, 'File not found.');
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: ' . (string) $row['file_mime_type']);
    header('Content-Length: ' . $size);
    header('Content-Disposition: ' . user_file_library_content_disposition($row, $inline));
    header('X-Content-Type-Options: nosniff');
    header('Cross-Origin-Resource-Policy: same-origin');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    app_send_no_store_headers();
    readfile($path);
    exit;
} catch (Throwable $exception) {
    error_log('File Library content endpoint failed: ' . $exception->getMessage());
    file_content_error(500, 'File operation failed.');
}
