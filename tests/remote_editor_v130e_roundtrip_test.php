<?php

declare(strict_types=1);

const APP_REMOTE_EDITOR_MAX_BYTES = 256;

final class AppRemoteValidationException extends InvalidArgumentException
{
    public function __construct(private readonly string $reasonCode)
    {
        parent::__construct('validation');
    }
    public function reason(): string { return $this->reasonCode; }
}

function remote_path_normalize_relative(mixed $value): ?string
{
    if (!is_string($value) || $value === '' || str_contains($value, "\0") || str_contains($value, '\\')) {
        return null;
    }
    if (!str_starts_with($value, '/')) { $value = '/' . $value; }
    $parts = [];
    foreach (explode('/', $value) as $part) {
        if ($part === '') { continue; }
        if ($part === '.' || $part === '..' || strlen($part) > 255) { return null; }
        $parts[] = $part;
    }
    return $parts === [] ? '/' : '/' . implode('/', $parts);
}

function remote_path_basename(string $path): string
{
    $normalized = remote_path_normalize_relative($path);
    if ($normalized === null || $normalized === '/') { return ''; }
    return basename($normalized);
}

function remote_path_parent(string $path): string
{
    $normalized = remote_path_normalize_relative($path);
    if ($normalized === null || $normalized === '/') { return '/'; }
    $parent = dirname($normalized);
    return $parent === '.' || $parent === '\\' ? '/' : $parent;
}

function remote_path_child(string $parent, string $name): ?string
{
    $parent = remote_path_normalize_relative($parent);
    if ($parent === null || $name === '' || str_contains($name, '/') || str_contains($name, '\\')) { return null; }
    if ($name === '.' || $name === '..') { return null; }
    return $parent === '/' ? '/' . $name : $parent . '/' . $name;
}

require_once __DIR__ . '/../app/remote_file/remote_editor.php';

$pass = 0;
$fail = 0;

function check_e(bool $condition, string $label): void
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

function expect_editor_error_e(callable $fn, string $code, int $status, string $label): void
{
    try {
        $fn();
        check_e(false, $label);
    } catch (AppRemoteEditorException $exception) {
        check_e($exception->errorCode === $code && $exception->httpStatus === $status, $label);
    } catch (Throwable) {
        check_e(false, $label);
    }
}

/**
 * @return array{current:array<string,mixed>,bytes:string,saved:array<string,mixed>}
 */
function roundtrip_e(string $sourceBytes, string $browserText): array
{
    $current = remote_editor_inspect_bytes('/safe/test.txt', $sourceBytes);
    $bytes = remote_editor_build_save_bytes($browserText, $current);
    $saved = remote_editor_inspect_bytes('/safe/test.txt', $bytes);
    return ['current' => $current, 'bytes' => $bytes, 'saved' => $saved];
}

// LF: trailing newline and no trailing newline remain exact.
$r = roundtrip_e("a\nb\n", "x\ny\n");
check_e($r['bytes'] === "x\ny\n", 'LF source with trailing newline stays LF with trailing newline');
check_e($r['saved']['line_ending'] === 'lf', 'LF trailing save reports LF metadata');

$r = roundtrip_e("a\nb", "x\ny");
check_e($r['bytes'] === "x\ny", 'LF source without trailing newline stays without trailing newline');
check_e(!str_ends_with($r['bytes'], "\n"), 'LF no-trailing save does not invent a trailing newline');

// CRLF: browser LF is reconstructed, including trailing-newline choices.
$r = roundtrip_e("a\r\nb\r\n", "x\ny\n");
check_e($r['bytes'] === "x\r\ny\r\n", 'CRLF source reconstructs CRLF with trailing newline');
check_e($r['saved']['line_ending'] === 'crlf', 'CRLF trailing save reports CRLF metadata');

$r = roundtrip_e("a\r\nb", "x\ny");
check_e($r['bytes'] === "x\r\ny", 'CRLF source without trailing newline remains without trailing newline');
check_e(!str_ends_with($r['bytes'], "\r\n"), 'CRLF no-trailing save does not invent a trailing newline');

// Removing / introducing newlines is deterministic.
$r = roundtrip_e("a\r\nb\r\n", 'single');
check_e($r['bytes'] === 'single' && $r['saved']['line_ending'] === 'none', 'removing every CRLF yields a no-EOL file');

$r = roundtrip_e('single', "one\ntwo");
check_e($r['bytes'] === "one\ntwo" && $r['saved']['line_ending'] === 'lf', 'no-EOL source uses LF when editor introduces a newline');

