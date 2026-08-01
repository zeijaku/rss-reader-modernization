<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/feed/feed_parser.php';

$checks = 0;
$failures = [];

function m1d_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) {
        $failures[] = $message;
    }
}

function m1d_throws(callable $callable, string $class): bool
{
    try {
        $callable();
    } catch (Throwable $exception) {
        return $exception instanceof $class;
    }
    return false;
}

function m1d_item(
    string $title = 'Title',
    ?string $link = 'https://example.test/article',
    ?string $description = 'Description',
    ?string $content = 'Content',
    ?string $date = '2026-08-01 12:00:00',
    ?string $sourceItemId = null
): NormalizedItem {
    return new NormalizedItem($title, $link, $description, $content, $date, $sourceItemId);
}

$resolver = new ItemIdentityResolver();
$scope = 'https://feeds.example.test/rss.xml';

// Value object validation and immutability.
$valid = new ItemIdentity('m1i:v1:' . str_repeat('a', 64), ItemIdentity::BASIS_SOURCE_ID);
m1d_check($valid->value === 'm1i:v1:' . str_repeat('a', 64), 'ItemIdentity accepts the versioned opaque SHA-256 value');
m1d_check($valid->basis === ItemIdentity::BASIS_SOURCE_ID, 'ItemIdentity retains the internal basis');
m1d_check(m1d_throws(static fn() => new ItemIdentity('raw-guid', ItemIdentity::BASIS_SOURCE_ID), InvalidArgumentException::class), 'ItemIdentity rejects raw/non-opaque values');
m1d_check(m1d_throws(static fn() => new ItemIdentity('m1i:v1:' . str_repeat('a', 63), ItemIdentity::BASIS_SOURCE_ID), InvalidArgumentException::class), 'ItemIdentity rejects incorrect digest length');
m1d_check(m1d_throws(static fn() => new ItemIdentity('m1i:v1:' . str_repeat('A', 64), ItemIdentity::BASIS_SOURCE_ID), InvalidArgumentException::class), 'ItemIdentity rejects non-canonical uppercase digest');
m1d_check(m1d_throws(static fn() => new ItemIdentity('m1i:v1:' . str_repeat('a', 64), 'unknown'), InvalidArgumentException::class), 'ItemIdentity rejects unknown basis');
$identityReadonly = false;
try {
    $valid->basis = ItemIdentity::BASIS_LINK;
} catch (Error) {
    $identityReadonly = true;
}
m1d_check($identityReadonly, 'ItemIdentity fields are readonly');

// Native source ID has highest priority.
$native = m1d_item(sourceItemId: '  article-123  ');
$nativeResolved = $resolver->resolve($native, $scope);
m1d_check($nativeResolved !== $native, 'identity attachment returns a new immutable NormalizedItem');
m1d_check($native->identity === null, 'identity attachment does not mutate the original item');
m1d_check($nativeResolved->identity instanceof ItemIdentity, 'resolved item carries an ItemIdentity');
m1d_check($nativeResolved->identity?->basis === ItemIdentity::BASIS_SOURCE_ID, 'native source ID outranks item link');
m1d_check($nativeResolved->sourceItemId === '  article-123  ', 'raw source item ID remains internal metadata without destructive rewrite');
m1d_check(!str_contains((string) $nativeResolved->identity?->value, 'article-123'), 'opaque identity does not expose native source ID');
m1d_check(!str_contains((string) $nativeResolved->identity?->value, 'example.test'), 'opaque identity does not expose source or item URL');

// Stability despite mutable article fields when native ID exists.
$nativeChanged = m1d_item('Changed title', 'https://example.test/changed', 'Changed description', 'Changed content', '2030-01-01 00:00:00', 'article-123');
$nativeChangedResolved = $resolver->resolve($nativeChanged, $scope);
m1d_check($nativeChangedResolved->identity?->value === $nativeResolved->identity?->value, 'native ID identity survives title/link/content/date changes');

// Link fallback.
$linkOnly = m1d_item(sourceItemId: null);
$linkResolved = $resolver->resolve($linkOnly, $scope);
m1d_check($linkResolved->identity?->basis === ItemIdentity::BASIS_LINK, 'item link is used when native ID is absent');
$linkBlankId = m1d_item(sourceItemId: " \r\n ");
m1d_check($resolver->resolve($linkBlankId, $scope)->identity?->value === $linkResolved->identity?->value, 'blank native ID falls back to link deterministically');
$linkChangedFields = m1d_item('Other title', 'https://example.test/article', 'Other', null, null, null);
m1d_check($resolver->resolve($linkChangedFields, $scope)->identity?->value === $linkResolved->identity?->value, 'link identity survives title/content/date changes');
$differentLink = m1d_item(link: 'https://example.test/article-2');
m1d_check($resolver->resolve($differentLink, $scope)->identity?->value !== $linkResolved->identity?->value, 'different item links produce different identities');

