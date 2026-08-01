from __future__ import annotations

import base64
from pathlib import Path
import subprocess
import tempfile
import textwrap
from html.parser import HTMLParser

ROOT = Path(__file__).resolve().parents[1]
failures: list[str] = []

def check(condition: bool, message: str) -> None:
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failures.append(message)

worker = textwrap.dedent(r'''<?php
$root = $argv[1];
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');
putenv('DB_HOST=test');
putenv('DB_NAME=test');
putenv('DB_USER=test');
putenv('DB_PASSWORD=test');
putenv('REGISTRATION_ENABLED=true');
putenv('APP_LOG_ENABLED=false');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/?tab=0';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '80';
$_GET['tab'] = '0';

require $root . '/app/bootstrap.php';

final class M2cRenderStatement extends PDOStatement
{
    private array $rows = [];
    public function __construct(private string $sql) {}
    public function execute(?array $params = null): bool
    {
        if (str_contains($this->sql, 'FROM ig_user_conf')) {
            $this->rows = [[
                'conf_style' => 'bootstrap',
                'conf_style_nav' => 'dark',
                'conf_style_tabname1' => 'Base',
                'conf_style_tabname2' => 'Maint',
                'conf_style_tabname3' => 'IT',
                'conf_style_tabname4' => 'Observe',
                'conf_style_navlink1' => 'https://map.google.com/',
                'conf_style_navlink_view1' => 'Map',
                'conf_style_navlink_icon1' => 'map-marker-alt',
                'conf_style_navlink2' => 'https://mail.google.com/',
                'conf_style_navlink_view2' => 'Mail',
                'conf_style_navlink_icon2' => 'mail-bulk',
                'conf_style_navlink3' => 'https://www.google.com/',
                'conf_style_navlink_view3' => 'Search',
                'conf_style_navlink_icon3' => 'search',
                'conf_style_navlink4' => 'https://www.google.com/imghp',
                'conf_style_navlink_view4' => 'Image',
                'conf_style_navlink_icon4' => 'images',
            ]];
            return true;
        }
        if (str_contains($this->sql, 'FROM ig_content ')) {
            $this->rows = [];
            for ($i = 1; $i <= 8; $i++) {
                $this->rows[] = [
                    'content_id' => $i,
                    'content_owner' => 1,
                    'content_location' => 0,
                    'content_style' => $i % 2 === 0 ? 'info' : 'success',
                    'content_value' => 'https://example.com/feed' . $i . '.xml',
                    'content_flag' => 0,
                ];
            }
            return true;
        }
        if (str_contains($this->sql, 'FROM ig_content_stock')) {
            $this->rows = [];
            return true;
        }
        throw new RuntimeException('Unexpected dashboard render SQL: ' . $this->sql);
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
}

final class M2cRenderPDO extends PDO
{
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new M2cRenderStatement($query);
    }
}

set_db_connection_for_testing(new M2cRenderPDO());
app_session_start();
app_session_login(1);

ob_start();
require $root . '/public/index.php';
$html = ob_get_clean();
app_session_logout();
echo base64_encode($html);
''')

with tempfile.TemporaryDirectory(prefix='m2c-render-') as temp_dir:
    worker_path = Path(temp_dir) / 'worker.php'
    worker_path.write_text(worker, encoding='utf-8')
    result = subprocess.run(
        ['php', str(worker_path), str(ROOT)],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=False,
    )

check(result.returncode == 0, 'authenticated Dashboard render worker exits successfully')
check(result.stderr.strip() == '', 'authenticated Dashboard render has no PHP warning or stderr')
if result.returncode != 0:
    raise SystemExit(1)

html = base64.b64decode(result.stdout.strip()).decode('utf-8')


VOID_TAGS = {'area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr'}

class RenderParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.doctype = False
        self.stack: list[tuple[str, dict[str, str]]] = []
        self.records: list[tuple[str, dict[str, str], list[tuple[str, dict[str, str]]]]] = []
        self.text_parts: list[str] = []

    def handle_decl(self, decl: str) -> None:
        if decl.lower().strip() == 'doctype html':
            self.doctype = True

    def handle_starttag(self, tag: str, attrs) -> None:
        attr_map = {str(k): '' if v is None else str(v) for k, v in attrs}
        self.records.append((tag, attr_map, list(self.stack)))
        if tag not in VOID_TAGS:
            self.stack.append((tag, attr_map))

    def handle_startendtag(self, tag: str, attrs) -> None:
        self.handle_starttag(tag, attrs)
        if self.stack and self.stack[-1][0] == tag:
            self.stack.pop()

    def handle_endtag(self, tag: str) -> None:
        for index in range(len(self.stack) - 1, -1, -1):
            if self.stack[index][0] == tag:
                del self.stack[index:]
                return

    def handle_data(self, data: str) -> None:
        self.text_parts.append(data)

parser = RenderParser()
parser.feed(html)
records = parser.records
all_text = ''.join(parser.text_parts)

def has_ancestor(ancestors, tag: str, **attrs: str) -> bool:
    for ancestor_tag, ancestor_attrs in ancestors:
        if ancestor_tag != tag:
            continue
        if all(ancestor_attrs.get(key) == value for key, value in attrs.items()):
            return True
    return False

