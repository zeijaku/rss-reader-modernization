<?php

declare(strict_types=1);

const USER_FILE_TEXT_PREVIEW_MAX_BYTES = 65536;
const USER_FILE_TEXT_PREVIEW_MAX_LINES = 300;
const USER_FILE_CSV_PREVIEW_MAX_BYTES = 524288;
const USER_FILE_CSV_PREVIEW_MAX_ROWS = 50;
const USER_FILE_CSV_PREVIEW_MAX_COLUMNS = 30;
const USER_FILE_CSV_PREVIEW_MAX_RECORD_BYTES = 65536;

final class UserFilePreviewException extends RuntimeException
{
    public string $errorCode;

    public function __construct(string $errorCode, string $message)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
    }
}

function user_file_preview_utf8_prefix(string $text, bool $allowBoundaryTrim): ?string
{
    if (preg_match('//u', $text) === 1) {
        return $text;
    }
    if (!$allowBoundaryTrim) {
        return null;
    }
    for ($trim = 1; $trim <= 3 && $trim < strlen($text); $trim++) {
        $candidate = substr($text, 0, -$trim);
        if (preg_match('//u', $candidate) === 1) {
            return $candidate;
        }
    }
    return null;
}

/** @return array{content:string,truncated:bool,line_count:int,max_bytes:int,max_lines:int} */
function user_file_preview_text(array $row, string $path): array
{
    if (user_file_preview_kind($row) !== 'text') {
        throw new UserFilePreviewException('preview_type_not_supported', 'TXT preview is not available for this file.');
    }
    if (!is_file($path) || !is_readable($path)) {
        throw new UserFilePreviewException('preview_unavailable', 'TXT preview source is unavailable.');
    }

    $size = filesize($path);
    if (!is_int($size) || $size <= 0) {
        throw new UserFilePreviewException('preview_unavailable', 'TXT preview source is unavailable.');
    }

    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        throw new UserFilePreviewException('preview_unavailable', 'TXT preview source is unavailable.');
    }
    try {
        $raw = fread($handle, USER_FILE_TEXT_PREVIEW_MAX_BYTES + 4);
    } finally {
        fclose($handle);
    }
    if (!is_string($raw)) {
        throw new UserFilePreviewException('preview_unavailable', 'TXT preview could not be read.');
    }

    $truncatedByBytes = $size > USER_FILE_TEXT_PREVIEW_MAX_BYTES;
    $text = substr($raw, 0, USER_FILE_TEXT_PREVIEW_MAX_BYTES);
    if (str_starts_with($text, "\xEF\xBB\xBF")) {
        $text = substr($text, 3);
    }
    $text = user_file_preview_utf8_prefix($text, $truncatedByBytes);
    if ($text === null) {
        throw new UserFilePreviewException('preview_encoding_unsupported', 'TXT preview requires UTF-8 text.');
    }

    $normalized = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = explode("\n", $normalized);
    $truncatedByLines = count($lines) > USER_FILE_TEXT_PREVIEW_MAX_LINES;
    if ($truncatedByLines) {
        $lines = array_slice($lines, 0, USER_FILE_TEXT_PREVIEW_MAX_LINES);
    }

    return [
        'content' => implode("\n", $lines),
        'truncated' => $truncatedByBytes || $truncatedByLines,
        'line_count' => count($lines),
        'max_bytes' => USER_FILE_TEXT_PREVIEW_MAX_BYTES,
        'max_lines' => USER_FILE_TEXT_PREVIEW_MAX_LINES,
    ];
}


/** @param array<int,string|null> $record @return list<string> */
function user_file_preview_csv_record(array $record, bool &$truncatedByColumns): array
{
    if (count($record) > USER_FILE_CSV_PREVIEW_MAX_COLUMNS) {
        $record = array_slice($record, 0, USER_FILE_CSV_PREVIEW_MAX_COLUMNS);
        $truncatedByColumns = true;
    }
    return array_map(static fn($value): string => is_string($value) ? $value : '', array_values($record));
}

/**
 * Build a bounded CSV preview. The source read is capped before parsing so even
 * malformed quoted records cannot make the preview path consume the whole file.
 * The first CSV record is treated as the table header; up to 50 data rows follow.
 *
 * @return array{header:list<string>,rows:list<list<string>>,row_count:int,column_count:int,truncated:bool,truncated_by_bytes:bool,truncated_by_rows:bool,truncated_by_columns:bool,max_bytes:int,max_rows:int,max_columns:int,max_record_bytes:int}
 */
