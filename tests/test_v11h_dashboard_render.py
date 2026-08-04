from __future__ import annotations
import base64
from html.parser import HTMLParser
from pathlib import Path
import subprocess, tempfile, textwrap

ROOT=Path(__file__).resolve().parents[1]
failures=[]
def check(cond,msg): print(('PASS' if cond else 'FAIL')+': '+msg); failures.append(msg) if not cond else None
worker=textwrap.dedent(r'''<?php
$root=$argv[1];
putenv('APP_ENV=testing'); putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql'); putenv('DB_TABLE_PREFIX=ig_'); putenv('DB_HOST=test'); putenv('DB_NAME=test'); putenv('DB_USER=test'); putenv('DB_PASSWORD=test');
putenv('REGISTRATION_ENABLED=true'); putenv('APP_LOG_ENABLED=false');
$_SERVER['REQUEST_METHOD']='GET'; $_SERVER['REQUEST_URI']='/?tab=0'; $_SERVER['SERVER_PROTOCOL']='HTTP/1.1'; $_SERVER['REMOTE_ADDR']='127.0.0.1'; $_SERVER['SERVER_PORT']='80'; $_GET['tab']='0';
require $root.'/app/bootstrap.php';
final class S extends PDOStatement { private array $rows=[]; public function __construct(private string $sql){} public function execute(?array $params=null):bool{$params??=[];$this->rows=[];
if(str_contains($this->sql,'FROM ig_user_conf')){$this->rows=[['conf_style'=>'bootstrap','conf_style_nav'=>'dark','conf_style_tabname1'=>'Base','conf_style_tabname2'=>'Maint','conf_style_tabname3'=>'IT','conf_style_tabname4'=>'Observe','conf_style_navlink1'=>'','conf_style_navlink_view1'=>'','conf_style_navlink_icon1'=>'map-marker-alt','conf_style_navlink2'=>'','conf_style_navlink_view2'=>'','conf_style_navlink_icon2'=>'mail-bulk','conf_style_navlink3'=>'','conf_style_navlink_view3'=>'','conf_style_navlink_icon3'=>'search','conf_style_navlink4'=>'','conf_style_navlink_view4'=>'','conf_style_navlink_icon4'=>'images']];return true;}
if(str_contains($this->sql,'FROM `ig_dashboard_widget` w')){$this->rows=[
['widget_id'=>41,'widget_owner'=>1,'widget_location'=>0,'widget_type'=>'task','widget_reference_id'=>null,'widget_sort_order'=>5,'widget_width'=>2,'widget_style'=>'primary','widget_config'=>'{"schema":1,"title":"<Task & Test>"}','widget_flag'=>0,'widget_created_at'=>'2026-08-03 00:00:00','widget_updated_at'=>'2026-08-03 00:00:00','content_id'=>null,'content_date'=>null,'content_flag'=>null,'content_owner'=>null,'content_location'=>null,'content_style'=>null,'content_value'=>null,'memo_id'=>null,'memo_date'=>null,'memo_updated_at'=>null,'memo_flag'=>null,'memo_owner'=>null,'memo_title'=>null,'memo_body'=>null],
['widget_id'=>31,'widget_owner'=>1,'widget_location'=>0,'widget_type'=>'memo','widget_reference_id'=>301,'widget_sort_order'=>10,'widget_width'=>2,'widget_style'=>'warning','widget_config'=>null,'widget_flag'=>0,'widget_created_at'=>'2026-08-03 00:00:00','widget_updated_at'=>'2026-08-03 00:00:00','content_id'=>null,'content_date'=>null,'content_flag'=>null,'content_owner'=>null,'content_location'=>null,'content_style'=>null,'content_value'=>null,'memo_id'=>301,'memo_date'=>'2026-08-03 00:00:00','memo_updated_at'=>'2026-08-03 00:01:00','memo_flag'=>0,'memo_owner'=>1,'memo_title'=>'Memo','memo_body'=>'body'],
['widget_id'=>21,'widget_owner'=>1,'widget_location'=>0,'widget_type'=>'clock','widget_reference_id'=>null,'widget_sort_order'=>20,'widget_width'=>1,'widget_style'=>'primary','widget_config'=>'{"schema":1,"title":"Clock","hour_format":"24","show_seconds":false,"show_date":true}','widget_flag'=>0,'widget_created_at'=>'2026-08-03 00:00:00','widget_updated_at'=>'2026-08-03 00:00:00','content_id'=>null,'content_date'=>null,'content_flag'=>null,'content_owner'=>null,'content_location'=>null,'content_style'=>null,'content_value'=>null,'memo_id'=>null,'memo_date'=>null,'memo_updated_at'=>null,'memo_flag'=>null,'memo_owner'=>null,'memo_title'=>null,'memo_body'=>null],
['widget_id'=>11,'widget_owner'=>1,'widget_location'=>0,'widget_type'=>'feed','widget_reference_id'=>101,'widget_sort_order'=>30,'widget_width'=>1,'widget_style'=>'success','widget_config'=>null,'widget_flag'=>0,'widget_created_at'=>'2026-08-03 00:00:00','widget_updated_at'=>'2026-08-03 00:00:00','content_id'=>101,'content_date'=>'2026-08-03 00:00:00','content_flag'=>0,'content_owner'=>1,'content_location'=>0,'content_style'=>'success','content_value'=>'https://example.com/feed.xml','memo_id'=>null,'memo_date'=>null,'memo_updated_at'=>null,'memo_flag'=>null,'memo_owner'=>null,'memo_title'=>null,'memo_body'=>null]
];return true;}
if(str_contains($this->sql,'FROM `ig_task`')){$this->rows=[
['task_id'=>501,'task_date'=>'2026-08-03 00:00:00','task_updated_at'=>'2026-08-03 00:01:00','task_flag'=>0,'task_owner'=>1,'task_widget_id'=>41,'task_title'=>'<script>alert(1)</script>','task_due_date'=>'2026-08-31','task_priority'=>'high','task_completed'=>0,'task_completed_at'=>null,'task_sort_order'=>10],
['task_id'=>502,'task_date'=>'2026-08-03 00:00:00','task_updated_at'=>'2026-08-03 00:01:00','task_flag'=>0,'task_owner'=>1,'task_widget_id'=>41,'task_title'=>'完了済み','task_due_date'=>null,'task_priority'=>'low','task_completed'=>1,'task_completed_at'=>'2026-08-03 00:02:00','task_sort_order'=>20]
];return true;}
if(str_contains($this->sql,'FROM ig_content_stock')) return true; throw new RuntimeException('Unexpected SQL: '.$this->sql);} public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->rows;}}
final class P extends PDO {public function __construct(){} public function prepare(string $q,array $o=[]):PDOStatement|false{return new S($q);}}
set_db_connection_for_testing(new P()); app_session_start(); app_session_login(1); ob_start(); require $root.'/public/index.php'; $html=ob_get_clean(); app_session_logout(); echo base64_encode($html);
''')
class Parser(HTMLParser):
    def __init__(self): super().__init__(convert_charrefs=True); self.records=[]; self.text=[]
    def handle_starttag(self,tag,attrs): self.records.append((tag,{str(k):'' if v is None else str(v) for k,v in attrs}))
    def handle_data(self,data): self.text.append(data)
