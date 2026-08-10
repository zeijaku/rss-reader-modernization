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

$check(dashboard_widget_validate_feed_item_limit(null) === 'auto', 'missing RSS item limit selects auto');
$check(dashboard_widget_validate_feed_item_limit('') === 'auto', 'blank RSS item limit selects auto');
$check(dashboard_widget_validate_feed_item_limit('auto') === 'auto', 'explicit auto RSS item limit is valid');
$check(dashboard_widget_validate_feed_item_limit(1) === 1, 'RSS item limit 1 is valid');
$check(dashboard_widget_validate_feed_item_limit('5') === 5, 'RSS item limit 5 is valid');
$check(dashboard_widget_validate_feed_item_limit(30) === 30, 'RSS item limit 30 is valid');
foreach ([0, '0', 31, '31', -1, 'abc', '1.5', [], new stdClass()] as $invalid) {
    $check(dashboard_widget_validate_feed_item_limit($invalid) === null, 'invalid RSS item limit is rejected: ' . get_debug_type($invalid));
}

$defaults = dashboard_widget_feed_defaults();
$check(($defaults['schema'] ?? null) === 1 && ($defaults['item_limit'] ?? null) === 'auto', 'RSS defaults use automatic item limit');

$autoInput = dashboard_widget_feed_config_from_input(['feed_item_limit' => '']);
$check(is_array($autoInput) && ($autoInput['item_limit'] ?? null) === 'auto', 'blank form value normalizes to auto config');
$fixedInput = dashboard_widget_feed_config_from_input(['feed_item_limit' => '12']);
$check(is_array($fixedInput) && ($fixedInput['item_limit'] ?? null) === 12, 'numeric form value normalizes to integer config');
$check(dashboard_widget_feed_config_from_input(['feed_item_limit' => '99']) === null, 'out-of-range form value is rejected');

$legacy = dashboard_widget_feed_config_from_storage(null);
$check(($legacy['item_limit'] ?? null) === 'auto', 'legacy Feed widget_config NULL safely defaults to auto');
$storedAuto = dashboard_widget_feed_config_from_storage('{"schema":1,"item_limit":"auto"}');
$check(($storedAuto['item_limit'] ?? null) === 'auto', 'stored automatic item limit survives decoding');
$storedFixed = dashboard_widget_feed_config_from_storage('{"schema":1,"item_limit":17}');
$check(($storedFixed['item_limit'] ?? null) === 17, 'stored numeric item limit survives decoding');
$storedInvalid = dashboard_widget_feed_config_from_storage('{"schema":1,"item_limit":50}');
$check(($storedInvalid['item_limit'] ?? null) === 'auto', 'invalid stored item limit fails safely to auto');

$row = [
    'widget_id' => 30,
    'widget_owner' => 1,
    'widget_location' => 0,
    'widget_type' => 'feed',
    'widget_reference_id' => 300,
    'widget_sort_order' => 10,
    'widget_width' => 1,
    'widget_height' => 2,
    'widget_style' => 'success',
    'widget_config' => '{"schema":1,"item_limit":14}',
    'widget_flag' => 0,
    'widget_created_at' => '2026-08-07 00:00:00',
    'widget_updated_at' => '2026-08-07 00:00:00',
];
$normalized = dashboard_widget_normalize_row($row);
$check(is_array($normalized) && (($normalized['widget_config_data']['item_limit'] ?? null) === 14), 'normalized Feed exposes stored RSS item limit');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' V1.7-H/R2 RSS item-limit checks failed.' . PHP_EOL);
    exit(1);
}

echo 'All V1.7-H/R2 RSS item-limit checks passed.' . PHP_EOL;
