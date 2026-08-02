<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');
putenv('DB_TABLE_PREFIX=ig_');
putenv('APP_FEED_ITEM_STATE_RETENTION_DAYS=90');

require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/common/common_db.php';
require_once $root . '/app/feed/feed_item_state.php';
require_once $root . '/app/validation.php';
require_once $root . '/app/api.php';

$checks = 0;
$failures = [];
function v11c_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) {
        $failures[] = $message;
    }
}

function v11c_identity(string $seed): string
{
    return 'm1i:v1:' . hash('sha256', $seed);
}

$now = '2026-08-02 16:20:00';
$idA = v11c_identity('a');
$idB = v11c_identity('b');
$idC = v11c_identity('c');

v11c_check(feed_item_state_valid_identity($idA) === $idA, 'canonical Item Identity is accepted');
v11c_check(feed_item_state_valid_identity(strtoupper($idA)) === null, 'uppercase/non-canonical identity is rejected');
v11c_check(feed_item_state_valid_identity('m1i:v1:short') === null, 'short identity is rejected');
v11c_check(feed_item_state_valid_identity(['array']) === null, 'non-string identity is rejected');

$items = [
    ['title' => 'A', 'item_identity' => $idA],
    ['title' => 'B', 'item_identity' => $idB],
];
$baseline = feed_item_state_plan($items, [], true, $now);
v11c_check($baseline['new_count'] === 0, 'initial baseline does not mark existing articles NEW');
v11c_check(($baseline['items'][0]['is_new'] ?? true) === false, 'initial first article is not NEW');
v11c_check(($baseline['insert_rows'][0]['seen_at'] ?? null) === $now, 'initial baseline records seen_at immediately');

$newPlan = feed_item_state_plan($items, [$idA => ['seen_at' => $now]], false, $now);
v11c_check($newPlan['new_count'] === 1, 'second fetch marks only first unseen identity NEW');
v11c_check(($newPlan['items'][0]['is_new'] ?? true) === false, 'previously seen identity remains not NEW');
v11c_check(($newPlan['items'][1]['is_new'] ?? false) === true, 'newly inserted identity is NEW');
v11c_check(($newPlan['insert_rows'][0]['item_identity'] ?? '') === $idB, 'new identity is scheduled for insert');
v11c_check(array_key_exists('seen_at', $newPlan['insert_rows'][0]) && $newPlan['insert_rows'][0]['seen_at'] === null, 'new identity is inserted unread');

$unreadPlan = feed_item_state_plan(
    [['title' => 'B', 'item_identity' => $idB]],
    [$idB => ['seen_at' => null]],
    false,
    $now
);
v11c_check($unreadPlan['new_count'] === 1 && ($unreadPlan['items'][0]['is_new'] ?? false) === true, 'unread state remains NEW across reloads');

$duplicatePlan = feed_item_state_plan(
    [
        ['title' => 'C1', 'item_identity' => $idC],
        ['title' => 'C2', 'item_identity' => $idC],
    ],
    [],
    false,
    $now
);
v11c_check($duplicatePlan['new_count'] === 1, 'duplicate Feed entries count as one NEW identity');
v11c_check(count($duplicatePlan['insert_rows']) === 1, 'duplicate Feed entries produce one DB insert');
v11c_check(($duplicatePlan['items'][0]['is_new'] ?? false) && ($duplicatePlan['items'][1]['is_new'] ?? false), 'duplicate rendered rows share the same NEW state');

$invalidPlan = feed_item_state_plan([
    ['title' => 'No identity', 'item_identity' => '<script>'],
    ['title' => 'Missing'],
], [], false, $now);
v11c_check($invalidPlan['new_count'] === 0 && count($invalidPlan['insert_rows']) === 0, 'invalid/missing identities never create state rows');
v11c_check(($invalidPlan['items'][0]['title'] ?? '') === 'No identity', 'state planning preserves existing item fields');


