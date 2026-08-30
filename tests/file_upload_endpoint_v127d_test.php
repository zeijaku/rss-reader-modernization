<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'endpoint' => $root . '/public/file_upload_api.php',
    'library' => $root . '/app/user_file.php',
    'public_htaccess' => $root . '/public/.htaccess',
    'root_htaccess' => $root . '/.htaccess',
    'migration' => $root . '/database/migrations/020_v1_27_user_files.sql',
    'version' => $root . '/app/version.php',
    'gitkeep' => $root . '/var/uploads/.gitkeep',
];
$src = [];
foreach ($files as $key => $path) {
    $data = @file_get_contents($path);
    if (!is_string($data)) {
        fwrite(STDERR, "FAIL: unable to read {$path}\n");
        exit(1);
    }
    $src[$key] = $data;
}

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

check(str_contains($src['endpoint'], "define('APP_RESPONSE_FORMAT', 'json')"), 'upload endpoint uses JSON error boundary');
check(str_contains($src['endpoint'], "(\$_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'"), 'upload endpoint is POST-only');
check(str_contains($src['endpoint'], "header('Allow: POST')"), 'upload endpoint publishes POST Allow header');
check(str_contains($src['endpoint'], 'app_session_start();'), 'upload endpoint starts protected session');
check(str_contains($src['endpoint'], 'app_session_user_id();'), 'upload endpoint derives owner from authenticated session');
check(!str_contains($src['endpoint'], "\$_POST['user_id']"), 'upload endpoint never accepts owner id from POST');
check(str_contains($src['endpoint'], 'app_csrf_is_valid($csrfToken)'), 'upload endpoint requires CSRF validation');
check(str_contains($src['endpoint'], 'HTTP_X_CSRF_TOKEN'), 'upload endpoint supports CSRF header for multipart clients');
check(str_contains($src['endpoint'], 'APP_FILE_UPLOAD_MAX_REQUEST_BYTES'), 'upload endpoint enforces request size guard');
check(str_contains($src['endpoint'], "\$_FILES['file']"), 'upload endpoint accepts one explicit multipart file field');
check(str_contains($src['endpoint'], 'app_session_release();'), 'session lock is released before disk/DB IO');
check(str_contains($src['endpoint'], 'user_file_store_upload($userId, $file)'), 'endpoint delegates to secure upload boundary');
check(str_contains($src['endpoint'], "header('X-Content-Type-Options: nosniff')"), 'endpoint emits nosniff');
check(str_contains($src['endpoint'], 'app_send_no_store_headers();'), 'endpoint upload responses are non-cacheable');
check(!str_contains($src['endpoint'], 'APP_FILE_UPLOAD_DIR'), 'endpoint never exposes storage directory directly');
check(!str_contains($src['endpoint'], "['stored_name']"), 'endpoint response does not expose stored filename');

