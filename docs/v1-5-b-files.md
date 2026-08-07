# V1.5-B ファイル構成

## Runtime変更

- `app/version.php`：`1.5.0-dev.1`、V1.5-B / R1
- `public/index.php`：Clock／Timer UIと専用Asset読込み
- `public/js/dashboard.js`：Clock削除成功後のTimer Storage cleanup
- `public/js/clock-timer.js`：Timer状態、時間計算、保存・復元、操作
- `public/css/clock-timer.css`：Timer UI、Responsive、Dark Theme、44px操作領域

## Test

- `tests/test_v15b_clock_timer_runtime.js`：時間計算、Validation、Storage、Fallback
- `tests/test_v15b_architecture.py`：境界、Asset、DB非変更、Security
- `tests/test_v15b_dashboard_render.py`：複数ClockのHTML、ID、ARIA
- `tests/test_v15b_browser.py`：360px、操作、復元、完了、複数Widget

## 既存Test追従

- Asset inventoryへTimer専用JS／CSSを追加
- V1.4 Game回帰TestのVersion判定をV1.5以降でも実行可能に更新

## 追加していないもの

- PHP Domain／API Action
- DB Migration
- Server Timer
- Audio Asset
- 外部Library
