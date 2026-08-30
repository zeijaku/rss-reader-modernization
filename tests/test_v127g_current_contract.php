<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'version' => $root . '/app/version.php',
    'normalizer' => $root . '/app/url_normalizer.php',
    'upload' => $root . '/app/user_file.php',
    'library' => $root . '/app/file_library.php',
    'page' => $root . '/public/file-library.php',
    'content' => $root . '/public/file_content.php',
    'css' => $root . '/public/css/file-library.css',
    'utilityCss' => $root . '/public/css/utility-widgets.css',
    'js' => $root . '/public/js/file-library.js',
    'drawer' => $root . '/public/js/drawer-categories.js',
    'publicHtaccess' => $root . '/public/.htaccess',
    'migration' => $root . '/database/migrations/020_v1_27_user_files.sql',
    'schema' => $root . '/database/schema.sql',
    'runner' => $root . '/tests/run-current-features.sh',
    'fileDoc' => $root . '/docs/v1-27-file-library.md',
    'checklist' => $root . '/docs/v1-27-g-production-checklist.md',
];

$source = [];
foreach ($paths as $key => $path) {
    $value = @file_get_contents($path);
    if (!is_string($value)) {
        fwrite(STDERR, "FAIL: unable to read {$path}\n");
        exit(1);
    }
    $source[$key] = $value;
}

$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "PASS: {$label}\n";
        return;
    }
    $fail++;
    echo "FAIL: {$label}\n";
};

// Integration/version boundary.
$check(str_contains($source['version'], "APP_VERSION = '1.26.0'"), 'formal APP_VERSION remains 1.26.0 until release phase');
$check(str_contains($source['version'], "APP_ASSET_REVISION = '1.27.0-dev-g1'"), 'G integration asset revision is set');
$check(str_contains($source['runner'], 'test_v127g_current_contract.php'), 'current feature runner includes V1.27 G contract');
$check(str_contains($source['fileDoc'], 'var/uploads/') && str_contains($source['fileDoc'], '020_v1_27_user_files.sql'), 'File Library deployment/storage documentation is present');
$check(str_contains($source['checklist'], 'Direct upgrade from formal V1.26.0') && str_contains($source['checklist'], 'owner scoped'), 'G production checklist covers upgrade and ownership verification');

// Tracking-parameter scope.
$check(str_contains($source['normalizer'], 'function app_remove_tracking_parameters'), 'article URL tracking normalizer remains present');
$check(str_contains($source['normalizer'], "'utm_source'"), 'utm_source is removed by article normalizer');
$check(str_contains($source['normalizer'], "'utm_id'"), 'utm_id is removed by article normalizer');
$check(str_contains($source['normalizer'], "'msclkid'"), 'msclkid is removed by article normalizer');
$check(str_contains($source['normalizer'], "parse_url"), 'normalizer parses URL structure before rewriting query');
$check(!str_contains($source['normalizer'], 'parse_str(') && !str_contains($source['normalizer'], 'http_build_query('), 'normalizer avoids lossy query reconstruction helpers');

// Dashboard UI normalization remains scoped.
$check(str_contains($source['utilityCss'], 'min-height:44px') || str_contains($source['utilityCss'], 'min-height: 44px'), 'Dashboard touch target normalization remains present');
$check(!str_contains($source['utilityCss'], 'grid-template-areas'), 'G does not introduce Dashboard grid redesign');

// Secure upload boundary.
$check(str_contains($source['upload'], "app_env('APP_FILE_UPLOAD_MAX_BYTES', '10485760')"), 'upload default remains 10 MiB');
$check(str_contains($source['upload'], 'finfo(FILEINFO_MIME_TYPE)'), 'server Fileinfo remains MIME authority');
$check(str_contains($source['upload'], 'random_bytes(32)'), 'stored file name remains cryptographically random');
$check(str_contains($source['upload'], 'move_uploaded_file'), 'HTTP upload still uses move_uploaded_file');
$check(str_contains($source['upload'], "'zip' => ['mimes'"), 'ZIP remains explicitly allowlisted');
$check(str_contains($source['upload'], '$extension === \'zip\''), 'ZIP receives content signature validation');
$check(!str_contains($source['upload'], 'ZipArchive') && !str_contains($source['upload'], 'extractTo'), 'ZIP is never extracted server-side');
$check(!preg_match('/\b(?:eval|exec|shell_exec|system|passthru|proc_open|popen)\s*\(/', $source['upload']), 'upload path contains no code execution primitive');

