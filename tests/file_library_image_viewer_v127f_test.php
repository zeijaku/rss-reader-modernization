<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'page' => $root . '/public/file-library.php',
    'js' => $root . '/public/js/file-library.js',
    'css' => $root . '/public/css/file-library.css',
    'content' => $root . '/public/file_content.php',
    'helper' => $root . '/app/file_library.php',
    'version' => $root . '/app/version.php',
    'migration' => $root . '/database/migrations/020_v1_27_user_files.sql',
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

$check(str_contains($source['version'], "APP_ASSET_REVISION = '1.27.0'"), 'formal V1.27.0 asset revision is set while F viewer behavior remains intact');
$check(str_contains($source['version'], "APP_VERSION = '1.27.0'"), 'formal version is 1.27.0');
$check(str_contains($source['js'], "badge.textContent = 'V1.27-F'"), 'runtime File Library badge advances to F');
$check(str_contains($source['js'], "modalElement.id = 'fileLibraryImageModal'"), 'Image Viewer creates one named modal');
$check(str_contains($source['js'], "modalElement.className = 'modal fade file-library-image-modal'"), 'Image Viewer uses Bootstrap modal classes');
$check(str_contains($source['js'], "setAttribute('aria-labelledby', 'fileLibraryImageModalTitle')"), 'Image Viewer modal has accessible labelled-by relationship');
$check(str_contains($source['js'], 'aria-label="閉じる"'), 'Image Viewer exposes accessible close button');
$check(str_contains($source['js'], 'id="fileLibraryImageLoading"') && str_contains($source['js'], 'spinner-border'), 'Image Viewer creates loading indicator');
$check(str_contains($source['js'], 'id="fileLibraryImageError"') && str_contains($source['js'], '画像を表示できませんでした。'), 'Image Viewer creates load-error state');
$check(str_contains($source['js'], 'id="fileLibraryImageViewer"') && str_contains($source['js'], 'hidden>'), 'full image starts hidden until load succeeds');
$check(str_contains($source['js'], "querySelectorAll('.file-library-card')"), 'viewer enhancement is scoped to File Library cards');
$check(str_contains($source['js'], "querySelector('.file-library-actions a[href*=\"mode=view\"]')"), 'only cards with existing image view action are enhanced');
$check(str_contains($source['js'], "preview.querySelector('img')"), 'viewer requires an existing image thumbnail');
$check(str_contains($source['js'], "fileIdFromViewHref"), 'viewer derives file id from server-rendered protected view link');
$check(str_contains($source['js'], "href.match(/[?&]id=([1-9]\\d*)(?:&|$)/)"), 'viewer accepts only positive numeric id from view link');
$check(str_contains($source['js'], "document.createElement('a')"), 'viewer creates a keyboard-accessible thumbnail link');
$check(str_contains($source['js'], "previewLink.setAttribute('aria-label', fileName + 'を拡大表示')"), 'thumbnail viewer link receives accessible label');
$check(str_contains($source['js'], "viewLink.removeAttribute('target')") || str_contains($source['js'], "trigger.removeAttribute('target')"), 'legacy image new-tab target is removed after viewer enhancement');
$check(str_contains($source['js'], "trigger.setAttribute('href', './file_content.php?id='"), 'enhanced links keep protected endpoint fallback href');
$check(!str_contains($source['js'], 'data-view-url'), 'viewer does not accept arbitrary URL from DOM');

$check(str_contains($source['js'], 'function bindImageViewer()'), 'Image Viewer JS binding exists');
$check(str_contains($source['js'], 'window.bootstrap.Modal'), 'viewer uses existing Bootstrap Modal implementation');
$check(str_contains($source['js'], 'Modal.getOrCreateInstance'), 'viewer reuses one modal instance');
$check(str_contains($source['js'], "if (!/^[1-9]\\d*$/.test(fileId))"), 'click handler revalidates positive numeric file id');
$check(str_contains($source['js'], "'./file_content.php?id=' + encodeURIComponent(fileId) + '&mode=view'"), 'full image source is constructed from fixed protected endpoint');
$check(str_contains($source['js'], 'event.preventDefault()'), 'viewer intercepts navigation only in enhanced click path');
$check(str_contains($source['js'], 'title.textContent = fileName'), 'filename is rendered with textContent rather than HTML');
$check(str_contains($source['js'], 'image.alt = fileName'), 'viewer image receives filename alt text');
$check(str_contains($source['js'], "image.addEventListener('load'"), 'viewer handles successful image load');
$check(str_contains($source['js'], "image.addEventListener('error'"), 'viewer handles image load failure');
$check(str_contains($source['js'], "modalElement.addEventListener('hidden.bs.modal', resetViewer)"), 'viewer clears state when modal closes');
$check(str_contains($source['js'], "image.removeAttribute('src')"), 'full image source is removed after viewer closes');
$check(str_contains($source['js'], 'modal.show()'), 'validated trigger opens modal');
$check(!preg_match('/\b(?:eval|Function)\s*\(/', $source['js']), 'viewer adds no dynamic code execution primitive');

