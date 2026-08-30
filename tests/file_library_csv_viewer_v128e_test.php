<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'preview' => $root . '/app/file_preview.php',
    'api' => $root . '/public/file_preview_api.php',
    'loader' => $root . '/public/js/file-library.js',
    'core' => $root . '/public/js/file-library-core.js',
    'text' => $root . '/public/js/file-library-text-preview.js',
    'csv' => $root . '/public/js/file-library-csv-preview.js',
    'css' => $root . '/public/css/file-library.css',
    'version' => $root . '/app/version.php',
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
function check_contract_e(bool $condition, string $label): void
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

check_contract_e(str_contains($source['version'], "APP_VERSION = '1.28.0-dev.4'"), 'checkpoint version is V1.28-E dev.4');
check_contract_e(str_contains($source['version'], "APP_ASSET_REVISION = '1.28.0-dev.4'"), 'asset revision advances with CSV Preview assets');

check_contract_e(str_contains($source['preview'], 'USER_FILE_CSV_PREVIEW_MAX_BYTES = 524288'), 'CSV helper fixes 512 KiB source ceiling');
check_contract_e(str_contains($source['preview'], 'USER_FILE_CSV_PREVIEW_MAX_ROWS = 50'), 'CSV helper fixes 50 data row ceiling');
check_contract_e(str_contains($source['preview'], 'USER_FILE_CSV_PREVIEW_MAX_COLUMNS = 30'), 'CSV helper fixes 30 column ceiling');
check_contract_e(str_contains($source['preview'], 'USER_FILE_CSV_PREVIEW_MAX_RECORD_BYTES = 65536'), 'CSV helper fixes 64 KiB record ceiling');
check_contract_e(str_contains($source['preview'], 'function user_file_preview_csv('), 'dedicated CSV preview helper exists');
check_contract_e(str_contains($source['preview'], "fread(\$handle, USER_FILE_CSV_PREVIEW_MAX_BYTES + 4)"), 'CSV source read is bounded before parsing');
check_contract_e(str_contains($source['preview'], "php://temp/maxmemory:1048576"), 'CSV parser works from a bounded temporary stream');
check_contract_e(str_contains($source['preview'], 'fgetcsv('), 'CSV uses PHP CSV parser rather than string splitting');
check_contract_e(str_contains($source['preview'], "fgetcsv(\$stream, 0, ',', '\"', '')"), 'CSV parser specifies comma/enclosure/empty escape explicitly');
check_contract_e(str_contains($source['preview'], 'preview_record_too_large'), 'oversized logical CSV record fails safely');
check_contract_e(str_contains($source['preview'], "str_contains(\$csv, \"\\0\")"), 'CSV preview rejects NUL content in bounded prefix');
check_contract_e(str_contains($source['preview'], 'user_file_preview_utf8_prefix($csv, $truncatedByBytes)'), 'CSV preview requires safe UTF-8 prefix');
check_contract_e(!str_contains($source['preview'], 'eval('), 'CSV helper has no dynamic code execution');

check_contract_e(str_contains($source['api'], "['detail', 'text', 'csv']"), 'preview API allowlists only detail/text/csv modes');
check_contract_e(str_contains($source['api'], "\$mode === 'csv'"), 'preview API has explicit CSV branch');
check_contract_e(str_contains($source['api'], "user_file_preview_kind(\$row) !== 'csv'"), 'CSV API branch rejects non-CSV files');
check_contract_e(str_contains($source['api'], 'user_file_preview_csv($row, $path)'), 'CSV API delegates parsing to bounded helper');
check_contract_e(str_contains($source['api'], "preview_record_too_large', 'CSV record exceeds the preview limit.', 422"), 'record-limit failure is a safe 422 response');
check_contract_e(str_contains($source['api'], "preview_encoding_unsupported', 'CSV preview requires UTF-8 text.', 422"), 'encoding failure is a safe 422 response');
check_contract_e(str_contains($source['api'], 'user_file_library_find_owned($userId, $fileId)'), 'CSV preview remains owner-scoped');
check_contract_e(str_contains($source['api'], 'user_file_library_content_is_intact($row, $path)'), 'CSV preview revalidates stored content before parse');
check_contract_e(str_contains($source['api'], 'X-Content-Type-Options: nosniff'), 'CSV JSON response keeps nosniff');
check_contract_e(str_contains($source['api'], 'Cross-Origin-Resource-Policy: same-origin'), 'CSV JSON response keeps same-origin policy');
check_contract_e(str_contains($source['api'], 'app_send_no_store_headers()'), 'CSV JSON response remains no-store');

