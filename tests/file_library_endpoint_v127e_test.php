<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'page' => $root . '/public/file-library.php',
    'content' => $root . '/public/file_content.php',
    'helper' => $root . '/app/file_library.php',
    'upload' => $root . '/app/user_file.php',
    'htaccess' => $root . '/public/.htaccess',
    'drawer' => $root . '/public/js/drawer-categories.js',
    'js' => $root . '/public/js/file-library.js',
    'css' => $root . '/public/css/file-library.css',
    'version' => $root . '/app/version.php',
];
$source = [];
foreach ($files as $key => $path) {
    $data = @file_get_contents($path);
    if (!is_string($data)) { fwrite(STDERR, "FAIL: unable to read {$path}\n"); exit(1); }
    $source[$key] = $data;
}
$pass = 0; $fail = 0;
function check(bool $condition, string $label): void
{
    global $pass, $fail;
    if ($condition) { $pass++; echo "PASS: {$label}\n"; }
    else { $fail++; echo "FAIL: {$label}\n"; }
}

check(str_contains($source['page'], "app_session_user_id()"), 'File Library page requires authenticated session identity');
check(str_contains($source['page'], "header('Location: ./', true, 302)"), 'unauthenticated File Library page redirects to login');
check(str_contains($source['page'], "app_csrf_is_valid"), 'File Library state changes require CSRF validation');
check(str_contains($source['page'], "['upload', 'delete']"), 'File Library POST actions are allowlisted');
check(!str_contains($source['page'], "\$_POST['user_id']") && !str_contains($source['page'], "\$_GET['user_id']"), 'page never trusts request user id');
check(str_contains($source['page'], 'user_file_store_upload($currentUserId, $file)'), 'upload owner comes from authenticated user');
check(str_contains($source['page'], 'user_file_library_delete_owned($currentUserId, $fileId)'), 'delete owner comes from authenticated user');
check(str_contains($source['page'], 'APP_FILE_UPLOAD_MAX_REQUEST_BYTES'), 'page enforces upload request-size guard');
check(strpos($source['page'], 'APP_FILE_UPLOAD_MAX_REQUEST_BYTES') < strpos($source['page'], 'app_csrf_is_valid'), 'request-size guard runs before CSRF so PHP post_max_size overflow fails as too large');
check(str_contains($source['page'], 'enctype="multipart/form-data"'), 'upload form uses multipart encoding');
check(str_contains($source['js'], ".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.zip"), 'browser accept hint is upgraded to include ZIP');
check(str_contains($source['page'], 'row-cols-2 row-cols-md-3 row-cols-xl-4'), 'mobile baseline is two-column File Library grid');
check(str_contains($source['page'], 'loading="lazy"'), 'image previews are lazy-loaded');
check(str_contains($source['page'], 'mode=thumb'), 'image cards use authenticated content endpoint for preview');
check(str_contains($source['page'], 'mode=view'), 'image cards expose secure view action');
check(str_contains($source['page'], 'mode=download'), 'all cards expose secure download action');
check(!str_contains($source['page'], 'file_stored_name'), 'physical stored filename is never rendered by File Library page');
check(!str_contains($source['page'], 'APP_FILE_UPLOAD_DIR'), 'physical upload directory is never rendered by File Library page');
check(str_contains($source['page'], 'name="return_page"'), 'delete preserves pagination position');
check(str_contains($source['page'], 'V1.27-E'), 'base page retains V1.27-E label before F runtime enhancement');
check(str_contains($source['js'], "modalElement.id = 'fileLibraryImageModal'") && str_contains($source['js'], "modalElement.className = 'modal fade file-library-image-modal'"), 'V1.27-F image modal is created by File Library JS');

check(str_contains($source['helper'], 'const USER_FILE_LIBRARY_PAGE_SIZE = 24'), 'server page size is fixed at 24');
check(str_contains($source['helper'], 'file_owner = :owner AND file_flag = 0'), 'list/find queries are owner scoped and active-only');
check(str_contains($source['helper'], 'ORDER BY file_id DESC LIMIT '), 'File Library ordering/pagination is deterministic');
check(str_contains($source['helper'], 'file_owner = :owner AND file_flag = 0 LIMIT 1'), 'single-file lookup is owner scoped');
check(str_contains($source['helper'], 'SET file_flag = 1'), 'delete is soft-delete at metadata boundary');
check(str_contains($source['helper'], 'user_file_library_resolve_path'), 'physical path is derived from validated metadata only');
check(str_contains($source['helper'], "[a-f0-9]{64}"), 'stored random filename format is revalidated before filesystem access');
check(str_contains($source['helper'], 'user_file_path_is_within'), 'resolved path must stay within private storage');
check(str_contains($source['helper'], 'user_file_library_content_is_intact'), 'content is revalidated before serving');
check(str_contains($source['helper'], 'user_file_detect_mime'), 'serve-time MIME is server detected');
check(str_contains($source['helper'], 'user_file_validate_image_content'), 'serve-time image structure is revalidated');
check(str_contains($source['helper'], 'user_file_validate_non_image_content'), 'serve-time non-image signature/content is revalidated');

