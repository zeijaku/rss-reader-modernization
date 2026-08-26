<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/common/common_conf.php';

$expected = (string) DB_TABLE_PREFIX . 'feed_metadata';
$actual = db_table_name('feed_metadata');
if ($actual !== $expected) {
    fwrite(STDERR, "feed_metadata table allowlist failed\n");
    exit(1);
}

$quoted = db_table_identifier('feed_metadata');
if ($quoted !== '`' . $expected . '`') {
    fwrite(STDERR, "feed_metadata table identifier failed\n");
    exit(1);
}

echo "V1.22-A feed_metadata table allowlist: PASS\n";
