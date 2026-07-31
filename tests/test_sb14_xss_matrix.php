<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
require $root . '/app/common/common_conf.php';
require $root . '/app/validation.php';
require $root . '/app/api.php';
require $root . '/app/common/common_func.php';

$tests = 0;
$failures = [];
function sb14_xss_check(bool $condition, string $message): void
{
    global $tests, $failures;
    $tests++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) {
        $failures[] = $message;
    }
}

$payload = '<img src=x onerror=alert(1)>';
$escaped = app_html($payload);
sb14_xss_check(!str_contains($escaped, '<img') && str_contains($escaped, '&lt;img'), 'generic DB/UI text is escaped before HTML rendering');

$tab = app_validate_text('<b>Tab</b>', 16, false);
sb14_xss_check($tab === '<b>Tab</b>' && !str_contains(app_html($tab), '<b>'), 'tab name remains data and is neutralized at output boundary');

$navbarView = app_validate_text('<svg>x</svg>', 32, true);
sb14_xss_check($navbarView === '<svg>x</svg>' && !str_contains(app_html($navbarView), '<svg>'), 'navbar label markup is escaped at output boundary');

$stockTitle = app_validate_text('<script>x</script>', 128, true);
sb14_xss_check(is_string($stockTitle) && !str_contains(app_html($stockTitle), '<script>'), 'Stock title markup cannot render as executable HTML');

sb14_xss_check(app_validate_stock_url('javascript:alert(1)') === null, 'Stock URL rejects javascript scheme');
sb14_xss_check(app_validate_navbar_url('data:text/html,<script>x</script>') === null, 'navbar URL rejects data scheme');
sb14_xss_check(app_validate_feed_url('javascript:alert(1)') === null, 'content/feed URL rejects javascript scheme');
sb14_xss_check(app_validate_external_link('vbscript:msgbox(1)') === null, 'Feed item URL rejects non-http executable schemes');

$feed = api_safe_feed_payload([
    'channel' => ['title' => $payload, 'link' => 'javascript:alert(1)', 'description' => $payload],
    'item' => [[
        'title' => $payload,
        'link' => 'data:text/html,x',
        'description' => $payload,
        'content' => '<svg onload=alert(1)>payload</svg>',
        'date' => '<b>today</b>',
    ]],
], 'https://source.example/feed');

sb14_xss_check(($feed['channel']['link'] ?? '') === 'https://source.example/feed', 'unsafe Feed channel URL falls back to source URL');
sb14_xss_check(($feed['item'][0]['link'] ?? 'x') === '', 'unsafe Feed item URL is suppressed');
foreach (['title', 'description', 'content', 'date'] as $field) {
    $value = (string) ($feed['item'][0][$field] ?? '');
    sb14_xss_check(!str_contains($value, '<') && !str_contains($value, '>'), "Feed item {$field} contains no active markup after API normalization");
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d/%d SB-14 XSS matrix checks failed.\n", count($failures), $tests));
    exit(1);
}

echo "All {$tests} SB-14 XSS matrix checks passed.\n";
