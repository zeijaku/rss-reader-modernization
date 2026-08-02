<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

function v11g_usage(): void
{
    echo "Usage:\n";
    echo "  php tools/db_v11g.php verify\n";
    echo "  php tools/db_v11g.php apply --backup-confirmed\n\n";
    echo "verify is read-only. apply creates the prefixed memo table.\n";
}

function v11g_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $stmt->execute([':table_name' => $table]);
    return (int) $stmt->fetchColumn() === 1;
}

/** @return list<string> */
function v11g_schema_issues(PDO $pdo): array
{
    $table = db_table_name('memo');
    if (!v11g_table_exists($pdo, $table)) {
        return ["Missing table: {$table}"];
    }

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM ' . db_table_identifier('memo'))->fetchAll() as $row) {
        $columns[(string) ($row['Field'] ?? '')] = strtolower((string) ($row['Type'] ?? ''));
    }
    $requiredColumns = [
        'memo_id' => 'int',
        'memo_date' => 'datetime',
        'memo_updated_at' => 'datetime',
        'memo_flag' => 'tinyint',
        'memo_owner' => 'int',
        'memo_title' => 'varchar(128)',
        'memo_body' => 'text',
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
    foreach ($pdo->query('SHOW INDEX FROM ' . db_table_identifier('memo'))->fetchAll() as $row) {
        $name = (string) ($row['Key_name'] ?? '');
        $indexes[$name][(int) ($row['Seq_in_index'] ?? 0)] = (string) ($row['Column_name'] ?? '');
    }
    foreach ($indexes as $name => $parts) {
        ksort($parts);
        $indexes[$name] = array_values($parts);
    }
    $requiredIndexes = [
        'PRIMARY' => ['memo_id'],
        'idx_memo_owner_flag_id' => ['memo_owner', 'memo_flag', 'memo_id'],
    ];
    foreach ($requiredIndexes as $name => $expected) {
        if (($indexes[$name] ?? null) !== $expected) {
            $issues[] = "Missing or unexpected index: {$table}.{$name}";
        }
    }
    return $issues;
}

/** @return list<string> */
function v11g_data_issues(PDO $pdo): array
{
    if (!v11g_table_exists($pdo, db_table_name('memo'))
        || !v11g_table_exists($pdo, db_table_name('dashboard_widget'))) {
        return [];
    }

    $stmt = $pdo->query(
        'SELECT m.memo_id FROM ' . db_table_identifier('memo') . ' m '
        . 'LEFT JOIN ' . db_table_identifier('dashboard_widget') . ' w '
        . "ON w.widget_owner = m.memo_owner AND w.widget_type = 'memo' "
        . 'AND w.widget_reference_id = m.memo_id AND w.widget_flag = 0 '
        . 'WHERE m.memo_flag = 0 AND w.widget_id IS NULL ORDER BY m.memo_id ASC LIMIT 20'
    );
    $missing = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if ($missing !== []) {
        return ['Active Memo without Widget: ' . implode(', ', $missing)];
    }

    $stmt = $pdo->query(
        'SELECT w.widget_id FROM ' . db_table_identifier('dashboard_widget') . ' w '
        . 'LEFT JOIN ' . db_table_identifier('memo') . ' m '
        . "ON w.widget_type = 'memo' AND w.widget_reference_id = m.memo_id "
        . 'AND w.widget_owner = m.memo_owner AND m.memo_flag = 0 '
        . "WHERE w.widget_type = 'memo' AND w.widget_flag = 0 AND m.memo_id IS NULL "
        . 'ORDER BY w.widget_id ASC LIMIT 20'
    );
    $orphans = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    return $orphans === [] ? [] : ['Memo Widget without active Memo: ' . implode(', ', $orphans)];
}

function v11g_create_table(PDO $pdo): void
{
    if (!v11g_table_exists($pdo, db_table_name('dashboard_widget'))) {
        throw new RuntimeException('dashboard_widget table was not found. Apply V1.1-D first.');
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ' . db_table_identifier('memo') . ' ('
        . '`memo_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
        . '`memo_date` DATETIME NOT NULL,'
        . '`memo_updated_at` DATETIME NOT NULL,'
        . '`memo_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0,'
        . '`memo_owner` INT UNSIGNED NOT NULL,'
        . '`memo_title` VARCHAR(128) NOT NULL,'
        . '`memo_body` TEXT NOT NULL,'
        . 'PRIMARY KEY (`memo_id`),'
        . 'KEY `idx_memo_owner_flag_id` (`memo_owner`, `memo_flag`, `memo_id`)'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Memo保管\''
    );
}

$command = $argv[1] ?? 'help';
if (!in_array($command, ['verify', 'apply'], true)) {
    v11g_usage();
    exit(in_array($command, ['help', '--help', '-h'], true) ? 0 : 2);
}
if ($command === 'apply' && !in_array('--backup-confirmed', $argv, true)) {
    fwrite(STDERR, "REFUSED: apply requires --backup-confirmed after a verified DB backup.\n");
    exit(5);
}

try {
    $pdo = conn_db('mysql');
    if ($command === 'apply') {
        v11g_create_table($pdo);
    }
    $issues = array_merge(v11g_schema_issues($pdo), v11g_data_issues($pdo));
    echo 'V1.1-G table: ' . db_table_name('memo') . "\n";
    echo 'Schema/data verification: ' . ($issues === [] ? 'PASS' : 'FAIL') . "\n";
    foreach ($issues as $issue) {
        echo "- {$issue}\n";
    }
    exit($issues === [] ? 0 : 4);
} catch (Throwable $exception) {
    fwrite(STDERR, 'V1.1-G DB ERROR: ' . $exception->getMessage() . "\n");
    exit(1);
}