def matching(tag: str | None = None, **attrs: str):
    result = []
    for record_tag, record_attrs, ancestors in records:
        if tag is not None and record_tag != tag:
            continue
        ok = True
        for key, value in attrs.items():
            actual = record_attrs.get(key, '')
            if key == 'class':
                ok = value in actual.split()
            else:
                ok = actual == value
            if not ok:
                break
        if ok:
            result.append((record_tag, record_attrs, ancestors))
    return result

check(parser.doctype, 'rendered Dashboard keeps the HTML5 doctype')
check(bool(matching('html', lang='ja')), 'rendered Dashboard keeps lang=ja')
check(bool(matching('a', **{'class':'skip-link', 'href':'#main-content'})), 'rendered Dashboard exposes the skip link')
check(any(tag == 'nav' and attrs.get('aria-label') == 'メインナビゲーション' and has_ancestor(ancestors, 'header') for tag, attrs, ancestors in records), 'rendered Dashboard has named main navigation')
check(bool(matching('main', id='main-content', tabindex='-1')), 'rendered Dashboard has focusable main content')
check(any(tag == 'h1' and 'sr-only' in attrs.get('class','').split() and has_ancestor(ancestors, 'main', id='main-content') for tag, attrs, ancestors in records), 'rendered Dashboard has a page heading')
check(bool(matching('footer', **{'data-app-version':''})) and 'Frontend M2-F / R1' in all_text, 'rendered Dashboard exposes the current version')

ids = [attrs['id'] for _, attrs, _ in records if attrs.get('id')]
check(len(ids) == len(set(ids)), 'rendered Dashboard contains no duplicate id attributes')

feed_cards = [(tag, attrs, ancestors) for tag, attrs, ancestors in records if attrs.get('data-feed-content-id')]
check(len(feed_cards) == 8, 'rendered Dashboard contains all eight Fake PDO Feed cards')
check(all(tag == 'section' and attrs.get('role') == 'region' for tag, attrs, _ in feed_cards), 'all rendered Feed cards are region sections')
check(all(attrs.get('aria-busy') == 'true' for _, attrs, _ in feed_cards), 'all rendered Feed cards start aria-busy')
check(all(attrs.get('aria-labelledby') in ids for _, attrs, _ in feed_cards), 'all Feed regions reference an existing title')
check(len([1 for tag, attrs, _ in records if tag == 'button' and 'content-edit-trigger' in attrs.get('class','').split() and attrs.get('aria-label') == 'このRSSを編集']) == 8, 'all Feed edit controls are named buttons')
check(len([1 for tag, attrs, _ in records if tag == 'tbody' and 'content-body' in attrs.get('class','').split() and attrs.get('aria-live') == 'polite']) == 8, 'all Feed bodies are polite live regions')
check(len([1 for tag, attrs, ancestors in records if tag == 'td' and attrs.get('role') == 'status' and any(a_attrs.get('class','').find('content-body') >= 0 for a_tag, a_attrs in ancestors if a_tag == 'tbody')]) == 8, 'all initial Feed messages use status semantics')
check(not any(tag == 'td' and has_ancestor(ancestors, 'thead') for tag, _, ancestors in records) and len([1 for tag, attrs, ancestors in records if tag == 'th' and attrs.get('scope') == 'col' and has_ancestor(ancestors, 'thead')]) == 8, 'Feed table headers use th instead of td')

check(any(tag == 'button' and attrs.get('type') == 'submit' and has_ancestor(ancestors, 'form', id='registerContentForm') for tag, attrs, ancestors in records), 'rendered RSS add modal has a submit form')
check(any(tag == 'button' and attrs.get('type') == 'submit' and has_ancestor(ancestors, 'form', id='changeContentForm') for tag, attrs, ancestors in records), 'rendered RSS change modal has a submit form')
label_fors = {attrs.get('for') for tag, attrs, _ in records if tag == 'label' and attrs.get('for')}
check('style_select' in label_fors and bool(matching('select', id='style_select')), 'rendered RSS style label points to its select')
check(len(matching('fieldset', **{'class':'navbar-link-setting'})) == 4, 'rendered Settings groups four Navbar links')
check(len([1 for tag, attrs, ancestors in records if tag == 'legend' and has_ancestor(ancestors, 'fieldset', **{'class':'navbar-icon-setting'})]) == 4, 'rendered icon radio groups have legends')

check(not any(tag == 'li' and attrs.get('data-toggle') == 'modal' for tag, attrs, _ in records), 'rendered Drawer has no clickable list-item pseudo controls')
check(len(matching('button', **{'class':'drawer-menu-action'})) == 3, 'rendered Drawer modal actions are buttons')
check(len([1 for tag, attrs, _ in records if tag == 'button' and 'drawer-toggle' in attrs.get('class','').split() and attrs.get('aria-controls') == 'drawerMenu']) == 2, 'rendered Drawer has two named trigger buttons')
check(bool(matching('nav', id='drawerMenu', **{'aria-label':'RSS Readerメニュー'})), 'rendered Drawer navigation has an accessible name')
check(bool(matching('a', href='#main-content', **{'aria-label':'ページ先頭へ移動'})), 'rendered Page Top targets main content')

unlabelled = []
for tag, attrs, _ in records:
    if tag not in {'input','select','textarea'} or not attrs.get('id'):
        continue
    if tag == 'input' and attrs.get('type') in {'hidden','radio'}:
        continue
    if attrs['id'] not in label_fors:
        unlabelled.append(attrs['id'])
check(not unlabelled, 'rendered visible form controls have associated labels')

if failures:
    raise SystemExit(1)
print('All M2-C authenticated Dashboard render checks passed.')
