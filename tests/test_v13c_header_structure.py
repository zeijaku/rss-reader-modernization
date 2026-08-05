from __future__ import annotations

import base64
from html.parser import HTMLParser
from pathlib import Path
import re
import subprocess
import tempfile
import textwrap

ROOT = Path(__file__).resolve().parents[1]
failures: list[str] = []
count = 0


def check(condition: bool, message: str) -> None:
    global count
    count += 1
    ok = bool(condition)
    print(('PASS' if ok else 'FAIL') + ': ' + message)
    if not ok:
        failures.append(message)


index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
js = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')

version_match = re.search(r"const APP_VERSION = '([0-9]+)\.([0-9]+)\.([0-9]+)(?:-dev\.[0-9]+)?';", version)
version_tuple = tuple(int(part) for part in version_match.groups()) if version_match else (0, 0, 0)
check(version_tuple >= (1, 3, 0), 'V1.3-C or later Version is visible')
check('$currentViewName' in index and '$tab_name' not in index, 'Header current-page label is separated from the legacy concatenated brand text')
check("$navbarScheme = $navbarBackground === 'light' ? 'light' : 'dark';" in index, 'Light uses navbar-light while dark and primary use navbar-dark')
check('header class="app-header"' in index, 'Header has a dedicated application header class')
check('class="navbar navbar-expand-lg navbar-<?php echo app_html($navbarScheme); ?> bg-<?php echo app_html($navbarBackground); ?> app-navbar"' in index, 'Navbar separates contrast scheme from selected background')
check('class="navbar-brand app-navbar-brand"' in index and 'aria-label="iGuguru ホーム"' in index, 'Brand is a clearly named home link')
check('class="app-navbar-current"' in index and '現在の表示：' in index, 'Current page is a separate accessible text region')
check('class="app-navbar-current-label"' in index and 'app_html($currentViewName)' in index, 'Current page label is safely escaped')
check(index.count('aria-controls="drawerMenu" aria-expanded="false" aria-label="メニューを開く"') == 2, 'Mobile and desktop menu buttons share Drawer ARIA state')
check(index.count('class="fas fa-bars" aria-hidden="true"') >= 2, 'Both menu buttons use the same existing Font Awesome icon')
check('class="navbar-nav ml-auto app-navbar-links"' in index, 'Desktop external links are grouped at the right side')
check('class="nav-link app-navbar-link"' in index and 'app-navbar-link-label' in index, 'External links use shared icon and truncating label layout')
check('target="_blank" rel="noopener noreferrer"' in index, 'External links retain safe new-tab attributes')
check('navbar-toggler-icon' not in index, 'Header no longer depends on Bootstrap background-image toggler icons')
check('.app-navbar {' in css and 'min-height: 56px;' in css, 'Header uses a consistent 56px minimum height')
check('.app-navbar-identity {' in css and 'min-width: 0;' in css and 'overflow: hidden;' in css, 'Brand and current page can shrink without horizontal overflow')
check('.app-navbar-current {' in css and 'text-overflow: ellipsis;' in css and 'white-space: nowrap;' in css, 'Long current-page names truncate on one line')
check('.app-navbar-menu-button {' in css and 'width: 44px;' in css and 'height: 44px;' in css, 'Menu buttons keep 44px pointer and touch targets')
check('.navbar-dark .app-navbar-menu-button' in css and '.navbar-light .app-navbar-menu-button' in css, 'Menu contrast is defined for dark and light Navbar schemes')
check('@media (max-width: 575.98px)' in css and '.app-navbar-current {' in css, 'Narrow smartphone spacing and text sizing are explicit')
check('app-navbar' not in js and 'app-header' not in js, 'Header layout requires no new JavaScript dependency')

