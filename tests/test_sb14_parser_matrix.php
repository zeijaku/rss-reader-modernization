<?php

declare(strict_types=1);

error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$root = dirname(__DIR__);
require_once $root . '/app/validation.php';
require_once $root . '/app/common/common_func.php';

$tests = 0;
$failures = [];
function sb14_parser_check(bool $condition, string $message): void
{
    global $tests, $failures;
    $tests++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) {
        $failures[] = $message;
    }
}

sb14_parser_check(rss_normalize_date('Wed, 29 Jul 2026 10:00:00 +0900') === '2026-07-29 10:00:00', 'RFC822 RSS date normalizes deterministically');
sb14_parser_check(rss_normalize_date('invalid-date') === null, 'invalid Feed date becomes null without warning');
sb14_parser_check(rss_select_link_candidate([
    ['href' => 'https://example.test/self', 'rel' => 'self', 'type' => 'application/atom+xml'],
    ['href' => 'https://example.test/article', 'rel' => 'alternate', 'type' => 'text/html'],
]) === 'https://example.test/article', 'Atom alternate browser link wins over self metadata');

$extensionsAvailable = function_exists('simplexml_load_string')
    && function_exists('mb_detect_encoding')
    && function_exists('mb_convert_encoding');

if ($extensionsAvailable) {
    $cases = [
        'rss2_zero.xml' => ['type' => 'rss2', 'items' => 0, 'channel' => 'https://example.test/zero'],
        'rss2_four.xml' => ['type' => 'rss2', 'items' => 4, 'channel' => 'https://example.test/four'],
        'rss2_six.xml' => ['type' => 'rss2', 'items' => 6, 'channel' => 'https://example.test/six'],
        'atom_no_declaration.xml' => ['type' => 'atom', 'items' => 1, 'channel' => 'https://example.test/'],
        'rss1_basic.xml' => ['type' => 'rss1', 'items' => 1, 'channel' => 'https://example.test/'],
    ];

    foreach ($cases as $fixture => $expected) {
        $body = file_get_contents($root . '/tests/fixtures/' . $fixture);
        $parser = new rss_parse();
        $feed = $parser->parse_start($body);
        sb14_parser_check(($feed['type'] ?? null) === $expected['type'], "{$fixture} type parsed as {$expected['type']}");
        sb14_parser_check(count($feed['item'] ?? []) === $expected['items'], "{$fixture} item count preserved");
        sb14_parser_check(($feed['channel']['link'] ?? null) === $expected['channel'], "{$fixture} channel link parsed");
    }

    $parser = new rss_parse();
    $four = $parser->parse_start(file_get_contents($root . '/tests/fixtures/rss2_four.xml'));
    sb14_parser_check(($four['item'][0]['date'] ?? null) === '2026-07-29 10:00:00', 'valid RSS item date is normalized');
    sb14_parser_check(($four['item'][1]['date'] ?? 'not-null') === null, 'invalid RSS item date becomes null');
    sb14_parser_check(($four['item'][2]['date'] ?? 'not-null') === null, 'missing RSS item date remains null');

    $atom = $parser->parse_start(file_get_contents($root . '/tests/fixtures/atom_no_declaration.xml'));
    sb14_parser_check(($atom['item'][0]['link'] ?? null) === 'https://example.test/article', 'Atom alternate item link parses without XML declaration');

    $rss1 = $parser->parse_start(file_get_contents($root . '/tests/fixtures/rss1_basic.xml'));
    sb14_parser_check(($rss1['item'][0]['date'] ?? null) === '2026-07-30 01:02:03', 'RSS 1.0 Dublin Core date parses');

    $malformed = $parser->parse_start(file_get_contents($root . '/tests/fixtures/malformed.xml'));
    sb14_parser_check($malformed === [] && is_string($parser->last_error) && $parser->last_error !== '', 'malformed XML fails with controlled parser error');
} else {
    echo "SKIP: SB-14 live parser matrix requires SimpleXML and mbstring.\n";
}

restore_error_handler();

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d/%d SB-14 parser matrix checks failed.\n", count($failures), $tests));
    exit(1);
}

echo "All {$tests} executable SB-14 parser matrix checks passed.\n";
