<?php

declare(strict_types=1);

const APP_REMOTE_EDITOR_MAX_BYTES = 64;

final class AppRemoteValidationException extends InvalidArgumentException
{
    public function __construct(private readonly string $reason)
    {
        parent::__construct('validation failed');
    }

    public function reason(): string
    {
        return $this->reason;
    }
}

final class AppRemoteTransportException extends RuntimeException
{
    public function __construct(public readonly string $errorCode)
    {
        parent::__construct('transport failed');
    }
}

function remote_path_normalize_relative(mixed $value): ?string
{
    if (!is_string($value) || $value === '' || str_contains($value, "\0") || str_contains($value, '\\')) {
        return null;
    }
    if ($value[0] !== '/') {
        $value = '/' . $value;
    }
    $segments = explode('/', $value);
    $out = [];
    foreach ($segments as $segment) {
        if ($segment === '') {
            continue;
        }
        if ($segment === '.' || $segment === '..') {
            return null;
        }
        $out[] = $segment;
    }
    return '/' . implode('/', $out);
}

function remote_path_basename(string $path): string
{
    $parts = explode('/', trim($path, '/'));
    return (string) end($parts);
}

$GLOBALS['REMOTE_EDITOR_TEST_BYTES'] = '';
$GLOBALS['REMOTE_EDITOR_DOWNLOAD_ARGS'] = null;
$GLOBALS['REMOTE_EDITOR_LAST_TEMP'] = null;

function remote_service_download_temp(int $ownerId, int $connectionId, string $relativePath, int $maxBytes): string
{
    $GLOBALS['REMOTE_EDITOR_DOWNLOAD_ARGS'] = [$ownerId, $connectionId, $relativePath, $maxBytes];
    $bytes = (string) $GLOBALS['REMOTE_EDITOR_TEST_BYTES'];
    if (strlen($bytes) > $maxBytes) {
        throw new AppRemoteTransportException('transfer_too_large');
    }
    $path = tempnam(sys_get_temp_dir(), 'v130b-');
    if (!is_string($path)) {
        throw new RuntimeException('temp failed');
    }
    file_put_contents($path, $bytes);
    $GLOBALS['REMOTE_EDITOR_LAST_TEMP'] = $path;
    return $path;
}

require_once dirname(__DIR__) . '/app/remote_file/remote_editor.php';

$checks = 0;

function ok(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    $checks++;
    echo "PASS: {$message}\n";
}

function expect_editor_error(string $code, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (AppRemoteEditorException $exception) {
        ok($exception->errorCode === $code, $message . ' (' . $code . ')');
        return;
    }
    ok(false, $message . ' (exception expected)');
}

$allowed = ['txt','md','csv','json','xml','html','htm','css','js','php','ini','conf','yml','yaml'];
foreach ($allowed as $extension) {
    $info = remote_editor_path_info('/folder/example.' . $extension);
    ok($info['extension'] === $extension, 'allows .' . $extension);
}
$upper = remote_editor_path_info('/folder/Example.PHP');
ok($upper['extension'] === 'php', 'extension comparison is case-insensitive');
expect_editor_error('editor_type_unsupported', fn() => remote_editor_path_info('/folder/archive.zip'), 'rejects ZIP extension');
expect_editor_error('editor_type_unsupported', fn() => remote_editor_path_info('/folder/.env'), 'rejects dotfile without an allowed suffix');
expect_editor_error('editor_type_unsupported', fn() => remote_editor_path_info('/folder/README'), 'rejects extensionless file');

$lf = remote_editor_inspect_bytes('/notes/test.txt', "alpha\nbeta\n");
ok($lf['line_ending'] === 'lf', 'detects LF');
ok($lf['text'] === "alpha\nbeta\n", 'returns LF text unchanged');
ok($lf['sha256'] === hash('sha256', "alpha\nbeta\n"), 'hash covers exact raw LF bytes');

$crlfBytes = "alpha\r\nbeta\r\n";
$crlf = remote_editor_inspect_bytes('/notes/test.txt', $crlfBytes);
ok($crlf['line_ending'] === 'crlf', 'detects CRLF');
ok($crlf['sha256'] === hash('sha256', $crlfBytes), 'hash covers exact raw CRLF bytes');

