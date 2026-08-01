<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');
putenv('DB_HOST=test');
putenv('DB_NAME=test');
putenv('DB_USER=test');
putenv('DB_PASSWORD=test');

require $root . '/app/common/common_conf.php';
require $root . '/app/common/common_db.php';
require $root . '/app/validation.php';
require $root . '/app/http_fetch.php';
require $root . '/app/feed/feed_fetcher.php';

$GLOBALS['test_fetched_urls'] = [];
$GLOBALS['app_http_fetch_test_resolver'] = static fn(string $host): array => ['93.184.216.34'];
$GLOBALS['app_http_fetch_test_transport'] = static function (array $request): array {
    $GLOBALS['test_fetched_urls'][] = (string) $request['url'];
    return [
        'ok' => true,
        'status' => 200,
        'body' => '<rss version="2.0"><channel><title>Fixture</title><link>https://feed.example/</link><item><title>One</title><link>https://feed.example/1</link></item></channel></rss>',
        'location' => null,
        'error_code' => '',
        'error_message' => '',
    ];
};
function rss_check_string($element) { return 'rss'; }
$GLOBALS['test_feed_parser_fail'] = false;
class FeedParser {
    public ?string $last_error = null;
    public function parse_start($contents): array {
        if (($GLOBALS['test_feed_parser_fail'] ?? false) === true) {
            $this->last_error = 'fixture parse failure';
            return [];
        }
        return [
            'type' => 'rss2',
            'channel' => ['title' => 'Fixture', 'link' => 'https://feed.example/', 'description' => null],
            'item' => [['title' => 'One', 'link' => 'https://feed.example/1', 'description' => null, 'content' => null, 'date' => null]],
        ];
    }
}
require $root . '/app/api.php';

final class ApiFakeStatement extends PDOStatement
{
    private array $rows = [];
    private mixed $column = false;
    private int $affected = 0;

    public function __construct(private ApiFakePDO $pdo, private string $sql) {}

    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $sql = $this->sql;
        $this->rows = [];
        $this->column = false;
        $this->affected = 0;

        if (str_starts_with($sql, 'INSERT INTO ig_content ')) {
            $id = ++$this->pdo->contentSeq;
            $this->pdo->contents[$id] = [
                'content_id' => $id,
                'content_date' => (string) ($params[':date'] ?? ''),
                'content_owner' => (int) ($params[':owner'] ?? 0),
                'content_location' => (int) ($params[':location'] ?? 0),
                'content_style' => (string) ($params[':style'] ?? ''),
                'content_value' => (string) ($params[':value'] ?? ''),
                'content_flag' => 0,
            ];
            $this->pdo->lastId = $id;
            $this->affected = 1;
            return true;
        }

        if (str_starts_with($sql, 'INSERT INTO ig_content_stock')) {
            $id = ++$this->pdo->stockSeq;
            $this->pdo->stocks[$id] = [
                'stock_id' => $id,
                'stock_owner' => (int) ($params[':owner'] ?? 0),
                'stock_data' => (string) ($params[':data'] ?? ''),
                'stock_title' => (string) ($params[':title'] ?? ''),
                'stock_flag' => 0,
            ];
            $this->pdo->lastId = $id;
            $this->affected = 1;
            return true;
        }

        if (str_starts_with($sql, 'SELECT * FROM ig_content WHERE content_id =')) {
            $id = (int) ($params[':content_id'] ?? 0);
            $owner = (int) ($params[':owner'] ?? 0);
            $row = $this->pdo->contents[$id] ?? null;
            if (is_array($row) && (int) $row['content_owner'] === $owner && (int) $row['content_flag'] === 0) {
                $this->rows = [$row];
            }
            return true;
        }

        if (str_starts_with($sql, 'UPDATE ig_content SET content_flag = 0')) {
            $id = (int) ($params[':content_id'] ?? 0);
            $owner = (int) ($params[':owner'] ?? 0);
            if (isset($this->pdo->contents[$id]) && (int) $this->pdo->contents[$id]['content_owner'] === $owner && (int) $this->pdo->contents[$id]['content_flag'] === 0) {
                $this->pdo->contents[$id]['content_value'] = (string) ($params[':value'] ?? '');
                $this->pdo->contents[$id]['content_style'] = (string) ($params[':style'] ?? '');
                $this->affected = 1;
            }
            return true;
        }