check_contract_e(str_contains($source['loader'], "loadScript('file-library-core.js'"), 'loader keeps existing core first');
check_contract_e(str_contains($source['loader'], "loadScript('file-library-text-preview.js'"), 'loader keeps TXT module second');
check_contract_e(str_contains($source['loader'], "loadScript('file-library-csv-preview.js'"), 'loader adds CSV module after TXT');
check_contract_e(strpos($source['loader'], 'file-library-core.js') < strpos($source['loader'], 'file-library-text-preview.js')
    && strpos($source['loader'], 'file-library-text-preview.js') < strpos($source['loader'], 'file-library-csv-preview.js'), 'loader preserves deterministic core/TXT/CSV order');

check_contract_e(str_contains($source['csv'], 'function bindCsvPreview()'), 'CSV UI uses a scoped binding module');
check_contract_e(str_contains($source['csv'], "querySelector('.file-library-preview-icon.fa-file-csv')"), 'CSV action attaches only to server-rendered CSV cards');
check_contract_e(str_contains($source['csv'], "'&mode=csv'"), 'CSV UI calls protected csv preview mode');
check_contract_e(str_contains($source['csv'], "credentials: 'same-origin'"), 'CSV fetch keeps authenticated same-origin credentials');
check_contract_e(str_contains($source['csv'], 'document.createElement(\'table\')'), 'CSV table is built with DOM APIs');
check_contract_e(str_contains($source['csv'], 'th.textContent ='), 'CSV header cells are assigned through textContent');
check_contract_e(str_contains($source['csv'], 'td.textContent ='), 'CSV data cells are assigned through textContent');
check_contract_e(!preg_match('/(?:th|td)\.innerHTML\s*=/', $source['csv']), 'CSV user cells are never assigned through innerHTML');
check_contract_e(str_contains($source['csv'], "badge.textContent = 'V1.28-E'"), 'visible phase marker advances to V1.28-E');
check_contract_e(str_contains($source['csv'], "'./file_content.php?id=' + encodeURIComponent(targetId) + '&mode=download'"), 'CSV modal keeps protected full-download path');
check_contract_e(!preg_match('/\b(?:eval|Function)\s*\(/', $source['csv']), 'CSV module adds no dynamic code execution primitive');
check_contract_e(!preg_match('/https?:\/\//i', $source['csv']), 'CSV module adds no remote dependency');

check_contract_e(str_contains($source['css'], '.file-library-csv-table-wrap:not([hidden])'), 'CSV table has bounded scroll container');
check_contract_e(str_contains($source['css'], 'overflow: auto'), 'CSV wide/long table can scroll instead of expanding layout');
check_contract_e(str_contains($source['css'], '.file-library-csv-table th'), 'CSV header receives scoped table styling');
check_contract_e(str_contains($source['css'], '.file-library-csv-modal .modal-dialog'), 'CSV modal participates in smartphone margin rule');
check_contract_e(str_contains($source['css'], '@media (max-width: 575.98px)'), 'CSV keeps smartphone-specific styling');
check_contract_e(!str_contains($source['css'], '@import') && !preg_match('/url\s*\(\s*["\']?https?:/i', $source['css']), 'CSV CSS adds no remote dependency');
check_contract_e(substr_count($source['css'], '{') === substr_count($source['css'], '}'), 'CSV CSS braces remain balanced');

check_contract_e(hash('sha1', "blob " . strlen($source['core']) . "\0" . $source['core']) === '044a617e754ca63e18a631455df724aac7caa9e3', 'V1.28-C core JS remains byte-identical');
check_contract_e(hash('sha1', "blob " . strlen($source['text']) . "\0" . $source['text']) === '79d945ae4c064236fc3c3732808c9f49a16227f3', 'V1.28-D TXT module remains byte-identical');

printf("RESULT: PASS %d / FAIL %d\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
