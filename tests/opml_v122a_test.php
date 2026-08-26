<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/validation.php';
require_once dirname(__DIR__) . '/app/feed_metadata.php';
require_once dirname(__DIR__) . '/app/opml.php';

function v122a_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$export = opml_build_export([
    ['feed_title' => 'A & B <script>alert(1)</script>', 'feed_url' => 'https://example.com/feed.xml', 'site_url' => 'https://example.com/?a=1&b=2', 'category_path' => 'Technology / Security'],
]);
v122a_assert(str_contains($export, 'A &amp; B &lt;script&gt;alert(1)&lt;/script&gt;'), 'export must XML-escape titles and markup');
v122a_assert(str_contains($export, 'a=1&amp;b=2'), 'export must XML-escape URLs');
v122a_assert(str_contains($export, '<outline text="Technology"'), 'export must rebuild category hierarchy');
v122a_assert(!str_contains($export, '<script'), 'export must not inject title markup');
fwrite(STDOUT, "PASS: V1.22-A OPML export focused tests\n");

if (!function_exists('simplexml_load_string')) {
    fwrite(STDOUT, "SKIP: OPML parser runtime tests (SimpleXML is not available in this PHP runtime).\n");
    exit(0);
}

$valid = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<opml version="2.0">
  <head><title>Fixture</title></head>
  <body>
    <outline text="Technology">
      <outline text="Security">
        <outline type="rss" text="Example &amp; News" xmlUrl="https://example.com/feed.xml" htmlUrl="https://example.com/" />
      </outline>
    </outline>
    <outline type="rss" text="Second" xmlUrl="HTTPS://EXAMPLE.ORG/rss" category="News/Japan" />
  </body>
</opml>
XML;

$parsed = opml_parse($valid);
v122a_assert(count($parsed['feeds']) === 2, 'valid OPML must contain two feeds');
v122a_assert($parsed['failure_count'] === 0, 'valid OPML must have no failures');
v122a_assert($parsed['feeds'][0]['title'] === 'Example & News', 'title must be decoded safely');
v122a_assert($parsed['feeds'][0]['category_path'] === 'Technology / Security', 'nested category path must be preserved');
v122a_assert($parsed['feeds'][1]['feed_url'] === 'https://example.org/rss', 'feed URL must use existing URL normalization');
v122a_assert($parsed['feeds'][1]['category_path'] === 'News / Japan', 'category attribute path must be normalized');

try {
    opml_parse('<!DOCTYPE opml [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><opml><body /></opml>');
    v122a_assert(false, 'DOCTYPE must be rejected');
} catch (InvalidArgumentException) {
}

$invalidFeed = opml_parse('<opml><body><outline text="bad" xmlUrl="file:///etc/passwd" /></body></opml>');
v122a_assert(count($invalidFeed['feeds']) === 0 && $invalidFeed['failure_count'] === 1, 'invalid feed URL must be a failure');

fwrite(STDOUT, "PASS: V1.22-A OPML parser focused tests\n");
