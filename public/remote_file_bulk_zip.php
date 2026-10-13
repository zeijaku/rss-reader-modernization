<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/remote_file/remote_bootstrap.php';

function remote_zip_fail(int $status, string $message): never
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
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    remote_zip_fail(405, 'POST is required.');
}
$userId = app_session_user_id();
if ($userId === null) {
    remote_zip_fail(401, 'Authentication is required.');
}
$csrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : null;
if (!app_csrf_is_valid($csrf)) {
    remote_zip_fail(403, 'CSRF validation failed.');
}
$connectionId = app_validate_positive_int($_POST['remote_connection_id'] ?? null);
$directory = remote_path_normalize_relative($_POST['path'] ?? null);
$names = $_POST['names'] ?? null;
if ($connectionId === null || $directory === null || !is_array($names) || count($names) === 0 || count($names) > 200) {
    remote_zip_fail(422, 'Invalid selection (maximum 200 items).');
}
foreach ($names as $name) {
    if (!is_string($name) || $name === '' || $name === '.' || $name === '..' || strlen($name) > 255 ||
        str_contains($name, '/') || str_contains($name, '\\') || preg_match('/[\x00-\x1f\x7f]/', $name)) {
        remote_zip_fail(422, 'Invalid selected filename.');
    }
}
if (count(array_unique($names)) !== count($names)) {
    remote_zip_fail(422, 'Duplicate selection.');
}
if (!class_exists('ZipArchive')) {
    remote_zip_fail(503, 'ZIP extension is unavailable.');
}

app_session_release();
$tempZip = null;
try {
    $MAX_FILES = 200;
    $MAX_BYTES = 100 * 1024 * 1024;
    $MAX_DEPTH = 15;
    $MAX_ENTRIES = 400;
    $listed = remote_service_list($userId, $connectionId, $directory);
    $index = [];
    foreach ($listed as $entry) {
        if (isset($entry['name']) && is_string($entry['name'])) {
            $index[$entry['name']] = $entry;
        }
    }
    $pending = [];
    foreach ($names as $name) {
        if (!isset($index[$name]) || !in_array($index[$name]['type'] ?? '', ['file', 'directory'], true)) {
            throw new RuntimeException('Selection is no longer available.');
        }
        $relative = remote_path_child($directory, $name);
        if ($relative === null) {
            throw new RuntimeException('Invalid remote path.');
        }
        $pending[] = ['path' => $relative, 'zip' => $name, 'type' => $index[$name]['type'], 'depth' => 0, 'size' => $index[$name]['size'] ?? null];
    }

    $files = [];
    $folders = [];
    $fileCount = 0;
    $entryCount = 0;
    $estimatedBytes = 0;
    while ($pending !== []) {
        $item = array_shift($pending);
        $entryCount++;
        if ($entryCount > $MAX_ENTRIES || $item['depth'] > $MAX_DEPTH) {
            throw new RuntimeException('ZIP item count or directory depth limit exceeded.');
        }
        if ($item['type'] === 'directory') {
            $folders[] = $item['zip'];
            $children = remote_service_list($userId, $connectionId, $item['path']);
            foreach ($children as $child) {
                $childName = $child['name'] ?? null;
                $kind = $child['type'] ?? null;
                if (!is_string($childName) || $childName === '' || $childName === '.' || $childName === '..' ||
                    strlen($childName) > 255 || str_contains($childName, '/') || str_contains($childName, '\\') ||
                    preg_match('/[\x00-\x1f\x7f]/', $childName) || !in_array($kind, ['file', 'directory'], true)) {
                    throw new RuntimeException('Unsupported entry in remote directory.');
                }
                $childPath = remote_path_child($item['path'], $childName);
                if ($childPath === null) { throw new RuntimeException('Invalid remote path.'); }
                $pending[] = ['path' => $childPath, 'zip' => $item['zip'] . '/' . $childName, 'type' => $kind,
                    'depth' => $item['depth'] + 1, 'size' => $child['size'] ?? null];
            }
        } else {
            $fileCount++;
            $size = $item['size'];
            if ($fileCount > $MAX_FILES || !is_numeric($size) || $size < 0 || $size > APP_REMOTE_TRANSFER_MAX_BYTES) {
                throw new RuntimeException('ZIP file count or file size limit exceeded.');
            }
            $estimatedBytes += (int) $size;
            if ($estimatedBytes > $MAX_BYTES) {
                throw new RuntimeException('ZIP total file size exceeds 100 MB.');
            }
            $files[] = $item;
        }
    }

    $tempZip = remote_service_temp_path();
    $zip = new ZipArchive();
    if ($zip->open($tempZip, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not prepare ZIP.');
    }
    try {
        foreach ($folders as $folder) {
            if (!$zip->addEmptyDir($folder)) {
                throw new RuntimeException('Could not add directory to ZIP.');
            }
        }
        $actualBytes = 0;
        foreach ($files as $file) {
            $download = remote_service_download_temp($userId, $connectionId, $file['path'], min(APP_REMOTE_TRANSFER_MAX_BYTES, $MAX_BYTES));
            try {
                $size = filesize($download);
                if (!is_int($size) || $size > $MAX_BYTES - $actualBytes) {
                    throw new RuntimeException('ZIP total size exceeded during download.');
                }
                $actualBytes += $size;
                if (!$zip->addFile($download, $file['zip'])) {
                    throw new RuntimeException('Could not add file to ZIP.');
                }
                // ZipArchive reads the temporary file when close() is called.
                $tempDownloads[] = $download;
                $download = null;
            } finally {
                if (is_string($download)) { @unlink($download); }
            }
        }
    } finally {
        $closed = $zip->close();
    }
    if (!$closed) {
        throw new RuntimeException('Could not finalize ZIP.');
    }
    $length = filesize($tempZip);
    if (!is_int($length) || $length <= 0) { throw new RuntimeException('Invalid ZIP result.'); }
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="remote-selection.zip"');
    header('Content-Length: ' . $length);
    header('X-Content-Type-Options: nosniff');
    header('Cross-Origin-Resource-Policy: same-origin');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    app_send_no_store_headers();
    readfile($tempZip);
} catch (Throwable $exception) {
    error_log(sprintf('Remote ZIP failed user_id=%d class=%s', $userId, $exception::class));
    if (!headers_sent()) { remote_zip_fail(422, 'ZIPを作成できませんでした。項目数・容量・ディレクトリ権限を確認してください。'); }
} finally {
    foreach ($tempDownloads ?? [] as $download) { @unlink($download); }
    if (is_string($tempZip)) { @unlink($tempZip); }
}
