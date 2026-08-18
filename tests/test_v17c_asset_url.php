<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/version.php';
require_once dirname(__DIR__) . '/app/asset.php';

$checks = [];
$check = static function (bool $condition, string $message) use (&$checks): void {
    $checks[] = $condition;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
};

$revision = defined('APP_ASSET_REVISION') && trim((string) APP_ASSET_REVISION) !== ''
    ? rawurlencode((string) APP_ASSET_REVISION)
    : rawurlencode(APP_VERSION);
$check(app_asset_url('css/dashboard.css') === './css/dashboard.css?v=' . $revision, 'CSS URL uses the active asset revision');
$check(app_asset_url('js/dashboard.js') === './js/dashboard.js?v=' . $revision, 'JavaScript URL uses the active asset revision');
$check(app_asset_url('favicon.png') === './favicon.png?v=' . $revision, 'favicon URL uses the active asset revision');
$check(app_asset_url('css/bootstrap-solar-5.3.8.min.css') === './css/bootstrap-solar-5.3.8.min.css?v=' . $revision, 'allowlisted Theme CSS is supported');

$invalid = [
    '', '../config/local.php', 'css/../config.css', '/css/dashboard.css',
    'https://example.com/app.js', '//example.com/app.js', 'css/dashboard.css?v=old',
    'css/dashboard.css#fragment', 'images/logo.png', 'js\\dashboard.js',
];

foreach ($invalid as $path) {
    $rejected = false;
    try {
        app_asset_url($path);
    } catch (InvalidArgumentException) {
        $rejected = true;
    }
    $check($rejected, 'unsafe or unsupported asset path is rejected: ' . ($path === '' ? '(empty)' : $path));
}

$failed = count($checks) - count(array_filter($checks));
echo 'RESULT: PASS ' . (count($checks) - $failed) . ' / FAIL ' . $failed . ' / SKIP 0' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
