<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'version' => $root . '/app/version.php',
    'page' => $root . '/public/file-library.php',
    'css' => $root . '/public/css/file-library.css',
    'loader' => $root . '/public/js/file-library.js',
    'ui' => $root . '/public/js/file-library-ui.js',
    'library' => $root . '/app/file_library.php',
    'preview' => $root . '/app/file_preview.php',
    'htaccess' => $root . '/public/.htaccess',
    'content' => $root . '/public/file_content.php',
    'api' => $root . '/public/file_preview_api.php',
    'core' => $root . '/public/js/file-library-core.js',
    'text' => $root . '/public/js/file-library-text-preview.js',
    'csv' => $root . '/public/js/file-library-csv-preview.js',
];
$source = [];
foreach ($paths as $key => $path) {
    $data = @file_get_contents($path);
    if (!is_string($data)) {
        fwrite(STDERR, "FAIL: unable to read {$path}\n");
        exit(1);
    }
    $source[$key] = $data;
}

$pass = 0;
$fail = 0;
function check_f(bool $condition, string $label): void
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

function git_blob_sha(string $content): string
{
    return sha1('blob ' . strlen($content) . "\0" . $content);
}

check_f(str_contains($source['version'], "APP_VERSION = '1.28.0-dev.5'"), 'checkpoint version advances to V1.28-F dev.5');
check_f(str_contains($source['version'], "APP_ASSET_REVISION = '1.28.0-dev.5'"), 'asset revision advances with F UI assets');
check_f(str_contains($source['version'], 'V1.28-F uses a checkpoint-specific revision'), 'version comment identifies F checkpoint');

check_f(str_contains($source['page'], '>V1.28-F</span>'), 'server-rendered badge is V1.28-F before JavaScript runs');
check_f(!str_contains($source['page'], '>V1.27-E</span>'), 'stale V1.27-E server badge is removed');
check_f(str_contains($source['page'], '$uploadExtensions = array_keys(user_file_allowed_types());'), 'upload extension display derives from server allowlist');
check_f(str_contains($source['page'], '$uploadAccept = implode'), 'accept attribute is derived from server allowlist');
check_f(str_contains($source['page'], '$uploadMaxLabel = user_file_library_format_bytes(APP_FILE_UPLOAD_MAX_BYTES);'), 'upload max label derives from server max bytes');
check_f(str_contains($source['page'], 'accept="<?php echo app_html($uploadAccept); ?>"'), 'server renders dynamic accept list');
check_f(str_contains($source['page'], 'app_html($uploadTypeLabel)'), 'server renders the derived type label safely');
check_f(str_contains($source['page'], 'app_html($uploadMaxLabel)'), 'server renders the derived size label safely');
check_f(!str_contains($source['page'], '1ファイル最大5 MiB'), 'stale 5 MiB help text is removed');
check_f(!str_contains($source['page'], 'accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv"'), 'stale accept list without ZIP is removed');
check_f(str_contains($source['page'], 'app_csrf_is_valid($csrfToken)'), 'upload/delete POST keeps CSRF validation');
check_f(str_contains($source['page'], 'user_file_store_upload($currentUserId, $file)'), 'upload still uses session owner id');
check_f(str_contains($source['page'], 'user_file_library_delete_owned($currentUserId, $fileId)'), 'delete still uses owner-scoped helper');
check_f(str_contains($source['page'], 'app_html($name)'), 'file names remain escaped in server HTML');
check_f(str_contains($source['page'], 'APP_VERSION_LABEL'), 'footer keeps central application version label');

$pageBase = $source['page'];
$uploadBlock = <<<'TXT'

$uploadExtensions = array_keys(user_file_allowed_types());
$uploadAccept = implode(',', array_map(static fn(string $extension): string => '.' . $extension, $uploadExtensions));
$uploadTypeLabel = implode(' / ', array_map('strtoupper', $uploadExtensions));
$uploadMaxLabel = user_file_library_format_bytes(APP_FILE_UPLOAD_MAX_BYTES);
TXT;
$pageBase = str_replace("\n" . $uploadBlock, '', $pageBase);
$pageBase = str_replace('>V1.28-F</span>', '>V1.27-E</span>', $pageBase);
$pageBase = str_replace('accept="<?php echo app_html($uploadAccept); ?>"', 'accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv"', $pageBase);
$pageBase = str_replace('<?php echo app_html($uploadTypeLabel); ?>、1ファイル最大<?php echo app_html($uploadMaxLabel); ?>。サーバー側で実ファイル形式を確認します。', 'JPEG / PNG / GIF / WebP / PDF / TXT / CSV、1ファイル最大5 MiB。サーバー側で実ファイル形式を確認します。', $pageBase);
check_f(git_blob_sha($pageBase) === '00c4c450a6541582d4f5b60ea1e5ad4dd1113b19', 'F page changes reverse exactly to the E GitHub page blob');