$safePayload = api_safe_feed_payload([
    'channel' => ['title' => 'Test', 'link' => 'https://example.com/', 'description' => ''],
    'item' => [[
        'title' => 'Safe',
        'link' => 'https://example.com/article',
        'description' => '',
        'content' => '',
        'date' => '',
        'item_identity' => $idA,
        'is_new' => true,
    ]],
], 'https://example.com/feed');
v11c_check(($safePayload['item'][0]['item_identity'] ?? '') === $idA, 'safe Feed payload preserves a canonical item identity');
v11c_check(($safePayload['item'][0]['is_new'] ?? false) === true, 'safe Feed payload preserves the server NEW flag');

$unsafePayload = api_safe_feed_payload([
    'channel' => ['title' => 'Test', 'link' => 'https://example.com/', 'description' => ''],
    'item' => [[
        'title' => 'Unsafe',
        'link' => 'https://example.com/article',
        'description' => '',
        'content' => '',
        'date' => '',
        'item_identity' => '<script>',
        'is_new' => true,
    ]],
], 'https://example.com/feed');
v11c_check(($unsafePayload['item'][0]['item_identity'] ?? 'x') === '', 'safe Feed payload drops a malformed item identity');
v11c_check(($unsafePayload['item'][0]['is_new'] ?? true) === false, 'malformed identity cannot forge a NEW flag');

final class V11cStateFakeStatement extends PDOStatement
{
    private array $rows = [];
    private mixed $column = false;
    private int $affected = 0;

    public function __construct(private V11cStateFakePDO $pdo, private string $sql) {}

    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $this->rows = [];
        $this->column = false;
        $this->affected = 0;

        if (str_starts_with($this->sql, 'SELECT content_id FROM `ig_content` ')) {
            $id = (int) ($params[':content_id'] ?? 0);
            $owner = (int) ($params[':owner_id'] ?? 0);
            $row = $this->pdo->contents[$id] ?? null;
            $this->column = is_array($row) && $row['owner'] === $owner && $row['flag'] === 0 ? $id : false;
            return true;
        }

        if (str_starts_with($this->sql, 'SELECT * FROM ig_content WHERE content_id =')) {
            $id = (int) ($params[':content_id'] ?? 0);
            $owner = (int) ($params[':owner'] ?? 0);
            $row = $this->pdo->contents[$id] ?? null;
            if (is_array($row) && $row['owner'] === $owner && $row['flag'] === 0) {
                $this->rows = [[
                    'content_id' => $id,
                    'content_owner' => $owner,
                    'content_flag' => 0,
                    'content_value' => 'https://feed.example/test',
                ]];
            }
            return true;
        }

        if (str_starts_with($this->sql, 'SELECT COUNT(*) FROM `ig_feed_item_state`')) {
            $owner = (int) ($params[':owner_id'] ?? 0);
            $content = (int) ($params[':content_id'] ?? 0);
            $this->column = count(array_filter(
                $this->pdo->states,
                static fn(array $row): bool => $row['owner_id'] === $owner && $row['content_id'] === $content
            ));
            return true;
        }

        if (str_starts_with($this->sql, 'SELECT item_identity, seen_at FROM `ig_feed_item_state`')) {
            $owner = (int) ($params[':owner_id'] ?? 0);
            $content = (int) ($params[':content_id'] ?? 0);
            $identities = [];
            foreach ($params as $name => $value) {
                if (str_starts_with((string) $name, ':identity_')) {
                    $identities[] = (string) $value;
                }
            }
            foreach ($this->pdo->states as $row) {
                if ($row['owner_id'] === $owner && $row['content_id'] === $content && $row['state_flag'] === 0 && in_array($row['item_identity'], $identities, true)) {
                    $this->rows[] = ['item_identity' => $row['item_identity'], 'seen_at' => $row['seen_at']];
                }
            }
            return true;
        }

