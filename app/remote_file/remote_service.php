<?php

declare(strict_types=1);

/** @return array<string,mixed> */
function remote_service_owned_connection(int $ownerId, int $connectionId, bool $requireEnabled = true): array
{
    $row = remote_connection_find_owned($ownerId, $connectionId, true);
    if ($row === null) {
        throw new AppRemoteTransportException('not_found');
    }
    if ($requireEnabled && (int) ($row['remote_connection_enabled'] ?? 0) !== 1) {
        throw new AppRemoteTransportException('connection_disabled');
    }
    return $row;
}

function remote_service_provider(int $ownerId, int $connectionId, ?callable $transport = null): RemoteFileProvider
{
    $row = remote_service_owned_connection($ownerId, $connectionId, true);
    $target = remote_validate_target(
        $row['remote_connection_protocol'] ?? null,
        $row['remote_connection_host'] ?? null,
        $row['remote_connection_port'] ?? null,
        (int) ($row['remote_connection_allow_private'] ?? 0) === 1
    );
    if (($target['ok'] ?? false) !== true) {
        throw new AppRemoteTransportException((string) ($target['error_code'] ?? 'invalid_target'));
    }
    $credentials = remote_crypto_decrypt($ownerId, $connectionId, (string) ($row['remote_connection_secret'] ?? ''));
    return remote_provider_create($row, $credentials, $target, $transport);
}


/**
 * Reject symlink/unknown path components when the provider can expose their type.
 * Server-side chroot/rooted shares remain the authoritative boundary for protocols
 * that cannot reliably expose every intermediate symlink.
 */
function remote_service_assert_safe_path(
    RemoteFileProvider $provider,
    string $relativePath,
    bool $allowMissingFinal = false,
    bool $requireFinalDirectory = false
): void {
    $path = remote_path_normalize_relative($relativePath);
    if ($path === null) {
        throw new AppRemoteValidationException('invalid_path');
    }
    if ($path === '/') {
        return;
    }

    $segments = explode('/', ltrim($path, '/'));
    $current = '/';
    $lastIndex = count($segments) - 1;
    foreach ($segments as $index => $segment) {
        $entry = null;
        foreach ($provider->list($current) as $candidate) {
            if ((string) ($candidate['name'] ?? '') === $segment) {
                $entry = $candidate;
                break;
            }
        }
        $isFinal = $index === $lastIndex;
        if ($entry === null) {
            if ($isFinal && $allowMissingFinal) {
                return;
            }
            throw new AppRemoteValidationException('invalid_path');
        }

        $type = (string) ($entry['type'] ?? 'other');
        if ($type === 'symlink' || $type === 'other') {
            throw new AppRemoteValidationException('unsafe_path');
        }
        if (!$isFinal && $type !== 'directory') {
            throw new AppRemoteValidationException('invalid_path');
        }
        if ($isFinal && $requireFinalDirectory && $type !== 'directory') {
            throw new AppRemoteValidationException('invalid_path');
        }

        $next = remote_path_child($current, $segment);
        if ($next === null) {
            throw new AppRemoteValidationException('invalid_path');
        }
        $current = $next;
    }
}

/** @return list<array{name:string,path:string,type:string,size:?int,modified_at:?string}> */
function remote_service_list(int $ownerId, int $connectionId, string $relativePath): array
{
    $path = remote_path_normalize_relative($relativePath);
    if ($path === null) {
        throw new AppRemoteValidationException('invalid_path');
    }
    $provider = remote_service_provider($ownerId, $connectionId);
    remote_service_assert_safe_path($provider, $path, false, true);
    $entries = $provider->list($path);
    usort($entries, static function (array $a, array $b): int {
        $aDirectory = ($a['type'] ?? '') === 'directory' ? 0 : 1;
        $bDirectory = ($b['type'] ?? '') === 'directory' ? 0 : 1;
        return $aDirectory <=> $bDirectory ?: strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });
    return $entries;
}

/** @return array{connected:bool,code:string} */
function remote_service_test_connection(int $ownerId, int $connectionId): array
{
    return remote_service_provider($ownerId, $connectionId)->testConnection();
}

function remote_service_mkdir(int $ownerId, int $connectionId, string $relativePath): void
{
    $path = remote_path_normalize_relative($relativePath);
    if ($path === null || $path === '/') {
        throw new AppRemoteValidationException('invalid_path');
    }
    $provider = remote_service_provider($ownerId, $connectionId);
    remote_service_assert_safe_path($provider, $path, true, false);
    $provider->mkdir($path);
}

function remote_service_move(int $ownerId, int $connectionId, string $fromPath, string $toPath, bool $overwrite): void
{
    $from = remote_path_normalize_relative($fromPath);
    $to = remote_path_normalize_relative($toPath);
    if ($from === null || $to === null || $from === '/' || $to === '/' || $from === $to) {
        throw new AppRemoteValidationException('invalid_path');
    }
    $provider = remote_service_provider($ownerId, $connectionId);
    remote_service_assert_safe_path($provider, $from, false, false);
    remote_service_assert_safe_path($provider, $to, true, false);
    $provider->move($from, $to, $overwrite);
}

function remote_service_delete(int $ownerId, int $connectionId, string $relativePath, bool $directory): void
{
    $path = remote_path_normalize_relative($relativePath);
    if ($path === null || $path === '/') {
        throw new AppRemoteValidationException('invalid_path');
    }
    $provider = remote_service_provider($ownerId, $connectionId);
    remote_service_assert_safe_path($provider, $path, false, $directory);
    $provider->delete($path, $directory);
}

