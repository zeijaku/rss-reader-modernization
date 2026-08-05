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
putenv('REGISTRATION_ENABLED=true'); putenv('APP_LOG_ENABLED=false');
$_SERVER['REQUEST_METHOD']='GET'; $_SERVER['REQUEST_URI']='/?tab=0'; $_SERVER['SERVER_PROTOCOL']='HTTP/1.1'; $_SERVER['REMOTE_ADDR']='127.0.0.1'; $_SERVER['SERVER_PORT']='80'; $_GET['tab']='0';
require $root.'/app/bootstrap.php';
final class S extends PDOStatement {
    private array $rows=[];
    public function __construct(private string $sql){}
    public function execute(?array $params=null):bool{
        $this->rows=[];
        if(str_contains($this->sql,'FROM ig_user_conf')){
            $this->rows=[['conf_style'=>'bootstrap','conf_style_nav'=>'dark','conf_style_tabname1'=>'Base','conf_style_tabname2'=>'Maint','conf_style_tabname3'=>'IT','conf_style_tabname4'=>'Observe','conf_style_navlink1'=>'','conf_style_navlink_view1'=>'','conf_style_navlink_icon1'=>'map-marker-alt','conf_style_navlink2'=>'','conf_style_navlink_view2'=>'','conf_style_navlink_icon2'=>'mail-bulk','conf_style_navlink3'=>'','conf_style_navlink_view3'=>'','conf_style_navlink_icon3'=>'search','conf_style_navlink4'=>'','conf_style_navlink_view4'=>'','conf_style_navlink_icon4'=>'images']]; return true;
        }
        if(str_contains($this->sql,'FROM `ig_dashboard_widget` w')) return true;
        if(str_contains($this->sql,'FROM `ig_task`')) return true;
        if(str_contains($this->sql,'FROM ig_content_stock')) return true;
        throw new RuntimeException('Unexpected SQL: '.$this->sql);
    }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->rows;}
}
final class P extends PDO {public function __construct(){} public function prepare(string $q,array $o=[]):PDOStatement|false{return new S($q);}}
set_db_connection_for_testing(new P()); app_session_start(); app_session_login(1); ob_start(); require $root.'/public/index.php'; $html=ob_get_clean(); app_session_logout(); echo base64_encode($html);
''')

class Parser(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.records=[]
        self.text=[]
    def handle_starttag(self, tag, attrs):
        self.records.append((tag,{str(k):'' if v is None else str(v) for k,v in attrs}))
    def handle_data(self,data):
        self.text.append(data)

with tempfile.TemporaryDirectory(prefix='v11j-render-') as temp:
    worker_path=Path(temp)/'worker.php'
    worker_path.write_text(worker,encoding='utf-8')
    result=subprocess.run(['php',str(worker_path),str(ROOT)],cwd=ROOT,text=True,capture_output=True,timeout=30)

check(result.returncode==0,'authenticated Dashboard render exits successfully')
check(result.stderr.strip()=='','Account Settings render has no PHP warning')
html=base64.b64decode(result.stdout.strip()).decode('utf-8') if result.returncode==0 else ''
parser=Parser(); parser.feed(html)
records=parser.records
text=' '.join(parser.text)
ids=[attrs.get('id') for _,attrs in records if attrs.get('id')]

check(html.count('id="accountSettings"')==1,'Account Settings modal renders once')
modal=[a for _,a in records if a.get('id')=='accountSettings']
check(len(modal)==1 and modal[0].get('role')=='dialog','Account Settings modal keeps dialog semantics')
check(modal[0].get('aria-labelledby')=='accountSettingsTitle','Account Settings modal names its title')
check(html.count('id="accountEmailForm"')==1,'email form renders once')
check(html.count('id="accountPasswordForm"')==1,'password form renders once')
check('id="accountEmailForm"' in html and html.index('id="accountEmailForm"') < html.index('id="accountPasswordForm"'),'email and password forms remain separate and ordered')
check('data-target="#accountSettings"' in html and 'アカウント設定' in text,'Drawer renders Account Settings action')
check('メールアドレス変更' in text and 'パスワード変更' in text,'both Account Settings sections render')
check('現在のメールアドレスは画面には表示していません' in text,'current email storage limitation is explained')

email=[a for tag,a in records if tag=='input' and a.get('id')=='accountNewEmail']
check(len(email)==1 and email[0].get('type')=='email','new email uses native email input')
check(email[0].get('name')=='new_email' and email[0].get('maxlength')=='254','new email name and maximum length are bounded')
check(email[0].get('autocomplete')=='email' and 'required' in email[0],'new email has autocomplete and required attributes')
check('value' not in email[0],'new email is not prefilled')

password_inputs=[a for tag,a in records if tag=='input' and a.get('type')=='password' and a.get('id','').startswith('account')]
check(len(password_inputs)==4,'four Account Settings password inputs render')
check(all('value' not in a for a in password_inputs),'no Account Settings password input is prefilled')
current=[a for a in password_inputs if a.get('autocomplete')=='current-password']
new=[a for a in password_inputs if a.get('autocomplete')=='new-password']
check(len(current)==2,'both forms use current-password autocomplete')
check(len(new)==2,'new password and confirmation use new-password autocomplete')
check(all(a.get('maxlength')=='72' for a in password_inputs),'all password fields use configured maximum length')
check(all(a.get('minlength')=='12' for a in new),'new password fields use configured minimum length')
check(all('required' in a for a in password_inputs),'all password fields are required')
check('12文字以上72文字以下' in text,'password bounds are visible to the user')

check('user_email' not in html,'stored keyed email field is not rendered')
check('user_password' not in html,'stored password hash field is not rendered')
check('CurrentPass123!' not in html and '$2y$' not in html and '$argon' not in html,'credential values and hashes are absent from HTML')
check('RSS Reader Modernization V1.1-J / R1' in text or 'RSS Reader Modernization 1.1.0' in text or 'RSS Reader Modernization 1.2.0-dev.3' or 'RSS Reader Modernization 1.2.0-dev.4' in text,'Dashboard displays V1.1-J or final 1.1.0 version marker')
check('method="post" action="./logout.php"' in html,'existing CSRF-protected logout remains present')
check(len(ids)==len(set(ids)),'Dashboard output contains no duplicate IDs')

if failures:
    raise SystemExit(1)
print('All V1.1-J Dashboard render checks passed.')
