<?php

declare(strict_types=1);

$capturedCreateConfig = null;

function app_validate_positive_int(mixed $value): ?int
{
    if (is_int($value)) return $value > 0 ? $value : null;
    return is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1 ? (int) $value : null;
}
function app_validate_text(mixed $value, int $maxLength, bool $allowEmpty = true): ?string
{
    if (!is_string($value)) return null;
    $value = trim($value);
    if (!$allowEmpty && $value === '') return null;
    return strlen($value) <= $maxLength ? $value : null;
}
function app_validate_feed_url(mixed $value): ?string { return is_string($value) ? $value : null; }
function app_validate_external_link(mixed $value, int $maxLength = 2048): ?string { return is_string($value) ? $value : null; }
function app_now(): string { return '2026-10-06 13:30:00'; }
function dashboard_widget_decode_config(mixed $value): array
{
    if (is_array($value)) return $value;
    $decoded = is_string($value) ? json_decode($value, true) : null;
    return is_array($decoded) ? $decoded : [];
}
function dashboard_widget_validate_location(mixed $value): ?int
{
    $value = app_validate_positive_int(((int) $value) + 1);
    return $value === null ? null : $value - 1;
}
function dashboard_widget_validate_width(mixed $value): ?int
{
    $n = is_numeric($value) ? (int) $value : 0;
    return in_array($n, [1,2,3,4], true) ? $n : null;
}
function dashboard_widget_validate_height(mixed $value): ?int
{
    $n = is_numeric($value) ? (int) $value : 0;
    return in_array($n, [1,2], true) ? $n : null;
}
function app_normalize_content_style(mixed $value): ?string
{
    return is_string($value) && in_array($value, ['success','primary','info','secondary','dark','warning','danger'], true) ? $value : null;
}
function search_feed_create(int $ownerId, int $location, string $style, int $width, array $config, int $height = 1): int
{
    global $capturedCreateConfig;
    $capturedCreateConfig = $config;
    return 321;
}
function api_positive_int(array $input, string $key): ?int { return app_validate_positive_int($input[$key] ?? null); }
function api_success(array $data = [], int $status = 200): array { return ['status'=>$status,'body'=>['ok'=>true,'data'=>$data]]; }
function api_error(string $code, string $message, int $status): array { return ['status'=>$status,'body'=>['ok'=>false,'error'=>['code'=>$code,'message'=>$message]]]; }
function api_validation_error(string $message): array { return api_error('validation_error', $message, 422); }

require_once dirname(__DIR__) . '/app/api/all_rss_recent.php';

$pass = 0;
$fail = 0;
function v145b_api_check(bool $condition, string $message): void
{
    global $pass, $fail;
    if ($condition) { $pass++; echo "PASS: {$message}\n"; }
    else { $fail++; echo "FAIL: {$message}\n"; }
}

$base = [
    'widget_location'=>'0',
    'widget_style'=>'secondary',
    'widget_width'=>'2',
    'widget_height'=>'2',
    'recent_limit'=>'10',
];

$response = api_widget_all_rss_recent_create(7, $base);
v145b_api_check($response['status'] === 201, 'Legacy create request without Category remains accepted');
v145b_api_check(!array_key_exists('feed_category_path', $capturedCreateConfig), 'Legacy create request stores All without new config key');

$response = api_widget_all_rss_recent_create(7, $base + ['recent_category_filter'=>'uncategorized']);
v145b_api_check($response['status'] === 201 && ($capturedCreateConfig['feed_category_path'] ?? null) === '', 'API maps Uncategorized token to empty Category path');

$response = api_widget_all_rss_recent_create(7, $base + ['recent_category_filter'=>'category:技術']);
v145b_api_check($response['status'] === 201 && ($capturedCreateConfig['feed_category_path'] ?? null) === '技術', 'API stores exact named Category path');

$response = api_widget_all_rss_recent_create(7, $base + ['recent_category_filter'=>'category:all']);
v145b_api_check($response['status'] === 201 && ($capturedCreateConfig['feed_category_path'] ?? null) === 'all', 'API preserves real Category named all');

$response = api_widget_all_rss_recent_create(7, $base + ['recent_category_filter'=>'category:']);
v145b_api_check($response['status'] === 422, 'API rejects blank named Category token');

$response = api_widget_all_rss_recent_create(7, $base + ['recent_category_filter'=>'unexpected']);
v145b_api_check($response['status'] === 422, 'API rejects unknown Category token');

$response = api_widget_all_rss_recent_update(7, [
    'widget_id'=>'1',
    'widget_style'=>'secondary',
    'widget_width'=>'2',
    'widget_height'=>'2',
    'recent_limit'=>'10',
    'recent_category_filter'=>'category:',
]);
v145b_api_check($response['status'] === 422, 'Update rejects invalid Category before ownership/DB access');

printf("RESULT: %s %d / FAIL %d / SKIP 0\n", $fail === 0 ? 'PASS' : 'FAIL', $pass, $fail);
exit($fail === 0 ? 0 : 1);
