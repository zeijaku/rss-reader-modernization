<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

function v11d_usage(): void
{
    echo "Usage:\n";
    echo "  php tools/db_v11d.php verify\n";
    echo "  php tools/db_v11d.php apply --backup-confirmed\n\n";
    echo "verify is read-only. apply creates the prefixed dashboard_widget table and backfills active Feed widgets.\n";
}

function v11d_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $stmt->execute([':table_name' => $table]);
    return (int) $stmt->fetchColumn() === 1;
}

/** @return list<string> */
function v11d_schema_issues(PDO $pdo): array
{
    $table = db_table_name('dashboard_widget');
    if (!v11d_table_exists($pdo, $table)) {
        return ["Missing table: {$table}"];
    }

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM ' . db_table_identifier('dashboard_widget'))->fetchAll() as $row) {
        $columns[(string) ($row['Field'] ?? '')] = strtolower((string) ($row['Type'] ?? ''));
    }
    $requiredColumns = [
        'widget_id' => 'bigint',
        'widget_owner' => 'int',
        'widget_location' => 'tinyint',
        'widget_type' => 'varchar(16)',
        'widget_reference_id' => 'int',
        'widget_sort_order' => 'int',
        'widget_width' => 'tinyint',
        'widget_style' => 'varchar(16)',
        'widget_config' => 'text',
        'widget_flag' => 'tinyint',
        'widget_created_at' => 'datetime',
        'widget_updated_at' => 'datetime',
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
    foreach ($pdo->query('SHOW INDEX FROM ' . db_table_identifier('dashboard_widget'))->fetchAll() as $row) {
        $name = (string) ($row['Key_name'] ?? '');
        $indexes[$name][(int) ($row['Seq_in_index'] ?? 0)] = (string) ($row['Column_name'] ?? '');
    }
    foreach ($indexes as $name => $parts) {
        ksort($parts);
        $indexes[$name] = array_values($parts);
    }
    $requiredIndexes = [
        'PRIMARY' => ['widget_id'],
        'uq_dashboard_widget_owner_type_reference' => ['widget_owner', 'widget_type', 'widget_reference_id'],
        'idx_dashboard_widget_owner_location_order' => ['widget_owner', 'widget_location', 'widget_flag', 'widget_sort_order', 'widget_id'],
        'idx_dashboard_widget_owner_type_flag' => ['widget_owner', 'widget_type', 'widget_flag'],
    ];
    foreach ($requiredIndexes as $name => $expected) {
        if (($indexes[$name] ?? null) !== $expected) {
            $issues[] = "Missing or unexpected index: {$table}.{$name}";
        }
    }

    return $issues;
}

/** @return list<string> */
function v11d_data_issues(PDO $pdo): array
{
    if (!v11d_table_exists($pdo, db_table_name('dashboard_widget'))) {
        return [];
    }

    $stmt = $pdo->query(
        'SELECT c.content_id FROM ' . db_table_identifier('content') . ' c '
        . 'LEFT JOIN ' . db_table_identifier('dashboard_widget') . ' w '
        . "ON w.widget_owner = c.content_owner AND w.widget_type = 'feed' "
        . 'AND w.widget_reference_id = c.content_id AND w.widget_flag = 0 '
        . 'WHERE c.content_flag = 0 AND w.widget_id IS NULL ORDER BY c.content_id ASC LIMIT 20'
    );
    $missing = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if ($missing !== []) {
        return ['Active Feed without Widget: ' . implode(', ', $missing)];
    }

    $stmt = $pdo->query(
        'SELECT c.content_id FROM ' . db_table_identifier('content') . ' c '
        . 'JOIN ' . db_table_identifier('dashboard_widget') . ' w '
        . "ON w.widget_owner = c.content_owner AND w.widget_type = 'feed' AND w.widget_reference_id = c.content_id "
        . 'WHERE c.content_flag = 0 AND w.widget_flag = 0 '
        . 'AND (w.widget_location <> c.content_location OR w.widget_style <> c.content_style) '
        . 'ORDER BY c.content_id ASC LIMIT 20'
    );
    $mismatch = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    return $mismatch === [] ? [] : ['Feed/Widget mirror mismatch: ' . implode(', ', $mismatch)];
}

