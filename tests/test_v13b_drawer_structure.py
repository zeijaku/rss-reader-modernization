from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
js = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
drawer = index[index.index('<!-- Drawer -->', index.index('<footer')):index.index('<!-- Bootstrap -->', index.index('<footer'))]

checks = []

def check(condition, message):
    ok = bool(condition)
    checks.append(ok)
    print(('PASS' if ok else 'FAIL') + ': ' + message)

check("APP_VERSION = '1.3.0-dev.1'" in version or "APP_VERSION = '1.3.0-dev.2'" in version or "APP_VERSION = '1.3.0-dev.3'" in version, 'V1.3-B or later development Version is visible')
check("APP_VERSION_LABEL = 'RSS Reader Modernization 1.3.0-dev.1'" in version or "APP_VERSION_LABEL = 'RSS Reader Modernization 1.3.0-dev.2'" in version or "APP_VERSION_LABEL = 'RSS Reader Modernization 1.3.0-dev.3'" in version, 'V1.3-B or later label is visible')

headings = ['表示', 'Widget追加', 'カスタマイズ', 'リンク', 'Account']
positions = [drawer.index('>' + label + '<') for label in headings]
check(positions == sorted(positions), 'Drawer groups follow Display, Widget, Customize, Links, Account order')

for label in ['Stock一覧', 'RSS追加', 'Search Feed追加', 'Task追加', 'Calendar追加', 'Clock追加', 'Memo追加', 'タブ表示変更', '表示設定', 'アカウント設定', 'ログアウト']:
    check(label in drawer, f'Drawer keeps the existing action: {label}')

widget_order = [drawer.index(label) for label in ['RSS追加', 'Search Feed追加', 'Task追加', 'Calendar追加', 'Clock追加', 'Memo追加']]
check(widget_order == sorted(widget_order), 'Widget add actions use the planned order')
check("$isCurrentTab = $tabParam === $tabLocation;" in drawer, 'current RSS tab is derived from validated tab state')
check("$isCurrentStock = $tabParam === 'stock';" in drawer, 'current Stock page is derived from validated tab state')
check(drawer.count('aria-current="page"') == 2, 'tab and Stock templates expose aria-current when selected')
check('drawer-item-current' in drawer and '.drawer-item-current' in css, 'selected Drawer item has a visual state hook')
check('border-left-color: #005fcc' in css and 'background-color: rgba(0, 95, 204, 0.1)' in css, 'selected state uses restrained border and background')

check('drawer-item-icon' in drawer and 'drawer-item-label' in drawer, 'all Drawer actions use shared icon and label structure')
check(drawer.count('drawer-menu-action drawer-item') >= 8, 'modal actions use the shared Drawer item class')
check('drawer-logout-button drawer-item' in drawer, 'logout uses the shared Drawer item class')
check('fa-user-cog fa-fw' in drawer and 'fa-sign-out-alt fa-fw' in drawer, 'Account icons use fixed Font Awesome width')
check('&nbsp;' not in drawer and '　' not in drawer, 'Drawer layout no longer depends on non-breaking or full-width spaces')
check('drawer-divider' not in drawer, 'per-item divider markup is removed')

check("echo '<li class=\"nav-item\">';" in index and "echo '<li class=\"nav-item active\">';" not in index, 'external Navbar links are not marked as current pages')
check('drawer-mobile-links' in drawer and '@media (min-width: 992px)' in css, 'user links switch between mobile Drawer and PC Navbar without duplication')
check(re.search(r'\.drawer-mobile-links\s*\{\s*display:\s*none;', css, re.S), 'PC breakpoint hides duplicate Drawer links')

base = re.search(r'\.drawer-menu > li > a,\s*\.drawer-menu-action,\s*\.drawer-logout-button\s*\{([^}]+)\}', css, re.S)
base_css = base.group(1) if base else ''
check('min-height: 40px' in base_css and 'padding: 8px 12px' in base_css, 'normal pointer items use a consistent 40px row')
check('@media (pointer: coarse)' in css and 'min-height: 44px' in css, 'touch devices retain 44px targets')
check('.drawer-item-icon' in css and 'flex: 0 0 1.5rem' in css, 'icon column has a fixed width')
check(':focus-visible' in css and 'outline-offset: -3px' in css, 'Drawer keeps an explicit keyboard focus treatment')
check('.drawer-logout-button:hover' in css and 'color: #b02a37' in css, 'logout has a distinguishable restrained hover state')
check('overflow-y: auto' in css and 'overscroll-behavior: contain' in css, 'long Drawer content remains scrollable')

check("event.key === 'Escape' || event.keyCode === 27" in js, 'Escape handling remains unchanged')
check("event.key !== 'Tab' && event.keyCode !== 9" in js, 'Tab focus containment remains unchanged')
check("$lastTrigger.focus();" in js, 'Drawer still returns focus to its trigger')
check('.html(' not in js and 'innerHTML' not in js, 'V1.3-B does not weaken safe DOM rendering')
check(not (ROOT / 'package.json').exists(), 'V1.3-B adds no build dependency')
check(not any(p.name.startswith('007_v1_3') or 'v1_3' in p.name for p in (ROOT / 'database').rglob('*.sql')), 'V1.3-B adds no SQL or migration')

passed = sum(checks)
failed = len(checks) - passed
print(f'RESULT: PASS {passed} / FAIL {failed}')
sys.exit(1 if failed else 0)