function user_file_preview_csv(array $row, string $path): array
{
    if (user_file_preview_kind($row) !== 'csv') {
        throw new UserFilePreviewException('preview_type_not_supported', 'CSV preview is not available for this file.');
    }
    if (!is_file($path) || !is_readable($path)) {
        throw new UserFilePreviewException('preview_unavailable', 'CSV preview source is unavailable.');
    }

    $size = filesize($path);
    if (!is_int($size) || $size <= 0) {
        throw new UserFilePreviewException('preview_unavailable', 'CSV preview source is unavailable.');
    }

    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        throw new UserFilePreviewException('preview_unavailable', 'CSV preview source is unavailable.');
    }
    try {
        $raw = fread($handle, USER_FILE_CSV_PREVIEW_MAX_BYTES + 4);
    } finally {
        fclose($handle);
    }
    if (!is_string($raw)) {
        throw new UserFilePreviewException('preview_unavailable', 'CSV preview could not be read.');
    }

    $truncatedByBytes = $size > USER_FILE_CSV_PREVIEW_MAX_BYTES;
    $csv = substr($raw, 0, USER_FILE_CSV_PREVIEW_MAX_BYTES);
    if (str_starts_with($csv, "\xEF\xBB\xBF")) {
        $csv = substr($csv, 3);
    }
    if (str_contains($csv, "\0")) {
        throw new UserFilePreviewException('preview_encoding_unsupported', 'CSV preview requires text content.');
    }
    $csv = user_file_preview_utf8_prefix($csv, $truncatedByBytes);
    if ($csv === null) {
        throw new UserFilePreviewException('preview_encoding_unsupported', 'CSV preview requires UTF-8 text.');
    }
    $csv = str_replace(["\r\n", "\r"], "\n", $csv);

    $stream = fopen('php://temp/maxmemory:1048576', 'w+b');
    if ($stream === false) {
        throw new UserFilePreviewException('preview_unavailable', 'CSV preview workspace is unavailable.');
    }
    try {
        if ($csv !== '' && fwrite($stream, $csv) === false) {
            throw new UserFilePreviewException('preview_unavailable', 'CSV preview workspace is unavailable.');
        }
        rewind($stream);
        $records = [];
        $truncatedByColumns = false;
        $truncatedByRows = false;
        $maxRecords = USER_FILE_CSV_PREVIEW_MAX_ROWS + 2; // header + data rows + one overflow probe
        $sourceLength = strlen($csv);

        while (count($records) < $maxRecords) {
            $start = ftell($stream);
            if (!is_int($start) || $start >= $sourceLength) {
                break;
            }
            $record = fgetcsv($stream, 0, ',', '"', '');
            if ($record === false) {
                break;
            }
            $end = ftell($stream);
            if (!is_int($end) || ($end - $start) > USER_FILE_CSV_PREVIEW_MAX_RECORD_BYTES) {
                throw new UserFilePreviewException('preview_record_too_large', 'CSV preview record exceeds the preview limit.');
            }
            $records[] = user_file_preview_csv_record($record, $truncatedByColumns);
        }

        $position = ftell($stream);
        if ($truncatedByBytes && is_int($position) && $position >= $sourceLength && $records !== []) {
            // The artificial 512 KiB EOF may cut a quoted or ordinary record. Never show that final partial row.
            array_pop($records);
        }
        if (count($records) > USER_FILE_CSV_PREVIEW_MAX_ROWS + 1) {
            $records = array_slice($records, 0, USER_FILE_CSV_PREVIEW_MAX_ROWS + 1);
            $truncatedByRows = true;
        }
    } finally {
        fclose($stream);
    }

    $header = $records !== [] ? array_shift($records) : [];
    $rows = array_slice($records, 0, USER_FILE_CSV_PREVIEW_MAX_ROWS);
    if (count($records) > USER_FILE_CSV_PREVIEW_MAX_ROWS) {
        $truncatedByRows = true;
    }

    $columnCount = count($header);
    foreach ($rows as $record) {
        $columnCount = max($columnCount, count($record));
    }
    $columnCount = min(USER_FILE_CSV_PREVIEW_MAX_COLUMNS, $columnCount);
    if ($columnCount > 0) {
        $header = array_slice(array_pad($header, $columnCount, ''), 0, $columnCount);
        foreach ($rows as $index => $record) {
            $rows[$index] = array_slice(array_pad($record, $columnCount, ''), 0, $columnCount);
        }
    }

    return [
        'header' => $header,
        'rows' => $rows,
        'row_count' => count($rows),
        'column_count' => $columnCount,
        'truncated' => $truncatedByBytes || $truncatedByRows || $truncatedByColumns,
        'truncated_by_bytes' => $truncatedByBytes,
        'truncated_by_rows' => $truncatedByRows,
        'truncated_by_columns' => $truncatedByColumns,
        'max_bytes' => USER_FILE_CSV_PREVIEW_MAX_BYTES,
        'max_rows' => USER_FILE_CSV_PREVIEW_MAX_ROWS,
        'max_columns' => USER_FILE_CSV_PREVIEW_MAX_COLUMNS,
        'max_record_bytes' => USER_FILE_CSV_PREVIEW_MAX_RECORD_BYTES,
    ];
}

