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
function v12b_check(bool $condition, string $label): void
{
    global $failures, $checks;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $label . "\n";
    if (!$condition) {
        $failures[] = $label;
    }
}

$feed = [
    'channel' => ['title' => 'Feed', 'link' => 'https://example.test/feed', 'description' => ''],
    'item' => [[
        'title' => '<b>Title</b>',
        'link' => 'https://example.test/article?utm_source=test&id=1',
        'description' => '<img src=x onerror=alert(1)>Description only',
        'content' => '<script>alert(1)</script>Content body',
        'date' => '2026-08-04',
        'item_identity' => 'm1i:v1:' . str_repeat('a', 64),
        'is_new' => true,
    ]],
];
$safe = api_safe_feed_payload($feed, 'https://example.test/feed');
$item = $safe['item'][0] ?? [];

v12b_check(($item['title'] ?? null) === 'Title', 'article title remains plain text');
v12b_check(($item['description'] ?? null) === 'Description only', 'description is available as sanitized text');
v12b_check(($item['content'] ?? null) === 'alert(1)Content body', 'content is available as sanitized text');
v12b_check(strpos((string) ($item['description'] ?? ''), '<') === false, 'description contains no HTML tag delimiter');
v12b_check(strpos((string) ($item['content'] ?? ''), '<') === false, 'content contains no HTML tag delimiter');
v12b_check(($item['link'] ?? null) === 'https://example.test/article?id=1', 'tracking parameters remain removed from article URL');
v12b_check(($item['item_identity'] ?? null) === 'm1i:v1:' . str_repeat('a', 64), 'NEW identity remains available');
v12b_check(($item['is_new'] ?? null) === true, 'NEW state remains available');

$longDescription = str_repeat('D', 3000);
$longContent = str_repeat('C', 5000);
$bounded = api_safe_feed_payload([
    'channel' => ['title' => 'Bounded', 'link' => 'https://example.test/feed', 'description' => ''],
    'item' => [[
        'title' => 'Long', 'link' => 'https://example.test/long',
        'description' => $longDescription, 'content' => $longContent, 'date' => '',
    ]],
], 'https://example.test/feed');
v12b_check(app_text_length($bounded['item'][0]['description']) === 2048, 'description payload keeps the existing 2048-character bound');
v12b_check(app_text_length($bounded['item'][0]['content']) === 4096, 'content payload keeps the existing 4096-character bound');

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d/%d V1.2-B payload checks failed.\n", count($failures), $checks));
    exit(1);
}
echo "V1.2-B payload checks: {$checks} passed.\n";
