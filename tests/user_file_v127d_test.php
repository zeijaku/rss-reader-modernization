<?php

declare(strict_types=1);

function app_env(string $name, ?string $default = null): ?string
{
    return $default;
}
function app_now(): string
{
    return '2026-08-30 16:00:00';
}
define('DB_TABLE_PREFIX', 'test_');

final class FakeStatement
{
    public function __construct(private FakeDb $db, private bool $fail = false) {}
    public function execute(array $params): bool
    {
        if ($this->fail) {
            throw new RuntimeException('simulated database failure');
        }
        $this->db->lastParams = $params;
        return true;
    }
}
final class FakeDb
{
    public string $lastSql = '';
    public array $lastParams = [];
    public string $lastId = '321';
    public bool $fail = false;
    public function prepare(string $sql): FakeStatement
    {
        $this->lastSql = $sql;
        return new FakeStatement($this, $this->fail);
    }
    public function lastInsertId(): string
    {
        return $this->lastId;
    }
}
$GLOBALS['fake_db'] = new FakeDb();
function conn_db(): FakeDb
{
    return $GLOBALS['fake_db'];
}

require_once dirname(__DIR__) . '/app/user_file.php';

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
function expect_upload_error(callable $callback, string $code, int $status, string $label): void
{
    try {
        $callback();
        check(false, $label);
    } catch (UserFileUploadException $e) {
        check($e->errorCode === $code && $e->httpStatus === $status, $label);
    }
}
function upload_array(string $name, string $path, int $error = UPLOAD_ERR_OK): array
{
    return [
        'name' => $name,
        'type' => 'application/octet-stream', // Intentionally untrusted/ignored.
        'tmp_name' => $path,
        'error' => $error,
        'size' => is_file($path) ? filesize($path) : 0,
    ];
}

$work = sys_get_temp_dir() . '/rss-v127d-' . bin2hex(random_bytes(4));
mkdir($work, 0700, true);