function user_file_preview_kind(array $row): string
{
    if (!user_file_library_row_type_is_valid($row)) {
        return 'download';
    }

    if (user_file_library_is_inline_image($row)) {
        return 'image';
    }

    return match (strtolower((string) ($row['file_extension'] ?? ''))) {
        'pdf' => 'pdf',
        'txt' => 'text',
        'csv' => 'csv',
        default => 'download',
    };
}

/** @return array{width:int,height:int}|null */
function user_file_preview_image_dimensions(array $row, string $path): ?array
{
    if (!user_file_library_is_inline_image($row) || !is_file($path) || !is_readable($path)) {
        return null;
    }

    $info = @getimagesize($path);
    if (!is_array($info)) {
        return null;
    }

    $width = isset($info[0]) ? (int) $info[0] : 0;
    $height = isset($info[1]) ? (int) $info[1] : 0;
    $mime = isset($info['mime']) && is_string($info['mime']) ? strtolower($info['mime']) : '';
    $expectedMime = strtolower((string) ($row['file_mime_type'] ?? ''));

    if ($width <= 0 || $height <= 0 || $width > 12000 || $height > 12000
        || ($width * $height) > 40000000 || $mime !== $expectedMime) {
        return null;
    }

    return ['width' => $width, 'height' => $height];
}

/**
 * Build public File Detail data from one already owner-scoped, serve-validated file.
 * Physical storage names, owner ids and paths are intentionally omitted.
 *
 * @return array<string,mixed>
 */
function user_file_preview_detail(array $row, string $path): array
{
    if (!user_file_library_row_type_is_valid($row)) {
        throw new InvalidArgumentException('Invalid file metadata.');
    }

    $fileId = isset($row['file_id']) && is_numeric($row['file_id']) ? (int) $row['file_id'] : 0;
    $originalName = isset($row['file_original_name']) && is_string($row['file_original_name'])
        ? $row['file_original_name']
        : '';
    $mime = isset($row['file_mime_type']) && is_string($row['file_mime_type'])
        ? strtolower($row['file_mime_type'])
        : '';
    $extension = isset($row['file_extension']) && is_string($row['file_extension'])
        ? strtolower($row['file_extension'])
        : '';
    $size = isset($row['file_size']) && is_numeric($row['file_size']) ? (int) $row['file_size'] : 0;
    $createdAt = isset($row['file_created_at']) && is_string($row['file_created_at'])
        ? $row['file_created_at']
        : '';

    if ($fileId <= 0 || $originalName === '' || $mime === '' || $extension === '' || $size <= 0 || $createdAt === '') {
        throw new InvalidArgumentException('Invalid file metadata.');
    }

    $dimensions = user_file_preview_image_dimensions($row, $path);

    return [
        'file_id' => $fileId,
        'filename' => $originalName,
        'mime_type' => $mime,
        'extension' => $extension,
        'file_size' => $size,
        'file_size_label' => user_file_library_format_bytes($size),
        'uploaded_at' => $createdAt,
        'preview_kind' => user_file_preview_kind($row),
        'dimensions' => $dimensions,
    ];
}
