<?php

declare(strict_types=1);

function app_validate_positive_int(mixed $value): ?int
{
    if (is_int($value)) {
        return $value > 0 ? $value : null;
    }
    if (!is_string($value) || preg_match('/\A[1-9][0-9]*\z/D', $value) !== 1) {
        return null;
    }
    $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return is_int($number) ? $number : null;
}

function dashboard_widget_validate_boolean(mixed $value): ?bool
{
    if (is_bool($value)) {
        return $value;
    }
    if ($value === 1 || $value === '1' || $value === 'true') {
        return true;
    }
    if ($value === 0 || $value === '0' || $value === 'false') {
        return false;
    }
    return null;
}

function dashboard_widget_decode_config(mixed $value): array
{
    if (!is_string($value) || $value === '') {
        return [];
    }
    try {
        $decoded = json_decode($value, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return [];
    }
    return is_array($decoded) && !array_is_list($decoded) ? $decoded : [];
}

function dashboard_widget_encode_config(array $config): string
{
    return json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function api_feed_text(mixed $value, int $maxLength): string
{
    $text = is_string($value) ? trim(strip_tags($value)) : '';
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maxLength, 'UTF-8');
    }
    return substr($text, 0, $maxLength);
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

require_once __DIR__ . '/../app/info_board.php';

$checks = 0;
$failures = [];
function v126b_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) {
        $failures[] = $message;
    }
}

$validInput = [
    'info_board_feed_mode' => 'all',
    'info_board_limit' => '10',
    'info_board_speed' => 'normal',
    'info_board_show_summary' => '1',
    'info_board_summary_max' => '200',
];
$config = info_board_config_from_input($validInput);
v126b_check(is_array($config), 'default RSS Information Board config is accepted');
v126b_check(($config['mode'] ?? '') === INFO_BOARD_MODE && ($config['source_type'] ?? '') === 'rss', 'private mode and RSS source type are fixed');
v126b_check(($config['feed_mode'] ?? '') === 'all' && array_key_exists('feed_id', $config) && $config['feed_id'] === null, 'all-RSS mode never stores a feed id');
v126b_check(($config['limit'] ?? 0) === 10 && ($config['speed'] ?? '') === 'normal', 'limit and speed are normalized');
v126b_check(($config['show_summary'] ?? false) === true && ($config['summary_max'] ?? 0) === 200, 'summary settings are normalized');

$specific = info_board_config_from_input(array_merge($validInput, [
    'info_board_feed_mode' => 'specific',
    'info_board_feed_id' => '42',
]));
v126b_check(is_array($specific) && $specific['feed_id'] === 42, 'specific Feed requires a positive content id');
v126b_check(info_board_config_from_input(array_merge($validInput, ['info_board_feed_mode' => 'specific'])) === null, 'specific Feed without content id is rejected');
v126b_check(info_board_config_from_input(array_merge($validInput, ['info_board_limit' => '30'])) === null, 'unsupported display limit is rejected');
v126b_check(info_board_config_from_input(array_merge($validInput, ['info_board_speed' => 'turbo'])) === null, 'unsupported speed is rejected');
v126b_check(info_board_config_from_input(array_merge($validInput, ['info_board_summary_max' => '999'])) === null, 'unsupported summary maximum is rejected');
$fullInput = info_board_config_from_input(array_merge($validInput, ['info_board_summary_max' => (string) INFO_BOARD_SUMMARY_HARD_LIMIT]));
v126b_check(is_array($fullInput) && ($fullInput['summary_max'] ?? 0) === INFO_BOARD_SUMMARY_HARD_LIMIT, 'safe RSS full-text ceiling is accepted as a bounded summary maximum');

$stored = dashboard_widget_encode_config($specific ?? []);
$roundTrip = info_board_config_from_storage($stored);
v126b_check(is_array($roundTrip) && $roundTrip['feed_id'] === 42, 'stored config round-trips through strict normalizer');
$foreignMode = json_decode($stored, true);
$foreignMode['mode'] = 'search';
v126b_check(info_board_config_from_storage(json_encode($foreignMode)) === null, 'normal Search Feed config cannot be mistaken for Information Board');
$foreignSource = json_decode($stored, true);
$foreignSource['source_type'] = 'weather';
v126b_check(info_board_config_from_storage(json_encode($foreignSource)) === null, 'unimplemented future source type is rejected');

