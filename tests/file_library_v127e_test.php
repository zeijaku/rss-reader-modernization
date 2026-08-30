<?php

declare(strict_types=1);

function app_env(string $name, ?string $default = null): ?string { return $default; }
function app_now(): string { return '2026-08-30 19:00:00'; }
define('DB_TABLE_PREFIX', 'test_');

final class LibraryFakeStatement
{
    private mixed $result = null;
    private int $affected = 0;
    public function __construct(private LibraryFakeDb $db, private string $sql) {}
    public function execute(array $params): bool
    {
        $owner = isset($params[':owner']) ? (int) $params[':owner'] : 0;
        if (str_starts_with($this->sql, 'SELECT COUNT(*)')) {
            $this->result = count(array_filter($this->db->rows, static fn(array $row): bool => (int) $row['file_owner'] === $owner && (int) $row['file_flag'] === 0));
            return true;
        }
        if (str_contains($this->sql, 'ORDER BY file_id DESC LIMIT')) {
            preg_match('/LIMIT ([0-9]+) OFFSET ([0-9]+)/', $this->sql, $m);
            $limit = isset($m[1]) ? (int) $m[1] : 24;
            $offset = isset($m[2]) ? (int) $m[2] : 0;
            $rows = array_values(array_filter($this->db->rows, static fn(array $row): bool => (int) $row['file_owner'] === $owner && (int) $row['file_flag'] === 0));
            usort($rows, static fn(array $a, array $b): int => (int) $b['file_id'] <=> (int) $a['file_id']);
            $this->result = array_slice($rows, $offset, $limit);
            return true;
        }
        if (str_contains($this->sql, 'WHERE file_id = :file_id AND file_owner = :owner AND file_flag = 0 LIMIT 1')) {
            $id = (int) ($params[':file_id'] ?? 0);
            $this->result = null;
            foreach ($this->db->rows as $row) {
                if ((int) $row['file_id'] === $id && (int) $row['file_owner'] === $owner && (int) $row['file_flag'] === 0) {
                    $this->result = $row;
                    break;
                }
            }
            return true;
        }
        if (str_starts_with($this->sql, 'UPDATE ')) {
            $id = (int) ($params[':file_id'] ?? 0);
            $this->affected = 0;
            foreach ($this->db->rows as &$row) {
                if ((int) $row['file_id'] === $id && (int) $row['file_owner'] === $owner && (int) $row['file_flag'] === 0) {
                    $row['file_flag'] = 1;
                    $this->affected = 1;
                    break;
                }
            }
            unset($row);
            return true;
        }
        throw new RuntimeException('Unexpected SQL: ' . $this->sql);
    }
    public function fetchColumn(): mixed { return $this->result; }
    public function fetchAll(): array { return is_array($this->result) ? $this->result : []; }
    public function fetch(): mixed { return $this->result ?? false; }
    public function rowCount(): int { return $this->affected; }
}
final class LibraryFakeDb
{
    /** @var list<array<string,mixed>> */
    public array $rows = [];
    public function prepare(string $sql): LibraryFakeStatement { return new LibraryFakeStatement($this, $sql); }
}
$GLOBALS['library_fake_db'] = new LibraryFakeDb();
function conn_db(): LibraryFakeDb { return $GLOBALS['library_fake_db']; }

require_once dirname(__DIR__) . '/app/user_file.php';
require_once dirname(__DIR__) . '/app/file_library.php';

$pass = 0;
$fail = 0;
function check(bool $condition, string $label): void
{
    global $pass, $fail;
    if ($condition) { $pass++; echo "PASS: {$label}\n"; }
    else { $fail++; echo "FAIL: {$label}\n"; }
}

function row_fixture(int $id, int $owner, string $ext = 'png', string $mime = 'image/png', int $flag = 0): array
{
    return [
        'file_id' => $id,
        'file_owner' => $owner,
        'file_original_name' => 'file-' . $id . '.' . $ext,
        'file_stored_name' => str_pad(dechex($id), 64, 'a', STR_PAD_LEFT) . '.' . $ext,
        'file_mime_type' => $mime,
        'file_extension' => $ext,
        'file_size' => 68,
        'file_created_at' => '2026-08-30 19:00:00',
        'file_flag' => $flag,
    ];
}

check(USER_FILE_LIBRARY_PAGE_SIZE === 24, 'File Library page size is fixed at 24');
check(user_file_library_page_size() === 24, 'page size helper returns 24');

$db = $GLOBALS['library_fake_db'];
for ($i = 1; $i <= 30; $i++) { $db->rows[] = row_fixture($i, 1); }
$db->rows[] = row_fixture(31, 2);
$db->rows[] = row_fixture(32, 1, 'png', 'image/png', 1);

check(user_file_library_count(1) === 30, 'count is owner scoped and excludes deleted rows');
check(user_file_library_count(2) === 1, 'second owner count is isolated');
check(user_file_library_count(0) === 0, 'invalid owner count fails closed');
$page1 = user_file_library_list(1, 1);
check(count($page1) === 24, 'first page returns 24 rows');
check((int) $page1[0]['file_id'] === 30 && (int) $page1[23]['file_id'] === 7, 'first page is newest-first');
$page2 = user_file_library_list(1, 2);
check(count($page2) === 6, 'second page returns remaining rows');
check((int) $page2[0]['file_id'] === 6 && (int) $page2[5]['file_id'] === 1, 'second page offset is correct');
check(count(user_file_library_list(1, 0)) === 24, 'page below 1 is clamped to first page');
check(count(user_file_library_list(1, 1, 999)) === 24, 'caller cannot raise page size above 24');
check(user_file_library_list(0, 1) === [], 'invalid owner list fails closed');

