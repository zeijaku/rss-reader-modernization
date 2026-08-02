<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/validation.php';
require_once $root . '/app/url_normalizer.php';
require_once $root . '/app/feed/item_identity.php';
require_once $root . '/app/feed/normalized_item.php';
require_once $root . '/app/feed/item_identity_resolver.php';
require_once $root . '/app/api.php';

$checks = 0;
$failures = [];

function v11b_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) {
        $failures[] = $message;
    }
}

function v11b_same(string $expected, string $actual, string $message): void
{
    v11b_check($expected === $actual, $message . ($expected === $actual ? '' : "\n  expected: {$expected}\n  actual:   {$actual}"));
}

$trackingNames = [
    'utm_source',
    'utm_medium',
    'utm_campaign',
    'utm_term',
    'utm_content',
    'fbclid',
    'gclid',
    'dclid',
    'mc_cid',
    'mc_eid',
    'ref_src',
];

foreach ($trackingNames as $name) {
    v11b_same(
        'https://example.test/article?id=42',
        app_remove_tracking_parameters('https://example.test/article?' . $name . '=tracking&id=42'),
        $name . ' is removed at the beginning of the query'
    );
    v11b_same(
        'https://example.test/article?id=42&page=3',
        app_remove_tracking_parameters('https://example.test/article?id=42&' . $name . '=tracking&page=3'),
        $name . ' is removed from the middle of the query'
    );
    v11b_same(
        'https://example.test/article?id=42',
        app_remove_tracking_parameters('https://example.test/article?id=42&' . $name . '=tracking'),
        $name . ' is removed at the end of the query'
    );
}

v11b_same(
    'https://example.test/article?id=42',
    app_remove_tracking_parameters('https://example.test/article?UTM_SOURCE=x&id=42&FbClId=y'),
    'tracking names are matched case-insensitively'
);
v11b_same(
    'https://example.test/article?id=42',
    app_remove_tracking_parameters('https://example.test/article?%75tm%5Fsource=x&id=42'),
    'percent-encoded tracking name is removed'
);
v11b_same(
    'https://example.test/article?id=42&page=2&article=abc&category=news&lang=ja',
    app_remove_tracking_parameters('https://example.test/article?id=42&utm_source=x&page=2&article=abc&category=news&lang=ja'),
    'general query parameters are retained in their original order'
);
v11b_same(
    'https://example.test/article?id=42#section-2',
    app_remove_tracking_parameters('https://example.test/article?id=42&utm_medium=rss#section-2'),
    'fragment is retained after tracking removal'
);
v11b_same(
    'https://example.test/article#section-2',
    app_remove_tracking_parameters('https://example.test/article?utm_medium=rss#section-2'),
    'question mark is removed when no query parameter remains'
);
v11b_same(
    'https://example.test/article',
    app_remove_tracking_parameters('https://example.test/article?utm_source=x&fbclid=y&gclid=z'),
    'all repeated tracking parameters can be removed'
);
v11b_same(
    'https://example.test/article?id=1&id=2',
    app_remove_tracking_parameters('https://example.test/article?id=1&utm_source=x&id=2'),
    'duplicate general parameters are retained'
);
v11b_same(
    'https://example.test/article?x=utm_source%3Dinside&id=4',
    app_remove_tracking_parameters('https://example.test/article?x=utm_source%3Dinside&utm_source=outside&id=4'),
    'tracking text inside a value is not treated as a parameter name'
);
v11b_same(
    'https://example.test/article?xutm_source=1&utm_source_extra=2&utm_source%5B%5D=3&id=4',
    app_remove_tracking_parameters('https://example.test/article?xutm_source=1&utm_source_extra=2&utm_source%5B%5D=3&utm_source=4&id=4'),
    'similar but different parameter names are retained'
);
v11b_same(
    'https://example.test/article?id=42',
    app_remove_tracking_parameters('https://example.test/article?utm_source&id=42&gclid='),
    'tracking names without values or with empty values are removed'
);
v11b_same(
    'https://example.test/article?id=42;page=3',
    app_remove_tracking_parameters('https://example.test/article?id=42;utm_source=x;page=3'),
    'semicolon-separated query parameters retain their separator'
);
v11b_same(
    'https://example.test/article?q=%E6%97%A5%E6%9C%AC%E8%AA%9E&sort=new',
    app_remove_tracking_parameters('https://example.test/article?q=%E6%97%A5%E6%9C%AC%E8%AA%9E&utm_campaign=test&sort=new'),
    'encoded Japanese query values are retained byte-for-byte'
);
v11b_same(
    'https://example.test/article?empty=&flag',
    app_remove_tracking_parameters('https://example.test/article?empty=&utm_term=x&flag'),
    'empty and valueless general parameters are retained'
);
v11b_same(
    'https://example.test/article',
    app_remove_tracking_parameters('https://example.test/article'),
    'URL without a query is unchanged'
);
v11b_same(
    'tag:example.test,2026:item?utm_source=source-id',
    app_remove_tracking_parameters('tag:example.test,2026:item?utm_source=source-id'),
    'non-http source ID is unchanged'
);
v11b_same(
    '/article?utm_source=x&id=1',
    app_remove_tracking_parameters('/article?utm_source=x&id=1'),
    'relative URL is unchanged'
);
v11b_same(
    'not a url?utm_source=x&id=1',
    app_remove_tracking_parameters('not a url?utm_source=x&id=1'),
    'invalid URL-like text is unchanged'
);

