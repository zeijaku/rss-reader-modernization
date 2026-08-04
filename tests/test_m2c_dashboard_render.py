from pathlib import Path
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
failures: list[str] = []


def check(condition: bool, message: str) -> None:
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failures.append(message)


render = subprocess.run(
    [sys.executable, str(ROOT / 'tests/test_v11d_dashboard_render.py')],
    cwd=ROOT,
    text=True,
    capture_output=True,
    check=False,
    timeout=60,
)
check(render.returncode == 0, 'current authenticated Dashboard render fixture passes')
if render.returncode != 0:
    print(render.stdout)
    print(render.stderr, file=sys.stderr)

check('<!doctype html>' in index.lower(), 'Dashboard keeps the HTML5 doctype')
check('<html lang="ja">' in index, 'Dashboard keeps lang=ja')
check('class="skip-link" href="#main-content"' in index, 'Dashboard exposes the skip link')
check('aria-label="メインナビゲーション"' in index, 'main navigation has an accessible name')
check('id="main-content"' in index and 'tabindex="-1"' in index, 'main content remains focusable')
check('<h1 class="sr-only">' in index, 'Dashboard keeps a page heading for assistive technology')
check('data-app-version' in index and 'APP_VERSION_LABEL' in index, 'Dashboard exposes the current dynamic Version marker')
check('role="region" aria-labelledby="feed-title-' in index, 'Feed Widgets remain named regions')
check('aria-busy="true"' in index, 'Feed Widgets expose initial loading state')
check('aria-live="polite" aria-relevant="all"' in index, 'Feed bodies remain polite live regions')
check('scope="col"' in index and '<th colspan="3"' in index, 'Feed table headings use th and scope')
check('aria-label="このRSSを編集"' in index, 'Feed edit controls remain named buttons')
check('id="registerContentForm"' in index and 'id="changeContentForm"' in index, 'RSS add/change forms remain explicit')
check('class="navbar-link-setting"' in index and 'class="navbar-icon-setting"' in index, 'Settings fieldset grouping remains')
check('aria-controls="drawerMenu"' in index and 'aria-expanded="false"' in index, 'Drawer triggers keep expanded-state semantics')
check('id="drawerMenu"' in index and 'aria-label="RSS Readerメニュー"' in index, 'Drawer navigation remains named')
check('aria-label="ページ先頭へ移動"' in index and 'href="#main-content"' in index, 'Page Top remains keyboard-accessible')
check('data-dashboard-widget-id' in index, 'current render path includes Dashboard Widget identity hooks')

if failures:
    raise SystemExit(1)
print('All M2-C authenticated Dashboard render checks passed.')
