<?php

declare(strict_types=1);

final class FakeStatement
{
    public array $params = [];
    public function __construct(public string $sql, private int $rowCountValue = 1) {}
    public function execute(array $params = []): bool { $this->params = $params; return true; }
    public function rowCount(): int { return $this->rowCountValue; }
    public function fetchAll(): array { return []; }
}

final class FakePdo
{
    /** @var list<FakeStatement> */
    public array $statements = [];
    public function getAttribute(int $attribute): string { return 'mysql'; }
    public function prepare(string $sql): FakeStatement
    {
        $stmt = new FakeStatement($sql);
        $this->statements[] = $stmt;
        return $stmt;
    }
}

$fakePdo = new FakePdo();
$fakeFeedResponse = [
    'status' => 200,
    'body' => [
        'ok' => true,
        'data' => [
            'content_id' => 42,
            'result_feed' => [
                'channel' => ['title' => 'Example Feed'],
                'item' => [],
            ],
        ],
    ],
];

function conn_db(): FakePdo { global $fakePdo; return $fakePdo; }
function db_table_identifier(string $name): string { return '`rss_' . $name . '`'; }
function app_now(): string { return '2026-08-26 16:00:00'; }
function app_validate_text(mixed $value, int $maxLength, bool $allowEmpty = true): ?string
{
    if (!is_string($value)) { return null; }
    $value = trim($value);
    if (!$allowEmpty && $value === '') { return null; }
    return strlen($value) <= $maxLength ? $value : null;
}
function app_validate_external_link(mixed $value, int $maxLength = 2048): ?string { return is_string($value) ? $value : null; }
function app_validate_feed_url(mixed $value): ?string { return is_string($value) ? $value : null; }
function app_is_valid_utf8(string $value): bool { return true; }
function app_has_control_characters(string $value): bool { return false; }
function app_text_length(string $value): int { return strlen($value); }
function api_feed_fetch(int $userId, array $input): array { global $fakeFeedResponse; return $fakeFeedResponse; }
function api_error(string $code, string $message, int $status): array { return ['status'=>$status,'body'=>['ok'=>false]]; }
function api_success(array $data = [], int $status = 200): array { return ['status'=>$status,'body'=>['ok'=>true,'data'=>$data]]; }
function api_validation_error(string $message): array { return api_error('validation_error',$message,422); }
function dashboard_widget_create_feed(int $ownerId, string $url, string $style, int $location, int $width = 1, int $height = 1, mixed $itemLimit = null): int { return 1; }

require_once dirname(__DIR__) . '/app/api/opml.php';

$response = api_feed_fetch_with_metadata_title(7, ['content_id' => '42']);
if ($response !== $fakeFeedResponse) {
    fwrite(STDERR, "FAIL: feed response was changed\n");
    exit(1);
}
if (count($fakePdo->statements) !== 1) {
    fwrite(STDERR, "FAIL: title metadata write was not attempted exactly once\n");
    exit(1);
}
$stmt = $fakePdo->statements[0];
if (!str_contains($stmt->sql, 'c.content_owner = :owner') || !str_contains($stmt->sql, "feed_title = IF(feed_title = '', VALUES(feed_title), feed_title)")) {
    fwrite(STDERR, "FAIL: ownership or non-overwrite guard is missing\n");
    exit(1);
}
if (str_contains($stmt->sql, 'site_url =') || str_contains($stmt->sql, 'category_path =')) {
    fwrite(STDERR, "FAIL: title fill must not update other metadata fields\n");
    exit(1);
}
if (($stmt->params[':owner'] ?? null) !== 7 || ($stmt->params[':content_id'] ?? null) !== 42 || ($stmt->params[':feed_title'] ?? null) !== 'Example Feed') {
    fwrite(STDERR, "FAIL: metadata title parameters are incorrect\n");
    exit(1);
}

$before = count($fakePdo->statements);
$fakeFeedResponse['body']['data']['result_feed']['channel']['title'] = '';
api_feed_fetch_with_metadata_title(7, ['content_id' => '42']);
if (count($fakePdo->statements) !== $before) {
    fwrite(STDERR, "FAIL: blank title must not write metadata\n");
    exit(1);
}

echo "PASS: V1.22-A feed metadata title supplementation\n";
