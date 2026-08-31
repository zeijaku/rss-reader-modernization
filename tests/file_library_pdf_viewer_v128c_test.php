<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'library' => $root . '/app/file_library.php',
    'content' => $root . '/public/file_content.php',
    'js' => $root . '/public/js/file-library.js',
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

check_contract(str_contains($source['version'], "APP_VERSION = '1.28.0-dev.2'"), 'checkpoint version is V1.28-C dev.2');
check_contract(str_contains($source['version'], "APP_ASSET_REVISION = '1.28.0-dev.2'"), 'asset revision advances with PDF Viewer JS/CSS');

check_contract(str_contains($source['library'], 'function user_file_library_is_inline_pdf'), 'server has explicit PDF inline eligibility helper');
check_contract(str_contains($source['library'], "=== 'pdf'") && str_contains($source['library'], "=== 'application/pdf'"), 'PDF inline eligibility requires extension and MIME');
check_contract(str_contains($source['content'], "['view', 'thumb', 'download']"), 'V1.27 content modes remain represented');
check_contract(str_contains($source['content'], '$contentModes[] = \'preview\';'), 'V1.28-C adds one explicit preview mode');
check_contract(str_contains($source['content'], '$pdfPreview = $mode === \'preview\';'), 'preview mode is handled separately from image view/thumb');
check_contract(str_contains($source['content'], 'user_file_library_is_inline_pdf($row)'), 'preview endpoint delegates PDF type authority to server helper');
check_contract(str_contains($source['content'], '$inline && !user_file_library_is_inline_image($row)'), 'existing image inline guard remains intact');
check_contract(str_contains($source['content'], 'user_file_library_content_is_intact($row, $path)'), 'PDF is revalidated at serve time before output');
check_contract(str_contains($source['content'], 'Content-Disposition:'), 'PDF response has explicit content disposition');
check_contract(str_contains($source['content'], 'X-Content-Type-Options: nosniff'), 'PDF response keeps nosniff');
check_contract(str_contains($source['content'], 'Cross-Origin-Resource-Policy: same-origin'), 'PDF response keeps same-origin resource policy');
check_contract(str_contains($source['content'], "Content-Security-Policy: default-src 'none'; sandbox"), 'PDF response keeps restrictive sandbox CSP');
check_contract(str_contains($source['content'], 'app_send_no_store_headers()'), 'PDF response remains no-store');
check_contract(str_contains($source['content'], 'readfile($path)'), 'PDF bytes are streamed only from resolved private path');

check_contract(str_contains($source['js'], 'function bindPdfViewer()'), 'File Library JS has scoped PDF Viewer binding');
check_contract(str_contains($source['js'], "querySelector('.file-library-preview-icon.fa-file-pdf')"), 'PDF action is only attached to server-rendered PDF cards');
check_contract(str_contains($source['js'], "'./file_content.php?id=' + encodeURIComponent(fileId) + '&mode=preview'"), 'PDF Viewer constructs fixed protected preview endpoint');
check_contract(str_contains($source['js'], "setAttribute('target', '_blank')"), 'PDF action retains new-tab fallback before enhancement');
check_contract(str_contains($source['js'], 'navigator.pdfViewerEnabled'), 'browser native PDF viewer capability is considered');
check_contract(str_contains($source['js'], 'window.bootstrap.Modal.getOrCreateInstance'), 'PDF Viewer reuses existing Bootstrap Modal runtime');
check_contract(str_contains($source['js'], 'fileLibraryPdfFrame'), 'PDF Viewer uses one dedicated iframe');
check_contract(str_contains($source['js'], 'referrerpolicy="no-referrer"'), 'PDF iframe suppresses referrer propagation');
check_contract(str_contains($source['js'], "frame.removeAttribute('src')"), 'PDF bytes are released when modal resets');
check_contract(str_contains($source['js'], 'title.textContent = fileName'), 'PDF filename is assigned as text rather than HTML');
check_contract(str_contains($source['js'], "downloadLink.setAttribute('href', './file_content.php?id=' + encodeURIComponent(fileId) + '&mode=download')"), 'PDF modal keeps protected download fallback');
check_contract(str_contains($source['js'], "badge.textContent = 'V1.28-C'"), 'visible phase marker advances to V1.28-C');
check_contract(str_contains($source['js'], 'bindImageViewer();') && str_contains($source['js'], 'bindFileDetail();'), 'Image Viewer and File Detail remain initialized');
check_contract(!str_contains(strtolower($source['js']), 'pdf.js') && !str_contains(strtolower($source['js']), 'pdfjs'), 'PDF.js dependency is not introduced');
check_contract(!preg_match('/\b(?:eval|Function)\s*\(/', $source['js']), 'PDF Viewer adds no dynamic code execution primitive');
check_contract(!preg_match('/https?:\/\//i', $source['js']), 'PDF Viewer JS adds no remote URL dependency');

check_contract(str_contains($source['css'], '.file-library-pdf-modal'), 'PDF Viewer has scoped modal styling');
check_contract(str_contains($source['css'], '.file-library-pdf-frame:not([hidden])'), 'PDF iframe has explicit visible-state sizing');
check_contract(str_contains($source['css'], '@media (max-width: 575.98px)'), 'PDF Viewer keeps smartphone-specific CSS');
check_contract(str_contains($source['css'], '.file-library-pdf-stage { min-height: 300px; height: calc(100vh - 11rem); }'), 'smartphone PDF stage is viewport-bounded');
check_contract(!str_contains($source['css'], '@import') && !preg_match('/url\s*\(\s*["\']?https?:/i', $source['css']), 'PDF Viewer CSS adds no remote dependency');
check_contract(substr_count($source['css'], '{') === substr_count($source['css'], '}'), 'PDF Viewer CSS braces remain balanced');

printf("RESULT: PASS %d / FAIL %d\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