worker = textwrap.dedent(r'''<?php
$root = $argv[1];
$tab = $argv[2];
$nav = $argv[3];
putenv('APP_ENV=testing'); putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql'); putenv('DB_TABLE_PREFIX=ig_'); putenv('DB_HOST=test'); putenv('DB_NAME=test'); putenv('DB_USER=test'); putenv('DB_PASSWORD=test');
putenv('REGISTRATION_ENABLED=true'); putenv('APP_LOG_ENABLED=false');
$_SERVER['REQUEST_METHOD']='GET'; $_SERVER['REQUEST_URI']='/?tab='.$tab; $_SERVER['SERVER_PROTOCOL']='HTTP/1.1'; $_SERVER['REMOTE_ADDR']='127.0.0.1'; $_SERVER['SERVER_PORT']='80'; $_GET['tab']=$tab;
require $root.'/app/bootstrap.php';
final class S13C extends PDOStatement {
    private array $rows=[];
    public function __construct(private string $sql, private string $nav){}
    public function execute(?array $params=null):bool{
        $this->rows=[];
        if(str_contains($this->sql,'FROM ig_user_conf')){
            $this->rows=[[ 
                'conf_style'=>'bootstrap', 'conf_style_nav'=>$this->nav,
                'conf_style_tabname1'=>'とても長い現在タブ名確認用',
                'conf_style_tabname2'=>'Maint', 'conf_style_tabname3'=>'IT', 'conf_style_tabname4'=>'Observe',
                'conf_style_navlink1'=>'https://example.com/map', 'conf_style_navlink_view1'=>'Map Link', 'conf_style_navlink_icon1'=>'map-marker-alt',
                'conf_style_navlink2'=>'https://example.com/mail', 'conf_style_navlink_view2'=>'Mail Link', 'conf_style_navlink_icon2'=>'mail-bulk',
                'conf_style_navlink3'=>'https://example.com/search', 'conf_style_navlink_view3'=>'Search', 'conf_style_navlink_icon3'=>'search',
                'conf_style_navlink4'=>'https://example.com/image', 'conf_style_navlink_view4'=>'Image', 'conf_style_navlink_icon4'=>'images'
            ]]; return true;
        }
        if(str_contains($this->sql,'FROM `ig_dashboard_widget` w')) return true;
        if(str_contains($this->sql,'FROM `ig_task`')) return true;
        if(str_contains($this->sql,'FROM ig_content_stock')) return true;
        throw new RuntimeException('Unexpected SQL: '.$this->sql);
    }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->rows;}
}
final class P13C extends PDO {
    public function __construct(private string $nav){}
    public function prepare(string $q,array $o=[]):PDOStatement|false{return new S13C($q,$this->nav);}
}
set_db_connection_for_testing(new P13C($nav)); app_session_start(); app_session_login(1);
ob_start(); require $root.'/public/index.php'; $html=ob_get_clean(); app_session_logout(); echo base64_encode($html);
''')

class Parser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.records: list[tuple[str, dict[str, str]]] = []
        self.text: list[str] = []
    def handle_starttag(self, tag, attrs):
        self.records.append((tag, {str(k): '' if v is None else str(v) for k, v in attrs}))
    def handle_data(self, data):
        self.text.append(data)

with tempfile.TemporaryDirectory(prefix='v13c-header-') as temp:
    worker_path = Path(temp) / 'worker.php'
    worker_path.write_text(worker, encoding='utf-8')
    for tab, expected_current in [('0', 'とても長い現在タブ名確認用'), ('stock', 'Stock')]:
        for nav, scheme in [('dark', 'dark'), ('primary', 'dark'), ('light', 'light')]:
            result = subprocess.run(['php', str(worker_path), str(ROOT), tab, nav], cwd=ROOT, text=True, capture_output=True, timeout=30)
            check(result.returncode == 0, f'{tab}/{nav}: authenticated Dashboard render exits successfully')
            check(result.stderr.strip() == '', f'{tab}/{nav}: Header render has no PHP warning')
            if result.returncode != 0:
                continue
            html = base64.b64decode(result.stdout.strip()).decode('utf-8')
            parser = Parser(); parser.feed(html)
            records = parser.records
            navs = [a for tag, a in records if tag == 'nav' and 'app-navbar' in a.get('class', '').split()]
            check(len(navs) == 1, f'{tab}/{nav}: one application Navbar renders')
            if navs:
                classes = navs[0].get('class', '').split()
                check(f'navbar-{scheme}' in classes and f'bg-{nav}' in classes, f'{tab}/{nav}: contrast scheme and background classes are correct')
            check(f'<span class="app-navbar-current-label">{expected_current}</span>' in html, f'{tab}/{nav}: current page text renders separately from brand')
            check('iGuguru - [' not in html, f'{tab}/{nav}: legacy combined brand text is absent')
            check(html.count('class="nav-link app-navbar-link"') == 4, f'{tab}/{nav}: configured desktop links render exactly once')
            check(html.count('aria-controls="drawerMenu" aria-expanded="false" aria-label="メニューを開く"') == 2, f'{tab}/{nav}: both menu buttons render with shared ARIA')

if failures:
    print(f'RESULT: PASS {count-len(failures)} / FAIL {len(failures)} / SKIP 0')
    raise SystemExit(1)
print(f'RESULT: PASS {count} / FAIL 0 / SKIP 0')