$owned = user_file_library_find_owned(1, 10);
check(is_array($owned) && (int) $owned['file_id'] === 10, 'owned active file can be found');
check(user_file_library_find_owned(2, 10) === null, 'cross-owner lookup returns not found');
check(user_file_library_find_owned(1, 32) === null, 'soft-deleted file returns not found');
check(user_file_library_find_owned(1, 0) === null, 'invalid file id fails closed');

$imageRow = row_fixture(100, 1);
$pdfRow = row_fixture(101, 1, 'pdf', 'application/pdf');
$badMime = row_fixture(102, 1, 'png', 'text/html');
$badExt = row_fixture(103, 1, 'exe', 'application/octet-stream');
check(user_file_library_row_type_is_valid($imageRow), 'stored image type must match server allowlist');
check(user_file_library_row_type_is_valid($pdfRow), 'stored PDF type must match server allowlist');
check(!user_file_library_row_type_is_valid($badMime), 'tampered MIME metadata is rejected');
check(!user_file_library_row_type_is_valid($badExt), 'unknown stored extension is rejected');
check(user_file_library_is_inline_image($imageRow), 'safe image is eligible for inline view');
check(!user_file_library_is_inline_image($pdfRow), 'PDF is download-only in V1.27-E');

$storage = (string) APP_FILE_UPLOAD_DIR;
@mkdir($storage, 0750, true);
$pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z1xkAAAAASUVORK5CYII=', true);
$storedName = str_repeat('b', 64) . '.png';
$storedPath = $storage . DIRECTORY_SEPARATOR . $storedName;
file_put_contents($storedPath, $pngBytes);
$physicalRow = [
    'file_id' => 200,
    'file_owner' => 1,
    'file_original_name' => '写真.png',
    'file_stored_name' => $storedName,
    'file_mime_type' => 'image/png',
    'file_extension' => 'png',
    'file_size' => strlen($pngBytes),
    'file_created_at' => '2026-08-30 19:00:00',
    'file_flag' => 0,
];
check(user_file_library_storage_directory() === realpath($storage), 'private upload directory resolves for read operations');
check(user_file_library_resolve_path($physicalRow) === realpath($storedPath), 'stored random name resolves only inside private storage');
$traversalRow = $physicalRow; $traversalRow['file_stored_name'] = '../' . $storedName;
check(user_file_library_resolve_path($traversalRow) === null, 'stored-name path traversal is rejected');
$mismatchRow = $physicalRow; $mismatchRow['file_extension'] = 'jpg';
check(user_file_library_resolve_path($mismatchRow) === null, 'stored filename extension mismatch is rejected');
check(user_file_library_content_is_intact($physicalRow, $storedPath), 'untampered image content passes serve-time validation');
$wrongSize = $physicalRow; $wrongSize['file_size'] = strlen($pngBytes) + 1;
check(!user_file_library_content_is_intact($wrongSize, $storedPath), 'serve-time metadata size mismatch is rejected');
$wrongMime = $physicalRow; $wrongMime['file_mime_type'] = 'image/jpeg';
check(!user_file_library_content_is_intact($wrongMime, $storedPath), 'serve-time MIME mismatch is rejected');

$db->rows[] = $physicalRow;
check(user_file_library_delete_owned(2, 200) === false, 'cross-owner delete is rejected');
check(is_file($storedPath), 'cross-owner delete does not remove physical file');
check(user_file_library_delete_owned(1, 200) === true, 'owner can soft-delete own file');
check(!is_file($storedPath), 'successful owner delete removes physical file when possible');
check(user_file_library_find_owned(1, 200) === null, 'deleted row is no longer accessible');

$fallback = user_file_library_fallback_filename("日本語 改行\r\n.png", 'png');
check(!str_contains($fallback, "\r") && !str_contains($fallback, "\n") && !str_contains($fallback, '"'), 'fallback filename cannot inject response headers');
$inline = user_file_library_content_disposition($physicalRow, true);
$attachment = user_file_library_content_disposition($physicalRow, false);
check(str_starts_with($inline, 'inline; filename='), 'inline image disposition is explicit');
check(str_starts_with($attachment, 'attachment; filename='), 'download disposition is explicit attachment');
check(str_contains($inline, "filename*=UTF-8''%E5%86%99%E7%9C%9F.png"), 'UTF-8 original filename is RFC5987 encoded');
check(user_file_library_format_bytes(512) === '512 B', 'byte formatter handles bytes');
check(user_file_library_format_bytes(1536) === '1.5 KiB', 'byte formatter handles KiB');
check(user_file_library_format_bytes(1572864) === '1.5 MiB', 'byte formatter handles MiB');

$_SERVER['CONTENT_LENGTH'] = '12345';
check(user_file_library_request_content_length() === 12345, 'numeric request content length is accepted');
$_SERVER['CONTENT_LENGTH'] = '-1';
check(user_file_library_request_content_length() === null, 'negative request content length is rejected');
$_SERVER['CONTENT_LENGTH'] = '12x';
check(user_file_library_request_content_length() === null, 'malformed request content length is rejected');
unset($_SERVER['CONTENT_LENGTH']);
check(user_file_library_request_content_length() === null, 'missing request content length is allowed');

@unlink($storedPath);

echo "SUMMARY: {$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
