<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

function v11i_usage(): void
{
    echo "Usage:\n";
    echo "  php tools/db_v11i.php verify\n";
    echo "  php tools/db_v11i.php apply --backup-confirmed\n\n";
    echo "verify is read-only. apply creates the prefixed calendar_event table.\n";
}

function v11i_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $stmt->execute([':table_name' => $table]);
    return (int) $stmt->fetchColumn() === 1;
}

/** @return list<string> */
function v11i_schema_issues(PDO $pdo): array
{
    $table = db_table_name('calendar_event');
    if (!v11i_table_exists($pdo, $table)) {
        return ["Missing table: {$table}"];
    }
    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM ' . db_table_identifier('calendar_event'))->fetchAll() as $row) {
        $columns[(string) ($row['Field'] ?? '')] = strtolower((string) ($row['Type'] ?? ''));
    }
    $required = [
        'calendar_event_id'=>'bigint','calendar_event_date'=>'datetime','calendar_event_updated_at'=>'datetime',
        'calendar_event_flag'=>'tinyint','calendar_event_owner'=>'int','calendar_event_title'=>'varchar(256)',
        'calendar_event_start_date'=>'date','calendar_event_end_date'=>'date','calendar_event_note'=>'text',
    ];
    $issues=[];
    foreach ($required as $name=>$typePart) {
        if (!isset($columns[$name])) $issues[]="Missing column: {$table}.{$name}";
        elseif (!str_contains($columns[$name],$typePart)) $issues[]="Unexpected column type: {$table}.{$name} ({$columns[$name]})";
    }
    $indexes=[];
    foreach ($pdo->query('SHOW INDEX FROM ' . db_table_identifier('calendar_event'))->fetchAll() as $row) {
        $name=(string)($row['Key_name']??'');
        $indexes[$name][(int)($row['Seq_in_index']??0)]=(string)($row['Column_name']??'');
    }
    foreach ($indexes as $name=>$parts) { ksort($parts); $indexes[$name]=array_values($parts); }
    $expected=[
        'PRIMARY'=>['calendar_event_id'],
        'idx_calendar_event_owner_range'=>['calendar_event_owner','calendar_event_flag','calendar_event_start_date','calendar_event_end_date','calendar_event_id'],
    ];
    foreach ($expected as $name=>$columnsExpected) {
        if (($indexes[$name]??null)!==$columnsExpected) $issues[]="Missing or unexpected index: {$table}.{$name}";
    }
    return $issues;
}

/** @return list<string> */
function v11i_data_issues(PDO $pdo): array
{
    if (!v11i_table_exists($pdo, db_table_name('calendar_event'))) return [];
    $stmt=$pdo->query(
        'SELECT calendar_event_id FROM ' . db_table_identifier('calendar_event') . ' '
        . 'WHERE calendar_event_flag = 0 AND calendar_event_end_date < calendar_event_start_date '
        . 'ORDER BY calendar_event_id ASC LIMIT 20'
    );
    $bad=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    return $bad===[]?[]:['Calendar event with end date before start date: '.implode(', ',$bad)];
}

function v11i_create_table(PDO $pdo): void
{
    if (!v11i_table_exists($pdo, db_table_name('dashboard_widget')) || !v11i_table_exists($pdo, db_table_name('task'))) {
        throw new RuntimeException('dashboard_widget or task table was not found. Apply V1.1-H first.');
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ' . db_table_identifier('calendar_event') . ' ('
        . '`calendar_event_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
        . '`calendar_event_date` DATETIME NOT NULL,'
        . '`calendar_event_updated_at` DATETIME NOT NULL,'
        . '`calendar_event_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0,'
        . '`calendar_event_owner` INT UNSIGNED NOT NULL,'
        . '`calendar_event_title` VARCHAR(256) NOT NULL,'
        . '`calendar_event_start_date` DATE NOT NULL,'
        . '`calendar_event_end_date` DATE NOT NULL,'
        . '`calendar_event_note` TEXT NOT NULL,'
        . 'PRIMARY KEY (`calendar_event_id`),'
        . 'KEY `idx_calendar_event_owner_range` (`calendar_event_owner`, `calendar_event_flag`, `calendar_event_start_date`, `calendar_event_end_date`, `calendar_event_id`)'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Calendar予定保管\''
    );
}

$command=$argv[1]??'help';
if (!in_array($command,['verify','apply'],true)) { v11i_usage(); exit(in_array($command,['help','--help','-h'],true)?0:2); }
if ($command==='apply' && !in_array('--backup-confirmed',$argv,true)) {
    fwrite(STDERR,"REFUSED: apply requires --backup-confirmed after a verified DB backup.\n"); exit(5);
}
try {
    $pdo=conn_db('mysql');
    if ($command==='apply') v11i_create_table($pdo);
    $issues=array_merge(v11i_schema_issues($pdo),v11i_data_issues($pdo));
    echo 'V1.1-I table: '.db_table_name('calendar_event')."\n";
    echo 'Schema/data verification: '.($issues===[]?'PASS':'FAIL')."\n";
    foreach ($issues as $issue) echo "- {$issue}\n";
    exit($issues===[]?0:4);
} catch (Throwable $exception) {
    fwrite(STDERR,'V1.1-I DB ERROR: '.$exception->getMessage()."\n"); exit(1);
}
