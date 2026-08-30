<?php

declare(strict_types=1);

final class UserFileUploadException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $httpStatus,
        string $message
    ) {
        parent::__construct($message);
    }
}

if (!defined('APP_FILE_UPLOAD_MAX_BYTES')) {
    define('APP_FILE_UPLOAD_MAX_BYTES', max(65536, min(10485760, (int) app_env('APP_FILE_UPLOAD_MAX_BYTES', '10485760'))));
}
if (!defined('APP_FILE_UPLOAD_MAX_REQUEST_BYTES')) {
    $requestDefault = (string) max(APP_FILE_UPLOAD_MAX_BYTES + 65536, 6291456);
    define(
        'APP_FILE_UPLOAD_MAX_REQUEST_BYTES',
        max(APP_FILE_UPLOAD_MAX_BYTES + 65536, min(12582912, (int) app_env('APP_FILE_UPLOAD_MAX_REQUEST_BYTES', $requestDefault)))
    );
}
if (!defined('APP_FILE_UPLOAD_DIR')) {
    define('APP_FILE_UPLOAD_DIR', app_env('APP_FILE_UPLOAD_DIR', dirname(__DIR__) . '/var/uploads'));
}

/** @return array<string,array{mimes:list<string>,image:bool}> */
function user_file_allowed_types(): array
{
    return [
        'jpg' => ['mimes' => ['image/jpeg'], 'image' => true],
        'jpeg' => ['mimes' => ['image/jpeg'], 'image' => true],
        'png' => ['mimes' => ['image/png'], 'image' => true],
        'gif' => ['mimes' => ['image/gif'], 'image' => true],
        'webp' => ['mimes' => ['image/webp'], 'image' => true],
        'pdf' => ['mimes' => ['application/pdf'], 'image' => false],
        'txt' => ['mimes' => ['text/plain'], 'image' => false],
        'csv' => ['mimes' => ['text/plain', 'text/csv', 'application/csv'], 'image' => false],
        'zip' => ['mimes' => ['application/zip', 'application/x-zip-compressed'], 'image' => false],
    ];
}

/** @return list<string> */
function user_file_dangerous_extensions(): array
{
    return [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps',
        'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'zsh', 'ksh',
        'exe', 'com', 'bat', 'cmd', 'msi', 'dll',
        'js', 'mjs', 'cjs', 'html', 'htm', 'shtml', 'xhtml', 'svg', 'xml',
        'jsp', 'asp', 'aspx', 'cfm', 'htaccess',
    ];
}

function user_file_normalize_original_name(mixed $value): ?string
{
    if (!is_string($value) || $value === '' || str_contains($value, "\0")) {
        return null;
    }

    $value = str_replace('\\', '/', $value);
    $name = basename($value);
    $name = trim($name, " \t\n\r\0\x0B.");
    if ($name === '' || strlen($name) > 255) {
        return null;
    }

    $validUtf8 = function_exists('mb_check_encoding')
        ? mb_check_encoding($name, 'UTF-8')
        : preg_match('//u', $name) === 1;
    if (!$validUtf8 || preg_match('/[\x00-\x1F\x7F]/u', $name) === 1) {
        return null;
    }

    return $name;
}

function user_file_extension_from_name(string $name): ?string
{
    $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    if ($extension === '' || !array_key_exists($extension, user_file_allowed_types())) {
        return null;
    }
    return $extension;
}

function user_file_has_dangerous_double_extension(string $name): bool
{
    $parts = explode('.', strtolower($name));
    if (count($parts) < 3) {
        return false;
    }
    array_pop($parts);
    $dangerous = user_file_dangerous_extensions();
    foreach ($parts as $part) {
        if ($part !== '' && in_array($part, $dangerous, true)) {
            return true;
        }
    }
    return false;
}

function user_file_detect_mime(string $path): ?string
{
    if (!is_file($path) || !is_readable($path) || !class_exists(finfo::class)) {
        return null;
    }
    try {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
    } catch (Throwable) {
        return null;
    }
    if (!is_string($mime) || $mime === '') {
        return null;
    }
    $mime = strtolower(trim($mime));
    return preg_match('/\A[a-z0-9.+-]+\/[a-z0-9.+-]+\z/D', $mime) === 1 ? $mime : null;
}

function user_file_validate_image_content(string $path, string $mime): bool
{
    $info = @getimagesize($path);
    if (!is_array($info)) {
        return false;
    }
    $width = isset($info[0]) ? (int) $info[0] : 0;
    $height = isset($info[1]) ? (int) $info[1] : 0;
    $detectedMime = isset($info['mime']) && is_string($info['mime']) ? strtolower($info['mime']) : '';
    if ($width <= 0 || $height <= 0 || $width > 12000 || $height > 12000 || $detectedMime !== $mime) {
        return false;
    }
    return ($width * $height) <= 40000000;
}

