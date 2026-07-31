<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/validation.php';
require_once $root . '/app/common/common_func.php';
require_once $root . '/app/api.php';

$tests = 0;
$failures = [];
function atom_link_check(bool $condition, string $message): void
{
    global $tests, $failures;
    $tests++;
    if ($condition) {
        echo "PASS: {$message}\n";
        return;
    }
    $failures[] = $message;
    echo "FAIL: {$message}\n";
}

atom_link_check(
    rss_select_link_candidate([
        ['href' => 'https://example.test/self', 'rel' => 'self', 'type' => 'application/atom+xml'],
        ['href' => 'https://example.test/article', 'rel' => 'alternate', 'type' => 'text/html'],
    ]) === 'https://example.test/article',
    'Atom alternate text/html wins over self link'
);
atom_link_check(
    rss_select_link_candidate([
        ['href' => 'https://example.test/self', 'rel' => 'self'],
        ['href' => 'https://example.test/article', 'rel' => 'alternate'],
    ]) === 'https://example.test/article',
    'Atom alternate link without type wins over self link'
);
atom_link_check(
    rss_select_link_candidate([
        ['href' => 'https://example.test/self', 'rel' => 'self'],
        ['href' => 'https://example.test/home', 'rel' => ''],
    ]) === 'https://example.test/home',
    'relation-less text-style link wins over self metadata'
);
atom_link_check(
    rss_select_link_candidate([
        ['href' => 'https://example.test/self', 'rel' => 'self'],
    ]) === 'https://example.test/self',
    'self link remains a final usable fallback'
);
atom_link_check(
    rss_select_link_candidate([
        ['href' => '   ', 'rel' => 'alternate'],
        ['href' => 'https://example.test/valid', 'rel' => 'related'],
    ]) === 'https://example.test/valid',
    'blank candidates are ignored'
);
atom_link_check(
    rss_select_link_candidate([
        ['href' => 'https://example.test/a', 'rel' => 'SELF'],
        ['href' => 'https://example.test/b', 'rel' => 'ALTERNATE', 'type' => 'TEXT/HTML'],
    ]) === 'https://example.test/b',
    'Atom rel/type comparison is case-insensitive'
);
atom_link_check(rss_select_link_candidate([]) === null, 'empty candidate list returns null');
atom_link_check(rss_select_link_candidate([['href' => null]]) === null, 'non-string href is rejected');

$payload = api_safe_feed_payload([
    'channel' => [
        'title' => 'Example',
        'link' => 'https://qiita.com/tags/test',
        'description' => '',
    ],
    'item' => [
        [
            'title' => 'Qiita article',
            'link' => 'https://qiita.com/example/items/0123456789abcdef',
            'description' => '',
            'content' => '',
            'date' => '2026-07-30 12:00:00',
        ],
        [
            'title' => 'Publickey article',
            'link' => 'https://www.publickey1.jp/blog/26/example.html',
            'description' => '',
            'content' => '',
            'date' => '2026-07-30 12:00:00',
        ],
    ],
], 'https://example.test/feed');
atom_link_check(
    ($payload['item'][0]['link'] ?? '') === 'https://qiita.com/example/items/0123456789abcdef',
    'API safe payload preserves a normal Qiita https article URL'
);
atom_link_check(
    ($payload['item'][1]['link'] ?? '') === 'https://www.publickey1.jp/blog/26/example.html',
    'API safe payload preserves a normal Publickey https article URL'
);
atom_link_check(
    app_validate_external_link('http://www.publickey1.jp/blog/26/example.html') === 'http://www.publickey1.jp/blog/26/example.html',
    'external link validator accepts normal http article URLs'
);
atom_link_check(
    app_validate_external_link('javascript:alert(1)') === null,
    'external link validator still rejects javascript URLs'
);

if (function_exists('simplexml_load_string') && function_exists('mb_detect_encoding') && function_exists('mb_convert_encoding')) {
    $parser = new rss_parse();
    foreach ([
        'atom_qiita_shape.xml' => ['https://qiita.example/tags/test', 'https://qiita.example/user/items/a'],
        'atom_publickey_shape.xml' => ['https://www.publickey.example/', 'https://www.publickey.example/blog/26/article.html'],
        'rss2_text_link.xml' => ['https://example.test/', 'https://example.test/article-c'],
    ] as $fixture => [$expectedChannel, $expectedItem]) {
        $xml = file_get_contents($root . '/tests/fixtures/' . $fixture);
        $parsed = $parser->parse_start($xml);
        atom_link_check(($parsed['channel']['link'] ?? null) === $expectedChannel, "{$fixture} channel link is parsed");
        atom_link_check(($parsed['item'][0]['link'] ?? null) === $expectedItem, "{$fixture} article link is parsed");
    }
} else {
    echo "SKIP: live SimpleXML fixture parsing (SimpleXML/mbstring unavailable in this execution environment).\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("\n%d/%d tests failed.\n", count($failures), $tests));
    exit(1);
}

echo "\nAll {$tests} SB-12 R2 Atom-link tests passed.\n";