$plain = remote_editor_inspect_bytes('/notes/one-line.md', 'single line');
ok($plain['line_ending'] === 'none', 'accepts text without line endings');

$empty = remote_editor_inspect_bytes('/notes/empty.txt', '');
ok($empty['byte_size'] === 0, 'accepts empty remote text on read');
ok($empty['sha256'] === hash('sha256', ''), 'hashes empty content');

$bomBytes = "\xEF\xBB\xBFhello\n";
$bom = remote_editor_inspect_bytes('/notes/bom.txt', $bomBytes);
ok($bom['utf8_bom'] === true, 'detects UTF-8 BOM');
ok($bom['text'] === "hello\n", 'strips BOM from browser text value');
ok($bom['sha256'] === hash('sha256', $bomBytes), 'hash includes BOM for conflict state');

expect_editor_error('editor_line_endings_unsupported', fn() => remote_editor_inspect_bytes('/notes/mixed.txt', "a\r\nb\n"), 'rejects mixed line endings');
expect_editor_error('editor_line_endings_unsupported', fn() => remote_editor_inspect_bytes('/notes/cr.txt', "a\rb\r"), 'rejects CR-only line endings');
expect_editor_error('editor_binary_unsupported', fn() => remote_editor_inspect_bytes('/notes/nul.txt', "a\0b"), 'rejects NUL byte');
expect_editor_error('editor_binary_unsupported', fn() => remote_editor_inspect_bytes('/notes/control.txt', "a\x01b"), 'rejects unsafe C0 control byte');
expect_editor_error('editor_binary_unsupported', fn() => remote_editor_inspect_bytes('/notes/c1.txt', "a\xC2\x80b"), 'rejects unsafe C1 control code point');
expect_editor_error('editor_encoding_unsupported', fn() => remote_editor_inspect_bytes('/notes/bad.txt', "\xC3\x28"), 'rejects invalid UTF-8');
expect_editor_error('editor_too_large', fn() => remote_editor_inspect_bytes('/notes/large.txt', str_repeat('a', APP_REMOTE_EDITOR_MAX_BYTES + 1)), 'rejects content above editor byte ceiling');
$exact = remote_editor_inspect_bytes('/notes/exact.txt', str_repeat('a', APP_REMOTE_EDITOR_MAX_BYTES));
ok($exact['byte_size'] === APP_REMOTE_EDITOR_MAX_BYTES, 'accepts content exactly at editor byte ceiling');

$GLOBALS['REMOTE_EDITOR_TEST_BYTES'] = "remote\r\ntext\r\n";
$read = remote_editor_read(7, 11, '/folder/readme.md');
ok($read['text'] === "remote\r\ntext\r\n", 'read helper returns inspected remote text');
ok($GLOBALS['REMOTE_EDITOR_DOWNLOAD_ARGS'] === [7, 11, '/folder/readme.md', APP_REMOTE_EDITOR_MAX_BYTES], 'read helper uses bounded shared download service');
ok(is_string($GLOBALS['REMOTE_EDITOR_LAST_TEMP']) && !file_exists($GLOBALS['REMOTE_EDITOR_LAST_TEMP']), 'read helper removes private temp file before return');

$GLOBALS['REMOTE_EDITOR_TEST_BYTES'] = str_repeat('z', APP_REMOTE_EDITOR_MAX_BYTES + 1);
try {
    remote_editor_read(7, 11, '/folder/too-large.txt');
    ok(false, 'oversized transfer should fail');
} catch (AppRemoteTransportException $exception) {
    ok($exception->errorCode === 'transfer_too_large', 'shared bounded downloader rejects oversized remote content');
}

try {
    remote_editor_read(7, 11, '/folder/file.zip');
    ok(false, 'unsupported extension should fail before download');
} catch (AppRemoteEditorException $exception) {
    ok($exception->errorCode === 'editor_type_unsupported', 'unsupported extension is rejected before remote download');
    ok($GLOBALS['REMOTE_EDITOR_DOWNLOAD_ARGS'] === [7, 11, '/folder/too-large.txt', APP_REMOTE_EDITOR_MAX_BYTES], 'unsupported extension does not invoke remote download');
}

echo "RESULT: PASS {$checks} / FAIL 0 / SKIP 0\n";