with tempfile.TemporaryDirectory(prefix='v11h-render-') as temp:
    wp=Path(temp)/'w.php'; wp.write_text(worker,encoding='utf-8')
    result=subprocess.run(['php',str(wp),str(ROOT)],cwd=ROOT,text=True,capture_output=True,timeout=30)
check(result.returncode==0,'Feed, Clock, Memo and Task Dashboard render exits successfully')
check(result.stderr.strip()=='','mixed Dashboard render has no PHP warning')
html=base64.b64decode(result.stdout.strip()).decode() if result.returncode==0 else ''
p=Parser(); p.feed(html)
widgets=[a for _,a in p.records if a.get('data-dashboard-widget-id')]
check([a.get('data-dashboard-widget-type') for a in widgets]==['task','memo','clock','feed'],'mixed Widget output follows sort order')
tasks=[a for a in widgets if a.get('data-dashboard-widget-type')=='task']
check(len(tasks)==1 and tasks[0].get('data-task-widget-title')=='<Task & Test>','Task card exposes normalized title as escaped attribute data')
check('col-lg-6' in tasks[0].get('class','').split(),'Task width=2 uses existing width class')
check(tasks[0].get('role')=='region' and tasks[0].get('aria-labelledby'),'Task is a named region')
items=[a for _,a in p.records if 'task-item' in a.get('class','').split()]
check(len(items)==2,'Task Widget renders active Task items')
check(items[0].get('data-task-id')=='501' and items[1].get('data-task-id')=='502','Task item IDs are explicit')
check('task-completed' in items[1].get('class','').split(),'completed Task receives completed class')
check(len([1 for _,a in p.records if 'task-toggle' in a.get('class','').split()])==2,'each Task has one completion control')
check(len([1 for _,a in p.records if 'task-item-edit-trigger' in a.get('class','').split()])==2,'each Task has one edit control')
check('&lt;Task &amp; Test&gt;' in html and '<Task & Test>' not in html,'Task Widget title is escaped')
check('&lt;script&gt;alert(1)&lt;/script&gt;' in html and '<script>alert(1)</script>' not in html,'Task item title is escaped')
check('datetime="2026-08-31"' in html,'Task due date uses machine-readable time output')
check('優先度 高' in ''.join(p.text) and '優先度 低' in ''.join(p.text),'Task priority labels render')
check('id="registerTaskWidgetForm"' in html and 'id="changeTaskWidgetForm"' in html and 'id="changeTaskItemForm"' in html,'Task add and edit modals render')
check('Task追加' in ''.join(p.text),'Drawer contains Task add action')
check(any(label in ''.join(p.text) for label in ['RSS Reader Modernization V1.1-H / R1','RSS Reader Modernization V1.1-I / R1','RSS Reader Modernization V1.1-I / R2','RSS Reader Modernization V1.1-J / R1','RSS Reader Modernization 1.1.0']) or 'RSS Reader Modernization 1.2.0-dev.1' in ''.join(p.text),'Dashboard displays V1.1-H or later marker')
ids=[a['id'] for _,a in p.records if a.get('id')]
check(len(ids)==len(set(ids)),'mixed Dashboard has no duplicate ids')
if failures: raise SystemExit(1)
print('All V1.1-H Dashboard render checks passed.')
