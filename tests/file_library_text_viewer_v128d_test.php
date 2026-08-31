<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'preview' => $root . '/app/file_preview.php',
    'api' => $root . '/public/file_preview_api.php',
    'content' => $root . '/public/file_content.php',
    'loader' => $root . '/public/js/file-library.js',
    'core' => $root . '/public/js/file-library-core.js',
    'js' => $root . '/public/js/file-library-text-preview.js',
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
function check_contract_v128d(bool $condition, string $label): void
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

check_contract_v128d(str_contains($source['version'], "APP_VERSION = '1.28.0-dev.3'"), 'checkpoint version advances to V1.28-D dev.3');
check_contract_v128d(str_contains($source['version'], "APP_ASSET_REVISION = '1.28.0-dev.3'"), 'asset revision advances with TXT Preview JS/CSS');

check_contract_v128d(str_contains($source['preview'], 'function user_file_preview_text'), 'bounded TXT helper exists');
check_contract_v128d(str_contains($source['preview'], "user_file_preview_kind(\$row) !== 'text'"), 'TXT helper enforces preview type');
check_contract_v128d(str_contains($source['preview'], "fopen(\$path, 'rb')"), 'TXT helper streams from resolved path');
check_contract_v128d(str_contains($source['preview'], 'fread($handle, USER_FILE_TEXT_PREVIEW_MAX_BYTES + 4)'), 'TXT helper reads only the bounded byte window');
check_contract_v128d(!str_contains($source['preview'], 'file_get_contents($path)'), 'TXT helper does not read the full file at once');
check_contract_v128d(str_contains($source['preview'], 'USER_FILE_TEXT_PREVIEW_MAX_LINES'), 'TXT helper enforces line ceiling');
check_contract_v128d(str_contains($source['preview'], 'preview_encoding_unsupported'), 'invalid encoding has explicit safe failure path');
check_contract_v128d(str_contains($source['preview'], "str_starts_with(\$text, \"\\xEF\\xBB\\xBF\")"), 'UTF-8 BOM is handled explicitly');
check_contract_v128d(str_contains($source['preview'], "preg_match('//u'"), 'UTF-8 validity is checked without conversion');

check_contract_v128d(str_contains($source['api'], "['detail', 'text']"), 'preview API allowlist is limited to detail and text');
check_contract_v128d(str_contains($source['api'], "\$mode === 'text'"), 'text mode has an explicit branch');
check_contract_v128d(str_contains($source['api'], "user_file_preview_kind(\$row) !== 'text'"), 'API rejects non-TXT rows before text read');
check_contract_v128d(str_contains($source['api'], 'user_file_preview_text($row, $path)'), 'API delegates bounded TXT read to helper');
check_contract_v128d(str_contains($source['api'], "file_preview_error('preview_encoding_unsupported'"), 'encoding failure returns explicit API error');
check_contract_v128d(str_contains($source['api'], 'user_file_library_find_owned($userId, $fileId)'), 'owner-scoped lookup remains mandatory');
check_contract_v128d(str_contains($source['api'], 'user_file_library_content_is_intact($row, $path)'), 'serve-time integrity validation remains mandatory');
check_contract_v128d(str_contains($source['api'], 'X-Content-Type-Options: nosniff'), 'TXT JSON response keeps nosniff');
check_contract_v128d(str_contains($source['api'], 'Cross-Origin-Resource-Policy: same-origin'), 'TXT JSON response keeps same-origin policy');
check_contract_v128d(str_contains($source['api'], 'app_send_no_store_headers()'), 'TXT JSON response remains no-store');

check_contract_v128d(str_contains($source['js'], 'function bindTextPreview()'), 'File Library JS has scoped TXT Preview binding');
check_contract_v128d(str_contains($source['js'], "querySelector('.file-library-preview-icon.fa-file-alt')"), 'TXT Preview is attached only to TXT cards');
check_contract_v128d(str_contains($source['js'], "'&mode=text'"), 'TXT Preview calls fixed protected text endpoint');
check_contract_v128d(str_contains($source['js'], "credentials: 'same-origin'"), 'TXT Preview request keeps same-origin credentials');
check_contract_v128d(str_contains($source['js'], 'content.textContent = text.content'), 'TXT body is rendered strictly with textContent');
check_contract_v128d(!str_contains($source['js'], 'content.innerHTML = text.content'), 'TXT body is never assigned as HTML');
check_contract_v128d(str_contains($source['js'], 'text.truncated !== true'), 'bounded response exposes truncation notice');
check_contract_v128d(str_contains($source['js'], "downloadLink.setAttribute('href', './file_content.php?id=' + encodeURIComponent(targetId) + '&mode=download')"), 'TXT modal retains protected full download');
check_contract_v128d(str_contains($source['js'], "badge.textContent = 'V1.28-D'") || str_contains($source['js'], "lateBadge.textContent = 'V1.28-D'"), 'visible phase marker advances to V1.28-D');
check_contract_v128d(str_contains($source['core'], 'bindImageViewer();') && str_contains($source['core'], 'bindPdfViewer();') && str_contains($source['core'], 'bindFileDetail();'), 'C Image Viewer, PDF Viewer and File Detail remain byte-preserved in core');
check_contract_v128d(str_contains($source['loader'], "loadScript('file-library-core.js'") && str_contains($source['loader'], "loadScript('file-library-text-preview.js'"), 'small loader sequences C core before D TXT module');
check_contract_v128d(str_contains($source['content'], "\$contentModes[] = 'preview';") && str_contains($source['content'], 'user_file_library_is_inline_pdf($row)'), 'C PDF preview server boundary remains intact');
check_contract_v128d(!preg_match('/\b(?:eval|Function)\s*\(/', $source['js']), 'TXT Preview adds no dynamic code execution primitive');
check_contract_v128d(!preg_match('/https?:\/\//i', $source['js']) && !preg_match('/https?:\/\//i', $source['loader']), 'TXT Preview and loader add no remote dependency');

check_contract_v128d(str_contains($source['css'], '.file-library-text-modal'), 'TXT Preview has scoped modal styling');
check_contract_v128d(str_contains($source['css'], '.file-library-text-content:not([hidden])'), 'TXT content has explicit visible-state styling');
check_contract_v128d(str_contains($source['css'], 'white-space: pre-wrap'), 'TXT whitespace is preserved without HTML rendering');
check_contract_v128d(str_contains($source['css'], '@media (max-width: 575.98px)'), 'TXT Preview keeps smartphone-specific CSS');
check_contract_v128d(str_contains($source['css'], '.file-library-text-modal .modal-dialog'), 'TXT modal participates in smartphone dialog margins');
check_contract_v128d(!str_contains($source['css'], '@import') && !preg_match('/url\s*\(\s*["\']?https?:/i', $source['css']), 'TXT Preview CSS adds no remote dependency');
check_contract_v128d(substr_count($source['css'], '{') === substr_count($source['css'], '}'), 'TXT Preview CSS braces remain balanced');

printf("RESULT: PASS %d / FAIL %d\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