$png = $work . '/fixture.png';
file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z1xkAAAAASUVORK5CYII=', true));
$jpeg = $work . '/fixture.jpg';
file_put_contents($jpeg, base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAACAAIDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwDi6KKK+ZP3E//Z', true));
$gif = $work . '/fixture.gif';
file_put_contents($gif, base64_decode('R0lGODdhAgACAIEAAP8AAAAAAAAAAAAAACwAAAAAAgACAAAIBgABCAQQEAA7', true));
$webp = $work . '/fixture.webp';
file_put_contents($webp, base64_decode('UklGRjwAAABXRUJQVlA4IDAAAADQAQCdASoCAAIAAUAmJaACdLoB+AADsAD+8ut//NgVzXPv9//S4P0uD9Lg/9KQAAA=', true));
$pdf = $work . '/fixture.pdf';
file_put_contents($pdf, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");
$txt = $work . '/fixture.txt';
file_put_contents($txt, "safe plain text\nsecond line\n");
$csv = $work . '/fixture.csv';
file_put_contents($csv, "name,value\nalpha,1\n");
$zip = $work . '/fixture.zip';
file_put_contents($zip, "PK\x05\x06" . str_repeat("\x00", 18));
$html = $work . '/fixture.htmlish';
file_put_contents($html, "<!doctype html><script>alert(1)</script>");
$binary = $work . '/fixture.bin';
file_put_contents($binary, "abc\0def");

check(APP_FILE_UPLOAD_MAX_BYTES === 10485760, 'default per-file limit is 10 MiB');
check(APP_FILE_UPLOAD_MAX_REQUEST_BYTES > APP_FILE_UPLOAD_MAX_BYTES, 'request limit includes multipart overhead');
check(str_ends_with(str_replace('\\', '/', (string) APP_FILE_UPLOAD_DIR), '/var/uploads'), 'default storage is private var/uploads');

check(user_file_normalize_original_name('C:\\fakepath\\写真.png') === '写真.png', 'browser fake path is reduced to basename');
check(user_file_normalize_original_name("bad\0name.png") === null, 'NUL in original name is rejected');
check(user_file_normalize_original_name("bad\nname.png") === null, 'control character in original name is rejected');
check(user_file_extension_from_name('PHOTO.JPG') === 'jpg', 'allowed extension matching is case-insensitive');
check(user_file_extension_from_name('payload.exe') === null, 'executable extension is rejected');
check(user_file_extension_from_name('vector.svg') === null, 'SVG is not allowlisted');
check(user_file_has_dangerous_double_extension('avatar.php.png'), 'PHP double extension is rejected');
check(user_file_has_dangerous_double_extension('vector.svg.png'), 'SVG double extension is rejected');
check(!user_file_has_dangerous_double_extension('holiday.2026.photo.png'), 'normal multi-dot filename remains allowed');

check(user_file_detect_mime($png) === 'image/png', 'PNG MIME comes from server-side Fileinfo');
check(user_file_validate_image_content($png, 'image/png'), 'PNG passes structural image validation');
check(user_file_detect_mime($jpeg) === 'image/jpeg' && user_file_validate_image_content($jpeg, 'image/jpeg'), 'JPEG passes MIME and structural validation');
check(user_file_detect_mime($gif) === 'image/gif' && user_file_validate_image_content($gif, 'image/gif'), 'GIF passes MIME and structural validation');
check(user_file_detect_mime($webp) === 'image/webp' && user_file_validate_image_content($webp, 'image/webp'), 'WebP passes MIME and structural validation');
check(user_file_detect_mime($pdf) === 'application/pdf', 'PDF MIME comes from server-side Fileinfo');
check(user_file_validate_non_image_content($pdf, 'pdf'), 'PDF signature is verified');
check(user_file_validate_non_image_content($txt, 'txt'), 'plain text without NUL is accepted');
check(user_file_validate_non_image_content($csv, 'csv'), 'CSV text without NUL is accepted');
check(user_file_detect_mime($zip) === 'application/zip', 'ZIP MIME comes from server-side Fileinfo');
check(user_file_validate_non_image_content($zip, 'zip'), 'ZIP signature is verified');
check(!user_file_validate_non_image_content($binary, 'txt'), 'binary NUL content is rejected for text');

$validZip = user_file_validate_upload(upload_array('archive.zip', $zip), static fn(string $path): bool => true);
check($validZip['mime_type'] === 'application/zip' && $validZip['extension'] === 'zip', 'valid ZIP upload is accepted');

$valid = user_file_validate_upload(upload_array('photo.png', $png), static fn(string $path): bool => true);
check($valid['mime_type'] === 'image/png' && $valid['extension'] === 'png', 'valid PNG upload is accepted');
check($valid['file_size'] === filesize($png), 'actual temporary file size is authoritative');

expect_upload_error(
    static fn() => user_file_validate_upload(upload_array('photo.jpg', $png), static fn(string $path): bool => true),
    'mime_mismatch',
    422,
    'extension/MIME mismatch is rejected'
);
expect_upload_error(
    static fn() => user_file_validate_upload(upload_array('shell.php.png', $png), static fn(string $path): bool => true),
    'file_type_blocked',
    422,
    'dangerous double extension is rejected before storage'
);
expect_upload_error(
    static fn() => user_file_validate_upload(upload_array('page.txt', $html), static fn(string $path): bool => true),
    'mime_mismatch',
    422,
    'HTML disguised as TXT is rejected by Fileinfo'
);
expect_upload_error(
    static fn() => user_file_validate_upload(upload_array('vector.svg', $txt), static fn(string $path): bool => true),
    'file_type_blocked',
    422,
    'non-allowlisted SVG upload is rejected'
);
expect_upload_error(
    static fn() => user_file_validate_upload(upload_array('photo.png', $png), static fn(string $path): bool => false),
    'upload_invalid',
    400,
    'non-HTTP temporary file is rejected by production-style upload check'
);
expect_upload_error(
    static fn() => user_file_validate_upload(upload_array('photo.png', $png, UPLOAD_ERR_NO_FILE), static fn(string $path): bool => true),
    'file_required',
    422,
    'UPLOAD_ERR_NO_FILE is mapped safely'
);
expect_upload_error(
    static fn() => user_file_validate_upload(upload_array('photo.png', $png, UPLOAD_ERR_INI_SIZE), static fn(string $path): bool => true),
    'file_too_large',
    413,
    'server upload size rejection maps to 413'
);

$limitZip = $work . '/limit.zip';
$fh = fopen($limitZip, 'wb');
fwrite($fh, "PK\x03\x04");
ftruncate($fh, APP_FILE_UPLOAD_MAX_BYTES);
fclose($fh);
$limitValid = user_file_validate_upload(upload_array('limit.zip', $limitZip), static fn(string $path): bool => true);
check($limitValid['file_size'] === APP_FILE_UPLOAD_MAX_BYTES, 'file exactly at 10 MiB limit is accepted');

$oversize = $work . '/oversize.bin';
$fh = fopen($oversize, 'wb');
ftruncate($fh, APP_FILE_UPLOAD_MAX_BYTES + 1);
fclose($fh);
expect_upload_error(
    static fn() => user_file_validate_upload(upload_array('large.txt', $oversize), static fn(string $path): bool => true),
    'file_too_large',
    413,
    'actual file larger than application limit is rejected'
);

check(user_file_path_is_within('/srv/app/public/uploads', '/srv/app/public'), 'public child path is detected');
check(user_file_path_is_within('/srv/app/public', '/srv/app/public'), 'public root itself is detected');
check(!user_file_path_is_within('/srv/app/var/uploads', '/srv/app/public'), 'private var path is outside public');

$storageDir = user_file_storage_directory();
check(is_dir($storageDir) && is_writable($storageDir), 'private storage directory is available');
$name1 = user_file_generate_stored_name('png', $storageDir);
$name2 = user_file_generate_stored_name('png', $storageDir);
check((bool) preg_match('/^[a-f0-9]{64}\.png$/', $name1), 'physical filename is 256-bit random hex plus safe extension');
check($name1 !== $name2, 'generated physical names differ');
check(!str_contains($name1, 'photo'), 'original filename is not embedded in physical filename');

$copyForStore = $work . '/store.png';
copy($png, $copyForStore);
$GLOBALS['fake_db'] = new FakeDb();
$before = glob($storageDir . '/*') ?: [];
$result = user_file_store_upload(
    42,
    upload_array('my-photo.png', $copyForStore),
    static fn(string $path): bool => true,
    static fn(string $from, string $to): bool => rename($from, $to)
);
$after = glob($storageDir . '/*') ?: [];
check(count($after) === count($before) + 1, 'successful upload moves one file into private storage');
check($result['file_id'] === 321 && $result['original_name'] === 'my-photo.png', 'DB metadata result keeps public metadata');
check(!array_key_exists('stored_name', $result) && !array_key_exists('path', $result), 'public upload result hides stored name and physical path');
check(str_contains($GLOBALS['fake_db']->lastSql, '`test_user_file`'), 'metadata insert uses prefixed user_file table');
check(($GLOBALS['fake_db']->lastParams[':owner'] ?? null) === 42, 'authenticated owner is bound into metadata insert');
check((bool) preg_match('/^[a-f0-9]{64}\.png$/', (string) ($GLOBALS['fake_db']->lastParams[':stored_name'] ?? '')), 'DB receives random stored filename');
check(($GLOBALS['fake_db']->lastParams[':original_name'] ?? null) === 'my-photo.png', 'original filename is metadata only');

foreach (array_diff($after, $before) as $created) {
    @unlink($created);
}

$copyForFailure = $work . '/rollback.png';
copy($png, $copyForFailure);
$failingDb = new FakeDb();
$failingDb->fail = true;
$GLOBALS['fake_db'] = $failingDb;
$beforeFailure = glob($storageDir . '/*') ?: [];
try {
    user_file_store_upload(
        42,
        upload_array('rollback.png', $copyForFailure),
        static fn(string $path): bool => true,
        static fn(string $from, string $to): bool => rename($from, $to)
    );
    check(false, 'DB failure triggers storage rollback');
} catch (RuntimeException $e) {
    $afterFailure = glob($storageDir . '/*') ?: [];
    check(count($afterFailure) === count($beforeFailure), 'DB failure triggers storage rollback');
}

check(user_file_table_identifier() === '`test_user_file`', 'user_file table identifier is safely derived from validated prefix');

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($work, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($iterator as $item) {
    if ($item->isDir()) { @rmdir($item->getPathname()); }
    else { @unlink($item->getPathname()); }
}
@rmdir($work);

echo "RESULT: {$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
