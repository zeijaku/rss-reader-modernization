<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
require $root . '/app/common/common_conf.php';
require $root . '/app/common/common_db.php';

$tests = 0;
$failures = [];
function v18c_check(bool $condition, string $message): void
{
    global $tests, $failures;
    $tests++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) {
        $failures[] = $message;
    }
}

v18c_check(stock_search_like_pattern('PHP') === '%PHP%', 'plain search term is wrapped for contains search');
v18c_check(stock_search_like_pattern('100%_done!') === '%100!%!_done!!%', 'LIKE wildcard and escape characters are escaped');
v18c_check(stock_search_order_by('newest') === 'stock_id DESC', 'newest maps to descending stock id');
v18c_check(stock_search_order_by('oldest') === 'stock_id ASC', 'oldest maps to ascending stock id');
v18c_check(stock_search_order_by('title') === 'stock_title ASC, stock_id DESC', 'title maps to title order with id fallback');
v18c_check(stock_search_order_by('DROP TABLE content_stock') === 'stock_id DESC', 'unexpected sort value cannot become SQL and falls back to newest');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . "/{$tests} V1.8-C helper checks failed.\n");
    exit(1);
}

echo "All {$tests} V1.8-C helper checks passed.\n";
