<?php

declare(strict_types=1);

final class AppRemoteEditorException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $httpStatus = 422
    ) {
        parent::__construct('Remote Text Editor validation failed.');
    }
}

/** @return list<string> */
function remote_editor_allowed_extensions(): array
{
    return [
        'txt', 'md', 'csv', 'json', 'xml', 'html', 'htm',
        'css', 'js', 'php', 'ini', 'conf', 'yml', 'yaml',
    ];
}

/** @return array{path:string,name:string,extension:string} */
function remote_editor_path_info(string $relativePath): array
{
    $path = remote_path_normalize_relative($relativePath);
    if ($path === null || $path === '/') {
        throw new AppRemoteValidationException('invalid_path');
    }

    $name = remote_path_basename($path);
    $dot = strrpos($name, '.');
    if ($dot === false || $dot === 0 || $dot === strlen($name) - 1) {
        throw new AppRemoteEditorException('editor_type_unsupported', 415);
    }

    $extension = strtolower(substr($name, $dot + 1));
    if (!in_array($extension, remote_editor_allowed_extensions(), true)) {
        throw new AppRemoteEditorException('editor_type_unsupported', 415);
    }

    return ['path' => $path, 'name' => $name, 'extension' => $extension];
}

function remote_editor_detect_line_ending(string $text): string
{
    $crlf = substr_count($text, "\r\n");
    $withoutCrlf = str_replace("\r\n", '', $text);
    $lf = substr_count($withoutCrlf, "\n");
    $cr = substr_count($withoutCrlf, "\r");

    $types = ($crlf > 0 ? 1 : 0) + ($lf > 0 ? 1 : 0) + ($cr > 0 ? 1 : 0);
    if ($types > 1) {
        return 'mixed';
    }
    if ($crlf > 0) {
        return 'crlf';
    }
    if ($lf > 0) {
        return 'lf';
    }
    if ($cr > 0) {
        return 'cr';
    }
    return 'none';
}

/**
 * Validate and inspect raw remote bytes for browser text editing.
 * SHA-256 is calculated from the exact remote bytes, including BOM/EOL bytes.
 *
 * @return array{path:string,name:string,extension:string,text:string,byte_size:int,sha256:string,utf8_bom:bool,line_ending:string}
 */
function remote_editor_inspect_bytes(string $relativePath, string $bytes): array
{
    $pathInfo = remote_editor_path_info($relativePath);
    $byteSize = strlen($bytes);
    if ($byteSize > APP_REMOTE_EDITOR_MAX_BYTES) {
        throw new AppRemoteEditorException('editor_too_large', 413);
    }

    $sha256 = hash('sha256', $bytes);
    $hasBom = str_starts_with($bytes, "\xEF\xBB\xBF");
    $text = $hasBom ? substr($bytes, 3) : $bytes;

    if (strpos($text, "\0") !== false) {
        throw new AppRemoteEditorException('editor_binary_unsupported', 422);
    }
    if (preg_match('//u', $text) !== 1) {
        throw new AppRemoteEditorException('editor_encoding_unsupported', 422);
    }
    if (preg_match('/[\x{0001}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}]/u', $text) === 1) {
        throw new AppRemoteEditorException('editor_binary_unsupported', 422);
    }

    $lineEnding = remote_editor_detect_line_ending($text);
    if ($lineEnding === 'mixed' || $lineEnding === 'cr') {
        throw new AppRemoteEditorException('editor_line_endings_unsupported', 422);
    }

    return [
        'path' => $pathInfo['path'],
        'name' => $pathInfo['name'],
        'extension' => $pathInfo['extension'],
        'text' => $text,
        'byte_size' => $byteSize,
        'sha256' => $sha256,
        'utf8_bom' => $hasBom,
        'line_ending' => $lineEnding,
    ];
}

/**
 * Read a remote text file through the existing bounded Remote Service path.
 * The private temporary file is removed before returning to the caller.
 *
 * @return array{path:string,name:string,extension:string,text:string,byte_size:int,sha256:string,utf8_bom:bool,line_ending:string}
 */