function user_file_validate_non_image_content(string $path, string $extension): bool
{
    $handle = @fopen($path, 'rb');
    if (!is_resource($handle)) {
        return false;
    }
    $prefix = fread($handle, 8192);
    fclose($handle);
    if (!is_string($prefix)) {
        return false;
    }

    if ($extension === 'pdf') {
        return str_starts_with($prefix, '%PDF-');
    }
    if ($extension === 'txt' || $extension === 'csv') {
        return !str_contains($prefix, "\0");
    }
    if ($extension === 'zip') {
        return str_starts_with($prefix, "PK\x03\x04")
            || str_starts_with($prefix, "PK\x05\x06")
            || str_starts_with($prefix, "PK\x07\x08");
    }
    return false;
}

/**
 * Validate one PHP upload array without trusting browser MIME metadata.
 *
 * @param array<string,mixed> $file
 * @param callable(string):bool|null $isUploaded Override only for tests.
 * @return array{original_name:string,extension:string,mime_type:string,file_size:int,tmp_name:string}
 */
function user_file_validate_upload(array $file, ?callable $isUploaded = null): array
{
    foreach (['name', 'tmp_name', 'error', 'size'] as $required) {
        if (!array_key_exists($required, $file) || is_array($file[$required])) {
            throw new UserFileUploadException('upload_invalid', 400, 'Upload payload is invalid.');
        }
    }

    $error = is_int($file['error']) ? $file['error'] : (is_numeric($file['error']) ? (int) $file['error'] : -1);
    if ($error !== UPLOAD_ERR_OK) {
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new UserFileUploadException('file_too_large', 413, 'Uploaded file is too large.');
        }
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new UserFileUploadException('file_required', 422, 'A file is required.');
        }
        if ($error === UPLOAD_ERR_NO_TMP_DIR || $error === UPLOAD_ERR_CANT_WRITE || $error === UPLOAD_ERR_EXTENSION) {
            throw new UserFileUploadException('upload_unavailable', 503, 'File upload is temporarily unavailable.');
        }
        throw new UserFileUploadException('upload_incomplete', 400, 'File upload did not complete.');
    }

    $originalName = user_file_normalize_original_name($file['name']);
    if ($originalName === null) {
        throw new UserFileUploadException('filename_invalid', 422, 'File name is invalid.');
    }
    if (user_file_has_dangerous_double_extension($originalName)) {
        throw new UserFileUploadException('file_type_blocked', 422, 'Dangerous double extension is not allowed.');
    }

    $extension = user_file_extension_from_name($originalName);
    if ($extension === null) {
        throw new UserFileUploadException('file_type_blocked', 422, 'File type is not allowed.');
    }

    $tmpName = is_string($file['tmp_name']) ? $file['tmp_name'] : '';
    $checker = $isUploaded ?? static fn(string $path): bool => is_uploaded_file($path);
    if ($tmpName === '' || !$checker($tmpName) || !is_file($tmpName) || !is_readable($tmpName)) {
        throw new UserFileUploadException('upload_invalid', 400, 'Uploaded temporary file is invalid.');
    }

    $actualSize = filesize($tmpName);
    if (!is_int($actualSize) || $actualSize <= 0) {
        throw new UserFileUploadException('file_empty', 422, 'Empty files are not allowed.');
    }
    if ($actualSize > APP_FILE_UPLOAD_MAX_BYTES) {
        throw new UserFileUploadException('file_too_large', 413, 'Uploaded file is too large.');
    }

    $mime = user_file_detect_mime($tmpName);
    $type = user_file_allowed_types()[$extension];
    if ($mime === null || !in_array($mime, $type['mimes'], true)) {
        throw new UserFileUploadException('mime_mismatch', 422, 'File content does not match the allowed file type.');
    }

    $validContent = $type['image']
        ? user_file_validate_image_content($tmpName, $mime)
        : user_file_validate_non_image_content($tmpName, $extension);
    if (!$validContent) {
        throw new UserFileUploadException('file_content_invalid', 422, 'File content could not be validated.');
    }

    return [
        'original_name' => $originalName,
        'extension' => $extension === 'jpeg' ? 'jpg' : $extension,
        'mime_type' => $mime,
        'file_size' => $actualSize,
        'tmp_name' => $tmpName,
    ];
}

function user_file_path_is_within(string $path, string $parent): bool
{
    $normalize = static function (string $value): string {
        $value = rtrim(str_replace('\\', '/', $value), '/');
        return DIRECTORY_SEPARATOR === '\\' ? strtolower($value) : $value;
    };
    $path = $normalize($path);
    $parent = $normalize($parent);
    return $path === $parent || str_starts_with($path . '/', $parent . '/');
}

