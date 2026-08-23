<?php

declare(strict_types=1);

function app_validate_positive_int(mixed $value): ?int
{
    if (is_int($value)) {
        return $value > 0 ? $value : null;
    }
    if (!is_string($value) || !preg_match('/^[1-9][0-9]*$/', $value)) {
        return null;
    }
    $number = (int) $value;
    return $number > 0 ? $number : null;
}

/** @return array<string,mixed> */
function dashboard_widget_decode_config(mixed $value): array
{
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value) || $value === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

require dirname(__DIR__) . '/app/all_rss_recent.php';

$pass = 0;
$fail = 0;
function v120f_check(bool $condition, string $name): void
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "PASS: {$name}\n";
    } else {
        $fail++;
        echo "FAIL: {$name}\n";
    }
}

v120f_check(all_rss_recent_allowed_limits() === [5, 10, 20, 30], 'All RSS Recent allows only 5/10/20/30 items');
foreach ([5, 10, 20, 30] as $limit) {
    v120f_check(all_rss_recent_validate_limit($limit) === $limit, "All RSS Recent accepts limit {$limit}");
}
foreach ([0, -1, 1, 6, 40, 'abc', '05'] as $invalid) {
    v120f_check(all_rss_recent_validate_limit($invalid) === null, 'All RSS Recent rejects invalid limit ' . var_export($invalid, true));
}
$config = all_rss_recent_config(20);
v120f_check($config['schema'] === 1, 'All RSS Recent config schema remains 1');
v120f_check($config['mode'] === ALL_RSS_RECENT_MODE, 'All RSS Recent config stores the private mode marker');
v120f_check($config['query'] === ALL_RSS_RECENT_QUERY, 'All RSS Recent config stores the sentinel query');
v120f_check($config['scope'] === 'owned', 'All RSS Recent scope is owned RSS only');
v120f_check($config['limit'] === 20, 'All RSS Recent config preserves the requested bounded limit');
v120f_check(all_rss_recent_config_from_storage(json_encode($config, JSON_UNESCAPED_UNICODE)) === $config, 'All RSS Recent config round-trips from storage');
$normalSearch = $config;
$normalSearch['mode'] = 'search';
v120f_check(all_rss_recent_config_from_storage($normalSearch) === null, 'ordinary Search Feed is not misclassified as All RSS Recent');
$badQuery = $config;
$badQuery['query'] = '全RSS新着';
v120f_check(all_rss_recent_config_from_storage($badQuery) === null, 'All RSS Recent requires the exact private sentinel query');
$badLimit = $config;
$badLimit['limit'] = 100;
v120f_check(all_rss_recent_config_from_storage($badLimit) === null, 'All RSS Recent rejects invalid stored limits');
v120f_check(all_rss_recent_item_timestamp(['date' => '2026-08-23T10:20:30+09:00']) > 0, 'All RSS Recent parses a valid feed date');
v120f_check(all_rss_recent_item_timestamp(['date' => '']) === 0, 'All RSS Recent maps a missing date to zero');
v120f_check(all_rss_recent_item_timestamp(['date' => 'not-a-date']) === 0, 'All RSS Recent maps an invalid date to zero');

printf("RESULT: %s %d / FAIL %d / SKIP 0\n", $fail === 0 ? 'PASS' : 'FAIL', $pass, $fail);
exit($fail === 0 ? 0 : 1);
