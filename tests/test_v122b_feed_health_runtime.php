<?php

declare(strict_types=1);

function app_validate_text(mixed $value, int $maxLength, bool $allowEmpty = false): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);
    if (!$allowEmpty && $value === '') {
        return null;
    }
    if (mb_strlen($value, 'UTF-8') > $maxLength || str_contains($value, "\0")) {
        return null;
    }
    return $value;
}

function app_validate_external_link(mixed $value, int $maxLength = 2048): ?string
{
    if (!is_string($value) || strlen($value) > $maxLength) {
        return null;
    }
    $value = trim($value);
    if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) {
        return null;
    }
    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $value : null;
}

function app_validate_feed_url(mixed $value): ?string
{
    return app_validate_external_link($value, 1024);
}

require_once __DIR__ . '/../app/feed/feed_date_normalizer.php';
require_once __DIR__ . '/../app/feed_health.php';

$passed = 0;
$failed = 0;

function v122b_check(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "PASS: {$message}\n";
    } else {
        $failed++;
        echo "FAIL: {$message}\n";
    }
}

$unknown = feed_health_unknown_payload(7);
v122b_check($unknown['status'] === 'unknown' && $unknown['content_id'] === 7, 'never-checked Feed is Unknown');

$now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d H:i:s');
$normal = feed_health_payload_from_row([
    'health_content_id' => 7,
    'last_checked_at' => $now,
    'last_successful_fetch_at' => $now,
    'latest_article_at' => $now,
    'last_result' => 'success',
    'http_status' => 200,
    'consecutive_failure_count' => 0,
    'redirected' => 0,
    'effective_url' => 'https://example.com/feed.xml',
]);
v122b_check($normal['status'] === 'normal', 'successful current Feed is Normal');
v122b_check($normal['consecutive_failure_count'] === 0, 'Normal state starts with zero consecutive failures');

$error1 = feed_health_payload_from_row([
    'health_content_id' => 7,
    'last_checked_at' => $now,
    'last_successful_fetch_at' => $now,
    'latest_article_at' => $now,
    'last_result' => 'error',
    'http_status' => 500,
    'error_code' => 'http_error',
    'error_reason' => "upstream failed\nsecret-detail",
    'consecutive_failure_count' => 1,
]);
v122b_check($error1['status'] === 'error' && $error1['consecutive_failure_count'] === 1, 'first failure becomes Error with count 1');
v122b_check(!str_contains($error1['error_reason'], "\n"), 'error reason is flattened to bounded display text');

$error2 = feed_health_payload_from_row([
    'health_content_id' => 7,
    'last_checked_at' => $now,
    'last_successful_fetch_at' => $now,
    'latest_article_at' => $now,
    'last_result' => 'error',
    'http_status' => 503,
    'error_code' => 'http_error',
    'error_reason' => 'temporary upstream failure',
    'consecutive_failure_count' => 2,
]);
v122b_check($error2['status'] === 'error' && $error2['consecutive_failure_count'] === 2, 'repeated failure remains Error and exposes incremented count');

$recovered = feed_health_payload_from_row([
    'health_content_id' => 7,
    'last_checked_at' => $now,
    'last_successful_fetch_at' => $now,
    'latest_article_at' => $now,
    'last_result' => 'success',
    'http_status' => 200,
    'error_code' => '',
    'error_reason' => '',
    'consecutive_failure_count' => 0,
    'redirected' => 0,
    'effective_url' => 'https://example.com/feed.xml',
]);
v122b_check($recovered['status'] === 'normal' && $recovered['consecutive_failure_count'] === 0, 'successful recovery returns to Normal and clears failure count');

$redirect = feed_health_payload_from_row([
    'health_content_id' => 7,
    'last_checked_at' => $now,
    'last_successful_fetch_at' => $now,
    'latest_article_at' => $now,
    'last_result' => 'success',
    'http_status' => 200,
    'consecutive_failure_count' => 0,
    'redirected' => 1,
    'effective_url' => 'https://www.example.com/feed.xml',
]);
v122b_check($redirect['status'] === 'warning', 'redirected successful Feed is Warning');

$old = feed_health_payload_from_row([
    'health_content_id' => 7,
    'last_checked_at' => $now,
    'last_successful_fetch_at' => $now,
    'latest_article_at' => '2000-01-01 00:00:00',
    'last_result' => 'success',
    'http_status' => 200,
    'consecutive_failure_count' => 0,
    'redirected' => 0,
]);
v122b_check($old['status'] === 'warning', 'long-inactive Feed is Warning');

$latest = feed_health_latest_article_at([
    ['date' => '2026-08-20T09:00:00+09:00'],
    ['date' => 'invalid'],
    ['date' => '2026-08-25T18:30:00+09:00'],
]);
v122b_check($latest === '2026-08-25 18:30:00', 'latest article date is selected from normalized items');

v122b_check(feed_health_is_redirected('https://example.com/feed.xml', 'https://example.com/feed.xml') === false, 'same effective URL is not marked redirected');
v122b_check(feed_health_is_redirected('https://example.com/feed.xml', 'https://www.example.com/feed.xml') === true, 'changed effective URL is marked redirected');

echo "RESULT: PASS {$passed} / FAIL {$failed}\n";
exit($failed > 0 ? 1 : 0);
