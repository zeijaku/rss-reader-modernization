<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/remote_file/remote_bootstrap.php';

function remote_content_error(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    app_send_no_store_headers();
    echo $message;
    exit;
}

function remote_content_disposition(string $name, bool $inline): string
{
    $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    $extension = preg_match('/\A[a-z0-9]{1,12}\z/D', $extension) === 1 ? $extension : 'bin';
    $fallback = user_file_library_fallback_filename($name, $extension);
    return ($inline ? 'inline' : 'attachment') . '; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($name);
}

app_session_start();
app_send_private_no_store_headers();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    remote_content_error(405, 'Method not allowed.');
}
$userId = app_session_user_id();
if ($userId === null) {
    remote_content_error(401, 'Authentication is required.');
}
$connectionId = app_validate_positive_int($_GET['remote_connection_id'] ?? null);
$path = remote_path_normalize_relative($_GET['path'] ?? null);
$mode = app_validate_enum($_GET['mode'] ?? 'download', ['download', 'view', 'preview']);
if ($connectionId === null || $path === null || $path === '/' || $mode === null) {
    remote_content_error(404, 'File not found.');
}
$name = remote_service_file_name($path);
app_session_release();

if ($mode === 'download') {
    try {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: ' . remote_content_disposition($name, false));
        header('X-Content-Type-Options: nosniff');
        header('Cross-Origin-Resource-Policy: same-origin');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        app_send_no_store_headers();
        $out = fopen('php://output', 'wb');
        if (!is_resource($out)) {
            throw new AppRemoteTransportException('transport_error');
        }
        try {
            remote_service_download_stream($userId, $connectionId, $path, $out, APP_REMOTE_TRANSFER_MAX_BYTES);
        } finally {
            fclose($out);
        }
        exit;
    } catch (Throwable $exception) {
        // Headers or a partial stream may already have been sent; do not append internal error details to the file body.
        error_log(sprintf('Remote content download failed user_id=%d class=%s', $userId, $exception::class));
        exit;
    }
}

$tempPath = null;
try {
    $preview = remote_service_prepare_preview($userId, $connectionId, $path);
    $tempPath = $preview['path'];
    $kind = $preview['kind'];
    if (($mode === 'view' && $kind !== 'image') || ($mode === 'preview' && $kind !== 'pdf')) {
        throw new AppRemoteValidationException('preview_not_supported');
    }
    $size = filesize($tempPath);
    if (!is_int($size) || $size <= 0) {
        throw new AppRemoteTransportException('not_found');
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: ' . (string) $preview['row']['file_mime_type']);
    header('Content-Length: ' . $size);
    header('Content-Disposition: ' . remote_content_disposition($name, true));
    header('X-Content-Type-Options: nosniff');
    header('Cross-Origin-Resource-Policy: same-origin');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    app_send_no_store_headers();
    readfile($tempPath);
    @unlink($tempPath);
    $tempPath = null;
    exit;
} catch (Throwable $exception) {
    if (is_string($tempPath)) {
        @unlink($tempPath);
        $tempPath = null;
    }
    error_log(sprintf('Remote content preview failed user_id=%d class=%s', $userId, $exception::class));
    remote_content_error(404, 'File not found.');
}
