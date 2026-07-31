<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('DB_TABLE_PREFIX=rss_test_');
putenv('DB_DRIVER=mysql');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/common/common_db.php';

final class PrefixFakeStatement extends PDOStatement
{
    public function __construct(private string $sql) {}
    public function execute(?array $params = null): bool { return true; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return []; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return false; }
    public function fetchColumn(int $column = 0): mixed { return false; }
    public function rowCount(): int { return 1; }
}

final class PrefixFakePDO extends PDO
{
    /** @var list<string> */
    public array $queries = [];
    private bool $transaction = false;
    private int $lastId = 10;

    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->queries[] = $query;
        return new PrefixFakeStatement($query);
    }
    public function beginTransaction(): bool { $this->transaction = true; return true; }
    public function commit(): bool { $this->transaction = false; return true; }
    public function rollBack(): bool { $this->transaction = false; return true; }
    public function inTransaction(): bool { return $this->transaction; }
    public function lastInsertId(?string $name = null): string|false { return (string) ++$this->lastId; }
}

$tests = 0;
$failed = 0;
function pcheck(bool $ok, string $message): void
{
    global $tests, $failed;
    $tests++;
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$ok) {
        $failed++;
    }
}

pcheck(DB_TABLE_PREFIX === 'rss_test_', 'environment DB_TABLE_PREFIX is loaded');
pcheck(db_table_name('user_info') === 'rss_test_user_info', 'user_info physical name uses configured prefix');
pcheck(db_table_name('user_conf') === 'rss_test_user_conf', 'user_conf physical name uses configured prefix');
pcheck(db_table_name('content') === 'rss_test_content', 'content physical name uses configured prefix');
pcheck(db_table_name('content_stock') === 'rss_test_content_stock', 'content_stock physical name uses configured prefix');
pcheck(db_table_identifier('content') === '`rss_test_content`', 'quoted identifier is generated from validated prefix');
try {
    db_validate_table_prefix('');
    pcheck(false, 'empty prefix is rejected');
} catch (RuntimeException) {
    pcheck(true, 'empty prefix is rejected');
}
pcheck(db_validate_table_prefix('A0_test_') === 'A0_test_', 'safe prefix characters are accepted');
pcheck(db_validate_table_prefix(str_repeat('a', 40)) === str_repeat('a', 40), '40-character prefix boundary is accepted');

foreach (['bad-prefix', 'bad prefix', 'bad`prefix', '日本語_', '2rss_', str_repeat('a', 41)] as $invalid) {
    try {
        db_validate_table_prefix($invalid);
        pcheck(false, 'invalid prefix is rejected: ' . $invalid);
    } catch (RuntimeException) {
        pcheck(true, 'invalid prefix is rejected');
    }
}

try {
    db_table_name('not_a_table');
    pcheck(false, 'unknown logical table is rejected');
} catch (InvalidArgumentException) {
    pcheck(true, 'unknown logical table is rejected');
}

$pdo = new PrefixFakePDO();
set_db_connection_for_testing($pdo);
entry_user('id', 'hash');
entry_content(11, 'https://example.test/feed', 'success', 0);
info_dbsave(11, 'https://example.test/a', 'A');
find_active_users_by_identity('id');
user_identity_exists('id');
update_user_password_hash(11, 'hash2');
search_content(11, 0);
search_stock(11);
search_conf(11);
find_owned_active_content(11, 1);
update_content_owned(11, 1, 'https://example.test/b', 'info');
delete_content_owned(11, 1);
update_setting(11, 'bootstrap', 'dark', null, null, null, null, null, null, null, null, null, null, null, null);
update_tab(11, 'A', 'B', 'C', 'D');

pcheck(count($pdo->queries) >= 15, 'database API produced representative SQL statements');
pcheck(array_reduce($pdo->queries, static fn(bool $ok, string $sql): bool => $ok && !str_contains($sql, 'ig_'), true), 'custom-prefix runtime SQL contains no Legacy ig_ physical table name');
pcheck(array_reduce($pdo->queries, static fn(bool $ok, string $sql): bool => $ok && str_contains($sql, 'rss_test_'), true), 'every runtime SQL statement uses configured prefix');
$joined = implode("\n", $pdo->queries);
foreach (['rss_test_user_info', 'rss_test_user_conf', 'rss_test_content', 'rss_test_content_stock'] as $table) {
    pcheck(str_contains($joined, $table), "runtime SQL reaches {$table}");
}

if ($failed > 0) {
    fwrite(STDERR, "{$failed}/{$tests} SB-13 prefix tests failed.\n");
    exit(1);
}

echo "All {$tests} SB-13 prefix tests passed.\n";
