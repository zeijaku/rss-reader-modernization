<?php

declare(strict_types=1);

// Lightweight function-only test, without booting the application's session/database.
$source = file_get_contents(dirname(__DIR__) . '/public/remote_file_upload_api.php');
if (!is_string($source)) { throw new RuntimeException('Cannot read upload endpoint.'); }
$start = strpos($source, 'function remote_upload_normalize_remote_name(');
$end = strpos($source, '/** @return array{name:string,tmp_name:string,size:int} */', $start);
if ($start === false || $end === false) { throw new RuntimeException('Missing remote-only name validation.'); }
require_once dirname(__DIR__) . '/app/remote_file/remote_path.php';
eval(substr($source, $start, $end - $start));

$allowed = ['.htaccess', '.env', '.gitignore', 'normal.txt', 'file.', '..config', '日本語.txt'];
foreach ($allowed as $value) {
    if (remote_upload_normalize_remote_name($value) !== $value) {
        throw new RuntimeException('Valid name changed: ' . $value);
    }
}
$rejected = ['', '.', '..', '../secret', 'folder/file.txt', "a\\b", "bad\nname", str_repeat('a', 256)];
foreach ($rejected as $value) {
    if (remote_upload_normalize_remote_name($value) !== null) {
        throw new RuntimeException('Unsafe name accepted.');
    }
}
if (str_contains($source, "user_file_normalize_original_name(\$file['name'])")) {
    throw new RuntimeException('Upload still uses File Library filename normalizer.');
}
echo "dev8 dotfile filename validation PASS\n";
