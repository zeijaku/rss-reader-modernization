#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(ok: bool, message: str) -> None:
    checks.append(bool(ok))
    print(('PASS' if ok else 'FAIL') + ': ' + message)


css_path = ROOT / 'public/css/memo-widget.css'
js_path = ROOT / 'public/js/memo-counter.js'
index_path = ROOT / 'public/index.php'

check(css_path.is_file(), 'V1.24 Memo stylesheet exists')
check(js_path.is_file(), 'V1.24 Memo counter script exists')

css = css_path.read_text(encoding='utf-8') if css_path.is_file() else ''
js = js_path.read_text(encoding='utf-8') if js_path.is_file() else ''
index = index_path.read_text(encoding='utf-8')

check('.dashboard-grid > .memo-card' in css and 'overflow: hidden;' in css,
      'Memo card is bounded inside the Dashboard Grid')
check('.dashboard-grid .memo-card-body' in css and 'overflow-y: auto;' in css,
      'Memo body owns vertical scrolling')
check('data-widget-height="1"' in css and 'height: 320px;' in css,
      'Smartphone Height 1 remains explicitly bounded')
check('data-widget-height="2"' in css and 'height: 648px;' in css,
      'Smartphone Height 2 remains explicitly bounded')
check('var MEMO_MAX_LENGTH = 4000;' in js,
      'Memo counter uses the existing 4000-character server limit')
check("String(length) + '/' + String(MEMO_MAX_LENGTH)" in js,
      'Memo counter renders current length over maximum')
check("document.querySelectorAll('.memo-card').forEach(ensureDashboardCounter)" in js,
      'Dashboard Memo counters initialize for rendered Memo cards')
check("document.querySelectorAll('.registerMemoBody, .changeMemoBody').forEach(bindTextarea)" in js,
      'Create and edit Memo textareas receive realtime counters')
check("app_asset_url('css/memo-widget.css')" in index and "app_asset_url('js/memo-counter.js')" in index,
      'Dashboard loads Memo V1.24 assets through the application asset revision helper')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