check(str_contains($source['content'], "REQUEST_METHOD") && str_contains($source['content'], "'GET'"), 'content endpoint is GET-only');
check(str_contains($source['content'], 'app_session_user_id()'), 'content endpoint requires login');
check(str_contains($source['content'], 'user_file_library_find_owned($userId, $fileId)'), 'content lookup uses authenticated owner');
check(!str_contains($source['content'], "\$_GET['path']") && !str_contains($source['content'], "\$_GET['name']"), 'content endpoint never accepts filesystem path/name');
check(str_contains($source['content'], "['view', 'thumb', 'download']"), 'content modes are allowlisted');
check(str_contains($source['content'], 'user_file_library_is_inline_image'), 'inline content is limited to validated images');
check(str_contains($source['content'], 'Content-Disposition:'), 'content endpoint sets explicit disposition');
check(str_contains($source['content'], 'X-Content-Type-Options: nosniff'), 'content endpoint disables MIME sniffing');
check(str_contains($source['content'], 'Cross-Origin-Resource-Policy: same-origin'), 'content endpoint applies same-origin resource policy');
check(str_contains($source['content'], "Content-Security-Policy: default-src 'none'; sandbox"), 'inline response receives restrictive CSP');
check(str_contains($source['content'], 'app_send_no_store_headers()'), 'file responses are no-store');
check(str_contains($source['content'], 'readfile($path)'), 'content is streamed from resolved server path only');
check(!preg_match('/\b(?:eval|exec|shell_exec|system|passthru|proc_open|popen)\s*\(/', $source['content']), 'content endpoint has no code execution primitive');

check(str_contains($source['htaccess'], 'file-library\\.php$'), 'File Library page is explicit in public PHP allowlist');
check(str_contains($source['htaccess'], 'file_content\\.php$'), 'content endpoint is explicit in public PHP allowlist');
check(str_contains($source['htaccess'], 'file_upload_api\\.php$'), 'V1.27-D upload API remains allowlisted');
check(str_contains($source['htaccess'], 'RewriteRule ^file-library/?$ file-library.php [L,QSA]'), 'canonical /file-library route is configured');
check(str_contains($source['drawer'], "'./file-library'"), 'shared Drawer categories include File Library href');
check(str_contains($source['drawer'], 'ensureFileLibraryItem'), 'shared Drawer injects File Library into existing pages');
check(str_contains($source['drawer'], "text('File Library')"), 'Drawer uses File Library label');
check(str_contains($source['drawer'], "data-drawer-categories', 'v1.27-e1'"), 'Drawer checkpoint marker is updated');
check(str_contains($source['js'], 'window.confirm'), 'delete action has explicit browser confirmation');
check(str_contains($source['js'], "button.disabled = true"), 'upload button is guarded against duplicate submit');
check(str_contains($source['css'], '@media (max-width: 575.98px)'), 'File Library has smartphone-specific CSS');
check(str_contains($source['css'], '@media (pointer: coarse)'), 'coarse pointer controls preserve touch height');
check(!str_contains($source['css'], '@import') && !preg_match('/url\s*\(\s*["\']?https?:/i', $source['css']), 'File Library CSS adds no remote dependency');
check(substr_count($source['css'], '{') === substr_count($source['css'], '}'), 'File Library CSS braces are balanced');
check(str_contains($source['version'], "APP_ASSET_REVISION = '1.27.0'"), 'asset revision is formal V1.27.0');
check(str_contains($source['version'], "APP_VERSION = '1.27.0'"), 'formal APP_VERSION is V1.27.0');
check(str_contains($source['upload'], 'random_bytes(32)'), 'D random physical filename protection remains present');
check(str_contains($source['upload'], 'finfo(FILEINFO_MIME_TYPE)'), 'D server-side MIME detection remains present');
check(str_contains($source['upload'], 'move_uploaded_file'), 'D HTTP upload move boundary remains present');
check(str_contains($source['upload'], "'10485760'"), 'default upload size is 10 MiB');
check(str_contains($source['upload'], "'zip' => ['mimes'"), 'ZIP is server-side allowlisted');
check(str_contains($source['upload'], 'application/zip'), 'ZIP MIME is checked server-side');
check(str_contains($source['upload'], 'PK\\x03\\x04') || str_contains($source['upload'], 'PK\x03\x04'), 'ZIP signature validation is present');
check(str_contains($source['js'], '1ファイル最大10 MiB'), 'File Library runtime UI explains 10 MiB limit');
check(str_contains($source['js'], 'ZIP'), 'File Library runtime UI explains ZIP support');
check(str_contains($source['css'], 'grid-row: 2') && str_contains($source['css'], '.file-library-upload-submit'), 'upload input and button share the same explicit grid row');
check(str_contains($source['js'], 'spinner-border spinner-border-sm'), 'upload submit shows Bootstrap loading spinner');
check(str_contains($source['js'], 'アップロード中…'), 'upload submit exposes loading text');

echo "SUMMARY: {$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
