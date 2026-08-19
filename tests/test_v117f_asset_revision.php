<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/version.php';
require_once dirname(__DIR__) . '/app/asset.php';

$revision = defined('APP_ASSET_REVISION') ? (string) APP_ASSET_REVISION : (string) APP_VERSION;
$encodedRevision = rawurlencode($revision);

$checks = [
    'Visible APP_VERSION remains a semantic version' => preg_match('/^\d+\.\d+\.\d+(?:-[A-Za-z0-9._-]+)?$/', APP_VERSION) === 1,
    'Asset revision is available for cache busting' => $revision !== '',
    'Calendar URL uses the active asset revision' => app_asset_url('js/calendar.js') === './js/calendar.js?v=' . $encodedRevision,
    'CSS URL uses the same active asset revision' => app_asset_url('css/dashboard.css') === './css/dashboard.css?v=' . $encodedRevision,
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

echo 'PASS: asset revision cache contract checks passed' . PHP_EOL;
