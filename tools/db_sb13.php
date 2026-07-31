<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/db_integrity.php';

/** @param array<string, mixed> $snapshot */
function sb13_print_audit(array $snapshot): void
{
    echo "SB-13 database audit\n";
    echo 'Build: ' . APP_VERSION_LABEL . "\n";
    echo 'Database: ' . ($snapshot['database'] ?? '(unknown)') . "\n";
    echo 'Captured: ' . ($snapshot['captured_at'] ?? '(unknown)') . "\n\n";

    echo "Row counts:\n";
    foreach (sb13_tables() as $table) {
        $count = $snapshot['row_counts'][$table] ?? '(missing)';
        echo "- {$table}: {$count}\n";
    }

    echo "\nActive/distribution counts:\n";
    echo '- active users: ' . (int) ($snapshot['active_counts']['users'] ?? 0) . "\n";
    echo '- active content: ' . (int) ($snapshot['active_counts']['content'] ?? 0) . "\n";
    echo '- active stock: ' . (int) ($snapshot['active_counts']['stock'] ?? 0) . "\n";
    echo '- content locations: ' . json_encode($snapshot['content_location_counts'] ?? [], JSON_UNESCAPED_SLASHES) . "\n";
    echo '- content owners: ' . json_encode($snapshot['content_owner_counts'] ?? [], JSON_UNESCAPED_SLASHES) . "\n";
    echo '- stock owners: ' . json_encode($snapshot['stock_owner_counts'] ?? [], JSON_UNESCAPED_SLASHES) . "\n";

    echo "\nIntegrity observations:\n";
    echo '- duplicate identity groups: ' . (int) ($snapshot['duplicate_identity_groups'] ?? 0) . "\n";
    echo '- duplicate user_conf user_id groups: ' . (int) ($snapshot['duplicate_user_conf_user_ids'] ?? 0) . "\n";
    echo '- orphan content rows: ' . (int) ($snapshot['orphan_content_rows'] ?? 0) . "\n";
    echo '- orphan stock rows: ' . (int) ($snapshot['orphan_stock_rows'] ?? 0) . "\n";
    echo '- orphan conf rows: ' . (int) ($snapshot['orphan_conf_rows'] ?? 0) . "\n";
    echo '- users missing conf: ' . (int) ($snapshot['users_missing_conf'] ?? 0) . "\n";
    echo '- negative relationship IDs: ' . (int) ($snapshot['negative_relationship_ids'] ?? 0) . "\n";

    echo "\nTable collations:\n";
    foreach (sb13_tables() as $table) {
        echo '- ' . $table . ': ' . ($snapshot['table_collations'][$table] ?? '(missing)') . "\n";
    }

    $classification = sb13_classify_audit($snapshot);
    echo "\nBlocking gates: " . count($classification['blocking']) . "\n";
    foreach ($classification['blocking'] as $item) {
        echo "- BLOCK: {$item}\n";
    }
    echo "Warnings / preservation gates: " . count($classification['warnings']) . "\n";
    foreach ($classification['warnings'] as $item) {
        echo "- WARN: {$item}\n";
    }
}

/** @param array<string, mixed> $snapshot */
function sb13_save_snapshot(array $snapshot, string $label): string
{
    $directory = dirname(__DIR__) . '/var/db-migration';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create private DB migration directory.');
    }
    if (!is_writable($directory)) {
        throw new RuntimeException('Private DB migration directory is not writable.');
    }

    $stamp = (new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))->format('Ymd-His');
    $path = $directory . '/sb13-' . $label . '-' . $stamp . '.json';
    $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || file_put_contents($path, $json . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Unable to save DB migration snapshot.');
    }
    @chmod($path, 0600);
    return $path;
}

/** @param array<string, mixed> $snapshot */
function sb13_index_state(array $snapshot, string $table, string $name): string
{
    $expected = sb13_required_indexes()[$table][$name] ?? null;
    $actual = $snapshot['indexes'][$table][$name] ?? null;
    if (!is_array($expected)) {
        throw new InvalidArgumentException('Unknown required index.');
    }
    if ($actual === null) {
        return 'missing';
    }
    if (
        (bool) ($actual['unique'] ?? false) === $expected['unique']
        && ($actual['columns'] ?? []) === $expected['columns']
    ) {
        return 'ok';
    }
    return 'mismatch';
}

function sb13_execute(PDO $pdo, string $sql, string $label): void
{
    echo "APPLY: {$label}\n";
    $pdo->exec($sql);
}

