<?php

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/common/common_conf.php';
require_once APP_ROOT . '/app/validation.php';
require_once APP_ROOT . '/app/api.php';

$failures = [];
$checks = 0;

function sb10_check(bool $condition, string $label): void
{
    global $failures, $checks;
    $checks++;
    if ($condition) {
        echo "PASS: {$label}\n";
    } else {
        echo "FAIL: {$label}\n";
        $failures[] = $label;
    }
}

$sourceUrl = 'https://feeds.example.test/rss.xml';
$feed = [
    'channel' => [
        'title' => '<script>alert(1)</script>Safe & Sound',
        'link' => 'javascript:alert(1)',
        'description' => '<img src=x onerror=alert(1)>Description',
    ],
    'item' => [
        [
            'title' => '<b>Article</b><svg onload=alert(1)>',
            'link' => 'data:text/html,<script>alert(1)</script>',
            'description' => '<a href="javascript:alert(1)">Click</a> body',
            'content' => '<iframe src="https://evil.example"></iframe>content',
            'date' => '<em>2026-07-30</em>',
        ],
        [
            'title' => 'Valid link',
            'link' => 'https://example.test/post?id=1&x=2',
            'description' => 'plain',
            'content' => 'plain',
            'date' => 'now',
        ],
    ],
];

$safe = api_safe_feed_payload($feed, $sourceUrl);

sb10_check($safe['channel']['title'] === 'alert(1)Safe & Sound', 'channel title strips markup while retaining text');
sb10_check($safe['channel']['link'] === $sourceUrl, 'unsafe channel URL falls back to validated source URL');
sb10_check(strpos($safe['channel']['description'], '<') === false, 'channel description contains no markup');
sb10_check($safe['item'][0]['title'] === 'Article', 'item title strips active markup');
sb10_check($safe['item'][0]['link'] === '', 'javascript/data item URL is suppressed');
sb10_check(strpos($safe['item'][0]['description'], '<') === false, 'item description contains no markup');
sb10_check(strpos($safe['item'][0]['content'], '<') === false, 'item content contains no markup');
sb10_check($safe['item'][0]['date'] === '2026-07-30', 'item date strips markup');
sb10_check($safe['item'][1]['link'] === 'https://example.test/post?id=1&x=2', 'valid HTTPS item URL is retained');

$escaped = app_html('"<script>alert(1)</script> & \'x\'');
sb10_check(strpos($escaped, '<script>') === false, 'app_html escapes angle brackets');
sb10_check(strpos($escaped, '&quot;') !== false, 'app_html escapes double quotes');
sb10_check(strpos($escaped, '&#039;') !== false, 'app_html escapes single quotes');
sb10_check(strpos($escaped, '&amp;') !== false, 'app_html escapes ampersand');

$long = str_repeat('A', 600);
$bounded = api_feed_text($long, 512);
sb10_check(app_text_length($bounded) === 512, 'feed text is bounded to configured maximum');

$payloadJson = json_encode(
    ['ok' => true, 'data' => ['result_feed' => $safe]],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
sb10_check(is_string($payloadJson) && strpos($payloadJson, '<') === false, 'API JSON flags prevent literal HTML tag delimiters');

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d/%d SB-10 feed payload checks failed.\n", count($failures), $checks));
    exit(1);
}

echo "SB-10 feed payload checks: {$checks} passed.\n";
