<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'page' => $root . '/public/file-library.php',
    'css' => $root . '/public/css/file-library.css',
    'js' => $root . '/public/js/file-library.js',
    'upload' => $root . '/app/user_file.php',
    'version' => $root . '/app/version.php',
];
$source = [];
foreach ($paths as $key => $path) {
    $value = @file_get_contents($path);
    if (!is_string($value)) { fwrite(STDERR, "FAIL: unable to read {$path}\n"); exit(1); }
    $source[$key] = $value;
}
$pass = 0; $fail = 0;
function e3check(bool $condition, string $label): void
{
    global $pass, $fail;
    if ($condition) { $pass++; echo "PASS: {$label}\n"; }
    else { $fail++; echo "FAIL: {$label}\n"; }
}

e3check(str_contains($source['page'], 'file-library-upload-row'), 'existing upload row markup is retained');
e3check(str_contains($source['css'], 'grid-template-rows: auto auto auto'), 'upload row gets explicit label/control/help rows');
e3check(str_contains($source['css'], '.file-library-upload-row .form-control { grid-column: 1; grid-row: 2; }'), 'file input is pinned to control row');
e3check(str_contains($source['css'], '.file-library-upload-submit') && str_contains($source['css'], 'grid-column: 2; grid-row: 2'), 'Add button is pinned to the same control row');
e3check(str_contains($source['css'], '@media (max-width: 575.98px)') && str_contains($source['css'], '.file-library-upload-row { display: block; }'), 'smartphone upload controls stack safely');
e3check(str_contains($source['js'], 'spinner-border spinner-border-sm'), 'submit swaps to loading spinner');
e3check(str_contains($source['js'], 'アップロード中…'), 'submit communicates upload in progress');
e3check(str_contains($source['js'], "button.disabled = true"), 'duplicate upload submit remains blocked');
e3check(str_contains($source['js'], ".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.zip"), 'ZIP is included in runtime browser accept hint');
e3check(str_contains($source['js'], '1ファイル最大10 MiB'), 'runtime UI states 10 MiB limit');
e3check(str_contains($source['upload'], "app_env('APP_FILE_UPLOAD_MAX_BYTES', '10485760')"), 'server default is 10 MiB');
e3check(str_contains($source['upload'], "'zip' => ['mimes' => ['application/zip', 'application/x-zip-compressed']"), 'ZIP has strict server MIME allowlist');
e3check(str_contains($source['upload'], 'user_file_detect_mime($tmpName)'), 'browser MIME remains untrusted');
e3check(str_contains($source['upload'], 'finfo(FILEINFO_MIME_TYPE)'), 'Fileinfo remains MIME authority');
e3check(str_contains($source['upload'], '$extension === \'zip\''), 'ZIP receives content validation');
e3check(str_contains($source['upload'], 'PK\\x03\\x04') && str_contains($source['upload'], 'PK\\x05\\x06'), 'ZIP signatures are checked');
e3check(!preg_match('/\b(?:eval|exec|shell_exec|system|passthru|proc_open|popen)\s*\(/', $source['upload']), 'upload path adds no code execution primitive');
e3check(!str_contains($source['upload'], 'ZipArchive') && !str_contains($source['upload'], 'extractTo'), 'ZIP is never extracted server-side');
e3check(str_contains($source['version'], "APP_ASSET_REVISION = '1.27.0-dev-e4'"), 'asset revision is dev-e4');
e3check(str_contains($source['page'], 'V1.27-E'), 'page retains the File Library E phase badge');

echo "SUMMARY: {$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
