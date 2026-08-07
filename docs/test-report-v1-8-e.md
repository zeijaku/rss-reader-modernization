# V1.8-E / R1 Targeted Test Report

実施日: 2026-08-07 JST

V1.8-EではFull Regressionは実施せず、Stock UI / Actions / Task連携と直接影響範囲を中心に確認した。

## V1.8-E 新規

- `test_v18e_stock_ui_static.py`: 22 PASS / 0 FAIL
- `test_v18e_stock_task_targets.php`: 9 PASS / 0 FAIL
- `test_v18e_stock_render.php`: 13 PASS / 0 FAIL
- 合計: 44 PASS / 0 FAIL

## Stock Regression

- V1.8-D Pagination Static: 22 PASS
- V1.8-D Pagination Runtime: 17 PASS
- V1.8-D Page Clamp: 6 PASS
- V1.8-C Search Static: PASS
- V1.8-C Search Helper: 6 PASS
- V1.8-C Search Render: 11 PASS
- V1.8-B Stock Static: 14 PASS
- SB-05..07 API / Authorization: 44 PASS

`test_v18b_stock_db.php` は実行環境にPDO SQLite Driverが無いためSKIP。DB構造変更はない。

## Actions / UI / Security Regression

- V1.2-D Article Actions: 25 PASS
- M2-D Mutation Runtime: PASS
- M2-D Responsive UI: 52 PASS
- SB-10 / XSS Static: 35 PASS
- SB-14 Surface Static: PASS
- Static Checks: 32 PASS
- V1.1-D Dashboard Render: PASS

## Task Regression

- V1.1-H Task Widget: 52 PASS
- V1.1-H Frontend Runtime: 35 PASS
- V1.1-H Architecture: 52 PASS

## Syntax

- `app/dashboard_widget.php`: PASS
- `public/index.php`: PASS
- `app/version.php`: PASS
- `public/js/dashboard.js`: PASS

Clock / Calendar / Mini Game / Remember Login / RSS Parser全形式など、今回の変更と直接関係しないFull Regressionは実行していない。
