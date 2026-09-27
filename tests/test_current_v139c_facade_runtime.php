<?php

declare(strict_types=1);

if (!defined('APP_ENV')) {
    define('APP_ENV', 'testing');
}
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once APP_ROOT . '/app/common/common_conf.php';
require_once APP_ROOT . '/app/validation.php';
require_once APP_ROOT . '/app/api.php';

$checks = 0;
$failures = [];

function v139c_check(bool $condition, string $label): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $label . PHP_EOL;
    if (!$condition) {
        $failures[] = $label;
    }
}

foreach ([
    'api_content_create',
    'api_content_update',
    'api_content_delete',
    'api_stock_create',
    'api_stock_delete',
    'api_feed_fetch',
    'api_feed_reader_payload',
    'api_feed_reader',
    'api_feed_reader_full_text',
    'api_feed_new_clear',
] as $functionName) {
    v139c_check(function_exists($functionName), 'Content API facade exports ' . $functionName);
}

foreach ([
    'reader_full_text_fetch_url',
    'reader_full_text_content_type_allowed',
    'reader_full_text_normalize_html_utf8',
    'reader_full_text_extract',
] as $functionName) {
    v139c_check(function_exists($functionName), 'Reader Full Text facade exports ' . $functionName);
}

v139c_check(class_exists('ReaderFullTextCache'), 'Reader Full Text facade exports ReaderFullTextCache');
v139c_check(class_exists('ReaderFullTextService'), 'Reader Full Text facade exports ReaderFullTextService');

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d/%d V1.39-C facade runtime checks failed.\n", count($failures), $checks));
    exit(1);
}

echo "V1.39-C facade runtime checks: {$checks} passed." . PHP_EOL;
