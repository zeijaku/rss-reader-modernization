<?php

declare(strict_types=1);

function user_file_library_row_type_is_valid(array $row): bool
{
    $allowed = [
        'png' => ['image/png'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'zip' => ['application/zip'],
    ];
    $extension = strtolower((string) ($row['file_extension'] ?? ''));
    $mime = strtolower((string) ($row['file_mime_type'] ?? ''));
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
function check_v128e(bool $condition, string $label): void
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

function csv_row_fixture(): array
{
    return [
        'file_id' => 51,
        'file_owner' => 7,
        'file_original_name' => 'sample.csv',
        'file_stored_name' => str_repeat('a', 64) . '.csv',
        'file_mime_type' => 'text/csv',
        'file_extension' => 'csv',
        'file_size' => 1,
        'file_created_at' => '2026-08-31 08:00:00',
    ];
}

function write_csv_fixture(string $dir, string $name, string $content): array
{
    $path = $dir . '/' . $name;
    file_put_contents($path, $content);
    $row = csv_row_fixture();
    $row['file_size'] = filesize($path);
    return [$row, $path];
}

check_v128e(USER_FILE_CSV_PREVIEW_MAX_BYTES === 524288, 'CSV byte ceiling remains 512 KiB');
check_v128e(USER_FILE_CSV_PREVIEW_MAX_ROWS === 50, 'CSV data row ceiling remains 50');
check_v128e(USER_FILE_CSV_PREVIEW_MAX_COLUMNS === 30, 'CSV column ceiling remains 30');
check_v128e(USER_FILE_CSV_PREVIEW_MAX_RECORD_BYTES === 65536, 'CSV logical record ceiling is 64 KiB');
check_v128e(user_file_preview_kind(csv_row_fixture()) === 'csv', 'validated CSV row is classified as csv preview');

$work = sys_get_temp_dir() . '/rss-v128e-' . bin2hex(random_bytes(4));
mkdir($work, 0700, true);

[$row, $path] = write_csv_fixture($work, 'simple.csv', "Name,Note\nAlice,\"Hello, world\"\nBob,\"Line1\nLine2\"\n");
$preview = user_file_preview_csv($row, $path);
check_v128e($preview['header'] === ['Name', 'Note'], 'first CSV record is the table header');
check_v128e(($preview['rows'][0] ?? null) === ['Alice', 'Hello, world'], 'quoted comma is parsed as one cell');
check_v128e(($preview['rows'][1][1] ?? null) === "Line1\nLine2", 'quoted newline remains inside one CSV cell');
check_v128e($preview['row_count'] === 2, 'simple CSV exposes two data rows');
check_v128e($preview['column_count'] === 2, 'simple CSV exposes two columns');
check_v128e($preview['truncated'] === false, 'small CSV is not marked truncated');

[$row, $path] = write_csv_fixture($work, 'utf8.csv', "\xEF\xBB\xBF\u{540D}\u{524D},\u{5024}\r\n\u{592A}\u{90CE},<script>alert(1)</script>\r\n\u{6B21}\u{90CE},=SUM(A1:A2)\r\n");
$preview = user_file_preview_csv($row, $path);
check_v128e($preview['header'][0] === "\u{540D}\u{524D}", 'UTF-8 BOM is stripped from first header cell');
check_v128e(($preview['rows'][0][0] ?? null) === "\u{592A}\u{90CE}", 'UTF-8 Japanese CSV is preserved');
check_v128e(($preview['rows'][0][1] ?? null) === '<script>alert(1)</script>', 'HTML-looking CSV cell is returned literally');
check_v128e(($preview['rows'][1][1] ?? null) === '=SUM(A1:A2)', 'formula-looking CSV cell is not evaluated or altered');

$header = implode(',', array_map(static fn(int $i): string => 'H' . $i, range(1, 35)));
$data = implode(',', array_map(static fn(int $i): string => 'V' . $i, range(1, 35)));
[$row, $path] = write_csv_fixture($work, 'wide.csv', $header . "\n" . $data . "\n");
$preview = user_file_preview_csv($row, $path);
check_v128e($preview['column_count'] === 30, 'CSV preview caps columns at 30');
check_v128e(count($preview['header']) === 30 && count($preview['rows'][0]) === 30, 'header and data rows are sliced to 30 columns');
check_v128e($preview['truncated_by_columns'] === true && $preview['truncated'] === true, 'column overflow is reported as truncation');

$rows = ["id,value"];
for ($i = 1; $i <= 51; $i++) {
    $rows[] = $i . ',row-' . $i;
}
[$row, $path] = write_csv_fixture($work, 'rows.csv', implode("\n", $rows) . "\n");
$preview = user_file_preview_csv($row, $path);
check_v128e($preview['row_count'] === 50, 'CSV preview caps data rows at 50');
check_v128e(($preview['rows'][49][0] ?? null) === '50', '50th CSV data row remains visible');
check_v128e($preview['truncated_by_rows'] === true && $preview['truncated'] === true, 'row overflow is reported as truncation');

$largeRows = ["payload"];
for ($i = 0; $i < 10; $i++) {
    $largeRows[] = str_repeat(chr(65 + $i), 60000);
}
[$row, $path] = write_csv_fixture($work, 'bytes.csv', implode("\n", $largeRows) . "\n");
$preview = user_file_preview_csv($row, $path);
check_v128e($preview['truncated_by_bytes'] === true && $preview['truncated'] === true, 'CSV over 512 KiB is byte-truncated');
check_v128e($preview['row_count'] > 0 && $preview['row_count'] < 10, 'byte truncation keeps only complete bounded rows');
check_v128e(!str_contains(implode('', end($preview['rows'])), "\0"), 'byte-truncated output contains no injected binary terminator');

[$row, $path] = write_csv_fixture($work, 'record.csv', "payload\n" . str_repeat('Z', 70000) . "\n");
try {
    user_file_preview_csv($row, $path);
    check_v128e(false, 'CSV logical record over 64 KiB is rejected');
} catch (UserFilePreviewException $exception) {
    check_v128e($exception->errorCode === 'preview_record_too_large', 'CSV logical record over 64 KiB is rejected');
}

[$row, $path] = write_csv_fixture($work, 'invalid-utf8.csv', "name,value\nA,\xFF\n");
try {
    user_file_preview_csv($row, $path);
    check_v128e(false, 'invalid UTF-8 CSV is rejected');
} catch (UserFilePreviewException $exception) {
    check_v128e($exception->errorCode === 'preview_encoding_unsupported', 'invalid UTF-8 CSV is rejected');
}

[$row, $path] = write_csv_fixture($work, 'nul.csv', "name,value\nA,one\0two\n");
try {
    user_file_preview_csv($row, $path);
    check_v128e(false, 'NUL-containing CSV prefix is rejected');
} catch (UserFilePreviewException $exception) {
    check_v128e($exception->errorCode === 'preview_encoding_unsupported', 'NUL-containing CSV prefix is rejected');
}

$wrong = csv_row_fixture();
$wrong['file_extension'] = 'txt';
$wrong['file_mime_type'] = 'text/plain';
[$unused, $wrongPath] = write_csv_fixture($work, 'wrong.txt', "a,b\n1,2\n");
try {
    user_file_preview_csv($wrong, $wrongPath);
    check_v128e(false, 'non-CSV metadata cannot enter CSV preview helper');
} catch (UserFilePreviewException $exception) {
    check_v128e($exception->errorCode === 'preview_type_not_supported', 'non-CSV metadata cannot enter CSV preview helper');
}

foreach (glob($work . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($work);

printf("RESULT: PASS %d / FAIL %d\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
