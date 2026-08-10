<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');
putenv('DB_TABLE_PREFIX=ig_');
putenv('DB_HOST=test');
putenv('DB_NAME=test');
putenv('DB_USER=test');
putenv('DB_PASSWORD=test');
putenv('APP_LOG_ENABLED=false');
require $root . '/app/bootstrap.php';

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) {
        $failures[] = $message;
    }
};

$check(dashboard_widget_validate_height(1) === 1, 'integer height 1 is valid');
$check(dashboard_widget_validate_height(2) === 2, 'integer height 2 is valid');
$check(dashboard_widget_validate_height('1') === 1, 'string height 1 is valid');
$check(dashboard_widget_validate_height('2') === 2, 'string height 2 is valid');
foreach ([null, '', 0, '0', 3, '3', -1, '01', 1.5, true, [], new stdClass()] as $invalid) {
    $check(dashboard_widget_validate_height($invalid) === null, 'invalid Widget height is rejected: ' . get_debug_type($invalid));
}

$row = [
    'widget_id' => 10,
    'widget_owner' => 1,
    'widget_location' => 0,
    'widget_type' => 'clock',
    'widget_reference_id' => null,
    'widget_sort_order' => 10,
    'widget_width' => 1,
    'widget_height' => 2,
    'widget_style' => 'primary',
    'widget_config' => '{"schema":1,"title":"Clock","hour_format":"24","show_seconds":false,"show_date":true}',
    'widget_flag' => 0,
    'widget_created_at' => '2026-08-07 00:00:00',
    'widget_updated_at' => '2026-08-07 00:00:00',
];
$normalized = dashboard_widget_normalize_row($row);
$check(is_array($normalized) && ($normalized['widget_height'] ?? null) === 2, 'stored height 2 survives normalization');
unset($row['widget_height']);
$legacy = dashboard_widget_normalize_row($row);
$check(is_array($legacy) && ($legacy['widget_height'] ?? null) === 1, 'legacy fixture without height safely defaults to 1');
$row['widget_height'] = 3;
$check(dashboard_widget_normalize_row($row) === null, 'out-of-range stored height is rejected');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' V1.7-H Widget height checks failed.' . PHP_EOL);
    exit(1);
}

echo 'All V1.7-H Widget height checks passed.' . PHP_EOL;