function remote_editor_read(int $ownerId, int $connectionId, string $relativePath): array
{
    $pathInfo = remote_editor_path_info($relativePath);
    $tempPath = remote_service_download_temp(
        $ownerId,
        $connectionId,
        $pathInfo['path'],
        APP_REMOTE_EDITOR_MAX_BYTES
    );

    try {
        $bytes = @file_get_contents($tempPath);
        if (!is_string($bytes)) {
            throw new AppRemoteTransportException('invalid_response');
        }
        return remote_editor_inspect_bytes($pathInfo['path'], $bytes);
    } finally {
        @unlink($tempPath);
    }
}

function remote_editor_validate_expected_sha256(mixed $value): string
{
    if (!is_string($value) || preg_match('/\A[0-9a-f]{64}\z/D', $value) !== 1) {
        throw new AppRemoteEditorException('editor_state_invalid', 422);
    }
    return $value;
}

/**
 * Browser textarea values are expected to use LF internally. Reject raw CR so
 * direct API callers cannot create ambiguous mixed/newline state.
 */
function remote_editor_validate_browser_text(string $text): void
{
    if (strlen($text) > APP_REMOTE_EDITOR_MAX_BYTES) {
        throw new AppRemoteEditorException('editor_too_large', 413);
    }
    if (strpos($text, "\0") !== false) {
        throw new AppRemoteEditorException('editor_binary_unsupported', 422);
    }
    if (preg_match('//u', $text) !== 1) {
        throw new AppRemoteEditorException('editor_encoding_unsupported', 422);
    }
    if (str_contains($text, "\r")) {
        throw new AppRemoteEditorException('editor_line_endings_unsupported', 422);
    }
    if (preg_match('/[\x{0001}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}]/u', $text) === 1) {
        throw new AppRemoteEditorException('editor_binary_unsupported', 422);
    }
}

/** @param array{utf8_bom:bool,line_ending:string} $current */
function remote_editor_build_save_bytes(string $text, array $current): string
{
    remote_editor_validate_browser_text($text);

    $lineEnding = (string) ($current['line_ending'] ?? '');
    if ($lineEnding === 'crlf') {
        $bytes = str_replace("\n", "\r\n", $text);
    } elseif ($lineEnding === 'lf' || $lineEnding === 'none') {
        $bytes = $text;
    } else {
        throw new AppRemoteEditorException('editor_line_endings_unsupported', 422);
    }

    if (($current['utf8_bom'] ?? false) === true) {
        $bytes = "\xEF\xBB\xBF" . $bytes;
    }
    return $bytes;
}

/**
 * Read with a provider already created for the save transaction. This keeps
 * conflict checks and staged replacement on the same protocol-neutral provider.
 *
 * @return array{path:string,name:string,extension:string,text:string,byte_size:int,sha256:string,utf8_bom:bool,line_ending:string}
 */
function remote_editor_read_with_provider(RemoteFileProvider $provider, string $relativePath): array
{
    $pathInfo = remote_editor_path_info($relativePath);
    remote_service_assert_safe_path($provider, $pathInfo['path'], false, false);

    $tempPath = remote_service_temp_path();
    $stream = @fopen($tempPath, 'w+b');
    if (!is_resource($stream)) {
        @unlink($tempPath);
        throw new AppRemoteTransportException('temp_unavailable');
    }

    try {
        $provider->download($pathInfo['path'], $stream, APP_REMOTE_EDITOR_MAX_BYTES);
        fflush($stream);
        fclose($stream);
        $stream = null;

        $bytes = @file_get_contents($tempPath);
        if (!is_string($bytes)) {
            throw new AppRemoteTransportException('invalid_response');
        }
        return remote_editor_inspect_bytes($pathInfo['path'], $bytes);
    } finally {
        if (is_resource($stream)) {
            fclose($stream);
        }
        @unlink($tempPath);
    }
}