        if (str_starts_with($sql, 'UPDATE ig_content SET content_flag = 1')) {
            $id = (int) ($params[':content_id'] ?? 0);
            $owner = (int) ($params[':owner'] ?? 0);
            if (isset($this->pdo->contents[$id]) && (int) $this->pdo->contents[$id]['content_owner'] === $owner && (int) $this->pdo->contents[$id]['content_flag'] === 0) {
                $this->pdo->contents[$id]['content_flag'] = 1;
                $this->affected = 1;
            }
            return true;
        }

        if (str_starts_with($sql, 'SELECT * FROM ig_user_conf WHERE user_id =')) {
            $uid = (int) ($params[':user_id'] ?? 0);
            if (isset($this->pdo->confs[$uid])) {
                $this->rows = [$this->pdo->confs[$uid]];
            }
            return true;
        }

        if (str_starts_with($sql, 'UPDATE ig_user_conf SET conf_style =')) {
            $uid = (int) ($params[':user_id'] ?? 0);
            if (isset($this->pdo->confs[$uid])) {
                $this->pdo->confs[$uid]['conf_style'] = (string) ($params[':style'] ?? '');
                $this->pdo->confs[$uid]['conf_style_nav'] = (string) ($params[':nav_style'] ?? '');
                $this->affected = 1;
            }
            return true;
        }

        if (str_starts_with($sql, 'UPDATE ig_user_conf SET conf_style_tabname1 =')) {
            $uid = (int) ($params[':user_id'] ?? 0);
            if (isset($this->pdo->confs[$uid])) {
                $this->pdo->confs[$uid]['conf_style_tabname1'] = (string) ($params[':tab1'] ?? '');
                $this->pdo->confs[$uid]['conf_style_tabname2'] = (string) ($params[':tab2'] ?? '');
                $this->pdo->confs[$uid]['conf_style_tabname3'] = (string) ($params[':tab3'] ?? '');
                $this->pdo->confs[$uid]['conf_style_tabname4'] = (string) ($params[':tab4'] ?? '');
                $this->affected = 1;
            }
            return true;
        }

        throw new RuntimeException('Unexpected SQL in API fake: ' . $sql);
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->rows[0] ?? false;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column = 0): mixed { return $this->column; }
    public function rowCount(): int { return $this->affected; }
}

final class ApiFakePDO extends PDO
{
    public array $contents = [];
    public array $stocks = [];
    public array $confs = [];
    public int $contentSeq = 0;
    public int $stockSeq = 0;
    public int $lastId = 0;

    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new ApiFakeStatement($this, $query); }
    public function lastInsertId(?string $name = null): string|false { return (string) $this->lastId; }
}

$pdo = new ApiFakePDO();
$pdo->confs[10] = ['user_id' => 10, 'conf_style' => 'bootstrap', 'conf_style_nav' => 'dark'];
$pdo->confs[20] = ['user_id' => 20, 'conf_style' => 'bootstrap', 'conf_style_nav' => 'dark'];
set_db_connection_for_testing($pdo);

$tests = 0;
$failures = [];
function api_check(bool $condition, string $message): void
{
    global $tests, $failures;
    $tests++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) $failures[] = $message;
}

$r = api_dispatch('unknown.action', 10, []);
api_check($r['status'] === 400 && ($r['body']['ok'] ?? true) === false, 'unknown action is rejected with structured error');

$beforeContentSeq = $pdo->contentSeq;
$r = api_dispatch('content.create', 10, [
    'content_value' => 'javascript:alert(1)',
    'content_style' => 'success',
    'content_location' => '0',
]);
api_check($r['status'] === 422 && $pdo->contentSeq === $beforeContentSeq, 'invalid Feed scheme is rejected before DB insert');

$r = api_dispatch('content.create', 10, [
    'content_value' => 'https://feed.example/a',
    'content_style' => 'success onclick=x',
    'content_location' => '0',
]);
api_check($r['status'] === 422 && $pdo->contentSeq === $beforeContentSeq, 'invalid content style is rejected before DB insert');