$check(str_contains($source['css'], '.file-library-preview-link') && str_contains($source['css'], 'cursor: zoom-in'), 'image thumbnail communicates zoom interaction');
$check(str_contains($source['css'], '.file-library-preview-link:focus-visible'), 'thumbnail viewer trigger has keyboard focus treatment');
$check(str_contains($source['css'], '.file-library-viewer-image:not([hidden])'), 'viewer image has explicit visible-state styling');
$check(str_contains($source['css'], 'object-fit: contain'), 'viewer keeps full image aspect ratio');
$check(str_contains($source['css'], '@media (max-width: 575.98px)'), 'viewer keeps smartphone-specific responsive layer');
$check(str_contains($source['css'], '.file-library-image-modal .modal-dialog { margin: .5rem; }'), 'smartphone viewer modal keeps edge margin');
$check(!str_contains($source['css'], '@import') && !preg_match('/url\s*\(\s*["\']?https?:/i', $source['css']), 'viewer adds no external CSS dependency');
$check(substr_count($source['css'], '{') === substr_count($source['css'], '}'), 'viewer CSS braces remain balanced');

$check(str_contains($source['page'], 'href="./file_content.php?id=<?php echo $fileId; ?>&amp;mode=view"'), 'server page still emits protected image view link');
$check(str_contains($source['page'], 'href="./file_content.php?id=<?php echo $fileId; ?>&amp;mode=download"'), 'existing protected download action is unchanged');
$check(!str_contains($source['page'], 'file_stored_name') && !str_contains($source['page'], 'APP_FILE_UPLOAD_DIR'), 'viewer page exposes no physical filename or storage path');
$check(str_contains($source['content'], "['view', 'thumb', 'download']"), 'protected content endpoint keeps strict mode allowlist');
$check(str_contains($source['content'], 'user_file_library_find_owned($userId, $fileId)'), 'viewer content remains owner scoped');
$check(str_contains($source['content'], '$inline && !user_file_library_is_inline_image($row)'), 'non-image inline viewing remains denied');
$check(str_contains($source['content'], 'user_file_library_content_is_intact($row, $path)'), 'viewer content is revalidated at serve time');
$check(str_contains($source['content'], 'X-Content-Type-Options: nosniff'), 'viewer response remains nosniff');
$check(str_contains($source['content'], 'Cross-Origin-Resource-Policy: same-origin'), 'viewer response remains same-origin');
$check(str_contains($source['content'], "Content-Security-Policy: default-src 'none'; sandbox"), 'viewer response retains restrictive CSP');
$check(!str_contains($source['js'], 'file_stored_name') && !str_contains($source['js'], 'APP_FILE_UPLOAD_DIR'), 'viewer JS knows no physical filename or storage path');
$check(str_contains($source['helper'], 'user_file_library_is_inline_image'), 'server image eligibility helper remains authoritative');
$check(!str_contains($source['migration'], 'image_view') && !str_contains($source['migration'], 'viewer'), 'F requires no DB schema expansion');

$check(str_contains($source['js'], 'bindUploadDropZone();'), 'E4 drag-and-drop binding is retained');
$check(str_contains($source['js'], 'spinner-border spinner-border-sm'), 'E3 upload spinner is retained');
$check(str_contains($source['js'], '1ファイル最大10 MiB'), 'E3 10 MiB upload UI is retained');
$check(str_contains($source['js'], 'ZIP'), 'E3 ZIP upload UI is retained');

printf("RESULT: PASS %d / FAIL %d\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
