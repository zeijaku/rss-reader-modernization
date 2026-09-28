<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

function current_schema_usage(): void
{
    echo "Usage:\n";
    echo "  php tools/db_current.php verify\n\n";
    echo "'verify' is read-only and checks the Current MySQL / MariaDB schema.\n";
}

$command = $argv[1] ?? 'help';
if ($command !== 'verify') {
    current_schema_usage();
    exit(in_array($command, ['help', '--help', '-h'], true) ? 0 : 2);
}

if (strtolower((string) DB_DRIVER) !== 'mysql') {
    fwrite(STDERR, "CURRENT SCHEMA ERROR: DB_DRIVER must be mysql for Production schema verification.\n");
    exit(2);
}

$requiredTables = [
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
    'user_file',
    'remote_connection',
    'auth_totp',
    'auth_recovery_code',
    'auth_session',
    'auth_audit_log',
    'mail_account',
    'link_item',
    'stock_tag',
    'stock_tag_map',
    'feed_keyword',
    'feed_metadata',
    'feed_health',
    'rss_rule',
    'rss_rule_condition',
];

$requiredColumns = [
    'user_info' => ['user_id', 'user_email', 'user_password', 'user_flag'],
    'user_conf' => ['conf_id', 'user_id', 'conf_style', 'conf_style_nav'],
    'content' => ['content_id', 'content_owner', 'content_location', 'content_value'],
    'content_stock' => ['stock_id', 'stock_owner', 'stock_processed', 'stock_important', 'stock_archived'],
    'feed_item_state' => ['state_id', 'owner_id', 'content_id', 'item_identity', 'seen_at'],
    'memo' => ['memo_id', 'memo_owner', 'memo_title', 'memo_body'],
    'task' => ['task_id', 'task_owner', 'task_due_date', 'task_priority', 'task_completed'],
    'calendar_event' => [
        'calendar_event_id', 'calendar_event_owner', 'calendar_event_start_date',
        'calendar_event_end_date', 'calendar_event_color', 'calendar_event_all_day',
        'calendar_event_start_time', 'calendar_event_end_time', 'calendar_event_url',
        'calendar_event_repeat_type', 'calendar_event_repeat_until', 'calendar_event_reminder',
    ],
    'calendar_event_exception' => [
        'calendar_event_exception_id', 'calendar_event_exception_owner',
        'calendar_event_exception_event_id', 'calendar_event_exception_original_start_date',
        'calendar_event_exception_kind', 'calendar_event_exception_revision',
    ],
    'dashboard_widget' => ['widget_id', 'widget_owner', 'widget_type', 'widget_reference_id', 'widget_config'],
    'notification' => [
        'notification_id', 'notification_owner', 'notification_source_key',
        'notification_due_at', 'notification_read_at', 'notification_hidden_at',
    ],
    'remember_token' => [
        'remember_token_id', 'remember_token_user_id', 'remember_token_selector',
        'remember_token_validator_hash', 'remember_token_second_factor_verified_at',
    ],
    'user_file' => ['file_id', 'file_owner', 'file_stored_name', 'file_size', 'file_flag'],
    'remote_connection' => [
        'remote_connection_id', 'remote_connection_owner', 'remote_connection_protocol',
        'remote_connection_secret', 'remote_connection_allow_private',
        'remote_connection_enabled', 'remote_connection_flag',
    ],
    'auth_totp' => [
        'auth_totp_user_id', 'auth_totp_secret', 'auth_totp_enabled_at',
        'auth_totp_last_used_step',
    ],
    'auth_recovery_code' => [
        'auth_recovery_code_id', 'auth_recovery_code_user_id',
        'auth_recovery_code_hash', 'auth_recovery_code_used_at',
    ],
    'auth_session' => [
        'auth_session_id', 'auth_session_user_id', 'auth_session_token_hash',
        'auth_session_expires_at', 'auth_session_revoked_at',
    ],
    'auth_audit_log' => [
        'auth_audit_log_id', 'auth_audit_log_user_id', 'auth_audit_log_event',
        'auth_audit_log_result', 'auth_audit_log_created_at',
    ],
    'mail_account' => [
        'mail_account_id', 'mail_account_owner', 'mail_account_auth_type',
        'mail_account_secret', 'mail_account_smtp_enabled', 'mail_account_smtp_host',
        'mail_account_smtp_port', 'mail_account_smtp_encryption',
        'mail_account_smtp_secret', 'mail_account_sent_save_mode',
    ],
    'link_item' => ['link_id', 'link_owner', 'link_widget_id', 'link_url', 'link_sort_order'],
    'stock_tag' => ['tag_id', 'tag_owner', 'tag_name'],
    'stock_tag_map' => ['map_id', 'map_owner', 'map_stock_id', 'map_tag_id'],
    'feed_keyword' => ['keyword_id', 'keyword_owner', 'keyword_value'],
    'feed_metadata' => ['metadata_content_id', 'feed_title', 'site_url', 'category_path'],
    'feed_health' => [
        'health_content_id', 'last_checked_at', 'last_result',
        'error_code', 'consecutive_failure_count',
    ],
    'rss_rule' => [
        'rule_id', 'rule_owner', 'rule_enabled', 'match_mode',
        'rule_action', 'rule_flag',
    ],
    'rss_rule_condition' => [
        'condition_id', 'condition_rule_id', 'condition_order',
        'condition_field', 'condition_operator', 'condition_value',
    ],
];