check_f(str_contains($source['loader'], "loadScript('file-library-core.js'"), 'loader keeps C core first');
check_f(str_contains($source['loader'], "loadScript('file-library-text-preview.js'"), 'loader keeps D TXT second');
check_f(str_contains($source['loader'], "loadScript('file-library-csv-preview.js'"), 'loader keeps E CSV third');
check_f(str_contains($source['loader'], "loadScript('file-library-ui.js');"), 'loader adds F UI module last');
$posCore = strpos($source['loader'], 'file-library-core.js');
$posText = strpos($source['loader'], 'file-library-text-preview.js');
$posCsv = strpos($source['loader'], 'file-library-csv-preview.js');
$posUi = strpos($source['loader'], 'file-library-ui.js');
check_f(is_int($posCore) && is_int($posText) && is_int($posCsv) && is_int($posUi) && $posCore < $posText && $posText < $posCsv && $posCsv < $posUi, 'loader order is core -> TXT -> CSV -> F UI');
check_f(!preg_match('/https?:\/\//i', $source['loader']), 'loader adds no remote dependency');

check_f(str_contains($source['ui'], "badge.textContent = 'V1.28-F'"), 'F UI module restores visible phase marker after all modules');
check_f(str_contains($source['ui'], 'finalizeActionGroups()'), 'F UI module finalizes action layout after dynamic actions exist');
check_f(str_contains($source['ui'], "actions.setAttribute('role', 'group')"), 'card actions expose group semantics');
check_f(str_contains($source['ui'], "actions.setAttribute('aria-label'"), 'card action groups receive contextual aria labels');
check_f(str_contains($source['ui'], 'file-library-actions-count-4'), 'F UI module records four-action cards explicitly');
check_f(str_contains($source['ui'], 'localizeDetailModal()'), 'F UI module integrates File Detail labels');
check_f(str_contains($source['ui'], "'MIME\\u30bf\\u30a4\\u30d7'"), 'Detail MIME label is localized without source encoding risk');
check_f(str_contains($source['ui'], "'\\u30d5\\u30a1\\u30a4\\u30ebID'"), 'Detail file ID label is localized');
check_f(str_contains($source['ui'], 'window.setTimeout(function ()'), 'F UI performs one late pass after earlier phase modules');
check_f(!str_contains($source['ui'], 'innerHTML'), 'F UI does not inject user data through innerHTML');
check_f(!preg_match('/\b(?:eval|Function)\s*\(/', $source['ui']), 'F UI adds no dynamic code execution primitive');
check_f(!preg_match('/https?:\/\//i', $source['ui']), 'F UI adds no remote URL dependency');

check_f(str_contains($source['css'], '@media (max-width: 767.98px)'), 'F adds tablet/narrow-card breakpoint');
check_f(str_contains($source['css'], '.file-library-actions.file-library-actions-count-4'), 'F styles finalized four-action cards');
check_f(str_contains($source['css'], 'grid-template-columns: repeat(2,minmax(0,1fr))'), 'four actions become a two-column layout on narrow cards');
check_f(str_contains($source['css'], '.file-library-name { display: -webkit-box;'), 'mobile long filenames can use two lines');
check_f(str_contains($source['css'], '-webkit-line-clamp: 2'), 'mobile filename display is bounded to two lines');
check_f(str_contains($source['css'], '.file-library-meta time { overflow-wrap: anywhere; }'), 'metadata date cannot force card overflow');
check_f(str_contains($source['css'], '.file-library-actions .btn:focus-visible'), 'action buttons have explicit keyboard focus treatment');
check_f(str_contains($source['css'], '.file-library-detail-modal .modal-body { overflow-wrap: anywhere; }'), 'File Detail values cannot force modal overflow');
check_f(str_contains($source['css'], '.file-library-text-modal .modal-footer .btn'), 'TXT modal footer is mobile-friendly');
check_f(str_contains($source['css'], '.file-library-csv-modal .modal-footer .btn'), 'CSV modal footer is mobile-friendly');
check_f(str_contains($source['css'], 'margin: .25rem;'), 'mobile modal margins are tightened');
check_f(str_contains($source['css'], '@media (pointer: coarse)'), 'existing coarse-pointer touch sizing remains present');
check_f(str_contains($source['css'], 'min-height: 44px'), 'touch actions retain 44px minimum height');
check_f(!str_contains($source['css'], '@import') && !preg_match('/url\s*\(\s*["\']?https?:/i', $source['css']), 'F CSS adds no remote dependency');
check_f(substr_count($source['css'], '{') === substr_count($source['css'], '}'), 'CSS braces remain balanced');

$unchanged = [
    'library' => '265252f2dfc6cfa029ea84199c19edf95e222bb3',
    'preview' => '5cecde972ccfafc943a395ef9db1da5e582e7abd',
    'htaccess' => '070fff9c3b403ec7c61bf57e425a4e28500f42e5',
    'content' => 'a06175237d6848dc04fe2f15474a60a58e99b9f1',
    'api' => 'dbe04ad9a90e4b27fe753f0a1d8ce0b2119050b1',
    'core' => '044a617e754ca63e18a631455df724aac7caa9e3',
    'text' => '79d945ae4c064236fc3c3732808c9f49a16227f3',
    'csv' => '96aee07ac7eb65476d5cda75558f1129c858df11',
];
foreach ($unchanged as $key => $sha) {
    check_f(git_blob_sha($source[$key]) === $sha, "E runtime blob remains byte-identical: {$key}");
}

check_f(!preg_match('/\b(?:eval|assert|system|exec|shell_exec|passthru|proc_open|popen)\s*\(/', $source['ui']), 'F UI contains no common execution primitive');
check_f(!str_contains($source['ui'], 'file_stored_name') && !str_contains($source['ui'], 'APP_FILE_UPLOAD_DIR'), 'F UI exposes no physical storage metadata');

printf("RESULT: PASS %d / FAIL %d\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
