<?php

declare(strict_types=1);

const APP_REMOTE_EDITOR_MAX_BYTES = 128;

final class AppRemoteValidationException extends InvalidArgumentException
{
    public function __construct(private readonly string $reasonCode)
    {
        parent::__construct('validation');
    }
    public function reason(): string { return $this->reasonCode; }
}

final class AppRemoteTransportException extends RuntimeException
{
    public function __construct(public readonly string $errorCode)
    {
        parent::__construct('transport');
    }
}

interface RemoteFileProvider
{
    public function testConnection(): array;
    public function list(string $relativePath): array;
    public function download(string $relativePath, $outputStream, int $maxBytes): void;
    public function upload($inputStream, int $size, string $relativePath, bool $overwrite): void;
    public function mkdir(string $relativePath): void;
    public function move(string $fromRelativePath, string $toRelativePath, bool $overwrite): void;
    public function delete(string $relativePath, bool $directory): void;
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

final class V130DFakeProvider implements RemoteFileProvider
{
    /** @var array<string,string> */
    public array $files = [];
    /** @var list<array<string,mixed>> */
    public array $operations = [];
    public int $stageCollisionFailures = 0;
    public bool $mutateTargetOnStageUpload = false;
    public bool $moveFailure = false;
    public bool $partialUploadFailure = false;
    public bool $corruptAfterMove = false;
    public string $targetPath = '/safe/test.txt';

    public function testConnection(): array { return ['connected' => true, 'code' => 'connected']; }
    public function list(string $relativePath): array { return []; }

    public function download(string $relativePath, $outputStream, int $maxBytes): void
    {
        $this->operations[] = ['op' => 'download', 'path' => $relativePath, 'max' => $maxBytes];
        if (!array_key_exists($relativePath, $this->files)) {
            throw new AppRemoteTransportException('file_not_found');
        }
        $bytes = $this->files[$relativePath];
        if (strlen($bytes) > $maxBytes) {
            throw new AppRemoteTransportException('transfer_too_large');
        }
        fwrite($outputStream, $bytes);
    }

    public function upload($inputStream, int $size, string $relativePath, bool $overwrite): void
    {
        $this->operations[] = ['op' => 'upload', 'path' => $relativePath, 'size' => $size, 'overwrite' => $overwrite];
        if ($this->stageCollisionFailures > 0) {
            $this->stageCollisionFailures--;
            throw new AppRemoteTransportException('target_exists');
        }
        if (!$overwrite && array_key_exists($relativePath, $this->files)) {
            throw new AppRemoteTransportException('target_exists');
        }
        $bytes = stream_get_contents($inputStream);
        if (!is_string($bytes) || strlen($bytes) !== $size) {
            throw new AppRemoteTransportException('invalid_response');
        }
        $this->files[$relativePath] = $bytes;
        if ($this->partialUploadFailure) {
            $this->partialUploadFailure = false;
            throw new AppRemoteTransportException('upload_failed');
        }
        if ($this->mutateTargetOnStageUpload) {
            $this->files[$this->targetPath] = "external change\n";
            $this->mutateTargetOnStageUpload = false;
        }
    }

    public function mkdir(string $relativePath): void {}

    public function move(string $fromRelativePath, string $toRelativePath, bool $overwrite): void
    {
        $this->operations[] = ['op' => 'move', 'from' => $fromRelativePath, 'to' => $toRelativePath, 'overwrite' => $overwrite];
        if ($this->moveFailure) {
            throw new AppRemoteTransportException('move_failed');
        }
        if (!array_key_exists($fromRelativePath, $this->files)) {
            throw new AppRemoteTransportException('file_not_found');
        }
        if (!$overwrite && array_key_exists($toRelativePath, $this->files)) {
            throw new AppRemoteTransportException('target_exists');
        }
        $this->files[$toRelativePath] = $this->files[$fromRelativePath];
        unset($this->files[$fromRelativePath]);
        if ($this->corruptAfterMove) {
            $this->files[$toRelativePath] .= '!';
        }
    }

