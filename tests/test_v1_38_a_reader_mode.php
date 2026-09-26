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

function v138a_check(bool $condition, string $label): void
{
    global $failures, $checks;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $label . "\n";
    if (!$condition) {
        $failures[] = $label;
    }
}

$dangerous = '<h1 onclick="alert(1)">Heading</h1>'
    . '<script>alert("xss")</script>'
    . '<p style="background:url(javascript:alert(1))">First <a href="javascript:alert(1)">link</a></p>'
    . '<iframe src="https://evil.test/">frame text</iframe>'
    . '<object>object text</object><embed src="x">'
    . '<form><input value="secret">form text</form>'
    . '<blockquote>Quote</blockquote><ul><li>One</li><li>Two</li></ul>';

$plain = api_reader_plain_text($dangerous);
v138a_check(str_contains($plain, 'Heading'), 'Reader keeps safe visible heading text');
v138a_check(str_contains($plain, 'First link'), 'Reader keeps safe paragraph/link label text');
v138a_check(str_contains($plain, 'Quote') && str_contains($plain, 'One') && str_contains($plain, 'Two'), 'Reader keeps safe quote/list text');
v138a_check(!str_contains($plain, 'alert("xss")'), 'script contents are removed');
v138a_check(!str_contains($plain, 'frame text') && !str_contains($plain, 'object text') && !str_contains($plain, 'form text'), 'iframe/object/form contents are removed');
v138a_check(!str_contains($plain, '<') && !str_contains($plain, '>'), 'Reader sanitizer returns no HTML tags');
v138a_check(!str_contains(strtolower($plain), 'javascript:'), 'javascript URL text from attributes is not exposed');
v138a_check(str_contains($plain, "\n"), 'block boundaries remain readable as line breaks');

$identity = 'm1i:v1:' . str_repeat('a', 64);
$feed = [
    'channel' => [
        'title' => '<b>Example Source</b>',
        'link' => 'https://example.test/feed',
        'description' => '',
    ],
    'item' => [[
        'title' => '<b>Reader title</b>',
        'link' => 'https://example.test/article?id=7&utm_source=rss#section',
        'description' => '<p>Description fallback</p>',
        'content' => '<article><p>Content body</p><script>bad()</script><p>Second paragraph</p></article>',
        'date' => '2026-09-26T12:34:56+09:00',
        'item_identity' => $identity,
    ]],
];

$reader = api_feed_reader_payload($feed, $identity);
v138a_check(is_array($reader), 'Reader payload resolves the requested item identity');
v138a_check(($reader['title'] ?? '') === 'Reader title', 'Reader title remains sanitized plain text');
v138a_check(($reader['source'] ?? '') === 'Example Source', 'Reader source name is exposed');
v138a_check(($reader['date'] ?? '') === '2026-09-26T12:34:56+09:00', 'Reader published date is exposed');
v138a_check(($reader['body_source'] ?? '') === 'content', 'RSS content has priority over description');
v138a_check(str_contains((string) ($reader['body'] ?? ''), 'Content body') && !str_contains((string) ($reader['body'] ?? ''), 'bad()'), 'Reader body uses sanitized RSS content');
v138a_check(($reader['article_url'] ?? '') === 'https://example.test/article?id=7#section', 'Reader original link removes known tracking parameters');
v138a_check(($reader['full_text'] ?? null) === false, 'V1.38-A does not claim Full Text article fetch');

$fallback = api_feed_reader_payload([
    'channel' => ['title' => 'Fallback Source'],
    'item' => [[
        'title' => 'Fallback',
        'link' => 'https://example.test/fallback',
        'description' => '<p>Description only</p>',
        'content' => '',
        'date' => '',
        'item_identity' => $identity,
    ]],
], $identity);
v138a_check(($fallback['body'] ?? '') === 'Description only', 'description is used when RSS content is empty');
v138a_check(($fallback['body_source'] ?? '') === 'description', 'description fallback source is reported');

$empty = api_feed_reader_payload([
    'channel' => ['title' => 'Empty Source'],
    'item' => [[
        'title' => 'Empty',
        'link' => 'https://example.test/empty',
        'description' => '',
        'content' => '',
        'date' => '',
        'item_identity' => $identity,
    ]],
], $identity);
v138a_check(($empty['body'] ?? 'x') === '' && ($empty['body_source'] ?? '') === 'none', 'missing RSS body falls back to original-link guidance');

v138a_check(api_feed_reader_payload($feed, 'm1i:v1:' . str_repeat('b', 64)) === null, 'unknown item identity is rejected');
v138a_check(app_text_length(api_reader_plain_text(str_repeat('A', 70000), 65536)) === 65536, 'Reader body has an explicit 65536-character cap');

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d/%d V1.38-A Reader checks failed.\n", count($failures), $checks));
    exit(1);
}
echo "V1.38-A Reader checks: {$checks} passed.\n";