// No aggressive URL canonicalization.
$queryA = m1d_item(link: 'https://example.test/article?a=1&b=2');
$queryB = m1d_item(link: 'https://example.test/article?b=2&a=1');
m1d_check($resolver->resolve($queryA, $scope)->identity?->value !== $resolver->resolve($queryB, $scope)->identity?->value, 'query parameter order is not guessed/canonicalized');
$slashA = m1d_item(link: 'https://example.test/article');
$slashB = m1d_item(link: 'https://example.test/article/');
m1d_check($resolver->resolve($slashA, $scope)->identity?->value !== $resolver->resolve($slashB, $scope)->identity?->value, 'trailing slash is not guessed/canonicalized');

// Fingerprint fallback.
$fingerprintItem = m1d_item('日本語タイトル', null, "Line 1\r\nLine 2", '<p>本文</p>', '2026-08-01 12:00:00', null);
$fingerprintResolved = $resolver->resolve($fingerprintItem, $scope);
m1d_check($fingerprintResolved->identity?->basis === ItemIdentity::BASIS_FINGERPRINT, 'fingerprint is used only when native ID and link are absent');
$fingerprintLf = m1d_item('日本語タイトル', null, "Line 1\nLine 2", '<p>本文</p>', '2026-08-01 12:00:00', null);
m1d_check($resolver->resolve($fingerprintLf, $scope)->identity?->value === $fingerprintResolved->identity?->value, 'fingerprint normalizes CRLF and LF line endings');
$fingerprintChanged = m1d_item('日本語タイトル changed', null, "Line 1\nLine 2", '<p>本文</p>', '2026-08-01 12:00:00', null);
m1d_check($resolver->resolve($fingerprintChanged, $scope)->identity?->value !== $fingerprintResolved->identity?->value, 'fingerprint changes when fallback content changes');
$emptyFingerprint = $resolver->resolve(m1d_item('', null, null, null, null, null), $scope);
m1d_check($emptyFingerprint->identity?->basis === ItemIdentity::BASIS_FINGERPRINT, 'fully empty item fields still produce controlled deterministic fingerprint identity');
m1d_check($resolver->resolve(m1d_item('', null, null, null, null, null), $scope)->identity?->value === $emptyFingerprint->identity?->value, 'empty fallback identity is deterministic');

// Feed scoping and duplicate registration semantics.
$sameAgain = $resolver->resolve($native, $scope);
m1d_check($sameAgain->identity?->value === $nativeResolved->identity?->value, 'same feed and item resolve identically on repeated runs');
m1d_check($resolver->resolve($native, 'https://feeds.example.test/other.xml')->identity?->value !== $nativeResolved->identity?->value, 'same native ID in a different Feed URL has a different identity');
m1d_check($resolver->resolve($native, $scope)->identity?->value === $resolver->resolve($native, $scope)->identity?->value, 'identity does not depend on content_id or owner_id');
m1d_check(m1d_throws(static fn() => $resolver->resolve($native, ''), InvalidArgumentException::class), 'empty Feed scope is rejected');
m1d_check(m1d_throws(static fn() => $resolver->resolve($native, ' https://feeds.example.test/rss.xml'), InvalidArgumentException::class), 'non-canonical whitespace Feed scope is rejected');

// Boundary values and invalid UTF-8 fail safely and deterministically.
$longId = str_repeat('長い識別子', 2000);
$longResolved = $resolver->resolve(m1d_item(sourceItemId: $longId), $scope);
m1d_check((bool) preg_match('/\Am1i:v1:[a-f0-9]{64}\z/', (string) $longResolved->identity?->value), 'extremely long Unicode source ID hashes to bounded identity');
$invalidUtf8 = "bad\xC3\x28";
$invalidA = $resolver->resolve(m1d_item(sourceItemId: $invalidUtf8), $scope);
$invalidB = $resolver->resolve(m1d_item(sourceItemId: $invalidUtf8), $scope);
m1d_check($invalidA->identity?->value === $invalidB->identity?->value, 'invalid UTF-8 source metadata is substituted deterministically without uncaught exception');
$javascriptId = $resolver->resolve(m1d_item(sourceItemId: 'javascript:alert(1)'), $scope);
m1d_check(!str_contains((string) $javascriptId->identity?->value, 'javascript'), 'URL-like malicious source ID is treated as opaque hash input only');