    public function delete(string $relativePath, bool $directory): void
    {
        $this->operations[] = ['op' => 'delete', 'path' => $relativePath, 'directory' => $directory];
        unset($this->files[$relativePath]);
    }
}

$GLOBALS['v130d_provider'] = null;
$GLOBALS['v130d_provider_calls'] = 0;

function remote_service_provider(int $ownerId, int $connectionId, ?callable $transport = null): RemoteFileProvider
{
    $GLOBALS['v130d_provider_calls']++;
    return $GLOBALS['v130d_provider'];
}

function remote_service_assert_safe_path(RemoteFileProvider $provider, string $path, bool $allowMissingFinal = false, bool $requireFinalDirectory = false): void
{
    $normalized = remote_path_normalize_relative($path);
    if ($normalized === null || $normalized === '/') {
        throw new AppRemoteValidationException('invalid_path');
    }
    if (!$allowMissingFinal && !array_key_exists($normalized, $provider->files)) {
        throw new AppRemoteValidationException('invalid_path');
    }
}

function remote_service_temp_path(): string
{
    $path = tempnam(sys_get_temp_dir(), 'v130d-');
    if (!is_string($path)) { throw new AppRemoteTransportException('temp_unavailable'); }
    return $path;
}

function remote_service_download_temp(int $ownerId, int $connectionId, string $relativePath, int $maxBytes): string
{
    $provider = remote_service_provider($ownerId, $connectionId);
    remote_service_assert_safe_path($provider, $relativePath, false, false);
    $path = remote_service_temp_path();
    $stream = fopen($path, 'w+b');
    $provider->download($relativePath, $stream, $maxBytes);
    fclose($stream);
    return $path;
}

require_once __DIR__ . '/../app/remote_file/remote_editor.php';

$pass = 0;
$fail = 0;
function check_d(bool $condition, string $label): void
{
    global $pass, $fail;
    if ($condition) { $pass++; echo "PASS: {$label}\n"; }
    else { $fail++; echo "FAIL: {$label}\n"; }
}

function new_provider(string $bytes): V130DFakeProvider
{
    $provider = new V130DFakeProvider();
    $provider->files['/safe/test.txt'] = $bytes;
    $GLOBALS['v130d_provider'] = $provider;
    $GLOBALS['v130d_provider_calls'] = 0;
    return $provider;
}

function expect_editor_error(callable $fn, string $code, int $status, string $label): void
{
    try {
        $fn();
        check_d(false, $label);
    } catch (AppRemoteEditorException $e) {
        check_d($e->errorCode === $code && $e->httpStatus === $status, $label);
    } catch (Throwable) {
        check_d(false, $label);
    }
}

// 1-5: normal save / metadata / staged move.
$p = new_provider("one\ntwo\n");
$expected = hash('sha256', $p->files['/safe/test.txt']);
$result = remote_editor_save(1, 9, '/safe/test.txt', "alpha\nbeta\n", $expected);
check_d($p->files['/safe/test.txt'] === "alpha\nbeta\n", 'normal LF save replaces target content');
check_d($result['sha256'] === hash('sha256', "alpha\nbeta\n"), 'normal save returns SHA of verified remote bytes');
$move = array_values(array_filter($p->operations, static fn(array $op): bool => $op['op'] === 'move'));
check_d(count($move) === 1 && $move[0]['overwrite'] === true, 'staged replacement uses one overwrite move');
$uploads = array_values(array_filter($p->operations, static fn(array $op): bool => $op['op'] === 'upload'));
check_d(count($uploads) === 1 && str_starts_with($uploads[0]['path'], '/safe/.iguguru-editor-'), 'stage file is a random sibling in the target directory');
check_d($GLOBALS['v130d_provider_calls'] === 1, 'save uses one protocol-neutral provider instance');

// 6-8: preserve CRLF and BOM, plus no-EOL -> LF when new lines are introduced.
$p = new_provider("a\r\nb\r\n");
remote_editor_save(1, 9, '/safe/test.txt', "x\ny\n", hash('sha256', $p->files['/safe/test.txt']));
check_d($p->files['/safe/test.txt'] === "x\r\ny\r\n", 'CRLF source keeps CRLF on save');

$p = new_provider("\xEF\xBB\xBFa\nb\n");
remote_editor_save(1, 9, '/safe/test.txt', "z\n", hash('sha256', $p->files['/safe/test.txt']));
check_d($p->files['/safe/test.txt'] === "\xEF\xBB\xBFz\n", 'UTF-8 BOM is preserved on save');

$p = new_provider('single line');
remote_editor_save(1, 9, '/safe/test.txt', "line1\nline2", hash('sha256', $p->files['/safe/test.txt']));
check_d($p->files['/safe/test.txt'] === "line1\nline2", 'no-EOL source uses LF when user introduces new lines');

// 9-10: empty save is intentionally supported through provider-level stage upload.
$p = new_provider("clear me\n");
remote_editor_save(1, 9, '/safe/test.txt', '', hash('sha256', $p->files['/safe/test.txt']));
$uploads = array_values(array_filter($p->operations, static fn(array $op): bool => $op['op'] === 'upload'));
check_d($p->files['/safe/test.txt'] === '', 'editor can save an empty zero-byte text file');
check_d(count($uploads) === 1 && $uploads[0]['size'] === 0, 'zero-byte editor save reaches provider with size 0');

// 11-12: stale expected hash conflicts before staging and leaves original untouched.
$p = new_provider("remote current\n");
expect_editor_error(
    static fn() => remote_editor_save(1, 9, '/safe/test.txt', "mine\n", str_repeat('0', 64)),
    'editor_conflict', 409, 'stale expected SHA returns conflict before write'
);
check_d($p->files['/safe/test.txt'] === "remote current\n" && count(array_filter($p->operations, static fn(array $op): bool => $op['op'] === 'upload')) === 0,
    'first conflict leaves original untouched and does not stage');

// 13-15: remote mutation during stage transfer triggers second conflict and stage cleanup.
$p = new_provider("start\n");
$p->mutateTargetOnStageUpload = true;
$expected = hash('sha256', $p->files['/safe/test.txt']);
expect_editor_error(
    static fn() => remote_editor_save(1, 9, '/safe/test.txt', "mine\n", $expected),
    'editor_conflict', 409, 'remote change during staged upload triggers second conflict check'
);
check_d($p->files['/safe/test.txt'] === "external change\n", 'second conflict does not overwrite newer remote content');
$stageFiles = array_filter(array_keys($p->files), static fn(string $path): bool => str_contains($path, '.iguguru-editor-'));
check_d($stageFiles === [], 'second conflict cleans up staged remote file');

// 16-17: move failure leaves target unchanged and cleans stage.
$p = new_provider("before\n");
$p->moveFailure = true;
try {
    remote_editor_save(1, 9, '/safe/test.txt', "after\n", hash('sha256', $p->files['/safe/test.txt']));
    check_d(false, 'move failure is propagated');
} catch (AppRemoteTransportException $e) {
    check_d($e->errorCode === 'move_failed', 'move failure is propagated');
}
check_d($p->files['/safe/test.txt'] === "before\n" && count(array_filter(array_keys($p->files), static fn(string $path): bool => str_contains($path, '.iguguru-editor-'))) === 0,
    'move failure keeps original and cleans staged file');

// 18: partial stage upload failure is cleaned without touching the original.
$p = new_provider("before\n");
$p->partialUploadFailure = true;
try {
    remote_editor_save(1, 9, '/safe/test.txt', "after\n", hash('sha256', "before\n"));
    check_d(false, 'partial stage upload failure is propagated');
} catch (AppRemoteTransportException $e) {
    check_d($e->errorCode === 'upload_failed', 'partial stage upload failure is propagated');
}
check_d($p->files['/safe/test.txt'] === "before\n" && count(array_filter(array_keys($p->files), static fn(string $path): bool => str_contains($path, '.iguguru-editor-'))) === 0,
    'partial stage upload failure cleans candidate file and preserves original');

// 19: verification mismatch is detected after provider move.
$p = new_provider("before\n");
$p->corruptAfterMove = true;
expect_editor_error(
    static fn() => remote_editor_save(1, 9, '/safe/test.txt', "after\n", hash('sha256', "before\n")),
    'editor_save_verification_failed', 502, 'read-back mismatch fails save verification'
);

// 20: random stage collision retries without overwriting the collision target.
$p = new_provider("before\n");
$p->stageCollisionFailures = 2;
remote_editor_save(1, 9, '/safe/test.txt', "after\n", hash('sha256', "before\n"));
check_d(count(array_filter($p->operations, static fn(array $op): bool => $op['op'] === 'upload')) === 3,
    'stage target collision retries up to a new random sibling');

// 21-25: request-state and browser-text validation fail before write.
$p = new_provider("before\n");
expect_editor_error(static fn() => remote_editor_save(1, 9, '/safe/test.txt', 'x', 'ABC'), 'editor_state_invalid', 422, 'invalid expected SHA format is rejected');
expect_editor_error(static fn() => remote_editor_save(1, 9, '/safe/test.txt', "a\rb", hash('sha256', "before\n")), 'editor_line_endings_unsupported', 422, 'raw CR from direct API caller is rejected');
expect_editor_error(static fn() => remote_editor_save(1, 9, '/safe/test.txt', "a\0b", hash('sha256', "before\n")), 'editor_binary_unsupported', 422, 'NUL in browser text is rejected');
expect_editor_error(static fn() => remote_editor_save(1, 9, '/safe/test.txt', "\xC3\x28", hash('sha256', "before\n")), 'editor_encoding_unsupported', 422, 'invalid UTF-8 browser text is rejected');
expect_editor_error(static fn() => remote_editor_save(1, 9, '/safe/test.txt', "a\x01b", hash('sha256', "before\n")), 'editor_binary_unsupported', 422, 'unsafe control byte in browser text is rejected');

// 26: CRLF expansion is checked against the final remote byte ceiling.
$original = "a\r\n";
$p = new_provider($original);
$text = str_repeat("x\n", 64); // 128 browser bytes -> 192 CRLF bytes.
expect_editor_error(
    static fn() => remote_editor_save(1, 9, '/safe/test.txt', $text, hash('sha256', $original)),
    'editor_too_large', 413, 'final CRLF reconstruction cannot exceed editor byte ceiling'
);

// 27-28: B read path remains compatible and still uses bounded download/temp cleanup.
$p = new_provider("read\n");
$read = remote_editor_read(1, 9, '/safe/test.txt');
check_d($read['text'] === "read\n" && $read['line_ending'] === 'lf', 'existing B read path remains functional');
check_d(count(array_filter($p->operations, static fn(array $op): bool => $op['op'] === 'download')) === 1, 'B read path still performs bounded provider download');

// 29: unsupported extension is rejected before provider creation.
$GLOBALS['v130d_provider_calls'] = 0;
expect_editor_error(static fn() => remote_editor_save(1, 9, '/safe/archive.zip', 'x', str_repeat('0', 64)), 'editor_type_unsupported', 415, 'unsupported extension is rejected before save provider creation');
check_d($GLOBALS['v130d_provider_calls'] === 0, 'unsupported extension does not contact remote provider');

// 30: stage move path safety is re-checked immediately before mutation.
$p = new_provider("before\n");
remote_editor_save(1, 9, '/safe/test.txt', "after\n", hash('sha256', "before\n"));
$downloads = array_values(array_filter($p->operations, static fn(array $op): bool => $op['op'] === 'download'));
check_d(count($downloads) === 3, 'save reads current, rechecks after stage, and verifies after move');

echo "RESULT: PASS {$pass} / FAIL {$fail} / SKIP 0\n";
exit($fail === 0 ? 0 : 1);
