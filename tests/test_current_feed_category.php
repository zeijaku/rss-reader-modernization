<?php

declare(strict_types=1);

final class FakeStatement
{
    public array $params = [];

    public function __construct(
        public string $sql,
        private int $rowCountValue = 1,
        private array $fetchAllValue = [],
        private mixed $fetchColumnValue = false
    ) {}

    public function execute(array $params = []): bool { $this->params = $params; return true; }
    public function rowCount(): int { return $this->rowCountValue; }
    public function fetchAll(): array { return $this->fetchAllValue; }
    public function fetchColumn(): mixed { return $this->fetchColumnValue; }
}

final class FakePdo
{
    public array $queue = [];
    public array $statements = [];

    public function getAttribute(int $attribute): string { return 'mysql'; }

    public function prepare(string $sql): FakeStatement
    {
        $config = array_shift($this->queue) ?? [];
        $stmt = new FakeStatement(
            $sql,
            (int) ($config['rowCount'] ?? 1),
            is_array($config['fetchAll'] ?? null) ? $config['fetchAll'] : [],
            $config['fetchColumn'] ?? false
        );
        $this->statements[] = $stmt;
        return $stmt;
    }
}

$fakePdo = new FakePdo();
function conn_db(): FakePdo { global $fakePdo; return $fakePdo; }
function db_table_identifier(string $name): string { return 'rss_' . $name; }
function app_now(): string { return '2026-10-06 11:00:00'; }
function app_validate_text(mixed $value, int $maxLength, bool $allowEmpty = true): ?string
{
    if (!is_string($value) || preg_match('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/', $value)) return null;
    $value = trim($value);
    if (!$allowEmpty && $value === '') return null;
    return strlen($value) <= $maxLength ? $value : null;
}
function app_validate_external_link(mixed $value, int $maxLength = 2048): ?string { return is_string($value) ? $value : null; }
function app_validate_feed_url(mixed $value): ?string { return is_string($value) ? $value : null; }
function api_success(array $data = [], int $status = 200): array { return ['status'=>$status,'body'=>['ok'=>true,'data'=>$data]]; }
function api_error(string $code, string $message, int $status): array { return ['status'=>$status,'body'=>['ok'=>false,'error'=>['code'=>$code,'message'=>$message]]]; }
function api_validation_error(string $message): array { return api_error('validation_error', $message, 422); }
function api_positive_int(array $input, string $key): ?int
{
    $value = $input[$key] ?? null;
    if (is_int($value)) return $value > 0 ? $value : null;
    return is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1 ? (int) $value : null;
}

require_once dirname(__DIR__) . '/app/feed_metadata.php';
require_once dirname(__DIR__) . '/app/api/feed_category.php';

$pass = 0;
$fail = 0;
function v145a_check(bool $condition, string $message): void
{
    global $pass, $fail;
    if ($condition) { $pass++; echo "PASS: {$message}\n"; }
    else { $fail++; echo "FAIL: {$message}\n"; }
}
function v145a_last_statement(): FakeStatement
{
    global $fakePdo;
    return $fakePdo->statements[count($fakePdo->statements) - 1];
}

$fakePdo->queue[] = ['fetchAll' => [['category_path'=>'Cloud'],['category_path'=>'News']]];
v145a_check(feed_metadata_category_paths_owned(7) === ['Cloud','News'], 'Owned category list returns validated paths');
$stmt = v145a_last_statement();
v145a_check(str_contains($stmt->sql, 'c.content_owner = :owner') && str_contains($stmt->sql, 'c.content_flag = 0') && str_contains($stmt->sql, 'DISTINCT m.category_path'), 'Category list SQL is owner-scoped and active-only');
v145a_check(($stmt->params[':owner'] ?? null) === 7, 'Category list binds authenticated owner id');

$fakePdo->queue[] = ['rowCount'=>1];
v145a_check(feed_metadata_set_category_owned(7, 41, 'Cloud') === true, 'Owned category set reports success');
$stmt = v145a_last_statement();
v145a_check(str_contains($stmt->sql, 'c.content_id = :content_id') && str_contains($stmt->sql, 'c.content_owner = :owner') && str_contains($stmt->sql, 'c.content_flag = 0'), 'Set SQL enforces content id, owner, and active flag');
v145a_check(str_contains($stmt->sql, 'category_path = VALUES(category_path)') && !str_contains($stmt->sql, 'feed_title = VALUES(feed_title)') && !str_contains($stmt->sql, 'site_url = VALUES(site_url)'), 'Set updates Category only and preserves title/site metadata');
v145a_check(($stmt->params[':owner'] ?? null) === 7 && ($stmt->params[':content_id'] ?? null) === 41 && ($stmt->params[':category_path'] ?? null) === 'Cloud', 'Set binds owner, Feed id, and Category');