        if (str_starts_with($this->sql, 'INSERT INTO `ig_feed_item_state`')) {
            $key = (int) $params[':owner_id'] . ':' . (int) $params[':content_id'] . ':' . (string) $params[':item_identity'];
            if (isset($this->pdo->states[$key])) {
                throw new PDOException('duplicate identity');
            }
            $this->pdo->states[$key] = [
                'owner_id' => (int) $params[':owner_id'],
                'content_id' => (int) $params[':content_id'],
                'item_identity' => (string) $params[':item_identity'],
                'first_seen_at' => (string) $params[':first_seen_at'],
                'last_seen_at' => (string) $params[':last_seen_at'],
                'seen_at' => $params[':seen_at'],
                'state_flag' => 0,
            ];
            $this->affected = 1;
            return true;
        }

        if (str_starts_with($this->sql, 'UPDATE `ig_feed_item_state` SET last_seen_at')) {
            $key = (int) $params[':owner_id'] . ':' . (int) $params[':content_id'] . ':' . (string) $params[':item_identity'];
            if (isset($this->pdo->states[$key])) {
                $this->pdo->states[$key]['last_seen_at'] = (string) $params[':last_seen_at'];
                $this->affected = 1;
            }
            return true;
        }

        if (str_starts_with($this->sql, 'DELETE state FROM `ig_feed_item_state`')) {
            return true;
        }

        if (str_starts_with($this->sql, 'UPDATE `ig_feed_item_state` SET seen_at')) {
            $owner = (int) ($params[':owner_id'] ?? 0);
            $content = (int) ($params[':content_id'] ?? 0);
            $identity = isset($params[':item_identity']) ? (string) $params[':item_identity'] : null;
            foreach ($this->pdo->states as &$row) {
                if ($row['owner_id'] !== $owner || $row['content_id'] !== $content || $row['seen_at'] !== null || $row['state_flag'] !== 0) {
                    continue;
                }
                if ($identity !== null && $row['item_identity'] !== $identity) {
                    continue;
                }
                $row['seen_at'] = (string) $params[':seen_at'];
                $this->affected++;
            }
            unset($row);
            return true;
        }

        throw new RuntimeException('Unexpected V1.1-C fake SQL: ' . $this->sql);
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return array_shift($this->rows) ?? false;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column = 0): mixed { return $this->column; }
    public function rowCount(): int { return $this->affected; }
}

final class V11cStateFakePDO extends PDO
{
    public array $contents = [];
    public array $states = [];
    private bool $transaction = false;

    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new V11cStateFakeStatement($this, $query); }
    public function getAttribute(int $attribute): mixed { return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : null; }
    public function beginTransaction(): bool { $this->transaction = true; return true; }
    public function commit(): bool { $this->transaction = false; return true; }
    public function rollBack(): bool { $this->transaction = false; return true; }
    public function inTransaction(): bool { return $this->transaction; }
}

$pdo = new V11cStateFakePDO();
$pdo->contents[11] = ['owner' => 10, 'flag' => 0];
$pdo->contents[22] = ['owner' => 20, 'flag' => 0];
set_db_connection_for_testing($pdo);

$first = feed_item_state_sync(10, 11, [
    ['title' => 'A', 'item_identity' => $idA],
    ['title' => 'B', 'item_identity' => $idB],
]);
v11c_check($first['initial_baseline'] === true && $first['new_count'] === 0, 'first DB sync establishes a non-NEW baseline');
v11c_check(count($pdo->states) === 2, 'first DB sync stores both identities');
v11c_check(array_reduce($pdo->states, static fn(bool $ok, array $row): bool => $ok && $row['seen_at'] !== null, true), 'baseline DB rows are stored as seen');

