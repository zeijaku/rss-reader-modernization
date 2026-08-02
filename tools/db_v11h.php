<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

function v11h_usage(): void
{
    echo "Usage:\n";
    echo "  php tools/db_v11h.php verify\n";
    echo "  php tools/db_v11h.php apply --backup-confirmed\n\n";
    echo "verify is read-only. apply creates the prefixed task table.\n";
}

function v11h_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $stmt->execute([':table_name' => $table]);
    return (int) $stmt->fetchColumn() === 1;
}

/** @return list<string> */
function v11h_schema_issues(PDO $pdo): array
{
    $table = db_table_name('task');
    if (!v11h_table_exists($pdo, $table)) {
        return ["Missing table: {$table}"];
    }
    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM ' . db_table_identifier('task'))->fetchAll() as $row) {
        $columns[(string) ($row['Field'] ?? '')] = strtolower((string) ($row['Type'] ?? ''));
    }
    $required = [
        'task_id'=>'bigint','task_date'=>'datetime','task_updated_at'=>'datetime','task_flag'=>'tinyint',
        'task_owner'=>'int','task_widget_id'=>'bigint','task_title'=>'varchar(256)','task_due_date'=>'date',
        'task_priority'=>'varchar(8)','task_completed'=>'tinyint','task_completed_at'=>'datetime','task_sort_order'=>'int',
    ];
    $issues=[];
    foreach ($required as $name=>$typePart) {
        if (!isset($columns[$name])) $issues[]="Missing column: {$table}.{$name}";
        elseif (!str_contains($columns[$name],$typePart)) $issues[]="Unexpected column type: {$table}.{$name} ({$columns[$name]})";
    }
    $indexes=[];
    foreach ($pdo->query('SHOW INDEX FROM ' . db_table_identifier('task'))->fetchAll() as $row) {
        $name=(string)($row['Key_name']??'');
        $indexes[$name][(int)($row['Seq_in_index']??0)]=(string)($row['Column_name']??'');
    }
    foreach ($indexes as $name=>$parts) { ksort($parts); $indexes[$name]=array_values($parts); }
    $expected=[
        'PRIMARY'=>['task_id'],
        'idx_task_owner_widget_flag_order'=>['task_owner','task_widget_id','task_flag','task_sort_order','task_id'],
        'idx_task_owner_due'=>['task_owner','task_flag','task_completed','task_due_date'],
    ];
    foreach ($expected as $name=>$columnsExpected) {
        if (($indexes[$name]??null)!==$columnsExpected) $issues[]="Missing or unexpected index: {$table}.{$name}";
    }
    return $issues;
}

/** @return list<string> */
function v11h_data_issues(PDO $pdo): array
{
    if (!v11h_table_exists($pdo, db_table_name('task')) || !v11h_table_exists($pdo, db_table_name('dashboard_widget'))) return [];
    $stmt=$pdo->query(
        'SELECT t.task_id FROM ' . db_table_identifier('task') . ' t '
        . 'LEFT JOIN ' . db_table_identifier('dashboard_widget') . ' w '
        . 'ON w.widget_id = t.task_widget_id AND w.widget_owner = t.task_owner '
        . "AND w.widget_type = 'task' AND w.widget_flag = 0 "
        . 'WHERE t.task_flag = 0 AND w.widget_id IS NULL ORDER BY t.task_id ASC LIMIT 20'
    );
    $orphans=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    return $orphans===[]?[]:['Active Task without active Task Widget: '.implode(', ',$orphans)];
}

function v11h_create_table(PDO $pdo): void
{
    if (!v11h_table_exists($pdo, db_table_name('dashboard_widget'))) {
        throw new RuntimeException('dashboard_widget table was not found. Apply V1.1-D first.');
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ' . db_table_identifier('task') . ' ('
        . '`task_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
        . '`task_date` DATETIME NOT NULL,'
        . '`task_updated_at` DATETIME NOT NULL,'
        . '`task_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0,'
        . '`task_owner` INT UNSIGNED NOT NULL,'
        . '`task_widget_id` BIGINT UNSIGNED NOT NULL,'
        . '`task_title` VARCHAR(256) NOT NULL,'
        . '`task_due_date` DATE NULL DEFAULT NULL,'
        . '`task_priority` VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT \'normal\','
        . '`task_completed` TINYINT UNSIGNED NOT NULL DEFAULT 0,'
        . '`task_completed_at` DATETIME NULL DEFAULT NULL,'
        . '`task_sort_order` INT UNSIGNED NOT NULL DEFAULT 0,'
        . 'PRIMARY KEY (`task_id`),'
        . 'KEY `idx_task_owner_widget_flag_order` (`task_owner`, `task_widget_id`, `task_flag`, `task_sort_order`, `task_id`),'
        . 'KEY `idx_task_owner_due` (`task_owner`, `task_flag`, `task_completed`, `task_due_date`)'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Task保管\''
    );
}

$command=$argv[1]??'help';
if (!in_array($command,['verify','apply'],true)) { v11h_usage(); exit(in_array($command,['help','--help','-h'],true)?0:2); }
if ($command==='apply' && !in_array('--backup-confirmed',$argv,true)) {
    fwrite(STDERR,"REFUSED: apply requires --backup-confirmed after a verified DB backup.\n"); exit(5);
}
try {
    $pdo=conn_db('mysql');
    if ($command==='apply') v11h_create_table($pdo);
    $issues=array_merge(v11h_schema_issues($pdo),v11h_data_issues($pdo));
    echo 'V1.1-H table: '.db_table_name('task')."\n";
    echo 'Schema/data verification: '.($issues===[]?'PASS':'FAIL')."\n";
    foreach ($issues as $issue) echo "- {$issue}\n";
    exit($issues===[]?0:4);
} catch (Throwable $exception) {
    fwrite(STDERR,'V1.1-H DB ERROR: '.$exception->getMessage()."\n"); exit(1);
}
