<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

function v11c_usage(): void
{
    echo "Usage:\n";
    echo "  php tools/db_v11c.php verify\n";
    echo "  php tools/db_v11c.php apply --backup-confirmed\n\n";
    echo "verify is read-only. apply creates only the prefixed feed_item_state table.\n";
}

function v11c_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $stmt->execute([':table_name' => $table]);
    return (int) $stmt->fetchColumn() === 1;
}

/** @return list<string> */
function v11c_schema_issues(PDO $pdo): array
{
    $table = db_table_name('feed_item_state');
    if (!v11c_table_exists($pdo, $table)) {
        return ["Missing table: {$table}"];
    }

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM ' . db_table_identifier('feed_item_state'))->fetchAll() as $row) {
        $columns[(string) ($row['Field'] ?? '')] = strtolower((string) ($row['Type'] ?? ''));
    }
    $requiredColumns = [
        'state_id' => 'bigint',
        'owner_id' => 'int',
        'content_id' => 'int',
        'item_identity' => 'char(71)',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'seen_at' => 'datetime',
        'state_flag' => 'tinyint',
    ];

    $issues = [];
    foreach ($requiredColumns as $name => $typePart) {
        if (!isset($columns[$name])) {
            $issues[] = "Missing column: {$table}.{$name}";
        } elseif (!str_contains($columns[$name], $typePart)) {
            $issues[] = "Unexpected column type: {$table}.{$name} ({$columns[$name]})";
        }
    }

    $indexes = [];
    foreach ($pdo->query('SHOW INDEX FROM ' . db_table_identifier('feed_item_state'))->fetchAll() as $row) {
        $name = (string) ($row['Key_name'] ?? '');
        $indexes[$name][(int) ($row['Seq_in_index'] ?? 0)] = (string) ($row['Column_name'] ?? '');
    }
    foreach ($indexes as $name => $parts) {
        ksort($parts);
        $indexes[$name] = array_values($parts);
    }
    $requiredIndexes = [
        'PRIMARY' => ['state_id'],
        'uq_feed_item_state_owner_content_identity' => ['owner_id', 'content_id', 'item_identity'],
        'idx_feed_item_state_owner_content_seen' => ['owner_id', 'content_id', 'seen_at', 'state_flag'],
        'idx_feed_item_state_last_seen' => ['last_seen_at'],
    ];
    foreach ($requiredIndexes as $name => $columnsExpected) {
        if (($indexes[$name] ?? null) !== $columnsExpected) {
            $issues[] = "Missing or unexpected index: {$table}.{$name}";
        }
    }

    return $issues;
}

function v11c_create_table(PDO $pdo): void
{
    if (!v11c_table_exists($pdo, db_table_name('content'))) {
        throw new RuntimeException('Existing content table was not found. Confirm DB_TABLE_PREFIX and Version 1.0.0 schema first.');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ' . db_table_identifier('feed_item_state') . ' ('
        . '`state_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
        . '`owner_id` INT UNSIGNED NOT NULL,'
        . '`content_id` INT UNSIGNED NOT NULL,'
        . '`item_identity` CHAR(71) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
        . '`first_seen_at` DATETIME NOT NULL,'
        . '`last_seen_at` DATETIME NOT NULL,'
        . '`seen_at` DATETIME NULL DEFAULT NULL,'
        . '`state_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0,'
        . 'PRIMARY KEY (`state_id`),'
        . 'UNIQUE KEY `uq_feed_item_state_owner_content_identity` (`owner_id`, `content_id`, `item_identity`),'
        . 'KEY `idx_feed_item_state_owner_content_seen` (`owner_id`, `content_id`, `seen_at`, `state_flag`),'
        . 'KEY `idx_feed_item_state_last_seen` (`last_seen_at`)'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Feed記事NEW状態\''
    );
}

$command = $argv[1] ?? 'help';
if (!in_array($command, ['verify', 'apply'], true)) {
    v11c_usage();
    exit(in_array($command, ['help', '--help', '-h'], true) ? 0 : 2);
}
if ($command === 'apply' && !in_array('--backup-confirmed', $argv, true)) {
    fwrite(STDERR, "REFUSED: apply requires --backup-confirmed after a verified DB backup.\n");
    exit(5);
}

try {
    $pdo = conn_db('mysql');
    if ($command === 'apply') {
        v11c_create_table($pdo);
    }

    $issues = v11c_schema_issues($pdo);
    echo 'V1.1-C table: ' . db_table_name('feed_item_state') . "\n";
    echo 'Schema verification: ' . ($issues === [] ? 'PASS' : 'FAIL') . "\n";
    foreach ($issues as $issue) {
        echo "- {$issue}\n";
    }
    exit($issues === [] ? 0 : 4);
} catch (Throwable $exception) {
    fwrite(STDERR, 'V1.1-C DB ERROR: ' . $exception->getMessage() . "\n");
    exit(1);
}