$fakePdo->queue[] = ['rowCount'=>0];
$fakePdo->queue[] = ['fetchColumn'=>false];
v145a_check(feed_metadata_set_category_owned(7, 99, 'Nope') === false, 'Non-owned or missing Feed returns false');
$checkStmt = v145a_last_statement();
v145a_check(str_contains($checkStmt->sql, 'content_owner = :owner') && ($checkStmt->params[':owner'] ?? null) === 7, 'No-op ownership verification remains owner-scoped');

$fakePdo->queue[] = ['rowCount'=>2];
v145a_check(feed_metadata_rename_category_owned(7, 'Cloud', 'Platform') === 2, 'Rename reports changed owned Feed count');
$stmt = v145a_last_statement();
v145a_check(str_contains($stmt->sql, 'EXISTS') && str_contains($stmt->sql, 'c.content_owner = :owner') && str_contains($stmt->sql, 'c.content_flag = 0'), 'Rename SQL cannot cross the owner/active boundary');
v145a_check(($stmt->params[':source_category'] ?? null) === 'Cloud' && ($stmt->params[':target_category'] ?? null) === 'Platform', 'Rename binds source and target paths');

$fakePdo->queue[] = ['rowCount'=>3];
v145a_check(feed_metadata_delete_category_owned(7, 'Platform') === 3, 'Delete reassigns owned Feeds to uncategorized');
$stmt = v145a_last_statement();
v145a_check(($stmt->params[':target_category'] ?? null) === '' && ($stmt->params[':owner'] ?? null) === 7, 'Delete uses empty Category without deleting Feed rows');

$before = count($fakePdo->statements);
$response = api_feed_category_set(7, ['content_id'=>'0','category_path'=>'Cloud']);
v145a_check($response['status'] === 422 && count($fakePdo->statements) === $before, 'API rejects invalid Feed id before DB access');
$response = api_feed_category_set(7, ['content_id'=>'41','category_path'=>str_repeat('x', 513)]);
v145a_check($response['status'] === 422 && count($fakePdo->statements) === $before, 'API rejects overlong Category before DB access');

$fakePdo->queue[] = ['rowCount'=>1];
$response = api_feed_category_set(7, ['content_id'=>'41','category_path'=>'API']);
v145a_check($response['status'] === 200 && ($response['body']['data']['category_path'] ?? null) === 'API', 'API set returns normalized Category');

$fakePdo->queue[] = ['rowCount'=>0];
$fakePdo->queue[] = ['fetchColumn'=>false];
$response = api_feed_category_set(7, ['content_id'=>'88','category_path'=>'Nope']);
v145a_check($response['status'] === 404 && ($response['body']['error']['code'] ?? null) === 'feed_not_found', 'API hides non-owned Feed behind not-found response');

$response = api_feed_category_rename(7, ['category_path'=>'','new_category_path'=>'New']);
v145a_check($response['status'] === 422, 'API rejects blank rename source');
$response = api_feed_category_rename(7, ['category_path'=>'Old','new_category_path'=>'']);
v145a_check($response['status'] === 422, 'API rejects blank rename target');

$fakePdo->queue[] = ['rowCount'=>4];
$response = api_feed_category_rename(7, ['category_path'=>'Old','new_category_path'=>'New']);
v145a_check($response['status'] === 200 && ($response['body']['data']['changed'] ?? null) === 4, 'API rename returns changed Feed count');
$fakePdo->queue[] = ['rowCount'=>4];
$response = api_feed_category_delete(7, ['category_path'=>'New']);
v145a_check($response['status'] === 200 && ($response['body']['data']['changed'] ?? null) === 4, 'API delete returns uncategorized Feed count');

printf("RESULT: %s %d / FAIL %d / SKIP 0\n", $fail === 0 ? 'PASS' : 'FAIL', $pass, $fail);
exit($fail === 0 ? 0 : 1);
