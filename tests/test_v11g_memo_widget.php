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
function v11g_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) {
        $failures[] = $message;
    }
}

v11g_check(dashboard_widget_validate_memo_title('作業メモ') === '作業メモ', 'Memo title accepts safe UTF-8 text');
v11g_check(dashboard_widget_validate_memo_title('') === null, 'Memo title cannot be empty');
v11g_check(dashboard_widget_validate_memo_title(str_repeat('a', 33)) === null, 'Memo title is limited to 32 characters');
v11g_check(dashboard_widget_validate_memo_body("line1\r\nline2") === "line1\nline2", 'Memo body normalizes CRLF');
v11g_check(dashboard_widget_validate_memo_body('') === null, 'Memo body cannot be empty');
v11g_check(dashboard_widget_validate_memo_body(str_repeat('a', 4000)) !== null, 'Memo body accepts 4000 characters');
v11g_check(dashboard_widget_validate_memo_body(str_repeat('a', 4001)) === null, 'Memo body rejects more than 4000 characters');
v11g_check(dashboard_widget_validate_memo_body("ok\x00bad") === null, 'Memo body rejects control characters');
v11g_check(dashboard_widget_validate_memo_body('<script>alert(1)</script>') !== null, 'Memo HTML-like text remains data for escaped output');

$row = dashboard_widget_normalize_row([
    'widget_id' => '9', 'widget_owner' => '7', 'widget_location' => '1',
    'widget_type' => 'memo', 'widget_reference_id' => '4', 'widget_sort_order' => '20',
    'widget_width' => '2', 'widget_height' => '1', 'widget_style' => 'warning', 'widget_config' => null,
]);
v11g_check(is_array($row) && $row['widget_reference_id'] === 4, 'Memo Widget requires a positive reference id');
$rowBad = dashboard_widget_normalize_row([
    'widget_id' => '9', 'widget_owner' => '7', 'widget_location' => '1',
    'widget_type' => 'memo', 'widget_reference_id' => null, 'widget_sort_order' => '20',
    'widget_width' => '2', 'widget_height' => '1', 'widget_style' => 'warning', 'widget_config' => null,
]);
v11g_check($rowBad === null, 'Memo Widget rejects a missing reference id');

final class V11gMemoPDO extends PDO
{
    public array $widgets = [];
    public array $memos = [];
    public int $widgetSeq = 0;
    public int $memoSeq = 0;
    public int $lastId = 0;
    public bool $failWidgetInsert = false;
    private bool $transaction = false;
    private ?array $snapshot = null;
    public function __construct() {}
    public function getAttribute(int $attribute): mixed { return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : null; }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new V11gMemoStatement($this, $query); }
    public function beginTransaction(): bool { $this->transaction = true; $this->snapshot = [$this->widgets, $this->memos, $this->widgetSeq, $this->memoSeq, $this->lastId]; return true; }
    public function commit(): bool { $this->transaction = false; $this->snapshot = null; return true; }
    public function rollBack(): bool { if ($this->snapshot !== null) { [$this->widgets, $this->memos, $this->widgetSeq, $this->memoSeq, $this->lastId] = $this->snapshot; } $this->transaction = false; $this->snapshot = null; return true; }
    public function inTransaction(): bool { return $this->transaction; }
    public function lastInsertId(?string $name = null): string|false { return (string) $this->lastId; }
}

