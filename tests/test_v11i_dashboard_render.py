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
['widget_id'=>51,'widget_owner'=>1,'widget_location'=>0,'widget_type'=>'calendar','widget_reference_id'=>null,'widget_sort_order'=>5,'widget_width'=>2,'widget_style'=>'info','widget_config'=>'{"schema":1,"title":"<Calendar & Test>","show_completed_tasks":true}','widget_flag'=>0,'widget_created_at'=>'2026-08-03 00:00:00','widget_updated_at'=>'2026-08-03 00:00:00','content_id'=>null,'content_date'=>null,'content_flag'=>null,'content_owner'=>null,'content_location'=>null,'content_style'=>null,'content_value'=>null,'memo_id'=>null,'memo_date'=>null,'memo_updated_at'=>null,'memo_flag'=>null,'memo_owner'=>null,'memo_title'=>null,'memo_body'=>null],
['widget_id'=>41,'widget_owner'=>1,'widget_location'=>0,'widget_type'=>'task','widget_reference_id'=>null,'widget_sort_order'=>10,'widget_width'=>2,'widget_style'=>'primary','widget_config'=>'{"schema":1,"title":"Task"}','widget_flag'=>0,'widget_created_at'=>'2026-08-03 00:00:00','widget_updated_at'=>'2026-08-03 00:00:00','content_id'=>null,'content_date'=>null,'content_flag'=>null,'content_owner'=>null,'content_location'=>null,'content_style'=>null,'content_value'=>null,'memo_id'=>null,'memo_date'=>null,'memo_updated_at'=>null,'memo_flag'=>null,'memo_owner'=>null,'memo_title'=>null,'memo_body'=>null],
['widget_id'=>31,'widget_owner'=>1,'widget_location'=>0,'widget_type'=>'memo','widget_reference_id'=>301,'widget_sort_order'=>20,'widget_width'=>1,'widget_style'=>'warning','widget_config'=>null,'widget_flag'=>0,'widget_created_at'=>'2026-08-03 00:00:00','widget_updated_at'=>'2026-08-03 00:00:00','content_id'=>null,'content_date'=>null,'content_flag'=>null,'content_owner'=>null,'content_location'=>null,'content_style'=>null,'content_value'=>null,'memo_id'=>301,'memo_date'=>'2026-08-03 00:00:00','memo_updated_at'=>'2026-08-03 00:01:00','memo_flag'=>0,'memo_owner'=>1,'memo_title'=>'Memo','memo_body'=>'body'],
['widget_id'=>21,'widget_owner'=>1,'widget_location'=>0,'widget_type'=>'clock','widget_reference_id'=>null,'widget_sort_order'=>30,'widget_width'=>1,'widget_style'=>'primary','widget_config'=>'{"schema":1,"title":"Clock","hour_format":"24","show_seconds":false,"show_date":true}','widget_flag'=>0,'widget_created_at'=>'2026-08-03 00:00:00','widget_updated_at'=>'2026-08-03 00:00:00','content_id'=>null,'content_date'=>null,'content_flag'=>null,'content_owner'=>null,'content_location'=>null,'content_style'=>null,'content_value'=>null,'memo_id'=>null,'memo_date'=>null,'memo_updated_at'=>null,'memo_flag'=>null,'memo_owner'=>null,'memo_title'=>null,'memo_body'=>null],
['widget_id'=>11,'widget_owner'=>1,'widget_location'=>0,'widget_type'=>'feed','widget_reference_id'=>101,'widget_sort_order'=>40,'widget_width'=>1,'widget_style'=>'success','widget_config'=>null,'widget_flag'=>0,'widget_created_at'=>'2026-08-03 00:00:00','widget_updated_at'=>'2026-08-03 00:00:00','content_id'=>101,'content_date'=>'2026-08-03 00:00:00','content_flag'=>0,'content_owner'=>1,'content_location'=>0,'content_style'=>'success','content_value'=>'https://example.com/feed.xml','memo_id'=>null,'memo_date'=>null,'memo_updated_at'=>null,'memo_flag'=>null,'memo_owner'=>null,'memo_title'=>null,'memo_body'=>null]
];return true;}
if(str_contains($this->sql,'FROM `ig_task`')){$this->rows=[['task_id'=>501,'task_date'=>'2026-08-03 00:00:00','task_updated_at'=>'2026-08-03 00:01:00','task_flag'=>0,'task_owner'=>1,'task_widget_id'=>41,'task_title'=>'締切あり','task_due_date'=>'2026-08-31','task_priority'=>'high','task_completed'=>0,'task_completed_at'=>null,'task_sort_order'=>10]];return true;}
if(str_contains($this->sql,'FROM ig_content_stock')) return true; throw new RuntimeException('Unexpected SQL: '.$this->sql);} public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->rows;}}
final class P extends PDO {public function __construct(){} public function prepare(string $q,array $o=[]):PDOStatement|false{return new S($q);}}
set_db_connection_for_testing(new P()); app_session_start(); app_session_login(1); ob_start(); require $root.'/public/index.php'; $html=ob_get_clean(); app_session_logout(); echo base64_encode($html);
''')
class Parser(HTMLParser):
    def __init__(self): super().__init__(convert_charrefs=True); self.records=[]; self.text=[]
    def handle_starttag(self,tag,attrs): self.records.append((tag,{str(k):'' if v is None else str(v) for k,v in attrs}))
    def handle_data(self,data): self.text.append(data)
with tempfile.TemporaryDirectory(prefix='v11i-render-') as temp:
    wp=Path(temp)/'w.php'; wp.write_text(worker,encoding='utf-8')
    result=subprocess.run(['php',str(wp),str(ROOT)],cwd=ROOT,text=True,capture_output=True,timeout=30)
check(result.returncode==0,'Feed, Clock, Memo, Task and Calendar Dashboard render exits successfully')
check(result.stderr.strip()=='','mixed Dashboard render has no PHP warning')
html=base64.b64decode(result.stdout.strip()).decode() if result.returncode==0 else ''
p=Parser(); p.feed(html)
widgets=[a for _,a in p.records if a.get('data-dashboard-widget-id')]
check([a.get('data-dashboard-widget-type') for a in widgets]==['calendar','task','memo','clock','feed'],'mixed Widget output follows sort order')
cal=[a for a in widgets if a.get('data-dashboard-widget-type')=='calendar']
check(len(cal)==1,'one active Calendar Widget renders')
check(cal[0].get('data-calendar-title')=='<Calendar & Test>','Calendar card exposes normalized escaped title data')
check(cal[0].get('data-calendar-show-completed-tasks')=='1','Calendar card exposes completed Task setting')
check('col-lg-6' in cal[0].get('class','').split(),'Calendar width=2 uses existing width class')
check(cal[0].get('role')=='region' and cal[0].get('aria-labelledby'),'Calendar is a named region')
check('&lt;Calendar &amp; Test&gt;' in html and '<Calendar & Test>' not in html,'Calendar Widget title is escaped')
check(len([1 for _,a in p.records if 'calendar-prev-month' in a.get('class','').split()])==1,'Calendar has one previous-month control')
check(len([1 for _,a in p.records if 'calendar-next-month' in a.get('class','').split()])==1,'Calendar has one next-month control')
check(len([1 for _,a in p.records if 'calendar-today' in a.get('class','').split()])==1,'Calendar has one current-month control')
grids=[a for _,a in p.records if 'calendar-days' in a.get('class','').split()]
check(len(grids)==1 and grids[0].get('role')=='grid' and grids[0].get('aria-busy')=='true','Calendar month grid begins in loading state')
weekdays=[a for _,a in p.records if 'calendar-weekdays' in a.get('class','').split()]
check(len(weekdays)==1,'Calendar weekday row renders')
check('日月火水木金土' in ''.join(p.text).replace('\n','').replace(' ',''),'Calendar weekday labels render in order')
check('id="registerCalendarWidgetForm"' in html and 'id="changeCalendarWidgetForm"' in html,'Calendar Widget add and edit modals render')
check('id="registerCalendarEventForm"' in html and 'id="changeCalendarEventForm"' in html,'Calendar event add and edit modals render')
check('Calendar追加' in ''.join(p.text),'Drawer contains Calendar add action')
check('<script src="./js/calendar.js"></script>' in html,'Calendar external JavaScript is loaded')
check(any(label in ''.join(p.text) for label in ['RSS Reader Modernization V1.1-I / R2','RSS Reader Modernization V1.1-J / R1','RSS Reader Modernization 1.1.0']),'Dashboard displays V1.1-I R2 or later marker')
ids=[a['id'] for _,a in p.records if a.get('id')]
check(len(ids)==len(set(ids)),'mixed Dashboard has no duplicate ids')
if failures: raise SystemExit(1)
print('All V1.1-I Dashboard render checks passed.')
