<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/validation.php';
require_once dirname(__DIR__) . '/app/feed_metadata.php';
require_once dirname(__DIR__) . '/app/opml.php';

$pass = 0;
$fail = 0;

function v145d_check(bool $condition, string $message): void
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "PASS: {$message}\n";
    } else {
        $fail++;
        echo "FAIL: {$message}\n";
    }
}

v145d_check(
    opml_category_attribute_segments('/Harvard/Berkman,/Politics') === ['Harvard', 'Berkman'],
    'OPML multi-category attribute maps the first Category only for 1 Feed = 1 Category'
);
v145d_check(
    opml_category_attribute_segments('News/Japan') === ['News', 'Japan'],
    'Slash-delimited category attribute maps to one hierarchy'
);
v145d_check(
    opml_category_attribute_value('Technology / Security') === '/Technology/Security',
    'Internal hierarchy exports as OPML slash-delimited Category'
);
v145d_check(
    opml_category_attribute_value('') === '',
    'Uncategorized Feed emits no OPML Category value'
);

$export = opml_build_export([
    [
        'feed_title' => 'Security & Cloud',
        'feed_url' => 'https://example.test/security.xml',
        'site_url' => 'https://example.test/',
        'category_path' => 'Technology / Security',
    ],
    [
        'feed_title' => 'Uncategorized',
        'feed_url' => 'https://example.test/uncategorized.xml',
        'site_url' => '',
        'category_path' => '',
    ],
]);

v145d_check(
    str_contains($export, '<outline text="Technology"') && str_contains($export, '<outline text="Security"'),
    'Export preserves nested OPML outline hierarchy'
);
v145d_check(
    str_contains($export, 'category="/Technology/Security"'),
    'Categorized Feed export includes interoperable OPML category attribute'
);
v145d_check(
    substr_count($export, ' category=') === 1,
    'Uncategorized Feed does not receive a category attribute'
);

if (function_exists('simplexml_load_string')) {
    $roundTrip = opml_parse($export);
    v145d_check(count($roundTrip['feeds']) === 2, 'Export can be parsed back as two Feeds');
    v145d_check(
        ($roundTrip['feeds'][0]['category_path'] ?? null) === 'Technology / Security',
        'Export -> Import round-trip preserves hierarchical Category exactly'
    );
    v145d_check(
        ($roundTrip['feeds'][1]['category_path'] ?? null) === '',
        'Export -> Import round-trip preserves Uncategorized'
    );

    $flat = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<opml version="2.0">
  <head><title>Flat categories</title></head>
  <body>
    <outline type="rss" text="Multi" xmlUrl="https://example.test/multi.xml" category="/Harvard/Berkman,/Politics" />
    <outline text="Folder">
      <outline type="rss" text="Nested" xmlUrl="https://example.test/nested.xml" category="/Different/Tag" />
    </outline>
  </body>
</opml>
XML;
    $parsed = opml_parse($flat);
    v145d_check(
        ($parsed['feeds'][0]['category_path'] ?? null) === 'Harvard / Berkman',
        'Flat OPML category attribute uses first category path rather than false concatenation'
    );
    v145d_check(
        ($parsed['feeds'][1]['category_path'] ?? null) === 'Folder',
        'Nested subscription hierarchy takes priority over independent category attribute'
    );
} else {
    echo "SKIP: SimpleXML runtime round-trip checks unavailable.\n";
}

printf("RESULT: %s %d / FAIL %d / SKIP %d\n", $fail === 0 ? 'PASS' : 'FAIL', $pass, $fail, function_exists('simplexml_load_string') ? 0 : 1);
exit($fail === 0 ? 0 : 1);
