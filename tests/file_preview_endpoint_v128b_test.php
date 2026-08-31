<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'helper' => $root . '/app/file_preview.php',
    'endpoint' => $root . '/public/file_preview_api.php',
    'js' => $root . '/public/js/file-library.js',
    'css' => $root . '/public/css/file-library.css',
    'htaccess' => $root . '/public/.htaccess',
    'version' => $root . '/app/version.php',
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
function check_contract(bool $condition, string $label): void
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

check_contract(str_contains($source['version'], "APP_VERSION = '1.28.0-dev.1'"), 'V1.28-B checkpoint application version is explicit');
check_contract(str_contains($source['version'], "APP_ASSET_REVISION = '1.28.0-dev.1'"), 'V1.28-B changed assets receive a new immutable cache revision');

check_contract(str_contains($source['endpoint'], "REQUEST_METHOD") && str_contains($source['endpoint'], "!== 'GET'"), 'File Detail endpoint is GET-only');
check_contract(str_contains($source['endpoint'], 'app_session_user_id()'), 'File Detail endpoint requires authenticated session identity');
check_contract(str_contains($source['endpoint'], 'user_file_library_find_owned($userId, $fileId)'), 'File Detail lookup is owner scoped');
check_contract(!str_contains($source['endpoint'], "\$_GET['user_id']") && !str_contains($source['endpoint'], "\$_POST['user_id']"), 'File Detail never accepts owner from request');
check_contract(!str_contains($source['endpoint'], "\$_GET['path']") && !str_contains($source['endpoint'], "\$_GET['name']"), 'File Detail never accepts physical path or stored name');
check_contract(str_contains($source['endpoint'], "app_validate_enum(\$_GET['mode'] ?? 'detail', ['detail'])"), 'B endpoint strictly allowlists detail mode only');
check_contract(str_contains($source['endpoint'], 'user_file_library_resolve_path($row)'), 'File Detail resolves private storage path through existing helper');
check_contract(str_contains($source['endpoint'], 'user_file_library_content_is_intact($row, $path)'), 'File Detail revalidates content before exposing metadata');
check_contract(str_contains($source['endpoint'], 'app_session_release()'), 'session lock is released before filesystem work');
check_contract(str_contains($source['endpoint'], 'X-Content-Type-Options: nosniff'), 'File Detail response remains nosniff');
check_contract(str_contains($source['endpoint'], 'Cross-Origin-Resource-Policy: same-origin'), 'File Detail response remains same-origin');
check_contract(str_contains($source['endpoint'], "Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'"), 'File Detail JSON receives restrictive CSP');
check_contract(str_contains($source['endpoint'], 'app_send_no_store_headers()'), 'File Detail JSON is no-store');
check_contract(str_contains($source['endpoint'], 'JSON_HEX_TAG') && str_contains($source['endpoint'], 'JSON_HEX_QUOT'), 'JSON serialization hex-escapes active HTML characters');
check_contract(!preg_match('/\b(?:eval|exec|shell_exec|system|passthru|proc_open|popen)\s*\(/', $source['endpoint']), 'File Detail endpoint has no code execution primitive');

check_contract(str_contains($source['helper'], 'USER_FILE_TEXT_PREVIEW_MAX_BYTES = 65536'), 'bounded TXT foundation is defined');
check_contract(str_contains($source['helper'], 'USER_FILE_CSV_PREVIEW_MAX_BYTES = 524288'), 'bounded CSV foundation is defined');
check_contract(str_contains($source['helper'], "'zip' => 'download'") === false, 'ZIP has no content-preview implementation in B');
check_contract(!str_contains($source['helper'], 'ZipArchive') && !str_contains($source['helper'], 'extractTo'), 'Preview foundation never opens or extracts ZIP');
check_contract(str_contains($source['helper'], "'file_id' =>") && str_contains($source['helper'], "'filename' =>") && str_contains($source['helper'], "'mime_type' =>"), 'Detail exposes intended public metadata');
check_contract(!preg_match("/'file_owner'\\s*=>/", $source['helper']) && !preg_match("/'file_stored_name'\\s*=>/", $source['helper']), 'Detail output does not expose owner or physical name');

check_contract(str_contains($source['js'], "badge.textContent = 'V1.28-B'"), 'File Library runtime identifies V1.28-B checkpoint');
check_contract(str_contains($source['js'], 'function bindFileDetail()'), 'File Detail modal binding is present');
check_contract(str_contains($source['js'], "'./file_preview_api.php?id=' + encodeURIComponent(targetId) + '&mode=detail'"), 'Detail request uses fixed same-origin endpoint');
check_contract(str_contains($source['js'], "credentials: 'same-origin'"), 'Detail fetch explicitly remains same-origin credentialed');
check_contract(str_contains($source['js'], "if (!/^[1-9]\\d*$/.test(targetId))"), 'Detail click path revalidates positive file id');
check_contract(str_contains($source['js'], 'element.textContent = value'), 'Dynamic File Detail values are rendered with textContent');
check_contract(!str_contains($source['js'], 'data-preview-url') && !str_contains($source['js'], 'data-path'), 'Detail JS accepts no arbitrary path or preview URL');
check_contract(str_contains($source['js'], 'bindImageViewer();') && str_contains($source['js'], 'bindUploadDropZone();'), 'existing Image Viewer and Drag & Drop remain initialized');
check_contract(!preg_match('/\b(?:eval|Function)\s*\(/', $source['js']), 'File Library JS adds no dynamic code execution primitive');

check_contract(str_contains($source['css'], '.file-library-actions.file-library-actions-detail-four'), 'desktop four-action grid is explicit');
check_contract(str_contains($source['css'], '.file-library-detail-list'), 'File Detail has scoped responsive styling');
check_contract(str_contains($source['css'], '@media (max-width: 575.98px)'), 'File Detail keeps smartphone-specific layer');
check_contract(str_contains($source['css'], 'grid-template-columns: repeat(2,minmax(0,1fr))'), 'smartphone detail actions collapse to two columns');
check_contract(!str_contains($source['css'], '@import') && !preg_match('/url\s*\(\s*["\']?https?:/i', $source['css']), 'File Library CSS adds no external dependency');
check_contract(substr_count($source['css'], '{') === substr_count($source['css'], '}'), 'File Library CSS braces remain balanced');

check_contract(str_contains($source['htaccess'], 'file_preview_api\\.php$'), 'File Detail API is explicitly added to deny-by-default PHP allowlist');
foreach (['file-library\\.php$', 'file_content\\.php$', 'file_upload_api\\.php$'] as $existingEndpoint) {
    check_contract(str_contains($source['htaccess'], $existingEndpoint), 'existing File Library allowlist remains intact: ' . $existingEndpoint);
}

printf("RESULT: PASS %d / FAIL %d\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