$requiredIndexes = [
    'user_info' => ['PRIMARY', 'idx_user_identity_flag_id'],
    'user_conf' => ['PRIMARY', 'uq_user_conf_user_id'],
    'content' => ['PRIMARY', 'idx_content_owner_location_flag_id'],
    'content_stock' => ['PRIMARY', 'idx_stock_owner_flag_id', 'idx_stock_owner_flag_archived_id'],
    'feed_item_state' => ['PRIMARY', 'uq_feed_item_state_owner_content_identity', 'idx_feed_item_state_owner_content_seen'],
    'memo' => ['PRIMARY', 'idx_memo_owner_flag_id'],
    'task' => ['PRIMARY', 'idx_task_owner_widget_flag_order', 'idx_task_owner_due'],
    'calendar_event' => ['PRIMARY', 'idx_calendar_event_owner_range'],
    'calendar_event_exception' => ['PRIMARY', 'uq_cal_exception_owner_event_original', 'idx_cal_exception_owner_effective'],
    'dashboard_widget' => ['PRIMARY', 'uq_dashboard_widget_owner_type_reference', 'idx_dashboard_widget_owner_location_order'],
    'notification' => ['PRIMARY', 'uq_notification_owner_source', 'idx_notification_owner_visible_due'],
    'remember_token' => ['PRIMARY', 'uq_remember_token_selector', 'idx_remember_token_user_expiry'],
    'user_file' => ['PRIMARY', 'uq_user_file_stored_name', 'idx_user_file_owner_flag_id'],
    'remote_connection' => ['PRIMARY', 'idx_remote_connection_owner_flag_id', 'idx_remote_connection_owner_enabled_flag'],
    'auth_totp' => ['PRIMARY', 'idx_auth_totp_enabled_user'],
    'auth_recovery_code' => ['PRIMARY', 'uq_auth_recovery_user_hash', 'idx_auth_recovery_user_used_id'],
    'auth_session' => ['PRIMARY', 'uq_auth_session_token_hash', 'idx_auth_session_user_active'],
    'auth_audit_log' => ['PRIMARY', 'idx_auth_audit_user_created', 'idx_auth_audit_event_created'],
    'mail_account' => ['PRIMARY', 'idx_mail_account_owner_flag_id', 'idx_mail_account_owner_enabled_flag'],
    'link_item' => ['PRIMARY', 'idx_link_item_owner_widget_order'],
    'stock_tag' => ['PRIMARY', 'uq_stock_tag_owner_name', 'idx_stock_tag_owner_flag_name'],
    'stock_tag_map' => ['PRIMARY', 'uq_stock_tag_map_owner_stock_tag', 'idx_stock_tag_map_owner_stock'],
    'feed_keyword' => ['PRIMARY', 'uq_feed_keyword_owner_value', 'idx_feed_keyword_owner_flag_value'],
    'feed_metadata' => ['PRIMARY'],
    'feed_health' => ['PRIMARY'],
    'rss_rule' => ['PRIMARY', 'idx_rss_rule_owner_active', 'idx_rss_rule_scope'],
    'rss_rule_condition' => ['PRIMARY', 'idx_rss_rule_condition_rule'],
];

