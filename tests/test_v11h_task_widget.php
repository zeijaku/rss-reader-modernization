<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');
putenv('DB_TABLE_PREFIX=ig_');

require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/common/common_db.php';
require_once $root . '/app/validation.php';
require_once $root . '/app/dashboard_widget.php';
require_once $root . '/app/api.php';

$checks = 0;
$failures = [];
function v11h_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) {
        $failures[] = $message;
    }
}

v11h_check(dashboard_widget_task_defaults() === ['schema'=>1,'title'=>'Task'], 'Task Widget has a small explicit default config');
v11h_check(dashboard_widget_validate_task_widget_title('作業') === '作業', 'Task Widget title accepts safe UTF-8 text');
v11h_check(dashboard_widget_validate_task_widget_title('') === null, 'Task Widget title cannot be empty');
v11h_check(dashboard_widget_validate_task_widget_title(str_repeat('a', 33)) === null, 'Task Widget title is limited to 32 characters');
v11h_check(dashboard_widget_validate_task_title('<script>task</script>') === '<script>task</script>', 'Task title remains data for escaped output');
v11h_check(dashboard_widget_validate_task_title(str_repeat('a', 128)) !== null, 'Task title accepts 128 characters');
v11h_check(dashboard_widget_validate_task_title(str_repeat('a', 129)) === null, 'Task title rejects more than 128 characters');
v11h_check(dashboard_widget_validate_task_due_date('') === '', 'Task due date may be omitted');
v11h_check(dashboard_widget_validate_task_due_date(null) === '', 'Stored null due date normalizes to empty text');
v11h_check(dashboard_widget_validate_task_due_date('2026-08-31') === '2026-08-31', 'Task due date accepts a real ISO date');
v11h_check(dashboard_widget_validate_task_due_date('2026-02-30') === null, 'Task due date rejects an impossible date');
v11h_check(dashboard_widget_validate_task_due_date('2026/08/31') === null, 'Task due date rejects a non-ISO format');
v11h_check(dashboard_widget_validate_task_priority('low') === 'low', 'low priority is accepted');
v11h_check(dashboard_widget_validate_task_priority('normal') === 'normal', 'normal priority is accepted');
v11h_check(dashboard_widget_validate_task_priority('high') === 'high', 'high priority is accepted');
v11h_check(dashboard_widget_validate_task_priority('urgent') === null, 'unknown priority is rejected');
v11h_check(dashboard_widget_task_priority_label('high') === '高', 'priority has a bounded display label');

$config = dashboard_widget_task_config_from_input(['task_widget_title'=>'今日の作業']);
v11h_check($config === ['schema'=>1,'title'=>'今日の作業'], 'Task Widget input becomes a strict config');
v11h_check(dashboard_widget_task_config_from_storage('{"schema":99,"title":"保守"}') === ['schema'=>1,'title'=>'保守'], 'stored Task config is normalized');
v11h_check(dashboard_widget_task_config_from_storage('{broken') === dashboard_widget_task_defaults(), 'malformed Task config falls back safely');

$row = dashboard_widget_normalize_row([
    'widget_id'=>'8','widget_owner'=>'7','widget_location'=>'1','widget_type'=>'task','widget_reference_id'=>null,
    'widget_sort_order'=>'20','widget_width'=>'2','widget_style'=>'primary','widget_config'=>'{"schema":1,"title":"Task List"}',
]);
v11h_check(is_array($row) && $row['widget_reference_id'] === null, 'Task Widget does not require a reference id');
v11h_check(($row['widget_config_data']['title'] ?? '') === 'Task List', 'Task Widget row exposes normalized config');
$taskRow = dashboard_widget_normalize_task_row([
    'task_id'=>'12','task_owner'=>'7','task_widget_id'=>'8','task_sort_order'=>'10','task_title'=>'確認',
    'task_due_date'=>'2026-08-31','task_priority'=>'high','task_completed'=>'0',
]);
v11h_check(is_array($taskRow) && $taskRow['task_completed'] === false, 'Task DB row is normalized to typed values');
v11h_check(($taskRow['task_priority_label'] ?? '') === '高', 'normalized Task row contains the safe priority label');