$second = feed_item_state_sync(10, 11, [
    ['title' => 'A', 'item_identity' => $idA],
    ['title' => 'C', 'item_identity' => $idC],
]);
v11c_check($second['initial_baseline'] === false && $second['new_count'] === 1, 'later DB sync detects one new identity');
$keyC = '10:11:' . $idC;
v11c_check(isset($pdo->states[$keyC]) && $pdo->states[$keyC]['seen_at'] === null, 'new DB row remains unread');

$repeat = feed_item_state_sync(10, 11, [['title' => 'C', 'item_identity' => $idC]]);
v11c_check($repeat['new_count'] === 1, 'unread DB row remains NEW after repeated fetch');

$cleared = feed_item_state_mark_seen(10, 11, $idC);
v11c_check($cleared === 1 && $pdo->states[$keyC]['seen_at'] !== null, 'single-item NEW clear updates only the selected identity');
$clearedAgain = feed_item_state_mark_seen(10, 11, $idC);
v11c_check($clearedAgain === 0, 'repeated NEW clear is idempotent');

$pdo->states['10:11:' . v11c_identity('d')] = [
    'owner_id' => 10, 'content_id' => 11, 'item_identity' => v11c_identity('d'),
    'first_seen_at' => $now, 'last_seen_at' => $now, 'seen_at' => null, 'state_flag' => 0,
];
$pdo->states['10:11:' . v11c_identity('e')] = [
    'owner_id' => 10, 'content_id' => 11, 'item_identity' => v11c_identity('e'),
    'first_seen_at' => $now, 'last_seen_at' => $now, 'seen_at' => null, 'state_flag' => 0,
];
v11c_check(feed_item_state_mark_seen(10, 11, null) === 2, 'Feed-level NEW clear updates all unread identities in that owned Feed');

$thrown = false;
try {
    feed_item_state_mark_seen(20, 11, null);
} catch (RuntimeException) {
    $thrown = true;
}
v11c_check($thrown, 'another user cannot clear NEW state for an owned Feed');
v11c_check(feed_item_state_mark_seen(20, 22, null) === 0, 'owner scope allows own Feed and does not touch another Feed');

$thrown = false;
try {
    feed_item_state_mark_seen(10, 11, 'bad');
} catch (InvalidArgumentException) {
    $thrown = true;
}
v11c_check($thrown, 'invalid item_identity is rejected before DB update');

$apiInvalidId = api_dispatch('feed.new.clear', 10, ['content_id' => 'x']);
v11c_check($apiInvalidId['status'] === 422, 'NEW clear API rejects malformed content_id');
$apiInvalidIdentity = api_dispatch('feed.new.clear', 10, ['content_id' => '11', 'item_identity' => '<script>']);
v11c_check($apiInvalidIdentity['status'] === 422, 'NEW clear API rejects malformed item_identity');
$apiOtherOwner = api_dispatch('feed.new.clear', 20, ['content_id' => '11']);
v11c_check($apiOtherOwner['status'] === 404, 'NEW clear API hides another user Feed as not found');

$idF = v11c_identity('f');
$pdo->states['10:11:' . $idF] = [
    'owner_id' => 10, 'content_id' => 11, 'item_identity' => $idF,
    'first_seen_at' => $now, 'last_seen_at' => $now, 'seen_at' => null, 'state_flag' => 0,
];
$apiClear = api_dispatch('feed.new.clear', 10, ['content_id' => '11', 'item_identity' => $idF, 'owner_id' => '20']);
v11c_check($apiClear['status'] === 200 && ($apiClear['body']['data']['cleared_count'] ?? 0) === 1, 'NEW clear API updates an owned item');
v11c_check($pdo->states['10:11:' . $idF]['seen_at'] !== null, 'NEW clear API derives owner from authenticated user, not request data');

set_db_connection_for_testing(null);
if ($failures !== []) {
    fwrite(STDERR, count($failures) . "/{$checks} V1.1-C Feed item state checks failed.\n");
    exit(1);
}
echo "All {$checks} V1.1-C Feed item state checks passed.\n";
