from __future__ import annotations
import base64
import re
from html.parser import HTMLParser
from pathlib import Path
import subprocess
import tempfile
import textwrap

ROOT = Path(__file__).resolve().parents[1]
failures: list[str] = []
def check(condition: bool, message: str) -> None:
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition: failures.append(message)

worker = textwrap.dedent(r'''<?php
$root=$argv[1];
putenv('APP_ENV=testing');putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');putenv('DB_TABLE_PREFIX=ig_');putenv('DB_HOST=test');putenv('DB_NAME=test');putenv('DB_USER=test');putenv('DB_PASSWORD=test');putenv('REGISTRATION_ENABLED=true');putenv('APP_LOG_ENABLED=false');
$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/?tab=0';$_SERVER['SERVER_PROTOCOL']='HTTP/1.1';$_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['SERVER_PORT']='80';$_GET['tab']='0';
require $root.'/app/bootstrap.php';
final class HStmt extends PDOStatement {private array $rows=[];public function __construct(private string $sql){}public function execute(?array $params=null):bool{$params??=[];$this->rows=[];
if(str_contains($this->sql,'FROM ig_user_conf')){$this->rows=[['conf_style'=>'bootstrap','conf_style_nav'=>'dark','conf_style_tabname1'=>'Base','conf_style_tabname2'=>'Goship','conf_style_tabname3'=>'Tool','conf_style_tabname4'=>'Observ','conf_style_navlink1'=>'','conf_style_navlink_view1'=>'','conf_style_navlink_icon1'=>'map-marker-alt','conf_style_navlink2'=>'','conf_style_navlink_view2'=>'','conf_style_navlink_icon2'=>'mail-bulk','conf_style_navlink3'=>'','conf_style_navlink_view3'=>'','conf_style_navlink_icon3'=>'search','conf_style_navlink4'=>'','conf_style_navlink_view4'=>'','conf_style_navlink_icon4'=>'images']];return true;}
if(str_contains($this->sql,'FROM `ig_dashboard_widget` w')){$this->rows=[
['widget_id'=>21,'widget_owner'=>1,'widget_location'=>0,'widget_type'=>'feed','widget_reference_id'=>201,'widget_sort_order'=>10,'widget_width'=>1,'widget_height'=>2,'widget_style'=>'success','widget_config'=>'{"schema":1,"item_limit":"auto"}','widget_flag'=>0,'widget_created_at'=>'2026-08-07 00:00:00','widget_updated_at'=>'2026-08-07 00:00:00','content_id'=>201,'content_date'=>'2026-08-07 00:00:00','content_flag'=>0,'content_owner'=>1,'content_location'=>0,'content_style'=>'success','content_value'=>'https://example.com/feed.xml','memo_id'=>null,'memo_date'=>null,'memo_updated_at'=>null,'memo_flag'=>null,'memo_owner'=>null,'memo_title'=>null,'memo_body'=>null],
['widget_id'=>22,'widget_owner'=>1,'widget_location'=>0,'widget_type'=>'clock','widget_reference_id'=>null,'widget_sort_order'=>20,'widget_width'=>2,'widget_height'=>1,'widget_style'=>'primary','widget_config'=>'{"schema":1,"title":"Clock","hour_format":"24","show_seconds":false,"show_date":true}','widget_flag'=>0,'widget_created_at'=>'2026-08-07 00:00:00','widget_updated_at'=>'2026-08-07 00:00:00','content_id'=>null,'content_date'=>null,'content_flag'=>null,'content_owner'=>null,'content_location'=>null,'content_style'=>null,'content_value'=>null,'memo_id'=>null,'memo_date'=>null,'memo_updated_at'=>null,'memo_flag'=>null,'memo_owner'=>null,'memo_title'=>null,'memo_body'=>null]
];return true;}
if(str_contains($this->sql,'FROM ig_content_stock'))return true;throw new RuntimeException('Unexpected SQL: '.$this->sql);}public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->rows;}}
final class HPDO extends PDO {public function __construct(){}public function prepare(string $q,array $o=[]):PDOStatement|false{return new HStmt($q);}}
set_db_connection_for_testing(new HPDO());app_session_start();app_session_login(1);ob_start();require $root.'/public/index.php';$html=ob_get_clean();app_session_logout();echo base64_encode($html);
''')

class Parser(HTMLParser):
    def __init__(self): super().__init__(convert_charrefs=True); self.records=[]
    def handle_starttag(self,tag,attrs): self.records.append((tag,{str(k):'' if v is None else str(v) for k,v in attrs}))

with tempfile.TemporaryDirectory(prefix='v17h-render-') as temp:
    wp=Path(temp)/'worker.php';wp.write_text(worker,encoding='utf-8')
    result=subprocess.run(['php',str(wp),str(ROOT)],cwd=ROOT,text=True,capture_output=True,timeout=30)
check(result.returncode==0,'V1.7-H Dashboard render exits successfully')
check(result.stderr.strip()=='','V1.7-H Dashboard render has no PHP warning')
html=base64.b64decode(result.stdout.strip()).decode('utf-8') if result.returncode==0 else ''
p=Parser();p.feed(html)
grids=[a for _,a in p.records if 'dashboard-grid' in a.get('class','').split()]
check(len(grids)==1,'Dashboard content uses one CSS Grid container')
widgets=[a for _,a in p.records if a.get('data-dashboard-widget-id')]
check([a.get('data-dashboard-widget-id') for a in widgets]==['21','22'],'Widget DOM order remains widget_sort_order')
check(widgets[0].get('data-widget-height')=='2','height 2 is rendered on the Feed Widget')
check(widgets[0].get('data-feed-item-limit')=='auto','Feed Widget exposes automatic item limit')
check(widgets[1].get('data-widget-height')=='1','height 1 is rendered on the Clock Widget')
check(widgets[0].get('data-widget-width')=='1' and widgets[1].get('data-widget-width')=='2','existing horizontal widths remain explicit')
feed_edit=[a for _,a in p.records if 'content-edit-trigger' in a.get('class','').split()]
clock_edit=[a for _,a in p.records if 'clock-edit-trigger' in a.get('class','').split()]
check(len(feed_edit)==1 and feed_edit[0].get('data-widget-height')=='2','Feed edit trigger restores height 2')
check(len(feed_edit)==1 and feed_edit[0].get('data-feed-item-limit')=='auto','Feed edit trigger restores automatic item limit')
check(len(clock_edit)==1 and clock_edit[0].get('data-widget-height')=='1','Clock edit trigger restores height 1')
selects=[a for tag,a in p.records if tag=='select' and a.get('id','').endswith('Height')]
check(len(selects)==18,'nine current Widget add/edit pairs render vertical height selects')
check(html.count('>縦2段</option>')>=18,'every height select offers the vertical two-row option')
version_text=(ROOT/'app/version.php').read_text(encoding='utf-8')
version_match=re.search(r"APP_VERSION\s*=\s*'([^']+)'",version_text)
active_version=version_match.group(1) if version_match is not None else ''
check(active_version!='' and ('?v='+active_version) in html,'Dashboard assets use the active centralized version token')
ids=[a['id'] for _,a in p.records if a.get('id')]
check(len(ids)==len(set(ids)),'V1.7-H Dashboard has no duplicate ids')
if failures: raise SystemExit(1)
print('All V1.7-H Dashboard render checks passed.')