// Public compatibility contract.
$publicArray = $nativeResolved->toArray();
m1d_check(array_keys($publicArray) === ['title', 'link', 'description', 'content', 'date'], 'public item array remains exactly five fields');
m1d_check(!array_key_exists('identity', $publicArray) && !array_key_exists('sourceItemId', $publicArray), 'identity metadata is absent from legacy/public item array');
m1d_check($publicArray === $native->toArray(), 'identity attachment does not alter existing public field values');

// Live adapter/parser matrix when optional extensions are available.
$liveParserAvailable = function_exists('simplexml_load_string')
    && function_exists('mb_detect_encoding')
    && function_exists('mb_convert_encoding');

if ($liveParserAvailable) {
    $parser = new FeedParser();

    $rss2 = $parser->parse_normalized(file_get_contents($root . '/tests/fixtures/rss2_identity.xml'), $scope);
    $rss2Items = $rss2['item'] ?? [];
    m1d_check(($rss2['type'] ?? null) === 'rss2' && count($rss2Items) === 4, 'RSS 2.0 identity fixture parses all items');
    m1d_check(($rss2Items[0]->sourceItemId ?? null) === 'article-001', 'RSS 2.0 adapter extracts guid');
    m1d_check(($rss2Items[0]->identity?->basis ?? null) === ItemIdentity::BASIS_SOURCE_ID, 'RSS 2.0 guid produces source-id identity');
    m1d_check(($rss2Items[1]->identity?->basis ?? null) === ItemIdentity::BASIS_LINK, 'blank RSS 2.0 guid falls back to link');
    m1d_check(($rss2Items[2]->identity?->basis ?? null) === ItemIdentity::BASIS_FINGERPRINT, 'RSS 2.0 item without guid/link falls back to fingerprint');
    m1d_check(($rss2Items[3]->sourceItemId ?? null) === 'https://example.test/articles/permalink-guid', 'RSS guid is retained as opaque ID regardless of isPermaLink');

    $rss1 = $parser->parse_normalized(file_get_contents($root . '/tests/fixtures/rss1_basic.xml'), $scope);
    $rss1Item = $rss1['item'][0] ?? null;
    m1d_check($rss1Item instanceof NormalizedItem && $rss1Item->sourceItemId === 'https://example.test/rss1-item', 'RSS 1.0 adapter extracts rdf:about');
    m1d_check($rss1Item instanceof NormalizedItem && $rss1Item->identity?->basis === ItemIdentity::BASIS_SOURCE_ID, 'RSS 1.0 rdf:about produces source-id identity');

    $atom = $parser->parse_normalized(file_get_contents($root . '/tests/fixtures/atom_identity.xml'), $scope);
    $atomItems = $atom['item'] ?? [];
    m1d_check(($atom['type'] ?? null) === 'atom' && count($atomItems) === 3, 'Atom identity fixture parses all entries');
    m1d_check(($atomItems[0]->sourceItemId ?? null) === 'tag:example.test,2026:item-001', 'Atom adapter extracts entry id');
    m1d_check(($atomItems[0]->identity?->basis ?? null) === ItemIdentity::BASIS_SOURCE_ID, 'Atom id produces source-id identity');
    m1d_check(($atomItems[1]->identity?->basis ?? null) === ItemIdentity::BASIS_LINK, 'blank Atom id falls back to alternate link');
    m1d_check(($atomItems[2]->identity?->basis ?? null) === ItemIdentity::BASIS_FINGERPRINT, 'Atom entry without id/link falls back to fingerprint');

    $withoutScope = $parser->parse_normalized(file_get_contents($root . '/tests/fixtures/rss2_identity.xml'));
    m1d_check(($withoutScope['item'][0]->identity ?? 'sentinel') === null, 'legacy parser call without source scope remains compatible and leaves identity unresolved');

    $legacy = $parser->parse_start(file_get_contents($root . '/tests/fixtures/rss2_identity.xml'), $scope);
    m1d_check(array_keys($legacy['item'][0] ?? []) === ['title', 'link', 'description', 'content', 'date'], 'parse_start still removes internal identity metadata from public arrays');
} else {
    echo "SKIP: M1-D live identity adapter matrix requires SimpleXML and mbstring.\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d/%d M1-D item identity checks failed.\n", count($failures), $checks));
    exit(1);
}

echo "All {$checks} executable M1-D item identity checks passed.\n";
