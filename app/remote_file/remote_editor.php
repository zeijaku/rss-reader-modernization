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
