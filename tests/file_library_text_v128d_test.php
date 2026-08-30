<?php

declare(strict_types=1);

function user_file_library_row_type_is_valid(array $row): bool
{
    $extension = strtolower((string) ($row['file_extension'] ?? ''));
    $mime = strtolower((string) ($row['file_mime_type'] ?? ''));
    $allowed = [
        'png' => ['image/png'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'zip' => ['application/zip'],
    ];
    return isset($allowed[$extension]) && in_array($mime, $allowed[$extension], true);
}

function user_file_library_is_inline_image(array $row): bool
{
    return user_file_library_row_type_is_valid($row)
        && strtolower((string) ($row['file_extension'] ?? '')) === 'png';
}

function user_file_library_format_bytes(int $bytes): string
{
    return $bytes . ' B';
}

require_once dirname(__DIR__) . '/app/file_preview.php';

$pass = 0;
$fail = 0;
function check_v128d(bool $condition, string $label): void
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

function txt_row(): array
{
    return [
        'file_id' => 31,
        'file_owner' => 7,
        'file_original_name' => 'note.txt',
        'file_stored_name' => str_repeat('a', 64) . '.txt',
        'file_mime_type' => 'text/plain',
        'file_extension' => 'txt',
        'file_size' => 1,
        'file_created_at' => '2026-08-31 07:00:00',
    ];
}

check_v128d(USER_FILE_TEXT_PREVIEW_MAX_BYTES === 65536, 'TXT preview remains capped at 64 KiB');
check_v128d(USER_FILE_TEXT_PREVIEW_MAX_LINES === 300, 'TXT preview remains capped at 300 lines');

$work = sys_get_temp_dir() . '/rss-v128d-' . bin2hex(random_bytes(4));
mkdir($work, 0700, true);

$basic = $work . '/basic.txt';
$basicContent = "hello\n<script>alert('x')</script>\n日本語";
file_put_contents($basic, $basicContent);
$row = txt_row();
$row['file_size'] = filesize($basic);
$preview = user_file_preview_text($row, $basic);
check_v128d(($preview['content'] ?? null) === $basicContent, 'valid UTF-8 TXT is returned as literal text');
check_v128d(($preview['truncated'] ?? null) === false, 'small TXT is not marked truncated');
check_v128d(($preview['line_count'] ?? null) === 3, 'line count reflects previewed lines');
check_v128d(($preview['max_bytes'] ?? null) === 65536, 'response advertises byte ceiling');
check_v128d(($preview['max_lines'] ?? null) === 300, 'response advertises line ceiling');
check_v128d(str_contains((string) $preview['content'], '<script>'), 'HTML-like input remains present for client-side textContent rendering test');

$bom = $work . '/bom.txt';
file_put_contents($bom, "\xEF\xBB\xBF先頭BOM\nOK");
$row['file_size'] = filesize($bom);
$bomPreview = user_file_preview_text($row, $bom);
check_v128d(($bomPreview['content'] ?? null) === "先頭BOM\nOK", 'UTF-8 BOM is removed from preview only');

$crlf = $work . '/crlf.txt';
file_put_contents($crlf, "a\r\nb\rc\n");
$row['file_size'] = filesize($crlf);
$crlfPreview = user_file_preview_text($row, $crlf);
check_v128d(($crlfPreview['content'] ?? null) === "a\nb\nc\n", 'CRLF and CR are normalized for consistent preview');

$manyLines = $work . '/many-lines.txt';
$lineData = implode("\n", array_map(static fn (int $n): string => 'line-' . $n, range(1, 305)));
file_put_contents($manyLines, $lineData);
$row['file_size'] = filesize($manyLines);
$linePreview = user_file_preview_text($row, $manyLines);
check_v128d(($linePreview['truncated'] ?? null) === true, 'more than 300 lines is marked truncated');
check_v128d(($linePreview['line_count'] ?? null) === 300, 'line preview stops at exactly 300 lines');
check_v128d(str_contains((string) $linePreview['content'], 'line-300'), '300th line is included');
check_v128d(!str_contains((string) $linePreview['content'], 'line-301'), '301st line is excluded');

$large = $work . '/large.txt';
$largeData = str_repeat('あ', 23000);
file_put_contents($large, $largeData);
$row['file_size'] = filesize($large);
$largePreview = user_file_preview_text($row, $large);
check_v128d(($largePreview['truncated'] ?? null) === true, 'TXT over 64 KiB is marked truncated');
check_v128d(strlen((string) $largePreview['content']) <= USER_FILE_TEXT_PREVIEW_MAX_BYTES, 'byte-capped preview never exceeds 64 KiB');
check_v128d(preg_match('//u', (string) $largePreview['content']) === 1, 'multibyte cut is trimmed to a valid UTF-8 boundary');

$invalid = $work . '/invalid.txt';
file_put_contents($invalid, "valid\xFFbroken");
$row['file_size'] = filesize($invalid);
try {
    user_file_preview_text($row, $invalid);
    check_v128d(false, 'invalid UTF-8 is rejected');
} catch (UserFilePreviewException $exception) {
    check_v128d($exception->errorCode === 'preview_encoding_unsupported', 'invalid UTF-8 fails with safe preview encoding error');
}

$csvRow = txt_row();
$csvRow['file_original_name'] = 'sample.csv';
$csvRow['file_extension'] = 'csv';
$csvRow['file_mime_type'] = 'text/csv';
try {
    user_file_preview_text($csvRow, $basic);
    check_v128d(false, 'CSV cannot enter TXT preview helper');
} catch (UserFilePreviewException $exception) {
    check_v128d($exception->errorCode === 'preview_type_not_supported', 'non-TXT type fails closed');
}

try {
    user_file_preview_text(txt_row(), $work . '/missing.txt');
    check_v128d(false, 'missing file is rejected');
} catch (UserFilePreviewException $exception) {
    check_v128d($exception->errorCode === 'preview_unavailable', 'missing source fails safely');
}

$detailRow = txt_row();
$detailRow['file_size'] = filesize($basic);
$detail = user_file_preview_detail($detailRow, $basic);
check_v128d(($detail['preview_kind'] ?? null) === 'text', 'B File Detail still identifies TXT preview kind');
check_v128d(!array_key_exists('file_stored_name', $detail), 'B File Detail still hides stored physical filename');
check_v128d(!array_key_exists('file_owner', $detail), 'B File Detail still hides owner id');

foreach (glob($work . '/*') ?: [] as $path) {
    @unlink($path);
}
@rmdir($work);

printf("RESULT: PASS %d / FAIL %d\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
