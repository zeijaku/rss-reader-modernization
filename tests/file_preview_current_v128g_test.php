<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/bootstrap.php';
require_once $root . '/app/user_file.php';
require_once $root . '/app/file_library.php';
require_once $root . '/app/file_preview.php';

$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail): void {
    if ($ok) { $pass++; echo "PASS: {$label}\n"; return; }
    $fail++; echo "FAIL: {$label}\n";
};
$tmp = sys_get_temp_dir() . '/rss-v128g-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700, true);
$row = static fn(string $ext, string $mime, int $size, string $name = 'sample'): array => [
    'file_id' => 1,
    'file_original_name' => $name . '.' . $ext,
    'file_mime_type' => $mime,
    'file_extension' => $ext,
    'file_size' => $size,
    'file_created_at' => '2026-08-31 12:00:00',
];
try {
    $check(USER_FILE_TEXT_PREVIEW_MAX_BYTES === 65536, 'TXT max bytes is 64 KiB');
    $check(USER_FILE_TEXT_PREVIEW_MAX_LINES === 300, 'TXT max lines is 300');
    $check(USER_FILE_CSV_PREVIEW_MAX_BYTES === 524288, 'CSV max bytes is 512 KiB');
    $check(USER_FILE_CSV_PREVIEW_MAX_ROWS === 50, 'CSV max rows is 50');
    $check(USER_FILE_CSV_PREVIEW_MAX_COLUMNS === 30, 'CSV max columns is 30');
    $check(USER_FILE_CSV_PREVIEW_MAX_RECORD_BYTES === 65536, 'CSV record max is 64 KiB');

    $txtPath = $tmp . '/sample.txt';
    file_put_contents($txtPath, "\xEF\xBB\xBFline1\r\nline2\r<script>alert(1)</script>");
    $txt = user_file_preview_text($row('txt', 'text/plain', filesize($txtPath)), $txtPath);
    $check(!str_starts_with($txt['content'], "\xEF\xBB\xBF"), 'TXT UTF-8 BOM is removed');
    $check(str_contains($txt['content'], "line1\nline2\n"), 'TXT line endings are normalized');
    $check(str_contains($txt['content'], '<script>alert(1)</script>'), 'TXT HTML-like text remains data');
    $check($txt['truncated'] === false, 'small TXT is not truncated');

    $csvPath = $tmp . '/sample.csv';
    file_put_contents($csvPath, "\xEF\xBB\xBFname,note,formula\r\nAlice,\"Hello, world\",=1+1\r\nBob,\"line1\nline2\",<script>x</script>\r\n");
    $csv = user_file_preview_csv($row('csv', 'text/csv', filesize($csvPath)), $csvPath);
    $check($csv['header'] === ['name', 'note', 'formula'], 'CSV first record is header');
    $check($csv['rows'][0][1] === 'Hello, world', 'CSV quoted comma stays in one cell');
    $check($csv['rows'][1][1] === "line1\nline2", 'CSV quoted newline stays in one cell');
    $check($csv['rows'][0][2] === '=1+1', 'CSV formula-like value remains text');
    $check($csv['rows'][1][2] === '<script>x</script>', 'CSV HTML-like value remains text');

    $detail = user_file_preview_detail($row('txt', 'text/plain', 3, 'visible-name'), $txtPath);
    $check($detail['filename'] === 'visible-name.txt', 'File Detail exposes original filename');
    $check($detail['preview_kind'] === 'text', 'File Detail exposes preview kind');
    $check(!array_key_exists('file_owner', $detail), 'File Detail omits owner id');
    $check(!array_key_exists('file_stored_name', $detail), 'File Detail omits stored name');
    $check(!array_key_exists('path', $detail), 'File Detail omits filesystem path');
    $check(user_file_preview_kind($row('pdf', 'application/pdf', 10)) === 'pdf', 'PDF preview kind is detected');
    $check(user_file_preview_kind($row('csv', 'text/csv', 10)) === 'csv', 'CSV preview kind is detected');
    $check(user_file_preview_kind($row('zip', 'application/zip', 10)) === 'download', 'ZIP remains download-only');
} finally {
    foreach (glob($tmp . '/*') ?: [] as $file) { @unlink($file); }
    @rmdir($tmp);
}
printf("RESULT: PASS %d / FAIL %d\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