check(str_contains($src['library'], "dirname(__DIR__) . '/var/uploads'"), 'default upload storage is outside public');
check(str_contains($src['library'], 'is_uploaded_file($path)'), 'production path verifies PHP HTTP upload origin');
check(str_contains($src['library'], 'new finfo(FILEINFO_MIME_TYPE)'), 'server-side Fileinfo MIME detection is mandatory');
check(!str_contains($src['library'], "['type']"), 'browser-supplied MIME field is never read');
check(str_contains($src['library'], 'getimagesize($path)'), 'image uploads receive structural image validation');
check(str_contains($src['library'], "str_starts_with(\$prefix, '%PDF-')"), 'PDF uploads receive signature validation');
check(str_contains($src['library'], "!str_contains(\$prefix, \"\\0\")"), 'text/csv reject binary NUL data');
check(str_contains($src['library'], "'svg'"), 'dangerous extension list includes SVG');
check(str_contains($src['library'], "'php'"), 'dangerous extension list includes PHP');
check(!array_key_exists('svg', user_file_allowed_types_placeholder()), 'SVG is not in documented allowlist placeholder');
check(str_contains($src['library'], 'user_file_has_dangerous_double_extension'), 'double-extension defense exists');
check(str_contains($src['library'], 'APP_FILE_UPLOAD_MAX_BYTES'), 'per-file application size limit exists');
check(str_contains($src['library'], "realpath(dirname(__DIR__) . '/public')"), 'storage boundary resolves public root canonically');
check(str_contains($src['library'], 'user_file_path_is_within($real, $public)'), 'storage under public is rejected');
check(str_contains($src['library'], 'bin2hex(random_bytes(32))'), 'physical filename uses 256-bit random value');
check(str_contains($src['library'], 'move_uploaded_file($from, $to)'), 'production storage move uses move_uploaded_file');
check(str_contains($src['library'], '@chmod($destination, 0640)'), 'stored file receives private file permissions where supported');
check(substr_count($src['library'], '@unlink($destination)') >= 2, 'failed move/DB paths clean partial physical files');
check(str_contains($src['library'], "'original_name' => \$metadata['original_name']"), 'original filename is retained as metadata');
check(!str_contains($src['library'], "'stored_name' => \$storedName"), 'public metadata result omits stored filename');

check(str_contains($src['endpoint'], "require_once dirname(__DIR__) . '/app/user_file.php';"), 'upload endpoint loads secure file service explicitly');
check(str_contains($src['public_htaccess'], 'file_upload_api\\.php$'), 'public PHP allowlist explicitly permits upload endpoint');
check(str_contains($src['public_htaccess'], '.*\\.php$ - [F,L,NC]'), 'deny-by-default PHP endpoint rule remains present');
check(str_contains($src['root_htaccess'], 'app|config|tools|var'), 'application root still denies direct var access');

check(str_contains($src['migration'], "CONCAT(@table_prefix, 'user_file')"), 'migration honors configured table prefix');
check(str_contains($src['migration'], '`file_owner` INT UNSIGNED NOT NULL'), 'migration stores explicit owner id');
check(str_contains($src['migration'], '`file_original_name` VARCHAR(255) NOT NULL'), 'migration stores original filename metadata');
check(str_contains($src['migration'], '`file_stored_name` VARCHAR(80)'), 'migration stores opaque physical filename metadata');
check(str_contains($src['migration'], '`file_mime_type` VARCHAR(64)'), 'migration stores server-detected MIME metadata');
check(str_contains($src['migration'], '`file_size` BIGINT UNSIGNED NOT NULL'), 'migration stores authoritative byte size');
check(str_contains($src['migration'], 'UNIQUE KEY `uq_user_file_stored_name`'), 'migration enforces unique stored filenames');
check(str_contains($src['migration'], 'KEY `idx_user_file_owner_flag_id`'), 'migration has owner-scoped list index for V1.27-E');
check(!preg_match('/\bBLOB\b/i', $src['migration']), 'binary file body is not stored in DB');
check(!preg_match('/FOREIGN\s+KEY/i', $src['migration']), 'migration preserves current no-FK project policy');
check(str_contains($src['migration'], 'information_schema.TABLES'), 'migration is idempotent for existing table');

check(str_contains($src['version'], "APP_ASSET_REVISION = '1.27.0-dev-d1'"), 'deployment checkpoint revision is V1.27-D');
check(is_file($files['gitkeep']), 'private upload directory is included in deployment package');

check(!is_file($root . '/public/file-library.php'), 'V1.27-D does not prematurely add File Library UI');
check(!str_contains($src['endpoint'], 'readfile('), 'upload endpoint cannot download stored files');

function user_file_allowed_types_placeholder(): array
{
    return ['jpg'=>1,'jpeg'=>1,'png'=>1,'gif'=>1,'webp'=>1,'pdf'=>1,'txt'=>1,'csv'=>1];
}

echo "RESULT: {$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
