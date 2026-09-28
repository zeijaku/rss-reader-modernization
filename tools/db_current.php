<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

/** @return list<string> */
function current_schema_tables(): array
{
    return [
        'user_info',
        'user_conf',
        'content',
        'content_stock',
        'feed_item_state',
        'memo',
        'task',
        'calendar_event',
        'calendar_event_exception',
        'dashboard_widget',
        'notification',
        'remember_token',
        'mail_account',
        'link_item',
        'stock_tag',
        'stock_tag_map',
        'feed_keyword',
        'feed_metadata',
        'feed_health',
        'rss_rule',
        'rss_rule_condition',
        'user_file',
        'remote_connection',
        'auth_totp',
        'auth_recovery_code',
        'auth_session',
        'auth_audit_log',
    ];
}

/** @return array<string, list<string>> */
function current_schema_required_columns(): array
{
    return [
        'calendar_event' => [
            'calendar_event_color',
            'calendar_event_repeat_type',
            'calendar_event_repeat_until',
            'calendar_event_reminder',
        ],
        'calendar_event_exception' => [
            'calendar_event_exception_kind',
            'calendar_event_exception_revision',
        ],
        'notification' => [
            'notification_source_key',
            'notification_due_at',
            'notification_read_at',
            'notification_hidden_at',
        ],
        'remember_token' => [
            'remember_token_second_factor_verified_at',
        ],
        'mail_account' => [
            'mail_account_auth_type',
            'mail_account_smtp_enabled',
            'mail_account_smtp_secret',
            'mail_account_sent_save_mode',
        ],
        'remote_connection' => [
            'remote_connection_secret',
            'remote_connection_allow_private',
            'remote_connection_enabled',
        ],
        'auth_totp' => [
            'auth_totp_secret',
            'auth_totp_last_used_step',
        ],
        'auth_session' => [
            'auth_session_token_hash',
            'auth_session_revoked_at',
        ],
        'auth_audit_log' => [
            'auth_audit_log_identity_hash',
            'auth_audit_log_ip_hash',
        ],
        'feed_health' => [
            'last_result',
            'consecutive_failure_count',
        ],
        'rss_rule' => [
            'match_mode',
            'rule_action',
        ],
    ];
}

/** @return array<string, list<string>> */
function current_schema_required_indexes(): array
{
    return [
        'user_info' => ['PRIMARY', 'idx_user_identity_flag_id'],
        'user_conf' => ['PRIMARY', 'uq_user_conf_user_id'],
        'content' => ['PRIMARY', 'idx_content_owner_location_flag_id'],
        'content_stock' => ['PRIMARY', 'idx_stock_owner_flag_archived_id'],
        'calendar_event' => ['PRIMARY', 'idx_calendar_event_owner_range'],
        'calendar_event_exception' => ['PRIMARY', 'uq_cal_exception_owner_event_original'],
        'notification' => ['PRIMARY', 'uq_notification_owner_source'],
        'remember_token' => ['PRIMARY', 'uq_remember_token_selector'],
        'mail_account' => ['PRIMARY', 'idx_mail_account_owner_enabled_flag'],
        'remote_connection' => ['PRIMARY', 'idx_remote_connection_owner_enabled_flag'],
        'auth_session' => ['PRIMARY', 'uq_auth_session_token_hash'],
        'stock_tag' => ['PRIMARY', 'uq_stock_tag_owner_name'],
    ];
}

/** @return array<string, true> */
function current_schema_fetch_table_set(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
    );
    $tables = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (is_string($table)) {
            $tables[$table] = true;
        }
    }
    return $tables;
}

/** @return array<string, array<string, true>> */
function current_schema_fetch_columns(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE()'
    );
    $columns = [];
    foreach ($stmt->fetchAll() as $row) {
        $table = (string) ($row['TABLE_NAME'] ?? '');
        $column = (string) ($row['COLUMN_NAME'] ?? '');
        if ($table !== '' && $column !== '') {
            $columns[$table][$column] = true;
        }
    }
    return $columns;
}