final class V11gMemoStatement extends PDOStatement
{
    private array $rows = [];
    private mixed $column = false;
    private int $affected = 0;
    public function __construct(private V11gMemoPDO $pdo, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $this->rows = []; $this->column = false; $this->affected = 0;
        if (str_starts_with($this->sql, 'SELECT widget_sort_order FROM `ig_dashboard_widget`')) {
            $orders = [];
            foreach ($this->pdo->widgets as $widget) {
                if ($widget['widget_owner'] === (int)$params[':owner'] && $widget['widget_location'] === (int)$params[':location'] && $widget['widget_flag'] === 0) $orders[] = $widget['widget_sort_order'];
            }
            $this->column = $orders === [] ? false : max($orders); return true;
        }
        if (str_starts_with($this->sql, 'INSERT INTO `ig_memo`')) {
            $id = ++$this->pdo->memoSeq; $this->pdo->lastId = $id;
            $this->pdo->memos[$id] = [
                'memo_id'=>$id, 'memo_date'=>(string)$params[':memo_date'], 'memo_updated_at'=>(string)$params[':memo_updated_at'],
                'memo_flag'=>0, 'memo_owner'=>(int)$params[':memo_owner'], 'memo_title'=>(string)$params[':memo_title'], 'memo_body'=>(string)$params[':memo_body'],
            ]; $this->affected=1; return true;
        }
        if (str_starts_with($this->sql, 'INSERT INTO `ig_dashboard_widget`') && str_contains($this->sql, "'memo'")) {
            if ($this->pdo->failWidgetInsert) throw new PDOException('forced widget insert failure');
            $id = ++$this->pdo->widgetSeq; $this->pdo->lastId = $id;
            $this->pdo->widgets[$id] = [
                'widget_id'=>$id, 'widget_owner'=>(int)$params[':owner'], 'widget_location'=>(int)$params[':location'],
                'widget_type'=>'memo', 'widget_reference_id'=>(int)$params[':reference_id'], 'widget_sort_order'=>(int)$params[':sort_order'],
                'widget_width'=>(int)$params[':width'], 'widget_height' => '1', 'widget_style'=>(string)$params[':style'], 'widget_config'=>null, 'widget_flag'=>0,
                'widget_created_at'=>(string)$params[':created_at'], 'widget_updated_at'=>(string)$params[':updated_at'],
            ]; $this->affected=1; return true;
        }
        if (str_starts_with($this->sql, 'SELECT * FROM `ig_dashboard_widget`')) {
            $w=$this->pdo->widgets[(int)($params[':widget_id']??0)]??null;
            if (is_array($w) && $w['widget_owner']===(int)($params[':owner']??0) && $w['widget_type']===(string)($params[':widget_type']??'') && $w['widget_flag']===0) $this->rows=[$w];
            return true;
        }
        if (str_starts_with($this->sql, 'SELECT * FROM `ig_memo`')) {
            $m=$this->pdo->memos[(int)($params[':memo_id']??0)]??null;
            if (is_array($m) && $m['memo_owner']===(int)($params[':owner']??0) && $m['memo_flag']===0) $this->rows=[$m];
            return true;
        }
        if (str_starts_with($this->sql, 'UPDATE `ig_memo` SET memo_title')) {
            $id=(int)$params[':memo_id']; $m=$this->pdo->memos[$id]??null;
            if (is_array($m) && $m['memo_owner']===(int)$params[':owner'] && $m['memo_flag']===0) {
                $this->pdo->memos[$id]['memo_title']=(string)$params[':memo_title'];
                $this->pdo->memos[$id]['memo_body']=(string)$params[':memo_body'];
                $this->pdo->memos[$id]['memo_updated_at']=(string)$params[':updated_at']; $this->affected=1;
            } return true;
        }
        if (str_starts_with($this->sql, 'UPDATE `ig_dashboard_widget` SET widget_width')) {
            $id=(int)$params[':widget_id']; $w=$this->pdo->widgets[$id]??null;
            if (is_array($w) && $w['widget_owner']===(int)$params[':owner'] && $w['widget_type']==='memo' && $w['widget_flag']===0) {
                $this->pdo->widgets[$id]['widget_width']=(int)$params[':width']; $this->pdo->widgets[$id]['widget_style']=(string)$params[':style'];
                $this->pdo->widgets[$id]['widget_updated_at']=(string)$params[':updated_at']; $this->affected=1;
            } return true;
        }
        if (str_starts_with($this->sql, 'UPDATE `ig_memo` SET memo_flag = 1')) {
            $id=(int)$params[':memo_id']; $m=$this->pdo->memos[$id]??null;
            if (is_array($m) && $m['memo_owner']===(int)$params[':owner'] && $m['memo_flag']===0) { $this->pdo->memos[$id]['memo_flag']=1; $this->affected=1; } return true;
        }
        if (str_starts_with($this->sql, 'UPDATE `ig_dashboard_widget` SET widget_flag = 1')) {
            $id=(int)$params[':widget_id']; $w=$this->pdo->widgets[$id]??null;
            if (is_array($w) && $w['widget_owner']===(int)$params[':owner'] && $w['widget_type']==='memo' && $w['widget_flag']===0) { $this->pdo->widgets[$id]['widget_flag']=1; $this->affected=1; } return true;
        }
        throw new RuntimeException('Unexpected SQL in V1.1-G fixture: '.$this->sql);
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed{return array_shift($this->rows)??false;}
    public function fetchColumn(int $column=0):mixed{return $this->column;}
    public function rowCount():int{return $this->affected;}
}

$pdo = new V11gMemoPDO();
set_db_connection_for_testing($pdo);
$create = api_dispatch('widget.memo.create', 7, [
    'widget_owner'=>'999','widget_location'=>'1','widget_style'=>'warning','widget_width'=>'2', 'widget_height' => '1',
    'memo_title'=>'連絡','memo_body'=>"一行目\r\n二行目 <b>text</b>",
]);
v11g_check($create['status']===201 && ($create['body']['ok']??false)===true, 'authenticated user can create a Memo Widget');
$widgetId=(int)($create['body']['data']['widget_id']??0); $memoId=(int)($create['body']['data']['memo_id']??0);
v11g_check($widgetId===1 && $memoId===1, 'Memo and Widget IDs are returned');
v11g_check($pdo->memos[$memoId]['memo_owner']===7 && $pdo->widgets[$widgetId]['widget_owner']===7, 'Memo owner always comes from authenticated session');
v11g_check($pdo->widgets[$widgetId]['widget_reference_id']===$memoId, 'Memo Widget references the Memo row');
v11g_check($pdo->widgets[$widgetId]['widget_sort_order']===10, 'first Memo appends at initial order');
v11g_check($pdo->memos[$memoId]['memo_body']==="一行目\n二行目 <b>text</b>", 'Memo body keeps line breaks and text exactly after normalization');

$second=api_dispatch('widget.memo.create',7,['widget_location'=>'1','widget_style'=>'info','widget_width'=>'1', 'widget_height' => '1','memo_title'=>'Second','memo_body'=>'Body']);
$secondWidget=(int)($second['body']['data']['widget_id']??0);
v11g_check($pdo->widgets[$secondWidget]['widget_sort_order']===20, 'second Memo appends after current Widget order');

$wrong=api_dispatch('widget.memo.update',8,['widget_id'=>(string)$widgetId,'widget_style'=>'danger','widget_width'=>'4', 'widget_height' => '1','memo_title'=>'Hijack','memo_body'=>'Bad']);
v11g_check($wrong['status']===404 && $pdo->memos[$memoId]['memo_title']==='連絡', 'another user cannot update Memo');
$update=api_dispatch('widget.memo.update',7,['widget_id'=>(string)$widgetId,'widget_style'=>'success','widget_width'=>'3', 'widget_height' => '1','memo_title'=>'更新','memo_body'=>"新しい\n本文"]);
v11g_check($update['status']===200 && $pdo->memos[$memoId]['memo_title']==='更新', 'owner can update Memo content');
v11g_check($pdo->widgets[$widgetId]['widget_width']===3 && $pdo->widgets[$widgetId]['widget_style']==='success', 'Memo Widget style and width update together');
v11g_check($pdo->widgets[$widgetId]['widget_location']===1, 'Memo edit does not move it to another tab');

$wrongDelete=api_dispatch('widget.memo.delete',8,['widget_id'=>(string)$widgetId]);
v11g_check($wrongDelete['status']===404 && $pdo->memos[$memoId]['memo_flag']===0, 'another user cannot delete Memo');
$delete=api_dispatch('widget.memo.delete',7,['widget_id'=>(string)$widgetId]);
v11g_check($delete['status']===200 && $pdo->memos[$memoId]['memo_flag']===1 && $pdo->widgets[$widgetId]['widget_flag']===1, 'Memo delete deactivates content and placement together');

$invalid=api_dispatch('widget.memo.create',7,['widget_location'=>'9','widget_style'=>'x','widget_width'=>'9', 'widget_height' => '1','memo_title'=>'','memo_body'=>'']);
v11g_check($invalid['status']===422, 'invalid Memo payload is rejected before DB access');
v11g_check(api_dispatch('widget.memo.create',0,[])['status']===401, 'Memo API requires authentication');

$beforeMemos=count($pdo->memos); $beforeWidgets=count($pdo->widgets); $pdo->failWidgetInsert=true;
try { dashboard_widget_create_memo(7,0,'primary',1,'Rollback','Body'); v11g_check(false,'forced Widget failure throws'); }
catch (PDOException) { v11g_check(true,'forced Widget failure is surfaced'); }
v11g_check(count($pdo->memos)===$beforeMemos && count($pdo->widgets)===$beforeWidgets, 'transaction rolls back Memo if Widget insert fails');

set_db_connection_for_testing(null);
if ($failures !== []) { fwrite(STDERR,count($failures).'/'.$checks." V1.1-G checks failed.\n"); exit(1); }
echo 'All '.$checks." V1.1-G Memo Widget checks passed.\n";
