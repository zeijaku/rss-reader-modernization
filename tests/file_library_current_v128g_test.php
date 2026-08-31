<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'version'=>'app/version.php', 'page'=>'public/file-library.php', 'rssPage'=>'public/rss-management.php',
    'settings'=>'public/settings.php', 'preview'=>'app/file_preview.php', 'api'=>'public/file_preview_api.php',
    'content'=>'public/file_content.php', 'upload'=>'app/user_file.php', 'library'=>'app/file_library.php',
    'loader'=>'public/js/file-library.js', 'core'=>'public/js/file-library-core.js', 'text'=>'public/js/file-library-text-preview.js',
    'csv'=>'public/js/file-library-csv-preview.js', 'ui'=>'public/js/file-library-ui.js', 'css'=>'public/css/file-library.css',
    'htaccess'=>'public/.htaccess', 'runner'=>'tests/run-current-features.sh', 'calendar'=>'public/js/calendar.js',
    'rssJs'=>'public/js/rss-management.js', 'camera'=>'public/js/camera-video-streaming.js',
    'drawer'=>'public/js/drawer-categories.js', 'doc'=>'docs/v1-28-file-library-phase2.md', 'checklist'=>'docs/v1-28-g-production-checklist.md',
];
$src = [];
foreach ($files as $key => $rel) {
    $body = @file_get_contents($root . '/' . $rel);
    if (!is_string($body)) { fwrite(STDERR, "FAIL: unable to read {$rel}\n"); exit(1); }
    $src[$key] = $body;
}
$pass = 0; $fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail): void {
    if ($ok) { $pass++; echo "PASS: {$label}\n"; return; }
    $fail++; echo "FAIL: {$label}\n";
};
$check(str_contains($src['version'], "APP_VERSION = '1.28.0-dev.6'"), 'G checkpoint version is dev.6');
$check(str_contains($src['version'], "APP_ASSET_REVISION = '1.28.0-dev.6'"), 'G asset revision is dev.6');
$check(str_contains($src['drawer'], "$('#main-content h1 .badge').remove()"), 'shared UI removes user-visible heading phase badges');
$check(str_contains($src['drawer'], 'RSS Highlight用DB Migrationの適用状況を確認してください。'), 'shared UI neutralizes legacy migration warning version text');
$check(!str_contains($src['ui'], 'badge.textContent'), 'File Library UI does not recreate phase badge');
$check(str_contains($src['page'], 'user_file_allowed_types()') && str_contains($src['page'], 'APP_FILE_UPLOAD_MAX_BYTES'), 'upload guidance follows server contract');
$check(str_contains($src['upload'], "'zip' => ['mimes'") && !str_contains($src['upload'], 'ZipArchive') && !str_contains($src['upload'], 'extractTo'), 'ZIP remains allowlisted but never extracted');
$check(str_contains($src['library'], 'file_owner = :owner AND file_flag = 0'), 'File Library queries remain owner scoped');
$check(str_contains($src['api'], "['detail', 'text', 'csv']"), 'Preview API keeps fixed mode allowlist');
$check(str_contains($src['api'], 'user_file_library_find_owned($userId, $fileId)'), 'Preview API resolves by authenticated owner');
$check(str_contains($src['api'], 'user_file_library_content_is_intact($row, $path)'), 'Preview API revalidates content');
$check(str_contains($src['content'], "'preview'") && str_contains($src['content'], 'user_file_library_is_inline_pdf'), 'content endpoint limits preview mode to validated PDF');
$check(str_contains($src['content'], 'X-Content-Type-Options: nosniff'), 'content endpoint remains nosniff');
$check(str_contains($src['content'], 'Cross-Origin-Resource-Policy: same-origin'), 'content endpoint remains same-origin');
$check(str_contains($src['content'], "Content-Security-Policy: default-src 'none'; sandbox"), 'content endpoint keeps restrictive CSP');
$check(str_contains($src['preview'], 'USER_FILE_TEXT_PREVIEW_MAX_BYTES') && str_contains($src['preview'], 'USER_FILE_CSV_PREVIEW_MAX_BYTES'), 'bounded preview constants remain present');
$check(str_contains($src['preview'], 'fgetcsv('), 'CSV parser uses fgetcsv');
$check(str_contains($src['loader'], 'file-library-core.js') && str_contains($src['loader'], 'file-library-text-preview.js') && str_contains($src['loader'], 'file-library-csv-preview.js') && str_contains($src['loader'], 'file-library-ui.js'), 'split File Library modules load deterministically');
$check(str_contains($src['core'], 'function bindImageViewer()') && str_contains($src['core'], 'function bindPdfViewer()'), 'Image/PDF viewers remain enabled');
$check(str_contains($src['text'], '.textContent') && str_contains($src['csv'], '.textContent'), 'TXT/CSV dynamic values render as text');
$check(!preg_match('/\b(?:eval|Function)\s*\(/', $src['core'] . $src['text'] . $src['csv'] . $src['ui']), 'File Library JS adds no dynamic code execution');
$check(!str_contains($src['core'] . $src['text'] . $src['csv'] . $src['ui'], 'file_stored_name'), 'client modules expose no physical stored name');
$check(str_contains($src['css'], '@media (max-width: 575.98px)'), 'Smartphone responsive layer remains present');
$check(str_contains($src['css'], '.file-library-csv-table-wrap'), 'CSV table remains scroll-contained');
$check(!str_contains($src['css'], '@import') && !preg_match('/url\s*\(\s*["\']?https?:/i', $src['css']), 'File Library CSS adds no remote dependency');
$check(str_contains($src['htaccess'], 'file_preview_api\\.php$'), 'Preview API remains explicitly allowlisted');
$check(str_contains($src['runner'], 'file_preview_current_v128g_test.php') && str_contains($src['runner'], 'file_library_current_v128g_test.php'), 'Current suite includes V1.28 integration gates');
$check(!str_contains($src['runner'], 'file_library_image_viewer_v127f_test.php') && !str_contains($src['runner'], 'test_v127g_current_contract.php'), 'stale V1.27 phase-final UI gates are not active Current gates');
foreach (['calendar','rssJs','camera'] as $key) {
    $check(!str_contains($src[$key], '?v=1.27.0'), $key . ' has no stale V1.27 runtime cache key');
    $check(str_contains($src[$key], '?v=1.28.0-dev.6'), $key . ' follows G asset revision');
}
$check(str_contains($src['doc'], '64 KiB') && str_contains($src['doc'], '512 KiB') && str_contains($src['doc'], 'ZIP remains download-only'), 'V1.28 docs record bounds and ZIP policy');
$check(str_contains($src['checklist'], 'V1.28-H') && str_contains($src['checklist'], 'different authenticated user'), 'production checklist covers H boundary and owner isolation');
printf("RESULT: PASS %d / FAIL %d\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