$errors = [];
$warnings = [];

try {
    $pdo = conn_db('mysql');
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database === '') {
        throw new RuntimeException('No database is selected.');
    }

    $tableMeta = $pdo->prepare(
        'SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
    );
    $columnQuery = $pdo->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
    );
    $indexQuery = $pdo->prepare(
        'SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
    );

    $presentTables = 0;
    $requiredColumnCount = 0;
    $presentColumnCount = 0;
    $requiredIndexCount = 0;
    $presentIndexCount = 0;

    foreach ($requiredTables as $logicalTable) {
        $physicalTable = (string) DB_TABLE_PREFIX . $logicalTable;
        $tableMeta->execute([':table' => $physicalTable]);
        $meta = $tableMeta->fetch();

        if (!is_array($meta)) {
            $errors[] = "Missing table: {$physicalTable}";
            continue;
        }

        $presentTables++;
        if (strcasecmp((string) ($meta['ENGINE'] ?? ''), 'InnoDB') !== 0) {
            $errors[] = "Unexpected engine: {$physicalTable}=" . (string) ($meta['ENGINE'] ?? '(unknown)');
        }
        if (strcasecmp((string) ($meta['TABLE_COLLATION'] ?? ''), 'utf8mb4_unicode_ci') !== 0) {
            $errors[] = "Unexpected collation: {$physicalTable}=" . (string) ($meta['TABLE_COLLATION'] ?? '(unknown)');
        }

        $columnQuery->execute([':table' => $physicalTable]);
        $actualColumns = array_map(
            static fn(array $row): string => (string) $row['COLUMN_NAME'],
            $columnQuery->fetchAll()
        );
        foreach ($requiredColumns[$logicalTable] ?? [] as $column) {
            $requiredColumnCount++;
            if (in_array($column, $actualColumns, true)) {
                $presentColumnCount++;
            } else {
                $errors[] = "Missing column: {$physicalTable}.{$column}";
            }
        }

        $indexQuery->execute([':table' => $physicalTable]);
        $actualIndexes = array_map(
            static fn(array $row): string => (string) $row['INDEX_NAME'],
            $indexQuery->fetchAll()
        );
        foreach ($requiredIndexes[$logicalTable] ?? [] as $index) {
            $requiredIndexCount++;
            if (in_array($index, $actualIndexes, true)) {
                $presentIndexCount++;
            } else {
                $errors[] = "Missing index: {$physicalTable}.{$index}";
            }
        }
    }

    $pattern = strtr((string) DB_TABLE_PREFIX, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $extraQuery = $pdo->prepare(
        "SELECT TABLE_NAME FROM information_schema.TABLES "
        . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE :pattern ESCAPE '!' ORDER BY TABLE_NAME"
    );
    $extraQuery->execute([':pattern' => $pattern]);
    $actualPrefixedTables = array_map(
        static fn(array $row): string => (string) $row['TABLE_NAME'],
        $extraQuery->fetchAll()
    );
    $expectedPhysicalTables = array_map(
        static fn(string $name): string => (string) DB_TABLE_PREFIX . $name,
        $requiredTables
    );
    $extraTables = array_values(array_diff($actualPrefixedTables, $expectedPhysicalTables));
    foreach ($extraTables as $extraTable) {
        $warnings[] = "Extra prefixed table not required by Current schema: {$extraTable}";
    }

    echo "RSS Reader current schema verification\n";
    echo 'Build: ' . APP_VERSION_LABEL . "\n";
    echo "Database: {$database}\n";
    echo 'Table prefix: ' . DB_TABLE_PREFIX . "\n";
    echo "Required tables: {$presentTables}/" . count($requiredTables) . "\n";
    echo "Required columns: {$presentColumnCount}/{$requiredColumnCount}\n";
    echo "Required indexes: {$presentIndexCount}/{$requiredIndexCount}\n";

    foreach ($warnings as $warning) {
        echo "- WARN: {$warning}\n";
    }

    if ($errors !== []) {
        echo "CURRENT SCHEMA: FAIL\n";
        foreach ($errors as $error) {
            echo "- {$error}\n";
        }
        exit(4);
    }

    echo "CURRENT SCHEMA: PASS\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'CURRENT SCHEMA ERROR: ' . $exception->getMessage() . "\n");
    exit(1);
}
