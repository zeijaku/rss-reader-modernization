<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/version.php';
require_once dirname(__DIR__) . '/app/asset.php';

$checks = [
    'Visible APP_VERSION is final Version 1.17.0 at Release Gate' => APP_VERSION === '1.17.0',
    'Stable release asset revision matches APP_VERSION' => defined('APP_ASSET_REVISION') && APP_ASSET_REVISION === APP_VERSION,
    'Calendar URL uses the final asset revision' => app_asset_url('js/calendar.js') === './js/calendar.js?v=1.17.0',
    'CSS URL uses the same final asset revision' => app_asset_url('css/dashboard.css') === './css/dashboard.css?v=1.17.0',
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$passed) {
        $failed[] = $name;
    }
}

if ($failed !== []) {
    exit(1);
}

echo 'PASS: V1.17 final asset revision cache checks passed' . PHP_EOL;
