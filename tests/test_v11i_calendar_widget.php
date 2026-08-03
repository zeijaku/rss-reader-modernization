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
require_once $root . '/app/calendar.php';
require_once $root . '/app/api.php';

$checks = 0;
$failures = [];
function v11i_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) {
        $failures[] = $message;
    }
}

v11i_check(calendar_widget_defaults() === ['schema'=>1,'title'=>'Calendar','show_completed_tasks'=>false], 'Calendar has a small explicit default config');
v11i_check(calendar_widget_validate_title('予定') === '予定', 'Calendar title accepts UTF-8 text');
v11i_check(calendar_widget_validate_title('') === null, 'Calendar title cannot be empty');
v11i_check(calendar_widget_validate_title(str_repeat('a', 33)) === null, 'Calendar title is limited to 32 characters');
v11i_check(calendar_validate_year('2026') === 2026, 'Calendar year accepts supported integer text');
v11i_check(calendar_validate_year('1999') === null && calendar_validate_year('2101') === null, 'Calendar year is bounded');
v11i_check(calendar_validate_month('1') === 1 && calendar_validate_month('12') === 12, 'Calendar month accepts 1 through 12');
v11i_check(calendar_validate_month('0') === null && calendar_validate_month('13') === null, 'Calendar month rejects out-of-range values');
v11i_check(calendar_validate_date('2026-02-28') === '2026-02-28', 'Calendar date accepts a real ISO date');
v11i_check(calendar_validate_date('2026-02-30') === null, 'Calendar date rejects an impossible date');
v11i_check(calendar_validate_event_range('2026-08-01','2026-08-03') === ['2026-08-01','2026-08-03'], 'Calendar event accepts a forward date range');
v11i_check(calendar_validate_event_range('2026-08-03','2026-08-01') === null, 'Calendar event rejects end before start');
v11i_check(calendar_validate_event_range('2026-01-01','2027-01-01') !== null, 'Calendar event accepts a 366-day inclusive range');
v11i_check(calendar_validate_event_range('2026-01-01','2027-01-02') === null, 'Calendar event range is bounded to 366 days');
v11i_check(calendar_validate_event_title('<script>予定</script>') === '<script>予定</script>', 'Calendar title remains data for escaped output');
v11i_check(calendar_validate_event_title(str_repeat('a', 129)) === null, 'Calendar event title is limited to 128 characters');
v11i_check(calendar_validate_event_note("a\r\nb") === "a\nb", 'Calendar note normalizes line endings');
v11i_check(calendar_validate_event_note(str_repeat('a', 2001)) === null, 'Calendar note is limited to 2000 characters');
v11i_check(calendar_month_range(2024, 2) === ['start'=>'2024-02-01','end'=>'2024-02-29'], 'Calendar month range handles leap year');
$config = calendar_widget_config_from_input(['calendar_title'=>'仕事','calendar_show_completed_tasks'=>'1']);
v11i_check($config === ['schema'=>1,'title'=>'仕事','show_completed_tasks'=>true], 'Calendar Widget input becomes strict config');
v11i_check(calendar_widget_config_from_storage('{broken') === calendar_widget_defaults(), 'malformed Calendar config falls back safely');

$row = dashboard_widget_normalize_row([
    'widget_id'=>'8','widget_owner'=>'7','widget_location'=>'1','widget_type'=>'calendar','widget_reference_id'=>null,
    'widget_sort_order'=>'20','widget_width'=>'2','widget_style'=>'info','widget_config'=>'{"schema":1,"title":"予定","show_completed_tasks":true}',
]);
v11i_check(is_array($row) && $row['widget_reference_id'] === null, 'Calendar Widget does not require a reference id');
v11i_check(($row['widget_config_data']['show_completed_tasks'] ?? false) === true, 'Calendar Widget row exposes normalized config');
$eventRow = calendar_normalize_event_row([
    'calendar_event_id'=>'4','calendar_event_owner'=>'7','calendar_event_title'=>'会議',
    'calendar_event_start_date'=>'2026-08-01','calendar_event_end_date'=>'2026-08-01','calendar_event_note'=>'資料',
]);
v11i_check(is_array($eventRow) && $eventRow['calendar_event_id'] === 4, 'Calendar event DB row is normalized');