/** @return array<string, array<string, true>> */
function current_schema_fetch_indexes(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT TABLE_NAME, INDEX_NAME FROM information_schema.STATISTICS '
        . 'WHERE TABLE_SCHEMA = DATABASE()'
    );
    $indexes = [];
    foreach ($stmt->fetchAll() as $row) {
        $table = (string) ($row['TABLE_NAME'] ?? '');
        $index = (string) ($row['INDEX_NAME'] ?? '');
        if ($table !== '' && $index !== '') {
            $indexes[$table][$index] = true;
        }
    }
    return $indexes;
}

$command = $argv[1] ?? 'help';
if (!in_array($command, ['verify', 'help', '--help', '-h'], true)) {
    fwrite(STDERR, "Usage: php tools/db_current.php verify\n");
    exit(2);
}
if ($command !== 'verify') {
    echo "Usage: php tools/db_current.php verify\n";
    echo "Read-only verification of the Current database schema.\n";
    exit(0);
}

try {
    if (strtolower((string) DB_DRIVER) !== 'mysql') {
        throw new RuntimeException('Current production schema verification requires DB_DRIVER=mysql.');
    }

    $pdo = conn_db('mysql');
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $prefix = (string) DB_TABLE_PREFIX;

    $allTables = current_schema_fetch_table_set($pdo);
    $columns = current_schema_fetch_columns($pdo);
    $indexes = current_schema_fetch_indexes($pdo);

    $requiredPhysical = [];
    foreach (current_schema_tables() as $logical) {
        $requiredPhysical[$logical] = $prefix . $logical;
    }

    $missingTables = [];
    foreach ($requiredPhysical as $logical => $physical) {
        if (!isset($allTables[$physical])) {
            $missingTables[] = $physical;
        }
    }

    $missingColumns = [];
    foreach (current_schema_required_columns() as $logical => $requiredColumns) {
        $physical = $prefix . $logical;
        foreach ($requiredColumns as $column) {
            if (!isset($columns[$physical][$column])) {
                $missingColumns[] = $physical . '.' . $column;
            }
        }
    }

    $missingIndexes = [];
    foreach (current_schema_required_indexes() as $logical => $requiredIndexes) {
        $physical = $prefix . $logical;
        foreach ($requiredIndexes as $index) {
            if (!isset($indexes[$physical][$index])) {
                $missingIndexes[] = $physical . '.' . $index;
            }
        }
    }

    $prefixedTables = array_values(array_filter(
        array_keys($allTables),
        static fn(string $table): bool => str_starts_with($table, $prefix)
    ));
    sort($prefixedTables);

    $expectedPhysicalSet = array_fill_keys(array_values($requiredPhysical), true);
    $extraPrefixed = array_values(array_filter(
        $prefixedTables,
        static fn(string $table): bool => !isset($expectedPhysicalSet[$table])
    ));

    echo "RSS Reader Current database schema verification\n";
    echo 'Build: ' . APP_VERSION_LABEL . "\n";
    echo 'Database: ' . ($database !== '' ? $database : '(unknown)') . "\n";
    echo 'Table prefix: ' . $prefix . "\n";
    echo 'Required tables: ' . (count($requiredPhysical) - count($missingTables)) . '/' . count($requiredPhysical) . "\n";
    echo 'Prefixed tables found: ' . count($prefixedTables) . "\n";
    echo 'Required key columns: ' . ($missingColumns === [] ? 'PASS' : 'FAIL') . "\n";
    echo 'Required key indexes: ' . ($missingIndexes === [] ? 'PASS' : 'FAIL') . "\n";

    foreach ($missingTables as $item) {
        echo "- MISSING TABLE: {$item}\n";
    }
    foreach ($missingColumns as $item) {
        echo "- MISSING COLUMN: {$item}\n";
    }
    foreach ($missingIndexes as $item) {
        echo "- MISSING INDEX: {$item}\n";
    }
    foreach ($extraPrefixed as $item) {
        echo "- WARN EXTRA PREFIXED TABLE: {$item}\n";
    }

    if ($missingTables !== [] || $missingColumns !== [] || $missingIndexes !== []) {
        echo "CURRENT SCHEMA: FAIL\n";
        exit(4);
    }

    echo "CURRENT SCHEMA: PASS\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'CURRENT SCHEMA ERROR: ' . $exception->getMessage() . "\n");
    exit(1);
}
