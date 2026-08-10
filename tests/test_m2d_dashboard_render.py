from pathlib import Path
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
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
check(render.returncode == 0, 'current Feed/Stock Dashboard render fixture passes')
if render.returncode != 0:
    print(render.stdout)
    print(render.stderr, file=sys.stderr)

check('dashboard_widget_width_class(1)' in index, 'Feed Widget has the existing responsive width fallback')
check("'col-12 col-md-6 col-lg-3'" in (ROOT / 'app/dashboard_widget.php').read_text(encoding='utf-8'), 'width=1 keeps the 1/2/4 column layout')
check("'col-12 col-md-12 col-lg-6'" in (ROOT / 'app/dashboard_widget.php').read_text(encoding='utf-8'), 'width=2 responsive layout is available')
check('row content-grid feed-grid' in index, 'Feed Widgets share the existing grid row')
check('feed-card-inner' in index and 'feed-table' in index, 'Feed card inner/table surfaces remain stable')
check('feed-stock-column' in index and 'feed-summary-column' in index and '.feed-stock-column' in css and '.feed-summary-column' in css, 'Feed Stock and summary column hooks remain')
check(css.count('width: 44px') >= 2 and 'min-height: 44px' in css, 'Feed Stock and summary controls retain touch-sized columns')
check('id="app-notice"' in index and 'aria-live="polite"' in index, 'shared in-page notice remains accessible')
check('class="btn btn-outline-danger delete_content"' in index and 'type="button"' in index, 'RSS edit modal keeps explicit delete action')
check('追加先：' in index, 'RSS add modal still shows its destination tab')
check('article class="stock-card"' in index and 'col-md-6 col-lg-3 stock-card' not in index and '.stock-grid' in css, 'Stock uses the Version 1.8 compact one-column list')
check('stock-title' in index and '.stock-title' in css, 'Stock title wrapping hook remains')
check('@media (pointer: coarse)' in css and 'min-height: 44px' in css, 'coarse pointer targets remain touch-sized')
check('.dashboard-widget' in css and 'min-width: 0' in css, 'Widget base rule prevents responsive overflow')
check('APP_VERSION_LABEL' in index, 'Dashboard version display is not fixed to Version 1.0.0')

if failures:
    raise SystemExit(1)
print('All M2-D Dashboard render checks passed.')