function remote_editor_stage_path(string $relativePath): string
{
    $parent = remote_path_parent($relativePath);
    try {
        $name = '.iguguru-editor-' . bin2hex(random_bytes(16)) . '.tmp';
    } catch (Throwable) {
        throw new AppRemoteEditorException('editor_stage_unavailable', 503);
    }
    $path = remote_path_child($parent, $name);
    if ($path === null) {
        throw new AppRemoteEditorException('editor_stage_unavailable', 503);
    }
    return $path;
}

function remote_editor_upload_stage(RemoteFileProvider $provider, string $stagePath, string $bytes): void
{
    remote_service_assert_safe_path($provider, $stagePath, true, false);

    $stream = @fopen('php://temp', 'w+b');
    if (!is_resource($stream)) {
        throw new AppRemoteTransportException('temp_unavailable');
    }
    try {
        $size = strlen($bytes);
        if ($size > 0) {
            $written = fwrite($stream, $bytes);
            if (!is_int($written) || $written !== $size) {
                throw new AppRemoteTransportException('invalid_response');
            }
        }
        rewind($stream);
        $provider->upload($stream, $size, $stagePath, false);
    } finally {
        fclose($stream);
    }
}

/**
 * Save through a staged sibling file with optimistic SHA-256 conflict checks.
 * This is intentionally not described as an atomic cross-protocol replacement.
 * A remote change can still occur in the narrow race after the final check and
 * before the provider move; V1.30 does not implement remote file locking.
 *
 * @return array{path:string,name:string,extension:string,text:string,byte_size:int,sha256:string,utf8_bom:bool,line_ending:string}
 */
function remote_editor_save(
    int $ownerId,
    int $connectionId,
    string $relativePath,
    string $text,
    string $expectedSha256
): array {
    $pathInfo = remote_editor_path_info($relativePath);
    $expected = remote_editor_validate_expected_sha256($expectedSha256);
    remote_editor_validate_browser_text($text);

    $provider = remote_service_provider($ownerId, $connectionId);
    $current = remote_editor_read_with_provider($provider, $pathInfo['path']);
    if (!hash_equals($expected, $current['sha256'])) {
        throw new AppRemoteEditorException('editor_conflict', 409);
    }

    $saveBytes = remote_editor_build_save_bytes($text, $current);
    $prepared = remote_editor_inspect_bytes($pathInfo['path'], $saveBytes);

    $stagePath = '';
    $stageCreated = false;
    try {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $stagePath = remote_editor_stage_path($pathInfo['path']);
            // A failed transfer may still have created a partial remote file.
            // Mark it for safe cleanup before upload; target_exists is the one
            // case where the path belongs to someone else and must not be deleted.
            $stageCreated = true;
            try {
                remote_editor_upload_stage($provider, $stagePath, $saveBytes);
                break;
            } catch (AppRemoteTransportException $exception) {
                if ($exception->errorCode === 'target_exists') {
                    $stageCreated = false;
                    continue;
                }
                throw $exception;
            }
        }
        if (!$stageCreated) {
            throw new AppRemoteEditorException('editor_stage_unavailable', 503);
        }

        // Re-read after the staged upload so a remote modification that happened
        // during transfer is detected before the replacement is attempted.
        $latest = remote_editor_read_with_provider($provider, $pathInfo['path']);
        if (!hash_equals($expected, $latest['sha256'])) {
            throw new AppRemoteEditorException('editor_conflict', 409);
        }

        remote_service_assert_safe_path($provider, $stagePath, false, false);
        remote_service_assert_safe_path($provider, $pathInfo['path'], false, false);
        $provider->move($stagePath, $pathInfo['path'], true);
        $stageCreated = false;

        $saved = remote_editor_read_with_provider($provider, $pathInfo['path']);
        if (!hash_equals($prepared['sha256'], $saved['sha256'])) {
            throw new AppRemoteEditorException('editor_save_verification_failed', 502);
        }
        return $saved;
    } finally {
        if ($stageCreated && $stagePath !== '') {
            try {
                remote_service_assert_safe_path($provider, $stagePath, false, false);
                $provider->delete($stagePath, false);
            } catch (Throwable) {
                // Cleanup failure must not hide the original save/conflict error.
            }
        }
    }
}