$r = api_dispatch('content.create', 10, [
    'content_value' => 'https://feed.example/a',
    'content_style' => 'success',
    'content_location' => '4',
]);
api_check($r['status'] === 422 && $pdo->contentSeq === $beforeContentSeq, 'invalid content location is rejected before DB insert');

$r = api_dispatch('content.create', 10, [
    'content_value' => 'https://feed.example/a',
    'content_style' => 'success',
    'content_location' => '0',
    'content_owner' => '20',
    'user_id' => '20',
]);
$contentA = (int) ($r['body']['data']['content_id'] ?? 0);
api_check($r['status'] === 201 && $contentA > 0, 'content.create succeeds');
api_check((int) $pdo->contents[$contentA]['content_owner'] === 10, 'content.create owner comes from authenticated user, not request owner fields');

$r = api_dispatch('content.update', 20, [
    'content_id' => (string) $contentA,
    'content_value' => 'https://attacker.example/changed',
    'content_style' => 'danger',
]);
api_check($r['status'] === 404, 'other user cannot update content (IDOR blocked)');
api_check($pdo->contents[$contentA]['content_value'] === 'https://feed.example/a', 'unauthorized update leaves content unchanged');

$r = api_dispatch('content.delete', 20, ['content_id' => (string) $contentA]);
api_check($r['status'] === 404 && (int) $pdo->contents[$contentA]['content_flag'] === 0, 'other user cannot delete content');

$r = api_dispatch('content.update', 10, [
    'content_id' => (string) $contentA,
    'content_value' => 'https://feed.example/updated',
    'content_style' => 'primary',
]);
api_check($r['status'] === 200 && $pdo->contents[$contentA]['content_value'] === 'https://feed.example/updated', 'owner can update own content');

$GLOBALS['test_fetched_urls'] = [];
$r = api_dispatch('feed.fetch', 20, ['content_id' => (string) $contentA, 'steal_content' => 'http://127.0.0.1/']);
api_check($r['status'] === 404, 'other user cannot fetch another user feed');
api_check($GLOBALS['test_fetched_urls'] === [], 'unauthorized feed.fetch performs no outbound fetch');

$GLOBALS['test_fetched_urls'] = [];
$r = api_dispatch('feed.fetch', 10, ['content_id' => (string) $contentA, 'steal_content' => 'http://127.0.0.1/']);
api_check($r['status'] === 200 && ($r['body']['ok'] ?? false) === true, 'owner can fetch own registered feed');
api_check($GLOBALS['test_fetched_urls'] === ['https://feed.example/updated'], 'feed.fetch uses DB-owned URL and ignores client-supplied raw URL');

$GLOBALS['test_feed_parser_fail'] = true;
$r = api_dispatch('feed.fetch', 10, ['content_id' => (string) $contentA]);
api_check($r['status'] === 502 && ($r['body']['error']['code'] ?? '') === 'invalid_feed', 'non-feed upstream response is a structured failure, not Legacy text success');
$GLOBALS['test_feed_parser_fail'] = false;

$r = api_dispatch('settings.update', 10, [
    'conf_style' => '../secret',
    'conf_style_nav' => 'dark',
    'conf_style_navlink1' => 'javascript:alert(1)', 'conf_style_navlink_view1' => 'One', 'conf_style_navlink_icon1' => 'search',
    'conf_style_navlink2' => '', 'conf_style_navlink_view2' => '', 'conf_style_navlink_icon2' => 'images',
    'conf_style_navlink3' => '', 'conf_style_navlink_view3' => '', 'conf_style_navlink_icon3' => 'edit',
    'conf_style_navlink4' => '', 'conf_style_navlink_view4' => '', 'conf_style_navlink_icon4' => 'sync-alt',
]);
api_check($r['status'] === 422 && $pdo->confs[10]['conf_style'] === 'bootstrap', 'unsafe settings are rejected before configuration mutation');