/** @param resource $stream */
function remote_service_upload_stream(
    int $ownerId,
    int $connectionId,
    $stream,
    int $size,
    string $relativePath,
    bool $overwrite
): void {
    $path = remote_path_normalize_relative($relativePath);
    if (!is_resource($stream) || $path === null || $path === '/' || $size <= 0 || $size > APP_REMOTE_TRANSFER_MAX_BYTES) {
        throw new AppRemoteValidationException('invalid_transfer');
    }
    $provider = remote_service_provider($ownerId, $connectionId);
    remote_service_assert_safe_path($provider, $path, true, false);
    $provider->upload($stream, $size, $path, $overwrite);
}

/** @param resource $stream */
function remote_service_download_stream(int $ownerId, int $connectionId, string $relativePath, $stream, int $maxBytes): void
{
    $path = remote_path_normalize_relative($relativePath);
    if (!is_resource($stream) || $path === null || $path === '/' || $maxBytes <= 0) {
        throw new AppRemoteValidationException('invalid_transfer');
    }
    $provider = remote_service_provider($ownerId, $connectionId);
    remote_service_assert_safe_path($provider, $path, false, false);
    $provider->download($path, $stream, min($maxBytes, APP_REMOTE_TRANSFER_MAX_BYTES));
}

function remote_service_temp_path(): string
{
    $path = tempnam(remote_temp_directory(), 'remote-file-');
    if (!is_string($path)) {
        throw new AppRemoteTransportException('temp_unavailable');
    }
    @chmod($path, 0600);
    return $path;
}

function remote_service_download_temp(int $ownerId, int $connectionId, string $relativePath, int $maxBytes): string
{
    $path = remote_service_temp_path();
    $stream = @fopen($path, 'w+b');
    if (!is_resource($stream)) {
        @unlink($path);
        throw new AppRemoteTransportException('temp_unavailable');
    }
    try {
        remote_service_download_stream($ownerId, $connectionId, $relativePath, $stream, $maxBytes);
        fflush($stream);
    } catch (Throwable $exception) {
        fclose($stream);
        @unlink($path);
        throw $exception;
    }
    fclose($stream);
    return $path;
}

function remote_service_file_name(string $relativePath): string
{
    $name = remote_path_basename($relativePath);
    $normalized = user_file_normalize_original_name($name);
    if ($normalized === null) {
        throw new AppRemoteValidationException('invalid_filename');
    }
    return $normalized;
}

/** @return array<string,mixed> */
function remote_service_import_to_library(int $ownerId, int $connectionId, string $relativePath): array
{
    $name = remote_service_file_name($relativePath);
    // File Library supports up to its own configured limit and revalidates the downloaded content.
    $path = remote_service_download_temp($ownerId, $connectionId, $relativePath, APP_FILE_UPLOAD_MAX_BYTES);
    try {
        return user_file_store_local_file($ownerId, $path, $name, true);
    } finally {
        @unlink($path);
    }
}

function remote_service_export_library_file(
    int $ownerId,
    int $connectionId,
    int $fileId,
    string $remotePath,
    bool $overwrite
): void {
    $row = user_file_library_find_owned($ownerId, $fileId);
    if ($row === null || !user_file_library_row_type_is_valid($row)) {
        throw new AppRemoteTransportException('file_not_found');
    }
    $path = user_file_library_resolve_path($row);
    if ($path === null || !user_file_library_content_is_intact($row, $path)) {
        throw new AppRemoteTransportException('file_not_found');
    }
    $size = filesize($path);
    $stream = @fopen($path, 'rb');
    if (!is_int($size) || $size <= 0 || !is_resource($stream)) {
        if (is_resource($stream)) {
            fclose($stream);
        }
        throw new AppRemoteTransportException('file_not_found');
    }
    try {
        remote_service_upload_stream($ownerId, $connectionId, $stream, $size, $remotePath, $overwrite);
    } finally {
        fclose($stream);
    }
}

/**
 * Download a bounded remote file and validate it using the existing File Library rules.
 * @return array{path:string,row:array<string,mixed>,kind:string}
 */
function remote_service_prepare_preview(int $ownerId, int $connectionId, string $relativePath): array
{
    $name = remote_service_file_name($relativePath);
    $extension = user_file_extension_from_name($name);
    if ($extension === null || $extension === 'zip') {
        throw new AppRemoteValidationException('preview_not_supported');
    }
    $maxBytes = match ($extension) {
        'txt' => USER_FILE_TEXT_PREVIEW_MAX_BYTES,
        'csv' => USER_FILE_CSV_PREVIEW_MAX_BYTES,
        default => APP_FILE_UPLOAD_MAX_BYTES,
    };
    $path = remote_service_download_temp($ownerId, $connectionId, $relativePath, $maxBytes);
    try {
        $metadata = user_file_validate_local_source($path, $name, $maxBytes);
        $row = [
            'file_original_name' => $metadata['original_name'],
            'file_mime_type' => $metadata['mime_type'],
            'file_extension' => $metadata['extension'],
            'file_size' => $metadata['file_size'],
        ];
        return ['path' => $path, 'row' => $row, 'kind' => user_file_preview_kind($row)];
    } catch (Throwable $exception) {
        @unlink($path);
        throw $exception;
    }
}
