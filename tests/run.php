<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('DB_DRIVER=sqlite');
putenv('DB_SQLITE_PATH=:memory:');
putenv('APP_LOG_ENABLED=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
require $root . '/app/common/common_conf.php';
require $root . '/app/common/common_db.php';

$tests = 0;
$failures = [];
function check(bool $condition, string $message): void
{
    global $tests, $failures;
    $tests++;
    if (!$condition) {
        $failures[] = $message;
        echo "FAIL: {$message}\n";
    } else {
        echo "PASS: {$message}\n";
    }
}

$drivers = PDO::getAvailableDrivers();
if (in_array('sqlite', $drivers, true)) {
    $pdo = conn_db('sqlite');
    $pdo->exec(<<<'SQL'
CREATE TABLE ig_user_info (
 user_id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_date TEXT NOT NULL,
 user_email TEXT,
 user_password TEXT,
 user_flag INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE ig_user_conf (
 conf_id INTEGER PRIMARY KEY AUTOINCREMENT,
 conf_date TEXT NOT NULL,
 user_id INTEGER NOT NULL,
 conf_style TEXT,
 conf_style_nav TEXT,
 conf_style_navlink1 TEXT, conf_style_navlink_view1 TEXT, conf_style_navlink_icon1 TEXT,
 conf_style_navlink2 TEXT, conf_style_navlink_view2 TEXT, conf_style_navlink_icon2 TEXT,
 conf_style_navlink3 TEXT, conf_style_navlink_view3 TEXT, conf_style_navlink_icon3 TEXT,
 conf_style_navlink4 TEXT, conf_style_navlink_view4 TEXT, conf_style_navlink_icon4 TEXT,
 conf_style_tabname1 TEXT, conf_style_tabname2 TEXT, conf_style_tabname3 TEXT, conf_style_tabname4 TEXT,
 FOREIGN KEY(user_id) REFERENCES ig_user_info(user_id)
);
CREATE TABLE ig_content (
 content_id INTEGER PRIMARY KEY AUTOINCREMENT,
 content_date TEXT NOT NULL,
 content_owner INTEGER,
 content_location INTEGER NOT NULL DEFAULT 0,
 content_style TEXT,
 content_value TEXT,
 content_flag INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE ig_content_stock (
 stock_id INTEGER PRIMARY KEY AUTOINCREMENT,
 stock_date TEXT NOT NULL,
 stock_owner INTEGER,
 stock_data TEXT,
 stock_title TEXT,
 stock_flag INTEGER NOT NULL DEFAULT 0,
 stock_processed INTEGER NOT NULL DEFAULT 0,
 stock_important INTEGER NOT NULL DEFAULT 0,
 stock_archived INTEGER NOT NULL DEFAULT 0
);
SQL);

    check($pdo->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_EXCEPTION, 'PDO exception mode is enabled');
    check($pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE) === PDO::FETCH_ASSOC, 'PDO default fetch mode is associative');
    $userId = entry_user('identity-1', 'password-1');
    check($userId === 1, 'entry_user returns inserted user id');
    check((int) $pdo->query('SELECT COUNT(*) FROM ig_user_info')->fetchColumn() === 1, 'user row is inserted');
    check((int) $pdo->query('SELECT COUNT(*) FROM ig_user_conf')->fetchColumn() === 1, 'configuration row is inserted atomically');

    $pdo->exec("CREATE TRIGGER fail_conf_insert BEFORE INSERT ON ig_user_conf BEGIN SELECT RAISE(ABORT, 'forced'); END;");
    try {
        entry_user('identity-rollback', 'password-rollback');
        check(false, 'transaction failure throws');
    } catch (Throwable) {
        check(true, 'transaction failure throws');
    }
    check((int) $pdo->query("SELECT COUNT(*) FROM ig_user_info WHERE user_email='identity-rollback'")->fetchColumn() === 0, 'failed configuration insert rolls back user row');
    $pdo->exec('DROP TRIGGER fail_conf_insert');

    $payload = "https://example.test/feed?x='; DROP TABLE ig_content; --";
    $content1 = entry_content($userId, $payload, 'success', 0);
    $content2 = entry_content($userId, 'https://example.test/second', 'info', 0);
    check($content1 > 0 && $content2 > $content1, 'content rows are inserted');
    check((string) $pdo->query("SELECT content_value FROM ig_content WHERE content_id={$content1}")->fetchColumn() === $payload, 'SQL-like payload is stored as data');
    check((int) $pdo->query('SELECT COUNT(*) FROM ig_content')->fetchColumn() === 2, 'content table remains intact after injection payload');
    $contents = search_content($userId, 0);
    check(array_column($contents, 'content_id') === [$content1, $content2], 'content ordering is content_id ascending');
    $stock1 = info_dbsave($userId, 'https://example.test/a', 'A');
    $stock2 = info_dbsave($userId, 'https://example.test/b', 'B');
    $stocks = search_stock($userId);
    check(array_column($stocks, 'stock_id') === [$stock2, $stock1], 'stock ordering is stock_id descending');
    $stored = (string) $pdo->query("SELECT content_date FROM ig_content WHERE content_id={$content1}")->fetchColumn();
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $stored, new DateTimeZone('Asia/Tokyo'));
    check($parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d H:i:s') === $stored, 'timestamp uses valid Y-m-d H:i:s format');
} else {
    echo "SKIP: PDO SQLite integration tests (driver unavailable in this execution environment).\n";
}

check((bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', app_now()), 'app_now returns Y-m-d H:i:s');
// Sensitive/public boundary static tests
$publicFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/public', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile()) {
        $publicFiles[] = str_replace($root . '/public/', '', $file->getPathname());
    }
}
$forbidden = array_filter($publicFiles, static fn(string $name): bool => preg_match('/(?:^|\/)(?:dat|session_file)(?:\/|$)|\.(?:sql|log|env|sqlite|db|bak|zip)$/i', $name) === 1);
check($forbidden === [], 'public directory contains no known sensitive/runtime file types');
check(!is_dir($root . '/public/common'), 'private common PHP is outside DocumentRoot');
check(!file_exists($root . '/public/dat'), 'legacy web-root log directory is absent');
check(!file_exists($root . '/public/session_file'), 'legacy web-root session directory is absent');

$config = file_get_contents($root . '/app/common/common_conf.php');
check(strpos($config, '**********') === false, 'configuration contains no copied Legacy placeholder secret');
check(strpos($config, 'getenv(') !== false, 'configuration loads environment variables');
$dbCode = file_get_contents($root . '/app/common/common_db.php');
check(strpos($dbCode, 'var_dump') === false, 'database layer does not disclose exceptions using var_dump');
check(strpos($dbCode, 'charset=utf8mb4') !== false, 'MySQL DSN specifies utf8mb4');
check(strpos($dbCode, 'PDO::ATTR_EMULATE_PREPARES => false') !== false, 'native prepared statements are requested');

check(file_exists($root . '/config/local.php.example'), 'shared-hosting local config example exists');
check(strpos(file_get_contents($root . '/.gitignore'), '/config/local.php') !== false, 'real local config is ignored by Git');
check(strpos($config, 'app_local_config') !== false, 'configuration supports private local.php fallback');
check(strpos(file_get_contents($root . '/app/bootstrap.php'), 'APP_ERROR_LOG_PATH') !== false, 'private application error log is configured');
check(strpos($dbCode, 'function find_owned_active_content(int $userId, int $contentId)') !== false, 'owned content lookup uses typed authenticated user/resource ids');
check(strpos($dbCode, 'content_owner = :owner') !== false, 'content mutation/read queries contain owner predicates');

if ($failures !== []) {
    fwrite(STDERR, sprintf("\n%d/%d tests failed.\n", count($failures), $tests));
    exit(1);
}

echo "\nAll {$tests} tests passed.\n";