$resolver = new ItemIdentityResolver();
$scope = 'https://feeds.example.test/rss.xml?category=php&utm_source=feed-condition';
$linkA = new NormalizedItem('One', 'https://example.test/article?id=42&utm_source=rss', null, null, null);
$linkB = new NormalizedItem('One changed', 'https://example.test/article?id=42&fbclid=abc', null, null, null);
$linkC = new NormalizedItem('One', 'https://example.test/article?id=43&utm_source=rss', null, null, null);
$identityA = $resolver->resolve($linkA, $scope)->identity;
$identityB = $resolver->resolve($linkB, $scope)->identity;
$identityC = $resolver->resolve($linkC, $scope)->identity;
v11b_check($identityA?->value === $identityB?->value, 'link identity ignores known tracking parameter differences');
v11b_check($identityA?->value !== $identityC?->value, 'link identity retains meaningful query parameter differences');

$sourceA = new NormalizedItem('One', 'https://example.test/other', null, null, null, 'https://example.test/article?id=42&utm_campaign=a');
$sourceB = new NormalizedItem('Two', 'https://example.test/different', null, null, null, 'https://example.test/article?id=42&gclid=b');
$sourceIdentityA = $resolver->resolve($sourceA, $scope)->identity;
$sourceIdentityB = $resolver->resolve($sourceB, $scope)->identity;
v11b_check($sourceIdentityA?->basis === ItemIdentity::BASIS_SOURCE_ID, 'URL-shaped source ID remains the preferred identity basis');
v11b_check($sourceIdentityA?->value === $sourceIdentityB?->value, 'URL-shaped source ID is normalized before identity generation');

$opaqueA = new NormalizedItem('One', null, null, null, null, 'tag:example.test,2026:item?utm_source=a');
$opaqueB = new NormalizedItem('One', null, null, null, null, 'tag:example.test,2026:item?utm_source=b');
v11b_check(
    $resolver->resolve($opaqueA, $scope)->identity?->value !== $resolver->resolve($opaqueB, $scope)->identity?->value,
    'non-URL source IDs are not rewritten'
);

$scopeWithoutTracking = 'https://feeds.example.test/rss.xml?category=php';
v11b_check(
    $resolver->resolve($linkA, $scope)->identity?->value !== $resolver->resolve($linkA, $scopeWithoutTracking)->identity?->value,
    'registered Feed URL scope is not tracking-normalized'
);

$payload = api_safe_feed_payload([
    'channel' => [
        'title' => 'Channel',
        'link' => 'https://example.test/?utm_source=channel',
        'description' => 'Description',
    ],
    'item' => [
        [
            'title' => 'Tracked',
            'link' => 'https://example.test/article?id=42&utm_source=rss&fbclid=abc#entry',
            'description' => '<b>Description</b>',
            'content' => '<p>Content</p>',
            'date' => '2026-08-02',
        ],
        [
            'title' => 'Invalid',
            'link' => 'javascript:alert(1)',
            'description' => '',
            'content' => '',
            'date' => '',
        ],
    ],
], 'https://feeds.example.test/rss.xml');

v11b_same(
    'https://example.test/article?id=42#entry',
    (string) ($payload['item'][0]['link'] ?? ''),
    'feed item link is cleaned before API display payload'
);
v11b_same(
    'https://example.test/?utm_source=channel',
    (string) ($payload['channel']['link'] ?? ''),
    'channel link is outside article tracking removal scope'
);
v11b_same('', (string) ($payload['item'][1]['link'] ?? ''), 'invalid feed item link still fails closed');
v11b_same('Description', (string) ($payload['item'][0]['description'] ?? ''), 'existing feed text sanitization remains active');

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d/%d V1.1-B tracking parameter checks failed.\n", count($failures), $checks));
    exit(1);
}

echo "All {$checks} V1.1-B tracking parameter checks passed.\n";