$r = api_dispatch('settings.update', 10, [
    'user_id' => '20',
    'conf_style' => 'bootstrap-minty',
    'conf_style_nav' => 'primary',
    'conf_style_navlink1' => 'https://example.test/1', 'conf_style_navlink_view1' => 'One', 'conf_style_navlink_icon1' => 'search',
    'conf_style_navlink2' => 'https://example.test/2', 'conf_style_navlink_view2' => 'Two', 'conf_style_navlink_icon2' => 'images',
    'conf_style_navlink3' => '', 'conf_style_navlink_view3' => '', 'conf_style_navlink_icon3' => 'edit',
    'conf_style_navlink4' => '', 'conf_style_navlink_view4' => '', 'conf_style_navlink_icon4' => 'sync-alt',
]);
api_check($r['status'] === 200 && $pdo->confs[10]['conf_style'] === 'bootstrap-minty', 'settings.update changes authenticated user configuration');
api_check($pdo->confs[20]['conf_style'] === 'bootstrap', 'settings.update ignores request user_id and leaves other user unchanged');

$r = api_dispatch('tabs.update', 10, [
    'conf_style_tabname1' => str_repeat('A', 17),
    'conf_style_tabname2' => 'Two',
    'conf_style_tabname3' => 'Three',
    'conf_style_tabname4' => 'Four',
]);
api_check($r['status'] === 422 && !isset($pdo->confs[10]['conf_style_tabname1']), 'overlong tab name is rejected before DB update');

$r = api_dispatch('tabs.update', 10, [
    'content_owner' => '20',
    'conf_style_tabname1' => 'Mine',
    'conf_style_tabname2' => 'Two',
    'conf_style_tabname3' => 'Three',
    'conf_style_tabname4' => 'Four',
]);
api_check($r['status'] === 200 && $pdo->confs[10]['conf_style_tabname1'] === 'Mine', 'tabs.update changes authenticated user configuration');
api_check(!isset($pdo->confs[20]['conf_style_tabname1']), 'tabs.update cannot target request-supplied owner');

$beforeFetchCount = count($GLOBALS['test_fetched_urls']);
$r = api_dispatch('stock.create', 10, [
    'stock_data' => 'file:///etc/passwd',
    'stock_title' => 'bad',
]);
api_check($r['status'] === 422 && $pdo->stockSeq === 0, 'unsafe Stock URL is rejected before DB insert');
$r = api_dispatch('stock.create', 10, [
    'stock_data' => 'https://stock.example/item',
    'stock_title' => str_repeat('T', 129),
]);
api_check($r['status'] === 422 && $pdo->stockSeq === 0, 'overlong Stock title is rejected before DB insert');
api_check(count($GLOBALS['test_fetched_urls']) === $beforeFetchCount, 'Stock validation performs no outbound article request');

$r = api_dispatch('stock.create', 10, [
    'stock_data' => 'https://stock.example/item',
    'stock_title' => 'Stock Title',
    'save_owner' => '20',
]);
$stockId = (int) ($r['body']['data']['stock_id'] ?? 0);
api_check($r['status'] === 201 && $stockId > 0, 'stock.create succeeds');
api_check((int) $pdo->stocks[$stockId]['stock_owner'] === 10, 'stock.create owner comes from authenticated session');
api_check($pdo->stocks[$stockId]['stock_title'] === 'Stock Title', 'stock title is stored from validated feed data without article refetch');
api_check(count($GLOBALS['test_fetched_urls']) === $beforeFetchCount, 'successful Stock create still performs no outbound article request');

$r = api_dispatch('content.delete', 10, ['content_id' => (string) $contentA]);
api_check($r['status'] === 200 && (int) $pdo->contents[$contentA]['content_flag'] === 1, 'owner can logically delete own content');
$r = api_dispatch('feed.fetch', 10, ['content_id' => (string) $contentA]);
api_check($r['status'] === 404, 'logically deleted content cannot be fetched');

$r = api_dispatch('content.update', 10, ['content_id' => 'not-an-int', 'content_value' => 'x']);
api_check($r['status'] === 422 && ($r['body']['error']['code'] ?? '') === 'validation_error', 'malformed resource id is rejected before DB mutation');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . "/{$tests} SB-05..07 API checks failed.\n");
    exit(1);
}

echo "All {$tests} SB-05..07 API/authorization checks passed.\n";