// File Library owner/private-content boundary.
$check(str_contains($source['library'], 'file_owner = :owner AND file_flag = 0'), 'File Library queries remain owner scoped');
$check(str_contains($source['page'], 'app_csrf_is_valid'), 'File Library state changes remain CSRF protected');
$check(str_contains($source['content'], 'user_file_library_find_owned($userId, $fileId)'), 'content endpoint resolves file by authenticated owner');
$check(str_contains($source['content'], 'user_file_library_content_is_intact($row, $path)'), 'content is revalidated at serve time');
$check(str_contains($source['content'], 'X-Content-Type-Options: nosniff'), 'file responses remain nosniff');
$check(str_contains($source['content'], 'Cross-Origin-Resource-Policy: same-origin'), 'file responses remain same-origin');
$check(str_contains($source['content'], "Content-Security-Policy: default-src 'none'; sandbox"), 'inline image response keeps restrictive CSP');
$check(!str_contains($source['page'], 'file_stored_name') && !str_contains($source['js'], 'file_stored_name'), 'physical random file name is not exposed to page or viewer JS');
$check(!str_contains($source['page'], 'APP_FILE_UPLOAD_DIR') && !str_contains($source['js'], 'APP_FILE_UPLOAD_DIR'), 'physical upload path is not exposed to page or viewer JS');
$check(str_contains($source['publicHtaccess'], 'file-library\\.php$') && str_contains($source['publicHtaccess'], 'file_content\\.php$'), 'File Library public PHP endpoints remain explicit allowlist entries');

// File Library UX added during E/F.
$check(str_contains($source['drawer'], "text('File Library')"), 'Drawer still exposes File Library');
$check(str_contains($source['js'], 'bindUploadDropZone'), 'drag-and-drop file selection remains enabled');
$check(str_contains($source['js'], 'spinner-border spinner-border-sm'), 'upload loading spinner remains enabled');
$check(str_contains($source['js'], 'function bindImageViewer()'), 'Image Viewer remains enabled');
$check(str_contains($source['js'], "'./file_content.php?id=' + encodeURIComponent(fileId) + '&mode=view'"), 'Image Viewer uses fixed protected content endpoint');
$check(str_contains($source['css'], '.file-library-image-modal'), 'Image Viewer responsive modal styling remains present');
$check(str_contains($source['css'], '.file-library-upload-row.is-drag-over'), 'drag-over feedback styling remains present');

// Existing DB migration and fresh-install schema must now describe the same table.
$check(str_contains($source['migration'], "SET @table_name = CONCAT(@table_prefix, 'user_file')"), 'existing database migration 020 remains user_file migration');
$check(str_contains($source['migration'], '@table_exists'), 'migration 020 remains additive/idempotent for existing table');
$check(str_contains($source['schema'], "SET @t_user_file = CONCAT('`', @table_prefix, 'user_file`')"), 'fresh-install schema declares prefixed user_file table');
$check(str_contains($source['schema'], "'CREATE TABLE ', @t_user_file"), 'fresh-install schema creates user_file table');
foreach ([
    '`file_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
    '`file_owner` INT UNSIGNED NOT NULL',
    '`file_original_name` VARCHAR(255) NOT NULL',
    '`file_stored_name` VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',
    '`file_mime_type` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',
    '`file_extension` VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',
    '`file_size` BIGINT UNSIGNED NOT NULL',
    '`file_created_at` DATETIME NOT NULL',
    '`file_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0',
    'UNIQUE KEY `uq_user_file_stored_name` (`file_stored_name`)',
    'KEY `idx_user_file_owner_flag_id` (`file_owner`, `file_flag`, `file_id`)',
] as $needle) {
    $check(str_contains($source['schema'], $needle), 'fresh schema user_file contract: ' . $needle);
}
$check(!preg_match('/CREATE TABLE[^;]*user_file[^;]*\b(?:BLOB|LONGBLOB|MEDIUMBLOB|TINYBLOB)\b/is', $source['schema']), 'fresh schema stores metadata only, not file BLOB data');
$check(substr_count($source['schema'], "@t_user_file") >= 2, 'fresh schema user_file identifier is declared and used');

printf("RESULT: PASS %d / FAIL %d\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
