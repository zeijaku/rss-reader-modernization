<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/url_normalizer.php';

function expect_same(string $expected, string $actual, string $label): void
{
    if ($expected === $actual) {
        echo "PASS: {$label}\n";
        return;
    }

    fwrite(STDERR, "FAIL: {$label}\nExpected: {$expected}\nActual:   {$actual}\n");
    exit(1);
}

expect_same(
    'https://example.com/article?id=123&page=2&article=abc#comments',
    app_remove_tracking_parameters('https://example.com/article?utm_source=rss&id=123&utm_medium=email&page=2&utm_campaign=summer&article=abc&utm_term=php&utm_content=top&utm_id=42&fbclid=f&gclid=g&dclid=d&msclkid=m&mc_cid=c&mc_eid=e#comments'),
    'known tracking parameters are removed while functional parameters and fragment remain'
);

expect_same(
    'https://example.com/article?id=1&id=2&encoded=a%2Bb&name=hello+world',
    app_remove_tracking_parameters('https://example.com/article?id=1&utm_source=x&id=2&encoded=a%2Bb&msclkid=y&name=hello+world'),
    'duplicate parameters, ordering and original encoding are preserved'
);

expect_same(
    'https://example.com/article?id=1;page=2#part',
    app_remove_tracking_parameters('https://example.com/article?id=1;utm_id=abc;page=2#part'),
    'semicolon-separated query parameters remain usable'
);

expect_same(
    'https://example.com/article#frag',
    app_remove_tracking_parameters('https://example.com/article?utm_source=x&msclkid=y#frag'),
    'query delimiter is removed when only tracking parameters remain'
);

expect_same(
    'https://example.com/article?ID=123&Page=2',
    app_remove_tracking_parameters('https://example.com/article?ID=123&Page=2'),
    'unlisted query parameters remain untouched'
);

expect_same(
    'mailto:user@example.com?utm_source=x',
    app_remove_tracking_parameters('mailto:user@example.com?utm_source=x'),
    'non-http URL is not modified'
);

expect_same(
    '/article?id=1&utm_source=x',
    app_remove_tracking_parameters('/article?id=1&utm_source=x'),
    'relative URL is not modified'
);

expect_same(
    'https://example.com/article?id=9',
    app_remove_tracking_parameters('https://example.com/article?UTM_ID=abc&id=9'),
    'tracking parameter matching is case-insensitive'
);

echo "All V1.27-B URL normalizer focused tests passed.\n";
