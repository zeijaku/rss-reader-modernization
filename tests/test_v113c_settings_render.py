from __future__ import annotations
import base64
from html.parser import HTMLParser
from pathlib import Path
import subprocess
import tempfile
import textwrap

ROOT = Path(__file__).resolve().parents[1]
failures = []
def check(cond, msg):
    print(('PASS' if cond else 'FAIL') + ': ' + msg)
    if not cond:
        failures.append(msg)

worker = textwrap.dedent(r'''<?php
$root=$argv[1];
putenv('APP_ENV=testing'); putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql'); putenv('DB_TABLE_PREFIX=ig_'); putenv('DB_HOST=test'); putenv('DB_NAME=test'); putenv('DB_USER=test'); putenv('DB_PASSWORD=test');
putenv('APP_LOG_ENABLED=false');
$_SERVER['REQUEST_METHOD']='GET'; $_SERVER['REQUEST_URI']='/settings'; $_SERVER['SERVER_PROTOCOL']='HTTP/1.1'; $_SERVER['REMOTE_ADDR']='127.0.0.1'; $_SERVER['SERVER_PORT']='80';
require $root.'/app/bootstrap.php';
final class V113cStatement extends PDOStatement {
    private array $rows=[];
    public function __construct(private string $sql){}
    public function execute(?array $params=null):bool{
        $this->rows=[];
        if(str_contains($this->sql,'FROM ig_user_conf')){
            $this->rows=[[
                'conf_style'=>'bootstrap-minty','conf_style_nav'=>'primary',
                'conf_style_tabname1'=>'Base <One>','conf_style_tabname2'=>'Maint','conf_style_tabname3'=>'IT','conf_style_tabname4'=>'Observe',
                'conf_style_navlink1'=>'https://example.com/?a=1&b=2','conf_style_navlink_view1'=>'Example & Link','conf_style_navlink_icon1'=>'search',
                'conf_style_navlink2'=>'','conf_style_navlink_view2'=>'','conf_style_navlink_icon2'=>'mail-bulk',
                'conf_style_navlink3'=>'','conf_style_navlink_view3'=>'','conf_style_navlink_icon3'=>'search',
                'conf_style_navlink4'=>'','conf_style_navlink_view4'=>'','conf_style_navlink_icon4'=>'images'
            ]]; return true;
        }
        if(str_contains($this->sql,'FROM `ig_feed_keyword`')){
            $this->rows=[
                ['keyword_id'=>7,'keyword_value'=>'OpenAI <alert>'],
                ['keyword_id'=>8,'keyword_value'=>'PHP & MySQL']
            ]; return true;
        }
        throw new RuntimeException('Unexpected SQL: '.$this->sql);
    }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->rows;}
}
final class V113cPDO extends PDO {public function __construct(){} public function prepare(string $q,array $o=[]):PDOStatement|false{return new V113cStatement($q);}}
set_db_connection_for_testing(new V113cPDO());
app_session_start(); app_session_login(1);
ob_start(); require $root.'/public/settings.php'; $html=ob_get_clean();
app_session_logout(); echo base64_encode($html);
''')

class Parser(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=False)
        self.records=[]
        self.text=[]
    def handle_starttag(self, tag, attrs):
        self.records.append((tag,{str(k):'' if v is None else str(v) for k,v in attrs}))
    def handle_data(self,data):
        self.text.append(data)

with tempfile.TemporaryDirectory(prefix='v113c-render-') as temp:
    worker_path=Path(temp)/'worker.php'
    worker_path.write_text(worker,encoding='utf-8')
    result=subprocess.run(['php',str(worker_path),str(ROOT)],cwd=ROOT,text=True,capture_output=True,timeout=30)

check(result.returncode==0,'authenticated Settings render exits successfully')
check(result.stderr.strip()=='','Settings render has no PHP warning/error')
html=base64.b64decode(result.stdout.strip()).decode('utf-8') if result.returncode==0 else ''
parser=Parser(); parser.feed(html)
records=parser.records
ids=[attrs.get('id') for _,attrs in records if attrs.get('id')]

check(html.count('id="settingsForm"')==1,'Display Settings form renders once')
check(html.count('id="tabsForm"')==1,'Tab Settings form renders once')
check(html.count('id="rssHighlightKeywordForm"')==1,'RSS Highlight form renders once')
check(html.count('id="accountSettings"')==1,'Account Settings remains a separate modal')
check('id="changeConf"' not in html and 'id="tabContent"' not in html and 'id="rssHighlightSettings"' not in html,'legacy Settings modal containers are absent')
check('id="display"' in html and 'id="tabs"' in html and 'id="highlight"' in html,'three Settings anchors render')
check('Settings' in ''.join(parser.text),'Settings page has visible current-view label/title')

check('bootstrap-minty-5.3.8.min.css' in html,'saved theme controls Settings page theme asset')
check('navbar-dark' in html and 'bg-primary' in html,'saved Navbar style is applied')
check('value="bootstrap-minty" selected' in html,'saved theme is selected in form')
check('value="primary" selected' in html,'saved Navbar style is selected in form')
check('conf_style_navlink_icon1_search' in html and 'value="search" checked' in html,'saved Navbar icon remains checked')

check('Base &lt;One&gt;' in html and 'Base <One>' not in html,'tab name is HTML escaped')
check('Example & Link' not in html and '>Map</span>' in html,'invalid Navbar label is normalized by existing UI validation')
check('https://example.com/?a=1&amp;b=2' in html,'Navbar URL attribute is HTML escaped')
check('OpenAI &lt;alert&gt;' in html and 'OpenAI <alert>' not in html,'Highlight keyword is HTML escaped')
check('PHP &amp; MySQL' in html,'Highlight ampersand is HTML escaped')
check('"keyword_value":"OpenAI \\u003Calert\\u003E"' in html,'Highlight JSON hex-escapes tag characters')

check('href="./settings#display"' in html and 'href="./settings#tabs"' in html and 'href="./settings#highlight"' in html,'Settings Drawer uses extensionless section URLs')
check('href="./stock"' in html,'Settings Drawer uses extensionless Stock URL')
check('method="post" action="./logout.php"' in html and 'name="csrf_token"' in html,'Settings logout stays POST + CSRF')
check('meta name="csrf-token"' in html,'Settings renders API CSRF meta')
check('<meta name="robots" content="noindex,nofollow">' in html,'Settings render stays noindex/nofollow')
check('js/mini-game.js' not in html and 'js/clock-timer.js' not in html and 'js/calendar.js' not in html,'Settings omits unrelated Dashboard scripts')
check('js/dashboard.js' in html and 'js/bootstrap.bundle-5.3.8.min.js' in html,'Settings loads shared interaction scripts')
check(len(ids)==len(set(ids)),'Settings output contains no duplicate IDs')

if failures:
    print(f'RESULT: FAIL {len(failures)}')
    raise SystemExit(1)
print('RESULT: PASS')
