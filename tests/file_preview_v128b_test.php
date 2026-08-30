<?php

declare(strict_types=1);

function user_file_library_row_type_is_valid(array $row): bool
{
    $allowed = [
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];
    $extension = strtolower((string) ($row['file_extension'] ?? ''));
    $mime = strtolower((string) ($row['file_mime_type'] ?? ''));
    return isset($allowed[$extension]) && in_array($mime, $allowed[$extension], true);
}

function user_file_library_is_inline_image(array $row): bool
{
    return user_file_library_row_type_is_valid($row)
        && in_array(strtolower((string) ($row['file_extension'] ?? '')), ['png', 'jpg', 'gif', 'webp'], true);
}

function user_file_library_format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return number_format($bytes / 1024, 1) . ' KiB';
    }
    return number_format($bytes / 1048576, 1) . ' MiB';
}

require_once dirname(__DIR__) . '/app/file_preview.php';

$pass = 0;
$fail = 0;
function check(bool $condition, string $label): void
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "PASS: {$label}\n";
    } else {
        $fail++;
        echo "FAIL: {$label}\n";
    }
}

function row_fixture(string $extension, string $mime): array
{
    return [
        'file_id' => 42,
        'file_owner' => 7,
        'file_original_name' => 'sample.' . $extension,
        'file_stored_name' => str_repeat('a', 64) . '.' . $extension,
        'file_mime_type' => $mime,
        'file_extension' => $extension,
        'file_size' => 68,
        'file_created_at' => '2026-08-31 01:00:00',
    ];
}

check(USER_FILE_TEXT_PREVIEW_MAX_BYTES === 65536, 'TXT preview byte ceiling is fixed at 64 KiB');
check(USER_FILE_TEXT_PREVIEW_MAX_LINES === 300, 'TXT preview line ceiling is fixed at 300');
check(USER_FILE_CSV_PREVIEW_MAX_BYTES === 524288, 'CSV preview byte ceiling is fixed at 512 KiB');
check(USER_FILE_CSV_PREVIEW_MAX_ROWS === 50, 'CSV preview row ceiling is fixed at 50');
check(USER_FILE_CSV_PREVIEW_MAX_COLUMNS === 30, 'CSV preview column ceiling is fixed at 30');

check(user_file_preview_kind(row_fixture('png', 'image/png')) === 'image', 'image detail advertises image preview kind');
check(user_file_preview_kind(row_fixture('pdf', 'application/pdf')) === 'pdf', 'PDF detail advertises PDF preview kind');
check(user_file_preview_kind(row_fixture('txt', 'text/plain')) === 'text', 'TXT detail advertises text preview kind');
check(user_file_preview_kind(row_fixture('csv', 'text/csv')) === 'csv', 'CSV detail advertises CSV preview kind');
check(user_file_preview_kind(row_fixture('zip', 'application/zip')) === 'download', 'ZIP remains download-only');
check(user_file_preview_kind(row_fixture('exe', 'application/octet-stream')) === 'download', 'unknown type fails closed to download-only');

$work = sys_get_temp_dir() . '/rss-v128b-' . bin2hex(random_bytes(4));
mkdir($work, 0700, true);
$png = $work . '/fixture.png';
$pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z1xkAAAAASUVORK5CYII=', true);
file_put_contents($png, $pngBytes);

$imageRow = row_fixture('png', 'image/png');
$imageRow['file_size'] = strlen($pngBytes);
$detail = user_file_preview_detail($imageRow, $png);
check(($detail['file_id'] ?? null) === 42, 'detail exposes numeric file id');
check(($detail['filename'] ?? null) === 'sample.png', 'detail exposes original filename only');
check(($detail['mime_type'] ?? null) === 'image/png', 'detail exposes validated MIME metadata');
check(($detail['extension'] ?? null) === 'png', 'detail exposes canonical extension');
check(($detail['file_size'] ?? null) === strlen($pngBytes), 'detail exposes stored metadata size');
check(($detail['file_size_label'] ?? null) === strlen($pngBytes) . ' B', 'detail exposes formatted size');
check(($detail['uploaded_at'] ?? null) === '2026-08-31 01:00:00', 'detail exposes upload timestamp');
check(($detail['preview_kind'] ?? null) === 'image', 'detail exposes safe preview kind');
check(($detail['dimensions']['width'] ?? null) === 1 && ($detail['dimensions']['height'] ?? null) === 1, 'image dimensions are derived on demand');
check(!array_key_exists('file_owner', $detail), 'detail never exposes owner id');
check(!array_key_exists('file_stored_name', $detail), 'detail never exposes random physical filename');
check(!array_key_exists('path', $detail), 'detail never exposes physical path');

$pdfRow = row_fixture('pdf', 'application/pdf');
$pdf = $work . '/fixture.pdf';
file_put_contents($pdf, "%PDF-1.4\n%%EOF\n");
$pdfRow['file_size'] = filesize($pdf);
$pdfDetail = user_file_preview_detail($pdfRow, $pdf);
check(array_key_exists('dimensions', $pdfDetail) && $pdfDetail['dimensions'] === null, 'non-image detail does not inspect dimensions');

$mismatchRow = $imageRow;
$mismatchRow['file_mime_type'] = 'image/jpeg';
check(user_file_preview_image_dimensions($mismatchRow, $png) === null, 'dimension helper fails closed on image MIME mismatch');
check(user_file_preview_image_dimensions($imageRow, $work . '/missing.png') === null, 'dimension helper fails closed for missing files');

$invalid = $imageRow;
$invalid['file_id'] = 0;
try {
    user_file_preview_detail($invalid, $png);
    check(false, 'invalid detail metadata is rejected');
} catch (InvalidArgumentException) {
    check(true, 'invalid detail metadata is rejected');
}

@unlink($png);
@unlink($pdf);
@rmdir($work);

printf("RESULT: PASS %d / FAIL %d\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