$payload = info_board_item_payload([
    'title' => '<b>OpenAI</b> 新機能',
    'description' => "概要です。\n<script>alert(1)</script> 続き",
    'content' => '本文 fallback',
    'link' => 'https://example.com/article?id=1',
    'date' => '2026-08-28T12:00:00+09:00',
], '<em>Example News</em>', $config ?? []);
v126b_check(is_array($payload), 'safe RSS item becomes Information Board payload');
v126b_check($payload['kind'] === 'NEWS' && $payload['source_type'] === 'rss', 'payload keeps future-source-friendly kind/source fields');
v126b_check($payload['title'] === 'OpenAI 新機能', 'RSS title is plain text');
v126b_check(!str_contains($payload['summary'], '<') && !str_contains($payload['summary'], "\n"), 'RSS summary is HTML-free and single-line');
v126b_check($payload['text'] === '【OpenAI 新機能】概要です。 alert(1) 続き', 'display text combines title and available RSS summary');
v126b_check($payload['source_title'] === 'Example News', 'Feed title is plain text');

$fallback = info_board_item_payload([
    'title' => 'Fallback',
    'description' => '',
    'content' => '<p>content:encoded 相当</p>',
    'link' => 'https://example.com/fallback',
    'date' => '',
], 'RSS', $config ?? []);
v126b_check(is_array($fallback) && $fallback['summary'] === 'content:encoded 相当', 'content is used when description is absent');

$fullConfig = info_board_config('all', null, 5, 'normal', true, INFO_BOARD_SUMMARY_HARD_LIMIT);
$longerContent = info_board_item_payload([
    'title' => 'Longer content',
    'description' => str_repeat('d', 400),
    'content' => str_repeat('c', 3000),
    'link' => 'https://example.com/full',
    'date' => '',
], 'RSS', $fullConfig);
$longerContentLength = function_exists('mb_strlen') ? mb_strlen((string) ($longerContent['summary'] ?? ''), 'UTF-8') : strlen((string) ($longerContent['summary'] ?? ''));
v126b_check(is_array($longerContent) && $longerContentLength === 3000, 'longer sanitized content is preferred over a shorter description when fuller RSS text is available');

$hardBound = info_board_item_payload([
    'title' => 'Hard bound',
    'description' => '',
    'content' => str_repeat('x', INFO_BOARD_SUMMARY_HARD_LIMIT + 500),
    'link' => 'https://example.com/bound',
    'date' => '',
], 'RSS', $fullConfig);
$hardBoundLength = function_exists('mb_strlen') ? mb_strlen((string) ($hardBound['summary'] ?? ''), 'UTF-8') : strlen((string) ($hardBound['summary'] ?? ''));
v126b_check($hardBoundLength === INFO_BOARD_SUMMARY_HARD_LIMIT, 'fuller RSS summary remains strictly bounded by the 4096-character safety ceiling');

$noSummaryConfig = info_board_config('all', null, 5, 'slow', false, 100);
$noSummary = info_board_item_payload([
    'title' => 'Title only',
    'description' => 'hidden summary',
    'content' => 'hidden content',
    'link' => 'javascript:alert(1)',
    'date' => '',
], 'RSS', $noSummaryConfig);
v126b_check(is_array($noSummary) && $noSummary['summary'] === '' && $noSummary['text'] === 'Title only', 'summary OFF returns title-only display text');
v126b_check(is_array($noSummary) && $noSummary['link'] === '', 'unsafe article URL is dropped');

$longConfig = info_board_config('all', null, 5, 'fast', true, 100);
$longPayload = info_board_item_payload([
    'title' => 'Long',
    'description' => str_repeat('あ', 150),
    'content' => '',
    'link' => 'https://example.com/long',
    'date' => '',
], 'RSS', $longConfig);
$summaryLength = function_exists('mb_strlen') ? mb_strlen((string) $longPayload['summary'], 'UTF-8') : strlen((string) $longPayload['summary']);
v126b_check($summaryLength <= 100, 'summary is bounded by configured maximum');

v126b_check(info_board_item_payload(['title' => '', 'link' => 'https://example.com'], 'RSS', $config ?? []) === null, 'item without title is skipped safely');

echo "RESULT: " . ($failures === [] ? 'PASS' : 'FAIL') . " {$checks} checks\n";
if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAILED: {$failure}\n");
    }
    exit(1);
}
