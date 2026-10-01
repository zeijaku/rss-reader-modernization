<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
putenv('APP_ENV=testing'); putenv('APP_DEBUG=false'); putenv('APP_LOG_ENABLED=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql'); putenv('DB_TABLE_PREFIX=ig_'); putenv('DB_HOST=fixture'); putenv('DB_NAME=fixture'); putenv('DB_USER=fixture'); putenv('DB_PASSWORD=fixture');
require $root . '/app/bootstrap.php';
set_exception_handler(static function (Throwable $error): void { fwrite(STDERR, $error->getMessage() . PHP_EOL . $error->getTraceAsString()); exit(1); });
$fixtureRows = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
final class HeaderFixtureStatement extends PDOStatement {
    public function __construct(private string $sql) {}
    public function execute(?array $params = null): bool { return true; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return str_contains($this->sql, 'SELECT w.widget_id') ? $GLOBALS['fixtureRows'] : []; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return false; }
}
final class HeaderFixturePDO extends PDO {
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new HeaderFixtureStatement($query); }
}
set_db_connection_for_testing(new HeaderFixturePDO());
$currentUserId = 7; $tabParam = 0; $addTargetLocation = 0; $addTargetName = 'Fixture Dashboard'; $ui = ['conf_style' => 'bootstrap'];
require $root . '/app/view/dashboard_widgets.php';
// Load the existing index-only Search form helper without executing the authenticated entry point.
$entrySource = file_get_contents($root . '/public/index.php');
if (!preg_match('/function search_feed_form_fields\(.*?\n}\n/s', $entrySource, $helper)) { throw new RuntimeException('Search form helper missing'); }
eval($helper[0]);
require $root . '/app/view/dashboard_modals.php';

echo '<!-- header-fixture-complete -->';
