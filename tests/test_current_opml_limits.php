<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/validation.php';
require_once dirname(__DIR__) . '/app/feed_metadata.php';
require_once dirname(__DIR__) . '/app/opml.php';

$pass = 0;
$fail = 0;
$skip = 0;

function v145d_limits_check(bool $condition, string $message): void
{
    global $pass, $fail;
    if ($condition) { $pass++; echo "PASS: {$message}\n"; }
    else { $fail++; echo "FAIL: {$message}\n"; }
}

function v145d_expect_invalid(callable $fn, string $message): void
{
    try {
        $fn();
        v145d_limits_check(false, $message);
    } catch (InvalidArgumentException) {
        v145d_limits_check(true, $message);
    }
}

v145d_expect_invalid(
    static fn() => opml_parse(str_repeat('x', OPML_MAX_IMPORT_BYTES + 1)),
    'Oversized OPML is rejected before XML parsing'
);
v145d_expect_invalid(
    static fn() => opml_parse("<opml><body>\x01</body></opml>"),
    'Control characters are rejected'
);
v145d_expect_invalid(
    static fn() => opml_parse('<!DOCTYPE opml [<!ENTITY x SYSTEM "file:///etc/passwd">]><opml><body /></opml>'),
    'DOCTYPE/ENTITY input remains rejected'
);

if (!function_exists('simplexml_load_string')) {
    $skip++;
    echo "SKIP: SimpleXML runtime limit checks unavailable.\n";
} else {
    v145d_expect_invalid(
        static fn() => opml_parse('<opml><head></head></opml>'),
        'Missing OPML body is rejected'
    );
    v145d_expect_invalid(
        static fn() => opml_parse('<opml><body><outline text="broken"></body></opml>'),
        'Malformed XML is rejected'
    );

    $feed = '<outline type="rss" text="F" xmlUrl="https://example.test/f.xml" />';
    $xml500 = '<?xml version="1.0"?><opml><body>' . str_repeat($feed, OPML_MAX_FEEDS) . '</body></opml>';
    $parsed500 = opml_parse($xml500);
    v145d_limits_check(count($parsed500['feeds']) === OPML_MAX_FEEDS, 'Exactly 500 Feed outlines remain supported');

    $xml501 = '<?xml version="1.0"?><opml><body>' . str_repeat($feed, OPML_MAX_FEEDS + 1) . '</body></opml>';
    v145d_expect_invalid(static fn() => opml_parse($xml501), '501st Feed outline is rejected');

    $nested = '<outline text="L1">';
    for ($i = 2; $i <= OPML_MAX_DEPTH + 2; $i++) {
        $nested .= '<outline text="L' . $i . '">';
    }
    $nested .= $feed;
    for ($i = OPML_MAX_DEPTH + 2; $i >= 1; $i--) {
        $nested .= '</outline>';
    }
    $deepXml = '<?xml version="1.0"?><opml><body>' . $nested . '</body></opml>';
    v145d_expect_invalid(static fn() => opml_parse($deepXml), 'Excessive OPML nesting depth is rejected');

    $longSegments = array_fill(0, 40, str_repeat('あ', 30));
    $longCategory = '/' . implode('/', $longSegments);
    $longXml = '<?xml version="1.0"?><opml><body><outline type="rss" text="Long" xmlUrl="https://example.test/long.xml" category="' . $longCategory . '" /></body></opml>';
    $longParsed = opml_parse($longXml);
    $categoryPath = (string) ($longParsed['feeds'][0]['category_path'] ?? '');
    v145d_limits_check(app_text_length($categoryPath) <= FEED_METADATA_CATEGORY_MAX_LENGTH, 'Imported Category path remains bounded to metadata limit');
}

printf("RESULT: %s %d / FAIL %d / SKIP %d\n", $fail === 0 ? 'PASS' : 'FAIL', $pass, $fail, $skip);
exit($fail === 0 ? 0 : 1);
