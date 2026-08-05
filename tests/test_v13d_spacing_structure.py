from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
CSS = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
INDEX = (ROOT / 'public/index.php').read_text(encoding='utf-8')
VERSION = (ROOT / 'app/version.php').read_text(encoding='utf-8')
checks: list[bool] = []


def check(condition, message):
    ok = bool(condition)
    checks.append(ok)
    print(('PASS' if ok else 'FAIL') + ': ' + message)


version_match = re.search(r"const APP_VERSION = '([0-9]+)\.([0-9]+)\.([0-9]+)(?:-dev\.[0-9]+)?';", VERSION)
version_tuple = tuple(int(part) for part in version_match.groups()) if version_match else (0, 0, 0)
check(version_tuple >= (1, 3, 0), 'V1.3-D development or later Version is visible')
check("const APP_VERSION_LABEL = 'RSS Reader Modernization " in VERSION, 'V1.3-D development or later label is visible')
check('class="content-title widget-title-text" id="\' . app_html($searchTitleId)' in INDEX,
      'Search Feed uses the common Widget title hook')

for selector, expected in [
    (r'\.feed-table \.feed-item-title-cell\s*\{([^}]*)\}', ['padding: 7px 2px 7px 6px']),
    (r'\.feed-table \.feed-item-stock-cell\s*\{([^}]*)\}', ['width: 36px', 'padding: 0']),
    (r'\.feed-table \.feed-item-summary-cell\s*\{([^}]*)\}', ['width: 32px', 'padding: 0']),
]:
    match = re.search(selector, CSS, re.S)
    check(match is not None, f'Strong table-cell selector exists: {selector}')
    if match is not None:
        for token in expected:
            check(token in match.group(1), f'Table-cell rule keeps {token}')

common = re.search(r'\.widget-title-text\s*\{([^}]*)\}', CSS, re.S)
check(common is not None, 'Common Widget title rule exists')
if common is not None:
    body = common.group(1)
    for token in ['margin-left: 0', 'font-size: 80%', 'text-overflow: ellipsis', 'white-space: nowrap']:
        check(token in body, f'Common Widget title rule includes {token}')

coarse = re.search(r'@media \(pointer: coarse\)\s*\{(.*?)\n\}', CSS, re.S)
check(coarse is not None, 'Touch-pointer media rule remains present')
if coarse is not None:
    body = coarse.group(1)
    check('.feed-stock-column' in body and '.feed-table .feed-item-stock-cell' in body and 'width: 44px' in body,
          'Touch layout expands the three-dot column to 44px')
    check('.article-actions-trigger' in body and 'min-width: 44px' in body,
          'Touch layout expands the Article Actions trigger to 44px')

check('.feed-item-title-wrap.has-feed-item-new .feed-item-title-text' in CSS and 'text-indent: 24px' in CSS,
      'New Bell first-line spacing remains unchanged')
check('.feed-item-title-wrap.has-feed-item-new .feed-item-new' in CSS and 'position: absolute' in CSS,
      'New Bell remains outside normal title width')

passed = sum(checks)
failed = len(checks) - passed
print(f'RESULT: PASS {passed} / FAIL {failed} / SKIP 0')
sys.exit(1 if failed else 0)