final class V11iCalendarPDO extends PDO
{
    public array $widgets = [];
    public array $events = [];
    public array $tasks = [];
    public int $widgetSeq = 0;
    public int $eventSeq = 0;
    public int $lastId = 0;
    public bool $failEventInsert = false;
    private bool $transaction = false;
    private ?array $snapshot = null;
    public function __construct() {}
    public function getAttribute(int $attribute): mixed { return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : null; }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new V11iCalendarStatement($this, $query); }
    public function beginTransaction(): bool { $this->transaction=true; $this->snapshot=[$this->widgets,$this->events,$this->tasks,$this->widgetSeq,$this->eventSeq,$this->lastId]; return true; }
    public function commit(): bool { $this->transaction=false; $this->snapshot=null; return true; }
    public function rollBack(): bool { if ($this->snapshot !== null) { [$this->widgets,$this->events,$this->tasks,$this->widgetSeq,$this->eventSeq,$this->lastId]=$this->snapshot; } $this->transaction=false; $this->snapshot=null; return true; }
    public function inTransaction(): bool { return $this->transaction; }
    public function lastInsertId(?string $name = null): string|false { return (string)$this->lastId; }
}

final class V11iCalendarStatement extends PDOStatement
{
    private array $rows=[];
    private mixed $column=false;
    private int $affected=0;
    public function __construct(private V11iCalendarPDO $pdo, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        $params ??=[]; $this->rows=[]; $this->column=false; $this->affected=0;
        if (str_starts_with($this->sql, 'SELECT widget_sort_order FROM `ig_dashboard_widget`')) {
            $orders=[]; foreach($this->pdo->widgets as $w) if($w['widget_owner']===(int)$params[':owner']&&$w['widget_location']===(int)$params[':location']&&$w['widget_flag']===0)$orders[]=$w['widget_sort_order'];
            $this->column=$orders===[]?false:max($orders); return true;
        }
        if (str_starts_with($this->sql, 'INSERT INTO `ig_dashboard_widget`') && str_contains($this->sql, "'calendar'")) {
            $id=++$this->pdo->widgetSeq; $this->pdo->lastId=$id;
            $this->pdo->widgets[$id]=[
                'widget_id'=>$id,'widget_owner'=>(int)$params[':owner'],'widget_location'=>(int)$params[':location'],'widget_type'=>'calendar',
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
        if (str_starts_with($this->sql, 'SELECT widget_config FROM `ig_dashboard_widget`')) {
            $w=$this->pdo->widgets[(int)($params[':widget_id']??0)]??null;
            if(is_array($w)&&$w['widget_owner']===(int)($params[':owner']??0)&&$w['widget_type']==='calendar'&&$w['widget_flag']===0)$this->column=$w['widget_config'];
            return true;
        }
        if (str_starts_with($this->sql, 'UPDATE `ig_dashboard_widget` SET widget_width')) {
            $id=(int)$params[':widget_id']; $w=$this->pdo->widgets[$id]??null;
            if(is_array($w)&&$w['widget_owner']===(int)$params[':owner']&&$w['widget_type']==='calendar'&&$w['widget_flag']===0){
                $this->pdo->widgets[$id]['widget_width']=(int)$params[':width']; $this->pdo->widgets[$id]['widget_style']=(string)$params[':style'];
                $this->pdo->widgets[$id]['widget_config']=(string)$params[':config']; $this->affected=1;
            } return true;
        }
        if (str_starts_with($this->sql, 'UPDATE `ig_dashboard_widget` SET widget_flag = 1')) {
            $id=(int)$params[':widget_id']; $w=$this->pdo->widgets[$id]??null;
            if(is_array($w)&&$w['widget_owner']===(int)$params[':owner']&&$w['widget_type']==='calendar'&&$w['widget_flag']===0){$this->pdo->widgets[$id]['widget_flag']=1;$this->affected=1;} return true;
        }
        if (str_starts_with($this->sql, 'SELECT COUNT(*) FROM `ig_calendar_event`')) {
            $count=0; foreach($this->pdo->events as $e) if($e['calendar_event_owner']===(int)$params[':owner']&&$e['calendar_event_flag']===0)$count++;
            $this->column=$count; return true;
        }
        if (str_starts_with($this->sql, 'INSERT INTO `ig_calendar_event`')) {
            if($this->pdo->failEventInsert) throw new PDOException('forced event insert failure');
            $id=++$this->pdo->eventSeq; $this->pdo->lastId=$id;
            $this->pdo->events[$id]=[
                'calendar_event_id'=>$id,'calendar_event_date'=>(string)$params[':created_at'],'calendar_event_updated_at'=>(string)$params[':updated_at'],
                'calendar_event_flag'=>0,'calendar_event_owner'=>(int)$params[':owner'],'calendar_event_title'=>(string)$params[':title'],
                'calendar_event_start_date'=>(string)$params[':start_date'],'calendar_event_end_date'=>(string)$params[':end_date'],
                'calendar_event_note'=>(string)$params[':note'],
            ]; $this->affected=1; return true;
        }
        if (str_starts_with($this->sql, 'SELECT * FROM `ig_calendar_event`')) {
            $e=$this->pdo->events[(int)($params[':event_id']??0)]??null;
            if(is_array($e)&&$e['calendar_event_owner']===(int)($params[':owner']??0)&&$e['calendar_event_flag']===0)$this->rows=[$e];
            return true;
        }
        if (str_starts_with($this->sql, 'UPDATE `ig_calendar_event` SET calendar_event_title')) {
            $id=(int)$params[':event_id']; $e=$this->pdo->events[$id]??null;
            if(is_array($e)&&$e['calendar_event_owner']===(int)$params[':owner']&&$e['calendar_event_flag']===0){
                $this->pdo->events[$id]['calendar_event_title']=(string)$params[':title'];
                $this->pdo->events[$id]['calendar_event_start_date']=(string)$params[':start_date'];
                $this->pdo->events[$id]['calendar_event_end_date']=(string)$params[':end_date'];
                $this->pdo->events[$id]['calendar_event_note']=(string)$params[':note']; $this->affected=1;
            } return true;
        }
        if (str_starts_with($this->sql, 'UPDATE `ig_calendar_event` SET calendar_event_flag = 1')) {
            $id=(int)$params[':event_id']; $e=$this->pdo->events[$id]??null;
            if(is_array($e)&&$e['calendar_event_owner']===(int)$params[':owner']&&$e['calendar_event_flag']===0){$this->pdo->events[$id]['calendar_event_flag']=1;$this->affected=1;} return true;
        }
        if (str_starts_with($this->sql, 'SELECT calendar_event_id, calendar_event_date')) {
            foreach($this->pdo->events as $e) {
                if($e['calendar_event_owner']===(int)$params[':owner']&&$e['calendar_event_flag']===0&&$e['calendar_event_start_date']<=(string)$params[':month_end']&&$e['calendar_event_end_date']>=(string)$params[':month_start'])$this->rows[]=$e;
            }
            usort($this->rows,fn($a,$b)=>[$a['calendar_event_start_date'],$a['calendar_event_id']]<=>[$b['calendar_event_start_date'],$b['calendar_event_id']]); return true;
        }
        if (str_starts_with($this->sql, 'SELECT t.task_id')) {
            $onlyOpen=str_contains($this->sql,'t.task_completed = 0');
            foreach($this->pdo->tasks as $t) {
                $w=$this->pdo->widgets[$t['task_widget_id']]??null;
                if(!is_array($w)||$w['widget_owner']!==$t['task_owner']||$w['widget_type']!=='task'||$w['widget_flag']!==0)continue;
                if($t['task_owner']!==(int)$params[':owner']||$t['task_flag']!==0||$t['task_due_date']<(string)$params[':month_start']||$t['task_due_date']>(string)$params[':month_end'])continue;
                if($onlyOpen&&$t['task_completed']===1)continue;
                $this->rows[]=$t;
            }
            return true;
        }
        throw new RuntimeException('Unexpected SQL in V1.1-I fixture: '.$this->sql);
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed{return array_shift($this->rows)??false;}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->rows;}
    public function fetchColumn(int $column=0):mixed{return $this->column;}
    public function rowCount():int{return $this->affected;}
}

$pdo=new V11iCalendarPDO();
$pdo->widgets[90]=['widget_id'=>90,'widget_owner'=>7,'widget_location'=>0,'widget_type'=>'task','widget_reference_id'=>null,'widget_sort_order'=>5,'widget_width'=>1,'widget_style'=>'primary','widget_config'=>'{"schema":1,"title":"Task"}','widget_flag'=>0,'widget_created_at'=>'','widget_updated_at'=>''];
$pdo->tasks[900]=['task_id'=>900,'task_owner'=>7,'task_widget_id'=>90,'task_title'=>'提出','task_due_date'=>'2026-08-10','task_priority'=>'high','task_completed'=>0,'task_flag'=>0,'task_sort_order'=>10,'task_updated_at'=>'2026-08-01 00:00:00'];
$pdo->tasks[901]=['task_id'=>901,'task_owner'=>7,'task_widget_id'=>90,'task_title'=>'完了済み','task_due_date'=>'2026-08-11','task_priority'=>'low','task_completed'=>1,'task_flag'=>0,'task_sort_order'=>20,'task_updated_at'=>'2026-08-01 00:00:00'];
set_db_connection_for_testing($pdo);

$widgetCreate=api_dispatch('widget.calendar.create',7,[
    'widget_owner'=>'999','widget_location'=>'1','widget_style'=>'info','widget_width'=>'2','calendar_title'=>'予定','calendar_show_completed_tasks'=>'0',
]);
v11i_check($widgetCreate['status']===201&&($widgetCreate['body']['ok']??false)===true,'authenticated user can create a Calendar Widget');
$widgetId=(int)($widgetCreate['body']['data']['widget_id']??0);
v11i_check($pdo->widgets[$widgetId]['widget_owner']===7,'Calendar Widget owner comes from authenticated session');
v11i_check($pdo->widgets[$widgetId]['widget_reference_id']===null,'Calendar Widget uses no domain reference id');
v11i_check(json_decode($pdo->widgets[$widgetId]['widget_config'],true)['title']==='予定','Calendar Widget config is stored');

$eventCreate=api_dispatch('calendar.event.create',7,[
    'calendar_event_owner'=>'999','calendar_event_title'=>'会議','calendar_event_start_date'=>'2026-08-09','calendar_event_end_date'=>'2026-08-10','calendar_event_note'=>'資料を確認',
]);
v11i_check($eventCreate['status']===201&&($eventCreate['body']['ok']??false)===true,'Calendar event can be created');
$eventId=(int)($eventCreate['body']['data']['event_id']??0);
v11i_check($pdo->events[$eventId]['calendar_event_owner']===7,'Calendar event owner ignores a client owner field');
v11i_check($pdo->events[$eventId]['calendar_event_start_date']==='2026-08-09'&&$pdo->events[$eventId]['calendar_event_end_date']==='2026-08-10','Calendar event range is stored');

$otherUpdate=api_dispatch('calendar.event.update',8,['event_id'=>(string)$eventId,'calendar_event_title'=>'改変','calendar_event_start_date'=>'2026-08-09','calendar_event_end_date'=>'2026-08-10','calendar_event_note'=>'']);
v11i_check($otherUpdate['status']===404,'another user cannot update a Calendar event');
$update=api_dispatch('calendar.event.update',7,['event_id'=>(string)$eventId,'calendar_event_title'=>'会議変更','calendar_event_start_date'=>'2026-08-10','calendar_event_end_date'=>'2026-08-12','calendar_event_note'=>'更新']);
v11i_check($update['status']===200&&$pdo->events[$eventId]['calendar_event_title']==='会議変更','Calendar event can be updated');

$month=api_dispatch('calendar.month.list',7,['widget_id'=>(string)$widgetId,'calendar_year'=>'2026','calendar_month'=>'8']);
$data=$month['body']['data']??[];
v11i_check($month['status']===200&&($month['body']['ok']??false)===true,'owned Calendar month can be loaded');
v11i_check(count($data['events']??[])===1,'Calendar month contains overlapping normal event');
v11i_check(count($data['tasks']??[])===1&&($data['tasks'][0]['task_id']??0)===900,'Calendar month reads incomplete Task due date directly');
v11i_check(($data['events'][0]['title']??'')==='会議変更','Calendar month returns normalized event title');
$otherMonth=api_dispatch('calendar.month.list',8,['widget_id'=>(string)$widgetId,'calendar_year'=>'2026','calendar_month'=>'8']);
v11i_check($otherMonth['status']===404,'another user cannot read Calendar Widget settings');

$widgetUpdate=api_dispatch('widget.calendar.update',7,['widget_id'=>(string)$widgetId,'widget_style'=>'warning','widget_width'=>'4','calendar_title'=>'全予定','calendar_show_completed_tasks'=>'1']);
v11i_check($widgetUpdate['status']===200&&$pdo->widgets[$widgetId]['widget_width']===4,'Calendar Widget width can be updated');
$monthWithCompleted=api_dispatch('calendar.month.list',7,['widget_id'=>(string)$widgetId,'calendar_year'=>'2026','calendar_month'=>'8']);
v11i_check(count($monthWithCompleted['body']['data']['tasks']??[])===2,'Calendar can include completed Tasks when configured');

$invalidRange=api_dispatch('calendar.event.create',7,['calendar_event_title'=>'bad','calendar_event_start_date'=>'2026-08-20','calendar_event_end_date'=>'2026-08-19','calendar_event_note'=>'']);
v11i_check($invalidRange['status']===422,'Calendar event rejects end before start before DB mutation');
$invalidMonth=api_dispatch('calendar.month.list',7,['widget_id'=>(string)$widgetId,'calendar_year'=>'2026','calendar_month'=>'13']);
v11i_check($invalidMonth['status']===422,'Calendar month rejects invalid month');

$otherDelete=api_dispatch('calendar.event.delete',8,['event_id'=>(string)$eventId]);
v11i_check($otherDelete['status']===404&&$pdo->events[$eventId]['calendar_event_flag']===0,'another user cannot delete Calendar event');
$deleteWidget=api_dispatch('widget.calendar.delete',7,['widget_id'=>(string)$widgetId]);
v11i_check($deleteWidget['status']===200&&$pdo->widgets[$widgetId]['widget_flag']===1,'Calendar Widget delete is logical');
v11i_check($pdo->events[$eventId]['calendar_event_flag']===0,'Calendar Widget deletion preserves normal events');
$deleteEvent=api_dispatch('calendar.event.delete',7,['event_id'=>(string)$eventId]);
v11i_check($deleteEvent['status']===200&&$pdo->events[$eventId]['calendar_event_flag']===1,'Calendar event delete is owner-scoped logical delete');
$unauth=api_dispatch('widget.calendar.create',0,[]);
v11i_check($unauth['status']===401,'Calendar API requires authentication');

if ($failures !== []) { echo count($failures)."/{$checks} V1.1-I checks failed.\n"; exit(1); }
echo "All {$checks} V1.1-I Calendar Widget checks passed.\n";
