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
['widget_id'=>31,'widget_owner'=>1,'widget_location'=>0,'widget_type'=>'memo','widget_reference_id'=>301,'widget_sort_order'=>10,'widget_width'=>2,'widget_style'=>'warning','widget_config'=>null,'widget_flag'=>0,'widget_created_at'=>'2026-08-03 00:00:00','widget_updated_at'=>'2026-08-03 00:00:00','content_id'=>null,'content_date'=>null,'content_flag'=>null,'content_owner'=>null,'content_location'=>null,'content_style'=>null,'content_value'=>null,'memo_id'=>301,'memo_date'=>'2026-08-03 00:00:00','memo_updated_at'=>'2026-08-03 00:01:00','memo_flag'=>0,'memo_owner'=>1,'memo_title'=>'<Memo & Test>','memo_body'=>"一行目\n<script>alert(1)</script>"],
['widget_id'=>21,'widget_owner'=>1,'widget_location'=>0,'widget_type'=>'clock','widget_reference_id'=>null,'widget_sort_order'=>20,'widget_width'=>1,'widget_style'=>'primary','widget_config'=>'{"schema":1,"title":"Clock","hour_format":"24","show_seconds":false,"show_date":true}','widget_flag'=>0,'widget_created_at'=>'2026-08-03 00:00:00','widget_updated_at'=>'2026-08-03 00:00:00','content_id'=>null,'content_date'=>null,'content_flag'=>null,'content_owner'=>null,'content_location'=>null,'content_style'=>null,'content_value'=>null,'memo_id'=>null,'memo_date'=>null,'memo_updated_at'=>null,'memo_flag'=>null,'memo_owner'=>null,'memo_title'=>null,'memo_body'=>null],
['widget_id'=>11,'widget_owner'=>1,'widget_location'=>0,'widget_type'=>'feed','widget_reference_id'=>101,'widget_sort_order'=>30,'widget_width'=>1,'widget_style'=>'success','widget_config'=>null,'widget_flag'=>0,'widget_created_at'=>'2026-08-03 00:00:00','widget_updated_at'=>'2026-08-03 00:00:00','content_id'=>101,'content_date'=>'2026-08-03 00:00:00','content_flag'=>0,'content_owner'=>1,'content_location'=>0,'content_style'=>'success','content_value'=>'https://example.com/feed.xml','memo_id'=>null,'memo_date'=>null,'memo_updated_at'=>null,'memo_flag'=>null,'memo_owner'=>null,'memo_title'=>null,'memo_body'=>null]
];return true;}
if(str_contains($this->sql,'FROM ig_content_stock')) return true; throw new RuntimeException('Unexpected SQL: '.$this->sql);} public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->rows;}}
final class P extends PDO {public function __construct(){} public function prepare(string $q,array $o=[]):PDOStatement|false{return new S($q);}}
set_db_connection_for_testing(new P()); app_session_start(); app_session_login(1); ob_start(); require $root.'/public/index.php'; $html=ob_get_clean(); app_session_logout(); echo base64_encode($html);
''')
class Parser(HTMLParser):
    def __init__(self): super().__init__(convert_charrefs=True); self.records=[]; self.text=[]
    def handle_starttag(self,tag,attrs): self.records.append((tag,{str(k):'' if v is None else str(v) for k,v in attrs}))
    def handle_data(self,data): self.text.append(data)
with tempfile.TemporaryDirectory(prefix='v11g-render-') as temp:
    wp=Path(temp)/'w.php'; wp.write_text(worker,encoding='utf-8')
    result=subprocess.run(['php',str(wp),str(ROOT)],cwd=ROOT,text=True,capture_output=True,timeout=30)
check(result.returncode==0,'Feed, Clock and Memo Dashboard render exits successfully')
check(result.stderr.strip()=='','mixed Dashboard render has no PHP warning')
html=base64.b64decode(result.stdout.strip()).decode() if result.returncode==0 else ''
p=Parser(); p.feed(html)
widgets=[a for _,a in p.records if a.get('data-dashboard-widget-id')]
check([a.get('data-dashboard-widget-type') for a in widgets]==['memo','clock','feed'],'mixed Widget output follows sort order')
memos=[a for a in widgets if a.get('data-dashboard-widget-type')=='memo']
check(len(memos)==1 and memos[0].get('data-memo-id')=='301','Memo card exposes its owned reference id')
check('col-lg-6' in memos[0].get('class','').split(),'Memo width=2 uses existing width class')
check(memos[0].get('role')=='region' and memos[0].get('aria-labelledby'),'Memo is a named region')
check(len([1 for _,a in p.records if 'memo-edit-trigger' in a.get('class','').split()])==1,'Memo has one edit control')
check(len([1 for _,a in p.records if 'memo-body' in a.get('class','').split()])==1,'Memo body renders once')
check('&lt;Memo &amp; Test&gt;' in html and '<Memo & Test>' not in html,'Memo title is escaped')
check('&lt;script&gt;alert(1)&lt;/script&gt;' in html and '<script>alert(1)</script>' not in html,'Memo body is escaped')
check('一行目\n&lt;script&gt;' in html,'Memo line break remains text in raw output')
check('id="registerMemoForm"' in html and 'id="changeMemoForm"' in html,'Memo add and edit modals render')
check('Memo追加' in ''.join(p.text),'Drawer contains Memo add action')
check('RSS Reader Modernization V1.1-G / R1' in ''.join(p.text),'Dashboard displays V1.1-G marker')
ids=[a['id'] for _,a in p.records if a.get('id')]
check(len(ids)==len(set(ids)),'mixed Dashboard has no duplicate ids')
if failures: raise SystemExit(1)
print('All V1.1-G Dashboard render checks passed.')
