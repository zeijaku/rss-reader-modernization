<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$js = file_get_contents($root . '/public/js/file-library.js');
$css = file_get_contents($root . '/public/css/file-library.css');
$version = file_get_contents($root . '/app/version.php');

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

$check(is_string($js), 'File Library JS is readable');
$check(is_string($css), 'File Library CSS is readable');
$check(is_string($version), 'version marker is readable');
$check(str_contains((string) $version, "APP_ASSET_REVISION = '1.27.0-dev-g1'"), 'asset revision is G1 while E4 drag-and-drop remains intact');
$check(str_contains((string) $js, 'bindUploadDropZone'), 'drag-and-drop binding exists');
$check(str_contains((string) $js, "addEventListener('dragenter'"), 'dragenter is handled');
$check(str_contains((string) $js, "addEventListener('dragover'"), 'dragover is handled');
$check(str_contains((string) $js, "addEventListener('dragleave'"), 'dragleave is handled');
$check(str_contains((string) $js, "addEventListener('drop'"), 'drop is handled');
$check(str_contains((string) $js, 'event.preventDefault()'), 'browser file-open default is prevented inside drop zone');
$check(str_contains((string) $js, "files.length !== 1"), 'multiple dropped files are rejected');
$check(str_contains((string) $js, 'input.files = files') || str_contains((string) $js, 'input.files = transfer.files'), 'dropped file is assigned to the existing file input');
$check(str_contains((string) $js, "new DataTransfer()"), 'DataTransfer fallback is present');
$check(str_contains((string) $js, "dispatchEvent(new Event('change'"), 'normal file-input change flow is preserved');
$check(!str_contains((string) $js, 'form.submit()') && !str_contains((string) $js, 'form.requestSubmit()'), 'drop does not auto-upload');
$check(str_contains((string) $js, 'ドラッグ＆ドロップでも指定できます'), 'UI explains drag-and-drop availability');
$check(str_contains((string) $css, '.file-library-upload-row.is-drag-over'), 'drag-over visual state exists');
$check(str_contains((string) $css, 'box-shadow: inset 0 0 0 2px'), 'drag-over state has visible boundary');
$check(str_contains((string) $js, 'spinner-border spinner-border-sm'), 'E3 upload spinner remains unchanged');
$check(str_contains((string) $js, '.jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.zip'), 'E3 accepted type hint remains unchanged');
$check(str_contains((string) $js, '1ファイル最大10 MiB'), 'E3 10 MiB UI remains unchanged');

printf("RESULT: PASS %d / FAIL %d\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