function user_file_storage_directory(): string
{
    $configured = (string) APP_FILE_UPLOAD_DIR;
    if ($configured === '' || str_contains($configured, "\0")) {
        throw new UserFileUploadException('storage_unavailable', 503, 'File storage is unavailable.');
    }
    if (!is_dir($configured) && !@mkdir($configured, 0750, true) && !is_dir($configured)) {
        throw new UserFileUploadException('storage_unavailable', 503, 'File storage is unavailable.');
    }

    $real = realpath($configured);
    if (!is_string($real) || !is_dir($real) || !is_writable($real)) {
        throw new UserFileUploadException('storage_unavailable', 503, 'File storage is unavailable.');
    }
    $public = realpath(dirname(__DIR__) . '/public');
    if (is_string($public) && user_file_path_is_within($real, $public)) {
        throw new UserFileUploadException('storage_unsafe', 503, 'File storage location is unsafe.');
    }
    return $real;
}

function user_file_generate_stored_name(string $extension, string $directory): string
{
    if (preg_match('/\A[a-z0-9]{1,8}\z/D', $extension) !== 1) {
        throw new UserFileUploadException('storage_unavailable', 503, 'File storage is unavailable.');
    }
    for ($attempt = 0; $attempt < 8; $attempt++) {
        $name = bin2hex(random_bytes(32)) . '.' . $extension;
        if (!file_exists($directory . DIRECTORY_SEPARATOR . $name)) {
            return $name;
        }
    }
    throw new UserFileUploadException('storage_unavailable', 503, 'Unable to allocate file storage name.');
}

function user_file_table_identifier(): string
{
    $prefix = defined('DB_TABLE_PREFIX') ? (string) DB_TABLE_PREFIX : '';
    if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,39}\z/D', $prefix) !== 1) {
        throw new RuntimeException('Invalid database table prefix.');
    }
    return '`' . $prefix . 'user_file`';
}

/** @param array{original_name:string,extension:string,mime_type:string,file_size:int} $metadata */
function user_file_insert_metadata(int $userId, string $storedName, array $metadata): array
{
    if ($userId <= 0 || preg_match('/\A[a-f0-9]{64}\.[a-z0-9]{1,8}\z/D', $storedName) !== 1) {
        throw new InvalidArgumentException('Invalid file metadata.');
    }
    $createdAt = app_now();
    $stmt = conn_db()->prepare(
        'INSERT INTO ' . user_file_table_identifier() . ' '
        . '(file_owner, file_original_name, file_stored_name, file_mime_type, file_extension, file_size, file_created_at) '
        . 'VALUES (:owner, :original_name, :stored_name, :mime_type, :extension, :file_size, :created_at)'
    );
    $stmt->execute([
        ':owner' => $userId,
        ':original_name' => $metadata['original_name'],
        ':stored_name' => $storedName,
        ':mime_type' => $metadata['mime_type'],
        ':extension' => $metadata['extension'],
        ':file_size' => $metadata['file_size'],
        ':created_at' => $createdAt,
    ]);
    return [
        'file_id' => (int) conn_db()->lastInsertId(),
        'original_name' => $metadata['original_name'],
        'mime_type' => $metadata['mime_type'],
        'extension' => $metadata['extension'],
        'file_size' => $metadata['file_size'],
        'created_at' => $createdAt,
    ];
}

/**
 * Move a validated upload into private storage and record owner-scoped metadata.
 *
 * @param array<string,mixed> $file
 * @param callable(string):bool|null $isUploaded Test override only.
 * @param callable(string,string):bool|null $moveUploaded Test override only.
 */
function user_file_store_upload(
    int $userId,
    array $file,
    ?callable $isUploaded = null,
    ?callable $moveUploaded = null
): array {
    if ($userId <= 0) {
        throw new InvalidArgumentException('Invalid file owner.');
    }
    $metadata = user_file_validate_upload($file, $isUploaded);
    $directory = user_file_storage_directory();
    $storedName = user_file_generate_stored_name($metadata['extension'], $directory);
    $destination = $directory . DIRECTORY_SEPARATOR . $storedName;
    $mover = $moveUploaded ?? static fn(string $from, string $to): bool => move_uploaded_file($from, $to);

    if (!$mover($metadata['tmp_name'], $destination) || !is_file($destination)) {
        @unlink($destination);
        throw new UserFileUploadException('storage_write_failed', 503, 'Unable to store uploaded file.');
    }
    @chmod($destination, 0640);

    try {
        return user_file_insert_metadata($userId, $storedName, $metadata);
    } catch (Throwable $exception) {
        @unlink($destination);
        throw $exception;
    }
}
