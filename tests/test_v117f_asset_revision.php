<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/version.php';
require_once dirname(__DIR__) . '/app/asset.php';

$checks = [
    'Visible APP_VERSION stays at 1.16.0 before Release Gate' => APP_VERSION === '1.16.0',
    'Production verification uses V1.17-F R1 asset revision' => defined('APP_ASSET_REVISION') && APP_ASSET_REVISION === '1.17-f-r1',
    'Calendar URL uses the asset revision' => app_asset_url('js/calendar.js') === './js/calendar.js?v=1.17-f-r1',
    'CSS URL uses the same asset revision' => app_asset_url('css/dashboard.css') === './css/dashboard.css?v=1.17-f-r1',
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

echo 'PASS: V1.17-F R1 asset revision cache checks passed' . PHP_EOL;
