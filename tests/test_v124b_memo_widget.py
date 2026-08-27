#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
css = (ROOT / 'public/css/memo-widget.css').read_text(encoding='utf-8')
js = (ROOT / 'public/js/memo-counter.js').read_text(encoding='utf-8')
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')

checks: list[tuple[bool, str]] = [
    ('min-height: 0;' in css, 'Memo card/body can shrink inside the Dashboard Grid'),
    ('overflow-y: auto;' in css, 'Memo body scrolls internally'),
    ('@media (min-width: 768px)' in css and 'contain: size;' in css, 'desktop/tablet Memo does not grow the Grid track from long content'),
    ('data-widget-height="1"' in css and 'height: 320px;' in css, 'smartphone Height 1 remains explicit'),
    ('data-widget-height="2"' in css and 'height: 648px;' in css, 'smartphone Height 2 remains explicit'),
    ('var MEMO_MAX_LENGTH = 4000;' in js, 'Memo client counter keeps the 4000 character limit'),
    ("String(memoTextLength(value)) + '/' + String(MEMO_MAX_LENGTH)" in js, 'Memo counter renders current/max text'),
    ("document.querySelectorAll('.memo-card').forEach(ensureDashboardCounter);" in js, 'Dashboard Memo counters initialize'),
    ("document.querySelectorAll('.registerMemoBody, .changeMemoBody').forEach(bindTextarea);" in js, 'register/edit Memo counters initialize'),
    ("app_asset_url('css/memo-widget.css')" in index, 'Dashboard loads Memo widget CSS through the release asset helper'),
    ("app_asset_url('js/memo-counter.js')" in index, 'Dashboard loads Memo counter JS through the release asset helper'),
]

failures = 0
for ok, message in checks:
    print(('PASS' if ok else 'FAIL') + ': ' + message)
    failures += 0 if ok else 1

print(f'RESULT: PASS {len(checks) - failures} / FAIL {failures} / SKIP 0')
raise SystemExit(1 if failures else 0)