/** @param array<string, mixed> $before */
function sb13_apply(PDO $pdo, array $before): array
{
    $classification = sb13_classify_audit($before);
    if ($classification['blocking'] !== []) {
        throw new RuntimeException('Preflight has blocking gates; migration was not started.');
    }

    // Charset/collation operations are intentionally table-local. MySQL DDL
    // auto-commits, so a verified backup is mandatory before this function.
    foreach (sb13_tables() as $table) {
        $collation = strtolower((string) ($before['table_collations'][$table] ?? ''));
        if ($collation !== SB13_TARGET_COLLATION) {
            sb13_execute(
                $pdo,
                "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET " . SB13_TARGET_CHARSET . ' COLLATE ' . SB13_TARGET_COLLATION,
                "{$table} -> " . SB13_TARGET_COLLATION
            );
        } else {
            echo "SKIP: {$table} already uses " . SB13_TARGET_COLLATION . "\n";
        }
    }

    $current = sb13_collect_audit($pdo);
    $userInfoTable = db_table_name('user_info');
    $userConfTable = db_table_name('user_conf');
    $contentTable = db_table_name('content');
    $contentStockTable = db_table_name('content_stock');
    $qUserInfoTable = db_table_identifier('user_info');
    $qUserConfTable = db_table_identifier('user_conf');
    $qContentTable = db_table_identifier('content');
    $qContentStockTable = db_table_identifier('content_stock');

    $relationshipDefinitions = [
        [$userConfTable, 'user_id', "ALTER TABLE {$qUserConfTable} MODIFY `user_id` INT UNSIGNED NOT NULL COMMENT 'user_infoのuser_id'"],
        [$contentTable, 'content_owner', "ALTER TABLE {$qContentTable} MODIFY `content_owner` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所有者ID[user_info:id]'"],
        [$contentStockTable, 'stock_owner', "ALTER TABLE {$qContentStockTable} MODIFY `stock_owner` INT UNSIGNED NOT NULL COMMENT 'データオーナー'"],
    ];
    foreach ($relationshipDefinitions as [$table, $column, $sql]) {
        $type = strtolower((string) ($current['columns'][$table][$column]['type'] ?? ''));
        if (!str_contains($type, 'unsigned')) {
            sb13_execute($pdo, $sql, "{$table}.{$column} -> UNSIGNED");
        } else {
            echo "SKIP: {$table}.{$column} already UNSIGNED\n";
        }
    }

    $current = sb13_collect_audit($pdo);
    $indexSql = [
        [$userInfoTable, 'idx_user_identity_flag_id', "ALTER TABLE {$qUserInfoTable} ADD INDEX `idx_user_identity_flag_id` (`user_email`(64), `user_flag`, `user_id`)"],
        [$userConfTable, 'uq_user_conf_user_id', "ALTER TABLE {$qUserConfTable} ADD UNIQUE INDEX `uq_user_conf_user_id` (`user_id`)"],
        [$contentTable, 'idx_content_owner_location_flag_id', "ALTER TABLE {$qContentTable} ADD INDEX `idx_content_owner_location_flag_id` (`content_owner`, `content_location`, `content_flag`, `content_id`)"],
        [$contentStockTable, 'idx_stock_owner_flag_id', "ALTER TABLE {$qContentStockTable} ADD INDEX `idx_stock_owner_flag_id` (`stock_owner`, `stock_flag`, `stock_id`)"],
    ];
    foreach ($indexSql as [$table, $name, $sql]) {
        $state = sb13_index_state($current, $table, $name);
        if ($state === 'mismatch') {
            throw new RuntimeException("Index {$table}.{$name} already exists with an unexpected definition. Stop for manual review.");
        }
        if ($state === 'missing') {
            sb13_execute($pdo, $sql, "add {$table}.{$name}");
            $current = sb13_collect_audit($pdo);
        } else {
            echo "SKIP: {$table}.{$name} already correct\n";
        }
    }

    return sb13_collect_audit($pdo);
}

function sb13_usage(): void
{
    echo "Usage:\n";
    echo "  php tools/db_sb13.php audit\n";
    echo "  php tools/db_sb13.php verify\n";
    echo "  php tools/db_sb13.php apply --backup-confirmed\n\n";
    echo "'audit' and 'verify' are read-only.\n";
    echo "'apply' changes schema only; it never deletes/merges application rows and requires an explicit backup confirmation flag.\n";
}

$command = $argv[1] ?? 'help';
if (!in_array($command, ['audit', 'verify', 'apply'], true)) {
    sb13_usage();
    exit($command === 'help' || $command === '--help' || $command === '-h' ? 0 : 2);
}

if ($command === 'apply' && !in_array('--backup-confirmed', $argv, true)) {
    fwrite(STDERR, "REFUSED: apply requires --backup-confirmed after you have taken and verified a database backup.\n");
    exit(5);
}

try {
    $pdo = conn_db('mysql');
    $before = sb13_collect_audit($pdo);

    if ($command === 'audit') {
        sb13_print_audit($before);
        $classification = sb13_classify_audit($before);
        exit($classification['blocking'] === [] ? 0 : 3);
    }

    if ($command === 'verify') {
        sb13_print_audit($before);
        $issues = sb13_schema_issues($before);
        echo "\nSB-13 schema verification: " . ($issues === [] ? 'PASS' : 'FAIL') . "\n";
        foreach ($issues as $issue) {
            echo "- {$issue}\n";
        }
        exit($issues === [] ? 0 : 4);
    }

    $classification = sb13_classify_audit($before);
    sb13_print_audit($before);
    if ($classification['blocking'] !== []) {
        fwrite(STDERR, "REFUSED: preflight has blocking gates. No DDL was executed.\n");
        exit(6);
    }

    $beforePath = sb13_save_snapshot($before, 'before');
    echo "\nPrivate pre-migration snapshot: {$beforePath}\n";
    echo "Starting DDL migration. No application rows will be deleted or merged.\n\n";

    $after = sb13_apply($pdo, $before);
    $afterPath = sb13_save_snapshot($after, 'after');
    $schemaIssues = sb13_schema_issues($after);
    $countIssues = sb13_data_consistency_issues($before, $after);

    echo "\nPrivate post-migration snapshot: {$afterPath}\n";
    echo 'Data-consistency verification: ' . ($countIssues === [] ? 'PASS' : 'FAIL') . "\n";
    foreach ($countIssues as $issue) {
        echo "- {$issue}\n";
    }
    echo 'Schema verification: ' . ($schemaIssues === [] ? 'PASS' : 'FAIL') . "\n";
    foreach ($schemaIssues as $issue) {
        echo "- {$issue}\n";
    }

    if ($schemaIssues !== [] || $countIssues !== []) {
        fwrite(STDERR, "SB-13 migration completed with verification issues. Stop application changes and review/restore from backup as appropriate.\n");
        exit(7);
    }

    echo "SB-13 migration verified successfully.\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'SB-13 ERROR: ' . $exception->getMessage() . "\n");
    exit(1);
}
