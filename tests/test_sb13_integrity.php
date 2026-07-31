<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/common/common_conf.php';
require_once dirname(__DIR__) . '/app/common/common_db.php';
require_once dirname(__DIR__) . '/app/db_integrity.php';

$tests = 0;
$failed = 0;
function t(bool $ok, string $message): void
{
    global $tests, $failed;
    $tests++;
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$ok) {
        $failed++;
    }
}

$userInfo = db_table_name('user_info');
$userConf = db_table_name('user_conf');
$content = db_table_name('content');
$contentStock = db_table_name('content_stock');

$clean = [
    'present_tables' => sb13_tables(),
    'duplicate_user_conf_user_ids' => 0,
    'negative_relationship_ids' => 0,
    'duplicate_identity_groups' => 0,
    'orphan_content_rows' => 0,
    'orphan_stock_rows' => 0,
    'orphan_conf_rows' => 0,
    'users_missing_conf' => 0,
    'row_counts' => [
        $userInfo => 22,
        $userConf => 22,
        $content => 41,
        $contentStock => 63,
    ],
    'active_counts' => ['users' => 22, 'content' => 36, 'stock' => 63],
    'content_location_counts' => ['0' => 26, '1' => 2, '2' => 11, '3' => 2],
    'content_owner_counts' => ['0' => 9, '1' => 30, '2' => 2],
    'stock_owner_counts' => ['1' => 63],
    'table_collations' => array_fill_keys(sb13_tables(), SB13_TARGET_COLLATION),
    'columns' => [
        $userConf => ['user_id' => ['type' => 'int unsigned']],
        $content => ['content_owner' => ['type' => 'int unsigned']],
        $contentStock => ['stock_owner' => ['type' => 'int unsigned']],
    ],
    'indexes' => sb13_required_indexes(),
];

$class = sb13_classify_audit($clean);
t($class['blocking'] === [], 'clean audit has no blocking gates');
t($class['warnings'] === [], 'clean audit has no preservation warnings');

$dupConf = $clean;
$dupConf['duplicate_user_conf_user_ids'] = 2;
$class = sb13_classify_audit($dupConf);
t(count($class['blocking']) === 1 && str_contains($class['blocking'][0], 'unique 1:1'), 'duplicate user_conf blocks migration');

$negative = $clean;
$negative['negative_relationship_ids'] = 1;
$class = sb13_classify_audit($negative);
t(count($class['blocking']) === 1 && str_contains($class['blocking'][0], 'UNSIGNED'), 'negative relationship ID blocks unsigned conversion');

$preserve = $clean;
$preserve['duplicate_identity_groups'] = 1;
$preserve['orphan_content_rows'] = 9;
$preserve['orphan_stock_rows'] = 2;
$preserve['orphan_conf_rows'] = 1;
$preserve['users_missing_conf'] = 1;
$class = sb13_classify_audit($preserve);
t($class['blocking'] === [], 'duplicate identity and orphan rows are report-only, not destructive blockers');
t(count($class['warnings']) === 5, 'all preservation gates are reported');

$missing = $clean;
$missing['present_tables'] = [$userInfo, $content];
$class = sb13_classify_audit($missing);
t(count($class['blocking']) === 2, 'missing required tables block migration');

$issues = sb13_schema_issues($clean);
t($issues === [], 'target schema snapshot passes verification');

$badCollation = $clean;
$badCollation['table_collations'][$content] = 'utf8_general_ci';
t(count(sb13_schema_issues($badCollation)) === 1, 'non-target table collation fails verification');

$badUnsigned = $clean;
$badUnsigned['columns'][$content]['content_owner']['type'] = 'int';
t(count(sb13_schema_issues($badUnsigned)) === 1, 'signed owner column fails verification');

$badIndex = $clean;
unset($badIndex['indexes'][$content]['idx_content_owner_location_flag_id']);
t(count(sb13_schema_issues($badIndex)) === 1, 'missing query index fails verification');

$before = $clean;
$after = $clean;
t(sb13_row_count_issues($before, $after) === [], 'unchanged row counts pass migration verification');
$after['row_counts'][$content] = 40;
t(count(sb13_row_count_issues($before, $after)) === 1, 'row loss is detected');
$after = $clean;
$after['content_location_counts']['0'] = 25;
t(count(sb13_data_consistency_issues($clean, $after)) === 1, 'distribution change is detected even when total row counts match');

$statements = sb13_migration_statements();
$joined = implode("\n", $statements);
t(count($statements) === 11, 'migration plan has expected number of DDL statements');
t(!preg_match('/\b(?:DELETE|TRUNCATE|DROP)\b/i', $joined), 'migration plan contains no destructive cleanup statement');
t(!preg_match('/FOREIGN\s+KEY/i', $joined), 'migration plan does not add foreign keys');
t(str_contains($joined, 'utf8mb4_unicode_ci'), 'migration plan converts to utf8mb4 target collation');
t(str_contains($joined, 'uq_user_conf_user_id'), 'migration plan enforces user_conf 1:1 only after preflight gate');
t(str_contains($joined, 'user_email`(64)'), 'identity lookup uses a safe non-unique TEXT prefix index');

if ($failed > 0) {
    fwrite(STDERR, "{$failed}/{$tests} SB-13 integrity tests failed.\n");
    exit(1);
}

echo "All {$tests} SB-13 integrity tests passed.\n";
