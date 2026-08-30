<?php

declare(strict_types=1);

const USER_FILE_LIBRARY_PAGE_SIZE = 24;

function user_file_library_page_size(): int
{
    return USER_FILE_LIBRARY_PAGE_SIZE;
}

function user_file_library_count(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    $stmt = conn_db()->prepare(
        'SELECT COUNT(*) FROM ' . user_file_table_identifier() . ' '
        . 'WHERE file_owner = :owner AND file_flag = 0'
    );
    $stmt->execute([':owner' => $userId]);
    $count = $stmt->fetchColumn();
    return is_numeric($count) ? max(0, (int) $count) : 0;
}

/** @return list<array<string,mixed>> */
function user_file_library_list(int $userId, int $page, int $pageSize = USER_FILE_LIBRARY_PAGE_SIZE): array
{
    if ($userId <= 0) {
        return [];
    }
    $safePageSize = max(1, min(USER_FILE_LIBRARY_PAGE_SIZE, $pageSize));
    $safePage = max(1, $page);
    $offset = ($safePage - 1) * $safePageSize;
    $sql = 'SELECT file_id, file_original_name, file_mime_type, file_extension, file_size, file_created_at '
        . 'FROM ' . user_file_table_identifier() . ' '
        . 'WHERE file_owner = :owner AND file_flag = 0 '
        . 'ORDER BY file_id DESC LIMIT ' . $safePageSize . ' OFFSET ' . $offset;
    $stmt = conn_db()->prepare($sql);
    $stmt->execute([':owner' => $userId]);
    $rows = $stmt->fetchAll();
    return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
}

function user_file_library_find_owned(int $userId, int $fileId): ?array
{
    if ($userId <= 0 || $fileId <= 0) {
        return null;
    }
    $stmt = conn_db()->prepare(
        'SELECT file_id, file_owner, file_original_name, file_stored_name, file_mime_type, file_extension, file_size, file_created_at '
        . 'FROM ' . user_file_table_identifier() . ' '
        . 'WHERE file_id = :file_id AND file_owner = :owner AND file_flag = 0 LIMIT 1'
    );
    $stmt->execute([':file_id' => $fileId, ':owner' => $userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function user_file_library_row_type_is_valid(array $row): bool
{
    $extension = isset($row['file_extension']) && is_string($row['file_extension'])
        ? strtolower($row['file_extension'])
        : '';
    $mime = isset($row['file_mime_type']) && is_string($row['file_mime_type'])
        ? strtolower($row['file_mime_type'])
        : '';
    $allowed = user_file_allowed_types();
    if (!isset($allowed[$extension])) {
        return false;
    }
    return in_array($mime, $allowed[$extension]['mimes'], true);
}

function user_file_library_is_inline_image(array $row): bool
{
    if (!user_file_library_row_type_is_valid($row)) {
        return false;
    }
    $extension = strtolower((string) $row['file_extension']);
    return user_file_allowed_types()[$extension]['image'] === true;
}

function user_file_library_storage_directory(): ?string
{
    $configured = (string) APP_FILE_UPLOAD_DIR;
    if ($configured === '' || str_contains($configured, "\0") || !is_dir($configured)) {
        return null;
    }
    $real = realpath($configured);
    if (!is_string($real) || !is_dir($real) || !is_readable($real)) {
        return null;
    }
    $public = realpath(dirname(__DIR__) . '/public');
    if (is_string($public) && user_file_path_is_within($real, $public)) {
        return null;
    }
    return $real;
}

function user_file_library_resolve_path(array $row): ?string
{
    if (!user_file_library_row_type_is_valid($row)) {
        return null;
    }
    $storedName = isset($row['file_stored_name']) && is_string($row['file_stored_name'])
        ? $row['file_stored_name']
        : '';
    $extension = strtolower((string) ($row['file_extension'] ?? ''));
    if (preg_match('/\A[a-f0-9]{64}\.([a-z0-9]{1,8})\z/D', $storedName, $matches) !== 1
        || strtolower($matches[1]) !== $extension) {
        return null;
    }
    $directory = user_file_library_storage_directory();
    if ($directory === null) {
        return null;
    }
    $real = realpath($directory . DIRECTORY_SEPARATOR . $storedName);
    if (!is_string($real) || !is_file($real) || !is_readable($real)
        || !user_file_path_is_within($real, $directory)) {
        return null;
    }
    return $real;
}

function user_file_library_content_is_intact(array $row, string $path): bool
{
    $expectedSize = isset($row['file_size']) && is_numeric($row['file_size']) ? (int) $row['file_size'] : 0;
    $actualSize = filesize($path);
    if (!is_int($actualSize) || $actualSize <= 0 || $expectedSize !== $actualSize || $actualSize > APP_FILE_UPLOAD_MAX_BYTES) {
        return false;
    }
    $mime = user_file_detect_mime($path);
    if (!is_string($mime) || $mime !== strtolower((string) ($row['file_mime_type'] ?? ''))) {
        return false;
    }
    $extension = strtolower((string) ($row['file_extension'] ?? ''));
    return user_file_library_is_inline_image($row)
        ? user_file_validate_image_content($path, $mime)
        : user_file_validate_non_image_content($path, $extension);
}

function user_file_library_delete_owned(int $userId, int $fileId): bool
{
    $row = user_file_library_find_owned($userId, $fileId);
    if ($row === null) {
        return false;
    }
    $stmt = conn_db()->prepare(
        'UPDATE ' . user_file_table_identifier() . ' SET file_flag = 1 '
        . 'WHERE file_id = :file_id AND file_owner = :owner AND file_flag = 0'
    );
    $stmt->execute([':file_id' => $fileId, ':owner' => $userId]);
    if ($stmt->rowCount() !== 1) {
        return false;
    }

    $path = user_file_library_resolve_path($row);
    if ($path !== null && !@unlink($path)) {
        error_log('File Library orphan cleanup failed for file_id=' . $fileId);
    }
    return true;
}

function user_file_library_fallback_filename(string $originalName, string $extension): string
{
    $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $originalName);
    $fallback = is_string($fallback) ? trim($fallback, '._-') : '';
    if ($fallback === '') {
        $fallback = 'download.' . $extension;
    }
    if (strlen($fallback) > 120) {
        $fallback = substr($fallback, 0, 110) . '.' . $extension;
    }
    return $fallback;
}

function user_file_library_content_disposition(array $row, bool $inline): string
{
    $original = isset($row['file_original_name']) && is_string($row['file_original_name'])
        ? $row['file_original_name']
        : '';
    $extension = isset($row['file_extension']) && is_string($row['file_extension'])
        ? strtolower($row['file_extension'])
        : 'bin';
    $fallback = user_file_library_fallback_filename($original, $extension);
    $encoded = rawurlencode($original !== '' ? $original : $fallback);
    return ($inline ? 'inline' : 'attachment')
        . '; filename="' . $fallback . '"; filename*=UTF-8\'\'' . $encoded;
}

function user_file_library_format_bytes(int $bytes): string
{
    $bytes = max(0, $bytes);
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return number_format($bytes / 1024, 1) . ' KiB';
    }
    return number_format($bytes / 1048576, 1) . ' MiB';
}

function user_file_library_request_content_length(): ?int
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