function v11d_create_table(PDO $pdo): void
{
    if (!v11d_table_exists($pdo, db_table_name('content'))) {
        throw new RuntimeException('Existing content table was not found. Confirm DB_TABLE_PREFIX and Version 1.0.0 schema first.');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ' . db_table_identifier('dashboard_widget') . ' ('
        . '`widget_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
        . '`widget_owner` INT UNSIGNED NOT NULL,'
        . '`widget_location` TINYINT UNSIGNED NOT NULL DEFAULT 0,'
        . '`widget_type` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
        . '`widget_reference_id` INT UNSIGNED NULL DEFAULT NULL,'
        . '`widget_sort_order` INT UNSIGNED NOT NULL DEFAULT 0,'
        . '`widget_width` TINYINT UNSIGNED NOT NULL DEFAULT 1,'
        . '`widget_style` VARCHAR(16) NOT NULL DEFAULT \'success\','
        . '`widget_config` TEXT NULL,'
        . '`widget_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0,'
        . '`widget_created_at` DATETIME NOT NULL,'
        . '`widget_updated_at` DATETIME NOT NULL,'
        . 'PRIMARY KEY (`widget_id`),'
        . 'UNIQUE KEY `uq_dashboard_widget_owner_type_reference` (`widget_owner`, `widget_type`, `widget_reference_id`),'
        . 'KEY `idx_dashboard_widget_owner_location_order` (`widget_owner`, `widget_location`, `widget_flag`, `widget_sort_order`, `widget_id`),'
        . 'KEY `idx_dashboard_widget_owner_type_flag` (`widget_owner`, `widget_type`, `widget_flag`)'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Dashboard Widget配置\''
    );
}

function v11d_backfill(PDO $pdo): void
{
    $sql = 'INSERT INTO ' . db_table_identifier('dashboard_widget') . ' ('
        . 'widget_owner, widget_location, widget_type, widget_reference_id, widget_sort_order, '
        . 'widget_width, widget_style, widget_config, widget_flag, widget_created_at, widget_updated_at'
        . ') SELECT c.content_owner, c.content_location, \'feed\', c.content_id, c.content_id, '
        . '1, c.content_style, NULL, 0, c.content_date, c.content_date '
        . 'FROM ' . db_table_identifier('content') . ' c WHERE c.content_flag = 0 '
        . 'ON DUPLICATE KEY UPDATE '
        . 'widget_location = VALUES(widget_location), '
        . 'widget_style = VALUES(widget_style), '
        . 'widget_flag = 0, '
        . 'widget_updated_at = VALUES(widget_updated_at)';
    $pdo->exec($sql);
}

$command = $argv[1] ?? 'help';
if (!in_array($command, ['verify', 'apply'], true)) {
    v11d_usage();
    exit(in_array($command, ['help', '--help', '-h'], true) ? 0 : 2);
}
if ($command === 'apply' && !in_array('--backup-confirmed', $argv, true)) {
    fwrite(STDERR, "REFUSED: apply requires --backup-confirmed after a verified DB backup.\n");
    exit(5);
}

try {
    $pdo = conn_db('mysql');
    if ($command === 'apply') {
        v11d_create_table($pdo);
        v11d_backfill($pdo);
    }

    $issues = array_merge(v11d_schema_issues($pdo), v11d_data_issues($pdo));
    echo 'V1.1-D table: ' . db_table_name('dashboard_widget') . "\n";
    echo 'Schema/data verification: ' . ($issues === [] ? 'PASS' : 'FAIL') . "\n";
    foreach ($issues as $issue) {
        echo "- {$issue}\n";
    }
    exit($issues === [] ? 0 : 4);
} catch (Throwable $exception) {
    fwrite(STDERR, 'V1.1-D DB ERROR: ' . $exception->getMessage() . "\n");
    exit(1);
}
