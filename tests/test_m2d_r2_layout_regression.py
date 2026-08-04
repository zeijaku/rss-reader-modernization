from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
js = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')

checks: list[bool] = []
def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


def block(selector: str, source: str = css) -> str:
    match = re.search(re.escape(selector) + r'\s*\{([^}]+)\}', source, re.S)
    return match.group(1) if match else ''

check('<colgroup>' in index, 'Feed table declares a column group')
check('<col class="feed-stock-column">' in index and '<col class="feed-summary-column">' in index, 'Feed table marks independent Stock and summary columns')
check(index.index('<colgroup>') < index.index('<thead>'), 'column group appears before the table header')
check(index.count('feed-stock-column') == 1 and index.count('feed-summary-column') == 1, 'Feed fixed-column hooks are each declared once in the loop template')
fixed_columns = block('.feed-stock-column,\n.feed-summary-column')
check('width: 44px' in fixed_columns, 'Stock and summary columns each reserve one touch-sized control')
check('table-layout: fixed' in block('.feed-table'), 'stable fixed Feed table layout remains enabled')
check('padding-right: 2px' in block('.feed-item-title-cell'), 'article title cell retains compact action spacing')
check('padding: 0' in block('.feed-item-stock-cell,\n.feed-item-summary-cell'), 'Stock and summary cells keep compact horizontal padding')

section = block('.drawer-section-title')
check('padding: 5px 4px' in section, 'Drawer section headings use compact vertical padding')
base_match = re.search(r'\.drawer-menu > li > a,\s*\.drawer-menu-action,\s*\.drawer-logout-button\s*\{([^}]+)\}', css, re.S)
base = base_match.group(1) if base_match else ''
check('min-height: 36px' in base, 'normal pointer Drawer rows use compact 36px height')
check('padding: 5px 10px' in base, 'normal pointer Drawer rows use compact padding')
coarse_match = re.search(r'@media \(pointer: coarse\)\s*\{(.*?)\n\}', css, re.S)
coarse = coarse_match.group(1) if coarse_match else ''
check(bool(coarse), 'coarse pointer override is present')
check('min-height: 44px' in coarse, 'coarse pointer Drawer rows retain 44px targets')
check('padding: 8px 12px' in coarse, 'coarse pointer Drawer rows retain touch padding')
check('.drawer-menu-action' in coarse and '.drawer-logout-button' in coarse, 'touch override covers Drawer buttons and logout')
check("case 'Escape':" in js or "event.key === 'Escape'" in js, 'Drawer Escape keyboard handling remains present')
check("event.key !== 'Tab'" in js or "event.key === 'Tab'" in js, 'Drawer Tab focus handling remains present')
check('aria-expanded' in js, 'Drawer ARIA state handling remains present')
check('.html(' not in js and 'innerHTML' not in js, 'layout correction does not weaken safe DOM rendering')
check(not (ROOT / 'package.json').exists(), 'R2 adds no npm or build dependency')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M2-D R2 layout regression checks passed.')
