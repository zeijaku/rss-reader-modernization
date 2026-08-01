<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/feed/feed_parser.php';

$checks = 0;
$failures = [];

function m1c_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) {
        $failures[] = $message;
    }
}

function m1c_throws(callable $callable, string $class): bool
{
    try {
        $callable();
    } catch (Throwable $exception) {
        return $exception instanceof $class;
    }
    return false;
}

// Date normalization is executable without optional XML/mbstring extensions.
$dateCases = [
    [null, null, 'null date remains null'],
    [false, null, 'boolean date remains null'],
    [0, null, 'integer date remains null'],
    ['', null, 'empty date remains null'],
    ['   ', null, 'whitespace date remains null'],
    ['invalid-date', null, 'invalid date remains null'],
    ['Wed, 29 Jul 2026 10:00:00 +0900', '2026-07-29 10:00:00', 'RFC822 date is normalized'],
    ['2026-07-30T12:34:56+09:00', '2026-07-30 12:34:56', 'RFC3339 offset date is normalized without timezone conversion'],
    ['2026-08-01T02:03:04Z', '2026-08-01 02:03:04', 'UTC Z date is normalized'],
    ['2026-08-01T11:12:13+0900', '2026-08-01 11:12:13', 'compact numeric timezone date is normalized'],
    ['2026-08-01', '2026-08-01 00:00:00', 'date-only input becomes midnight'],
    [str_repeat('not-a-date-', 200), null, 'extremely long invalid date fails closed'],
];

$warningMessages = [];
set_error_handler(static function (int $severity, string $message) use (&$warningMessages): bool {
    $warningMessages[] = $message;
    return true;
});
foreach ($dateCases as [$input, $expected, $message]) {
    m1c_check(FeedDateNormalizer::normalize($input) === $expected, $message);
}
restore_error_handler();
m1c_check($warningMessages === [], 'date normalization emits no PHP warnings for boundary inputs');
m1c_check(rss_normalize_date('2026-08-01T11:12:13+09:00') === '2026-08-01 11:12:13', 'Legacy date wrapper delegates to centralized normalizer');

// Link selection behavior remains stable after moving it out of FeedParser.
$linkCandidates = [
    ['href' => 'https://example.test/self', 'rel' => 'self', 'type' => 'application/atom+xml'],
    ['href' => 'https://example.test/related', 'rel' => 'related'],
    ['href' => 'https://example.test/article', 'rel' => 'alternate', 'type' => 'text/html'],
];
m1c_check(FeedLinkSelector::select($linkCandidates) === 'https://example.test/article', 'shared link selector prefers browser-facing Atom alternate link');
m1c_check(rss_select_link_candidate($linkCandidates) === 'https://example.test/article', 'Legacy link wrapper delegates to centralized selector');
m1c_check(FeedLinkSelector::select([['href' => ' '], ['href' => null]]) === null, 'shared link selector rejects blank and non-string candidates');

// Adapter architecture can be verified even when SimpleXML is unavailable.
$adapterClasses = [Rss2Adapter::class, Rss1Adapter::class, AtomAdapter::class];
foreach ($adapterClasses as $adapterClass) {
    $reflection = new ReflectionClass($adapterClass);
    m1c_check($reflection->isFinal(), "{$adapterClass} is final");
    m1c_check($reflection->implementsInterface(FeedAdapterInterface::class), "{$adapterClass} implements FeedAdapterInterface");
}
m1c_check(m1c_throws(static fn() => new FeedParser([new stdClass()]), InvalidArgumentException::class), 'FeedParser rejects objects that do not implement FeedAdapterInterface');
m1c_check(is_subclass_of('rss_parse', FeedParser::class), 'Legacy rss_parse name remains compatible after adapter split');

$parser = new FeedParser();
m1c_check($parser->parse_normalized(null) === [] && $parser->last_error === 'Feed body is empty.', 'Parser rejects null body before adapter dispatch');
m1c_check($parser->parse_start('') === [] && $parser->last_error === 'Feed body is empty.', 'Compatibility parser rejects empty body before adapter dispatch');

$liveParserAvailable = function_exists('simplexml_load_string')
    && function_exists('mb_detect_encoding')
    && function_exists('mb_convert_encoding');

