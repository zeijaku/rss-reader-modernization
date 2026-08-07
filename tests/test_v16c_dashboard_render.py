from __future__ import annotations
import re
import base64
from html.parser import HTMLParser
from pathlib import Path
import subprocess, tempfile, textwrap

ROOT = Path(__file__).resolve().parents[1]
failures=[]
def check(condition,message):
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition: failures.append(message)

worker=textwrap.dedent(r'''<?php
$root=$argv[1];
putenv('APP_ENV=testing');putenv('APP_DEBUG=false');putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');putenv('DB_DRIVER=mysql');putenv('DB_TABLE_PREFIX=ig_');putenv('DB_HOST=test');putenv('DB_NAME=test');putenv('DB_USER=test');putenv('DB_PASSWORD=test');putenv('REGISTRATION_ENABLED=true');putenv('APP_LOG_ENABLED=false');
$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/?tab=0';$_SERVER['SERVER_PROTOCOL']='HTTP/1.1';$_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['SERVER_PORT']='80';$_GET['tab']='0';
require $root.'/app/bootstrap.php';
final class V16cStatement extends PDOStatement {private array $rows=[];public function __construct(private string $sql){}public function execute(?array $params=null):bool{$params??=[];$this->rows=[];if(str_contains($this->sql,'FROM ig_user_conf')){$this->rows=[['conf_style'=>'bootstrap','conf_style_nav'=>'dark','conf_style_tabname1'=>'Base','conf_style_tabname2'=>'Two','conf_style_tabname3'=>'Three','conf_style_tabname4'=>'Four','conf_style_navlink1'=>'','conf_style_navlink_view1'=>'','conf_style_navlink_icon1'=>'map-marker-alt','conf_style_navlink2'=>'','conf_style_navlink_view2'=>'','conf_style_navlink_icon2'=>'mail-bulk','conf_style_navlink3'=>'','conf_style_navlink_view3'=>'','conf_style_navlink_icon3'=>'search','conf_style_navlink4'=>'','conf_style_navlink_view4'=>'','conf_style_navlink_icon4'=>'images']];return true;}if(str_contains($this->sql,'FROM `ig_dashboard_widget` w')){if((int)($params[':owner']??0)!==1||(int)($params[':location']??-1)!==0)return true;$base=['widget_owner'=>1,'widget_location'=>0,'widget_reference_id'=>null,'widget_flag'=>0,'widget_created_at'=>'2026-08-06 23:00:00','widget_updated_at'=>'2026-08-06 23:00:00','content_id'=>null,'content_date'=>null,'content_flag'=>null,'content_owner'=>null,'content_location'=>null,'content_style'=>null,'content_value'=>null,'memo_id'=>null,'memo_date'=>null,'memo_updated_at'=>null,'memo_flag'=>null,'memo_owner'=>null,'memo_title'=>null,'memo_body'=>null];$this->rows=[array_merge($base,['widget_id'=>41,'widget_type'=>'game','widget_sort_order'=>10,'widget_width'=>1,'widget_style'=>'warning','widget_config'=>'{"schema":1,"title":"Lights Out","game":"lights_out"}']),array_merge($base,['widget_id'=>42,'widget_type'=>'game','widget_sort_order'=>20,'widget_width'=>1,'widget_style'=>'secondary','widget_config'=>'{"schema":1,"title":"Icon Quest","game":"icon_quest"}'])];return true;}if(str_contains($this->sql,'FROM ig_content_stock'))return true;throw new RuntimeException('Unexpected SQL: '.$this->sql);}public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->rows;}}
final class V16cPDO extends PDO {public function __construct(){}public function prepare(string $query,array $options=[]):PDOStatement|false{return new V16cStatement($query);}}
set_db_connection_for_testing(new V16cPDO());app_session_start();app_session_login(1);ob_start();require $root.'/public/index.php';$html=ob_get_clean();app_session_logout();echo base64_encode($html);
''')
class Parser(HTMLParser):
    def __init__(self):super().__init__(convert_charrefs=True);self.records=[];self.text=[]
    def handle_starttag(self,tag,attrs):self.records.append((tag,{str(k):'' if v is None else str(v) for k,v in attrs}))
    def handle_data(self,data):self.text.append(data)
with tempfile.TemporaryDirectory(prefix='v16c-render-') as temp:
    worker_path=Path(temp)/'worker.php';worker_path.write_text(worker)
    result=subprocess.run(['php',str(worker_path),str(ROOT)],cwd=ROOT,text=True,capture_output=True,timeout=30)
check(result.returncode==0,'mixed Icon Quest and Lights Out render exits successfully')
check(result.stderr.strip()=='','mixed Game render has no PHP warning')
html=base64.b64decode(result.stdout.strip()).decode() if result.returncode==0 else ''
parser=Parser();parser.feed(html)
games=[attrs for _,attrs in parser.records if attrs.get('data-dashboard-widget-type')=='game']
check([g.get('data-mini-game-type') for g in games]==['lights_out','icon_quest'],'both Game subtypes render in Widget order')
lights=[attrs for tag,attrs in parser.records if tag=='button' and 'lights-out-cell' in attrs.get('class','').split()]
icons=[attrs for tag,attrs in parser.records if tag=='button' and 'mini-game-cell' in attrs.get('class','').split() and 'lights-out-cell' not in attrs.get('class','').split()]
check(len(lights)==25,'Lights Out renders exactly 25 cells')
check(len(icons)==25,'Icon Quest still renders exactly 25 cells')
check(all(c.get('aria-pressed')=='false' and c.get('aria-rowindex') and c.get('aria-colindex') for c in lights),'Lights Out cells expose initial state and coordinates')
check(html.count('lights-out-reset')==1 and html.count('lights-out-new-game')==1,'Lights Out renders only the requested two controls')
check('lights-out-moves' in html and 'lights-out-result' in html,'Moves and Clear result render')
check('mini-game-direction' in html and 'mini-game-storage-reset' in html,'Icon Quest controls remain unchanged')
check(html.count('value="lights_out"')==2,'add and edit forms both offer Lights Out')
version_text = (ROOT / 'app/version.php').read_text(encoding='utf-8')
version_match = re.search(r"const APP_VERSION = '([^']+)';", version_text)
current_version = version_match.group(1) if version_match else ''
check(f'./js/lights-out.js?v={current_version}' in html and f'./css/mini-game.css?v={current_version}' in html, 'new and changed assets use the active Cache Busting strategy')
ids=[attrs['id'] for _,attrs in parser.records if attrs.get('id')]
check(len(ids)==len(set(ids)),'mixed Game render has no duplicate ids')
if failures:raise SystemExit(1)
print('All V1.6-C Dashboard render checks passed.')