$r = roundtrip_e("a\nb\n", '');
check_e($r['bytes'] === '' && $r['saved']['line_ending'] === 'none', 'clearing LF source creates exact zero-byte file');

$r = roundtrip_e("a\r\nb\r\n", '');
check_e($r['bytes'] === '' && $r['saved']['line_ending'] === 'none', 'clearing CRLF source creates exact zero-byte file');

// UTF-8 BOM is preserved independently from newline form.
$bom = "\xEF\xBB\xBF";
$r = roundtrip_e($bom . "a\nb\n", "z\n");
check_e($r['bytes'] === $bom . "z\n", 'BOM + LF preserves UTF-8 BOM and LF');
check_e($r['saved']['utf8_bom'] === true && $r['saved']['byte_size'] === 5, 'BOM + LF saved metadata counts exact remote bytes');

$r = roundtrip_e($bom . "a\r\nb\r\n", "z\ny\n");
check_e($r['bytes'] === $bom . "z\r\ny\r\n", 'BOM + CRLF preserves UTF-8 BOM and CRLF');
check_e($r['saved']['utf8_bom'] === true && $r['saved']['line_ending'] === 'crlf', 'BOM + CRLF metadata survives round-trip');

$r = roundtrip_e($bom, '');
check_e($r['bytes'] === $bom && $r['saved']['utf8_bom'] === true && $r['saved']['line_ending'] === 'none', 'BOM-only empty text remains BOM-only');

$r = roundtrip_e($bom, "x\n");
check_e($r['bytes'] === $bom . "x\n" && $r['saved']['line_ending'] === 'lf', 'BOM-only source can gain LF while retaining BOM');

$r = roundtrip_e('', '');
check_e($r['bytes'] === '' && $r['saved']['utf8_bom'] === false, 'plain zero-byte file remains zero-byte without BOM');

// Unicode content must remain byte-exact apart from requested newline reconstruction.
$r = roundtrip_e("日本語\r\n二行目", "更新\n二行目");
check_e($r['bytes'] === "更新\r\n二行目", 'UTF-8 multibyte text survives CRLF reconstruction exactly');
check_e($r['saved']['sha256'] === hash('sha256', "更新\r\n二行目"), 'saved SHA is calculated from exact reconstructed UTF-8 bytes');

// Read inspection remains fail-closed for ambiguous legacy newline forms.
expect_editor_error_e(
    static fn() => remote_editor_inspect_bytes('/safe/test.txt', "a\r\nb\nc"),
    'editor_line_endings_unsupported', 422,
    'mixed CRLF/LF source remains non-editable'
);
expect_editor_error_e(
    static fn() => remote_editor_inspect_bytes('/safe/test.txt', "a\rb"),
    'editor_line_endings_unsupported', 422,
    'CR-only source remains non-editable'
);
expect_editor_error_e(
    static fn() => remote_editor_build_save_bytes("a\rb", ['line_ending' => 'lf', 'utf8_bom' => false]),
    'editor_line_endings_unsupported', 422,
    'direct save input containing raw CR remains rejected'
);

// Final reconstructed remote bytes, not just browser LF bytes, remain bounded.
$browser = str_repeat("x\n", 128); // 256 browser bytes, 384 reconstructed CRLF bytes.
$oversized = remote_editor_build_save_bytes($browser, ['line_ending' => 'crlf', 'utf8_bom' => false]);
check_e(strlen($oversized) === 384, 'CRLF reconstruction expansion is explicit before final size inspection');
expect_editor_error_e(
    static fn() => remote_editor_inspect_bytes('/safe/test.txt', $oversized),
    'editor_too_large', 413,
    'reconstructed CRLF bytes above editor ceiling fail closed'
);

// Exact-byte metadata includes BOM and original newline bytes.
$bytes = $bom . "a\r\nb";
$info = remote_editor_inspect_bytes('/safe/test.txt', $bytes);
check_e($info['byte_size'] === strlen($bytes), 'byte_size includes BOM and CRLF bytes');
check_e($info['sha256'] === hash('sha256', $bytes), 'SHA-256 includes BOM and CRLF bytes exactly');
check_e($info['text'] === "a\r\nb", 'read API text excludes BOM but retains original CRLF for browser normalization');

// Unsupported extensions stay rejected before any edit round-trip.
expect_editor_error_e(
    static fn() => remote_editor_inspect_bytes('/safe/image.png', 'x'),
    'editor_type_unsupported', 415,
    'EOL helper cannot make unsupported file types editable'
);

echo "RESULT: PASS {$pass} / FAIL {$fail} / SKIP 0\n";
exit($fail === 0 ? 0 : 1);