final class V11hTaskPDO extends PDO
{
    public array $widgets = [];
    public array $tasks = [];
    public int $widgetSeq = 0;
    public int $taskSeq = 0;
    public int $lastId = 0;
    public bool $failTaskInsert = false;
    private bool $transaction = false;
    private ?array $snapshot = null;
    public function __construct() {}
    public function getAttribute(int $attribute): mixed { return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : null; }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new V11hTaskStatement($this, $query); }
    public function beginTransaction(): bool { $this->transaction=true; $this->snapshot=[$this->widgets,$this->tasks,$this->widgetSeq,$this->taskSeq,$this->lastId]; return true; }
    public function commit(): bool { $this->transaction=false; $this->snapshot=null; return true; }
    public function rollBack(): bool { if ($this->snapshot !== null) { [$this->widgets,$this->tasks,$this->widgetSeq,$this->taskSeq,$this->lastId]=$this->snapshot; } $this->transaction=false; $this->snapshot=null; return true; }
    public function inTransaction(): bool { return $this->transaction; }
    public function lastInsertId(?string $name = null): string|false { return (string)$this->lastId; }
}

final class V11hTaskStatement extends PDOStatement
{
    private array $rows=[];
    private mixed $column=false;
    private int $affected=0;
    public function __construct(private V11hTaskPDO $pdo, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        $params ??=[]; $this->rows=[]; $this->column=false; $this->affected=0;
        if (str_starts_with($this->sql, 'SELECT widget_sort_order FROM `ig_dashboard_widget`')) {
            $orders=[]; foreach($this->pdo->widgets as $w) if($w['widget_owner']===(int)$params[':owner']&&$w['widget_location']===(int)$params[':location']&&$w['widget_flag']===0)$orders[]=$w['widget_sort_order'];
            $this->column=$orders===[]?false:max($orders); return true;
        }
        if (str_starts_with($this->sql, 'INSERT INTO `ig_dashboard_widget`') && str_contains($this->sql, "'task'")) {
            $id=++$this->pdo->widgetSeq; $this->pdo->lastId=$id;
            $this->pdo->widgets[$id]=[
                'widget_id'=>$id,'widget_owner'=>(int)$params[':owner'],'widget_location'=>(int)$params[':location'],'widget_type'=>'task',
                'widget_reference_id'=>null,'widget_sort_order'=>(int)$params[':sort_order'],'widget_width'=>(int)$params[':width'],
                'widget_style'=>(string)$params[':style'],'widget_config'=>(string)$params[':config'],'widget_flag'=>0,
                'widget_created_at'=>(string)$params[':created_at'],'widget_updated_at'=>(string)$params[':updated_at'],
            ]; $this->affected=1; return true;
        }
        if (str_starts_with($this->sql, 'SELECT * FROM `ig_dashboard_widget`')) {
            $w=$this->pdo->widgets[(int)($params[':widget_id']??0)]??null;
            if(is_array($w)&&$w['widget_owner']===(int)($params[':owner']??0)&&$w['widget_type']===(string)($params[':widget_type']??'')&&$w['widget_flag']===0)$this->rows=[$w];
            return true;
        }
        if (str_starts_with($this->sql, 'UPDATE `ig_dashboard_widget` SET widget_width')) {
            $id=(int)$params[':widget_id']; $w=$this->pdo->widgets[$id]??null;
            if(is_array($w)&&$w['widget_owner']===(int)$params[':owner']&&$w['widget_type']==='task'&&$w['widget_flag']===0){
                $this->pdo->widgets[$id]['widget_width']=(int)$params[':width']; $this->pdo->widgets[$id]['widget_style']=(string)$params[':style'];
                $this->pdo->widgets[$id]['widget_config']=(string)$params[':config']; $this->pdo->widgets[$id]['widget_updated_at']=(string)$params[':updated_at']; $this->affected=1;
            } return true;
        }
        if (str_starts_with($this->sql, 'SELECT COUNT(*) FROM `ig_task`')) {
            $count=0; foreach($this->pdo->tasks as $t) if($t['task_owner']===(int)$params[':owner']&&$t['task_widget_id']===(int)$params[':widget_id']&&$t['task_flag']===0)$count++;
            $this->column=$count; return true;
        }
        if (str_starts_with($this->sql, 'SELECT task_sort_order FROM `ig_task`')) {
            $orders=[]; foreach($this->pdo->tasks as $t) if($t['task_owner']===(int)$params[':owner']&&$t['task_widget_id']===(int)$params[':widget_id']&&$t['task_flag']===0)$orders[]=$t['task_sort_order'];
            $this->column=$orders===[]?false:max($orders); return true;
        }
        if (str_starts_with($this->sql, 'INSERT INTO `ig_task`')) {
            if($this->pdo->failTaskInsert) throw new PDOException('forced task insert failure');
            $id=++$this->pdo->taskSeq; $this->pdo->lastId=$id;
            $this->pdo->tasks[$id]=[
                'task_id'=>$id,'task_date'=>(string)$params[':task_date'],'task_updated_at'=>(string)$params[':task_updated_at'],'task_flag'=>0,
                'task_owner'=>(int)$params[':task_owner'],'task_widget_id'=>(int)$params[':task_widget_id'],'task_title'=>(string)$params[':task_title'],
                'task_due_date'=>$params[':task_due_date'],'task_priority'=>(string)$params[':task_priority'],'task_completed'=>0,'task_completed_at'=>null,
                'task_sort_order'=>(int)$params[':task_sort_order'],
            ]; $this->affected=1; return true;
        }
        if (str_starts_with($this->sql, 'SELECT * FROM `ig_task`')) {
            $t=$this->pdo->tasks[(int)($params[':task_id']??0)]??null;
            if(is_array($t)&&$t['task_owner']===(int)($params[':owner']??0)&&$t['task_flag']===0)$this->rows=[$t];
            return true;
        }
        if (str_starts_with($this->sql, 'UPDATE `ig_task` SET task_title')) {
            $id=(int)$params[':task_id']; $t=$this->pdo->tasks[$id]??null;
            if(is_array($t)&&$t['task_owner']===(int)$params[':owner']&&$t['task_flag']===0){
                $this->pdo->tasks[$id]['task_title']=(string)$params[':task_title']; $this->pdo->tasks[$id]['task_due_date']=$params[':task_due_date'];
                $this->pdo->tasks[$id]['task_priority']=(string)$params[':task_priority']; $this->pdo->tasks[$id]['task_updated_at']=(string)$params[':updated_at']; $this->affected=1;
            } return true;
        }
        if (str_starts_with($this->sql, 'UPDATE `ig_task` SET task_completed')) {
            $id=(int)$params[':task_id']; $t=$this->pdo->tasks[$id]??null;
            if(is_array($t)&&$t['task_owner']===(int)$params[':owner']&&$t['task_flag']===0){
                $this->pdo->tasks[$id]['task_completed']=(int)$params[':completed']; $this->pdo->tasks[$id]['task_completed_at']=$params[':completed_at'];
                $this->pdo->tasks[$id]['task_updated_at']=(string)$params[':updated_at']; $this->affected=1;
            } return true;
        }
        if (str_starts_with($this->sql, 'UPDATE `ig_task` SET task_flag = 1') && str_contains($this->sql, 'task_id = :task_id')) {
            $id=(int)$params[':task_id']; $t=$this->pdo->tasks[$id]??null;
            if(is_array($t)&&$t['task_owner']===(int)$params[':owner']&&$t['task_flag']===0){$this->pdo->tasks[$id]['task_flag']=1;$this->affected=1;} return true;
        }
        if (str_starts_with($this->sql, 'UPDATE `ig_task` SET task_flag = 1') && str_contains($this->sql, 'task_widget_id = :widget_id')) {
            foreach($this->pdo->tasks as $id=>$t) if($t['task_owner']===(int)$params[':owner']&&$t['task_widget_id']===(int)$params[':widget_id']&&$t['task_flag']===0){$this->pdo->tasks[$id]['task_flag']=1;$this->affected++;}
            return true;
        }
        if (str_starts_with($this->sql, 'UPDATE `ig_dashboard_widget` SET widget_flag = 1')) {
            $id=(int)$params[':widget_id']; $w=$this->pdo->widgets[$id]??null;
            if(is_array($w)&&$w['widget_owner']===(int)$params[':owner']&&$w['widget_type']==='task'&&$w['widget_flag']===0){$this->pdo->widgets[$id]['widget_flag']=1;$this->affected=1;} return true;
        }
        throw new RuntimeException('Unexpected SQL in V1.1-H fixture: '.$this->sql);
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed{return array_shift($this->rows)??false;}
    public function fetchColumn(int $column=0):mixed{return $this->column;}
    public function rowCount():int{return $this->affected;}
}

$pdo=new V11hTaskPDO(); set_db_connection_for_testing($pdo);
$widgetCreate=api_dispatch('widget.task.create',7,[
    'widget_owner'=>'999','widget_location'=>'1','widget_style'=>'primary','widget_width'=>'2','task_widget_title'=>'今日の作業',
]);
v11h_check($widgetCreate['status']===201&&($widgetCreate['body']['ok']??false)===true,'authenticated user can create a Task Widget');
$widgetId=(int)($widgetCreate['body']['data']['widget_id']??0);
v11h_check($widgetId===1,'Task Widget ID is returned');
v11h_check($pdo->widgets[$widgetId]['widget_owner']===7,'Task Widget owner comes from authenticated session');
v11h_check($pdo->widgets[$widgetId]['widget_reference_id']===null,'Task Widget uses no domain reference id');
v11h_check(json_decode($pdo->widgets[$widgetId]['widget_config'],true)['title']==='今日の作業','Task Widget title is stored in bounded config');

$itemCreate=api_dispatch('task.item.create',7,[
    'widget_id'=>(string)$widgetId,'task_title'=>'請求書を確認','task_due_date'=>'2026-08-31','task_priority'=>'high','task_owner'=>'999',
]);
v11h_check($itemCreate['status']===201&&($itemCreate['body']['ok']??false)===true,'Task can be added to an owned Task Widget');
$taskId=(int)($itemCreate['body']['data']['task_id']??0);
v11h_check($pdo->tasks[$taskId]['task_owner']===7,'Task owner ignores a client owner field');
v11h_check($pdo->tasks[$taskId]['task_widget_id']===$widgetId,'Task is linked to the selected Widget');
v11h_check($pdo->tasks[$taskId]['task_due_date']==='2026-08-31'&&$pdo->tasks[$taskId]['task_priority']==='high','Task due date and priority are stored');
v11h_check($pdo->tasks[$taskId]['task_sort_order']===10,'first Task receives initial sort order');
$item2=api_dispatch('task.item.create',7,['widget_id'=>(string)$widgetId,'task_title'=>'期限なし','task_due_date'=>'','task_priority'=>'normal']);
$taskId2=(int)($item2['body']['data']['task_id']??0);
v11h_check($pdo->tasks[$taskId2]['task_due_date']===null,'empty due date is stored as SQL null');
v11h_check($pdo->tasks[$taskId2]['task_sort_order']===20,'second Task appends after the current order');

$otherUpdate=api_dispatch('task.item.update',8,['task_id'=>(string)$taskId,'task_title'=>'改変','task_due_date'=>'','task_priority'=>'low']);
v11h_check($otherUpdate['status']===404,'another user cannot update a Task');
$update=api_dispatch('task.item.update',7,['task_id'=>(string)$taskId,'task_title'=>'請求書を確認済みにする','task_due_date'=>'2026-09-01','task_priority'=>'normal']);
v11h_check($update['status']===200&&$pdo->tasks[$taskId]['task_title']==='請求書を確認済みにする','Task title can be updated');
v11h_check($pdo->tasks[$taskId]['task_due_date']==='2026-09-01'&&$pdo->tasks[$taskId]['task_priority']==='normal','Task due date and priority can be updated');

$toggle=api_dispatch('task.item.toggle',7,['task_id'=>(string)$taskId,'task_completed'=>'1']);
v11h_check($toggle['status']===200&&$pdo->tasks[$taskId]['task_completed']===1,'Task can be completed');
v11h_check(is_string($pdo->tasks[$taskId]['task_completed_at'])&&$pdo->tasks[$taskId]['task_completed_at']!=='','completion time is recorded');
$otherToggle=api_dispatch('task.item.toggle',8,['task_id'=>(string)$taskId,'task_completed'=>'0']);
v11h_check($otherToggle['status']===404&&$pdo->tasks[$taskId]['task_completed']===1,'another user cannot change completion state');
$uncomplete=api_dispatch('task.item.toggle',7,['task_id'=>(string)$taskId,'task_completed'=>'0']);
v11h_check($uncomplete['status']===200&&$pdo->tasks[$taskId]['task_completed_at']===null,'Task can return to incomplete state');

$widgetUpdate=api_dispatch('widget.task.update',7,['widget_id'=>(string)$widgetId,'widget_style'=>'warning','widget_width'=>'3','task_widget_title'=>'保守作業']);
v11h_check($widgetUpdate['status']===200&&$pdo->widgets[$widgetId]['widget_width']===3,'Task Widget width can be updated');
v11h_check(json_decode($pdo->widgets[$widgetId]['widget_config'],true)['title']==='保守作業','Task Widget title can be updated');
$otherWidgetDelete=api_dispatch('widget.task.delete',8,['widget_id'=>(string)$widgetId]);
v11h_check($otherWidgetDelete['status']===404,'another user cannot delete the Task Widget');

$deleteItem=api_dispatch('task.item.delete',7,['task_id'=>(string)$taskId2]);
v11h_check($deleteItem['status']===200&&$pdo->tasks[$taskId2]['task_flag']===1,'Task delete is an owner-scoped logical delete');
$deleteWidget=api_dispatch('widget.task.delete',7,['widget_id'=>(string)$widgetId]);
v11h_check($deleteWidget['status']===200&&$pdo->widgets[$widgetId]['widget_flag']===1,'Task Widget delete is logical');
v11h_check($pdo->tasks[$taskId]['task_flag']===1,'Task Widget delete also logically deletes active Tasks');

$invalidDate=api_dispatch('task.item.create',7,['widget_id'=>'1','task_title'=>'bad','task_due_date'=>'2026-02-30','task_priority'=>'normal']);
v11h_check($invalidDate['status']===422,'invalid Task date is rejected before database mutation');
$invalidPriority=api_dispatch('task.item.create',7,['widget_id'=>'1','task_title'=>'bad','task_due_date'=>'','task_priority'=>'urgent']);
v11h_check($invalidPriority['status']===422,'invalid priority is rejected before database mutation');
$unauth=api_dispatch('widget.task.create',0,[]);
v11h_check($unauth['status']===401,'Task API requires authentication');

if ($failures !== []) { echo count($failures)."/{$checks} V1.1-H checks failed.\n"; exit(1); }
echo "All {$checks} V1.1-H Task Widget checks passed.\n";