if ($liveParserAvailable) {
    $fixtureCases = [
        'rss2_zero.xml' => ['rss2', 0, 'https://example.test/zero'],
        'rss2_four.xml' => ['rss2', 4, 'https://example.test/four'],
        'rss2_six.xml' => ['rss2', 6, 'https://example.test/six'],
        'rss2_text_link.xml' => ['rss2', 1, 'https://example.test/'],
        'rss2_modules.xml' => ['rss2', 1, 'https://example.test/modules'],
        'rss1_basic.xml' => ['rss1', 1, 'https://example.test/'],
        'atom_no_declaration.xml' => ['atom', 1, 'https://example.test/'],
        'atom_qiita_shape.xml' => ['atom', 1, 'https://qiita.example/tags/test'],
        'atom_publickey_shape.xml' => ['atom', 1, 'https://www.publickey.example/'],
        'atom_updated_published.xml' => ['atom', 1, 'https://example.test/atom-priority'],
        'atom_zero.xml' => ['atom', 0, 'https://example.test/atom-zero'],
    ];

    foreach ($fixtureCases as $fixture => [$expectedType, $expectedItems, $expectedChannelLink]) {
        $body = file_get_contents($root . '/tests/fixtures/' . $fixture);
        $parsed = $parser->parse_normalized($body);
        m1c_check(($parsed['type'] ?? null) === $expectedType, "{$fixture} dispatches to {$expectedType} adapter");
        m1c_check(count($parsed['item'] ?? []) === $expectedItems, "{$fixture} preserves item count");
        m1c_check(($parsed['channel']['link'] ?? null) === $expectedChannelLink, "{$fixture} preserves channel browser link");
        m1c_check(array_reduce($parsed['item'] ?? [], static fn(bool $ok, mixed $item): bool => $ok && $item instanceof NormalizedItem, true), "{$fixture} emits only NormalizedItem objects");
    }

    $rssFour = $parser->parse_normalized(file_get_contents($root . '/tests/fixtures/rss2_four.xml'));
    m1c_check(($rssFour['item'][0]->date ?? null) === '2026-07-29 10:00:00', 'RSS 2.0 adapter normalizes valid pubDate');
    m1c_check(($rssFour['item'][1]->date ?? 'not-null') === null, 'RSS 2.0 adapter converts invalid pubDate to null');
    m1c_check(($rssFour['item'][2]->date ?? 'not-null') === null, 'RSS 2.0 adapter preserves missing date as null');

    $modules = $parser->parse_normalized(file_get_contents($root . '/tests/fixtures/rss2_modules.xml'));
    m1c_check(($modules['item'][0]->content ?? null) === '<article>Full body</article>', 'RSS 2.0 adapter extracts content:encoded');
    m1c_check(($modules['item'][0]->description ?? null) === '<p>Summary body</p>', 'RSS 2.0 adapter preserves CDATA description internally');
    m1c_check(($modules['item'][0]->date ?? null) === '2026-08-01 02:03:04', 'RSS 2.0 adapter falls back from invalid pubDate to dc:date');

    $rss1 = $parser->parse_normalized(file_get_contents($root . '/tests/fixtures/rss1_basic.xml'));
    m1c_check(($rss1['item'][0]->date ?? null) === '2026-07-30 01:02:03', 'RSS 1.0 adapter extracts Dublin Core date');

    $atom = $parser->parse_normalized(file_get_contents($root . '/tests/fixtures/atom_no_declaration.xml'));
    m1c_check(($atom['item'][0]->link ?? null) === 'https://example.test/article', 'Atom adapter prefers alternate HTML item link');
    m1c_check(($atom['item'][0]->date ?? null) === '2026-07-30 12:34:56', 'Atom adapter normalizes updated date');

    $qiita = $parser->parse_normalized(file_get_contents($root . '/tests/fixtures/atom_qiita_shape.xml'));
    m1c_check(($qiita['item'][0]->link ?? null) === 'https://qiita.example/user/items/a', 'Atom adapter preserves Qiita alternate-link behavior');
    m1c_check(($qiita['item'][0]->date ?? null) === '2026-07-30 01:02:03', 'Atom adapter supports published when updated is absent');

    $priority = $parser->parse_normalized(file_get_contents($root . '/tests/fixtures/atom_updated_published.xml'));
    m1c_check(($priority['item'][0]->date ?? null) === '2026-08-01 11:00:00', 'Atom adapter prefers updated over published');

    $legacy = $parser->parse_start(file_get_contents($root . '/tests/fixtures/rss2_modules.xml'));
    m1c_check(isset($legacy['item'][0]) && is_array($legacy['item'][0]), 'parse_start converts adapter NormalizedItem objects to Legacy arrays');
    m1c_check(array_keys($legacy['item'][0]) === ['title', 'link', 'description', 'content', 'date'], 'Legacy item array shape remains unchanged');

    $badCases = [
        'malformed.xml' => null,
        'unsupported_xml.xml' => 'Unsupported XML feed format.',
        'rss2_missing_channel.xml' => 'Unsupported XML feed format.',
        'rss1_missing_channel.xml' => 'RSS 1.0 channel is missing.',
    ];
    foreach ($badCases as $fixture => $expectedError) {
        $bad = $parser->parse_normalized(file_get_contents($root . '/tests/fixtures/' . $fixture));
        m1c_check($bad === [], "{$fixture} fails closed");
        m1c_check(is_string($parser->last_error) && $parser->last_error !== '', "{$fixture} returns a controlled parser error");
        if ($expectedError !== null) {
            m1c_check($parser->last_error === $expectedError, "{$fixture} preserves expected error classification");
        }
    }

    $xxe = $parser->parse_normalized(file_get_contents($root . '/tests/fixtures/rss2_external_entity.xml'));
    m1c_check(($xxe['type'] ?? null) === 'rss2', 'external-entity fixture remains parseable without entity expansion');
    $xxeDescription = (string) ($xxe['channel']['description'] ?? '');
    m1c_check(!str_contains($xxeDescription, 'root:') && !str_contains($xxeDescription, 'm1c-should-not-fetch'), 'external entity is not expanded into feed output');

    $afterError = $parser->parse_normalized(file_get_contents($root . '/tests/fixtures/atom_zero.xml'));
    m1c_check(($afterError['type'] ?? null) === 'atom' && $parser->last_error === null, 'successful parse clears previous parser error state');
} else {
    echo "SKIP: M1-C live adapter matrix requires SimpleXML and mbstring.\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d/%d M1-C feed adapter checks failed.\n", count($failures), $checks));
    exit(1);
}

echo "All {$checks} executable M1-C feed adapter checks passed.\n";
