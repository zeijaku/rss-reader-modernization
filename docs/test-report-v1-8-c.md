# V1.8-C Targeted Test Report

V1.8-CではFull Regressionを実施していない。Stock検索・Sortと直接影響するStock解除、API、Dashboard描画、Responsive / Security surfaceに限定した。

## V1.8-C新規Test

- `tests/test_v18c_stock_search_static.py`: 18 PASS / 0 FAIL
- `tests/test_v18c_stock_helpers.php`: 6 PASS / 0 FAIL
- `tests/test_v18c_stock_render.php`: 10 PASS / 0 FAIL

合計: 34 PASS / 0 FAIL

確認内容には、Ownership/active条件、Title/URL検索、LIKE Escape、Sort whitelist、GET状態保持、検索結果表示を含む。

## Direct Regression

- `tests/test_v18b_stock_static.py`: 14 PASS / 0 FAIL
- `tests/test_sb05_07_api.php`: 44 PASS / 0 FAIL
- `tests/test_v11d_dashboard_render.py`: PASS
- `tests/test_sb14_surface_static.py`: 33 PASS / 0 FAIL
- `tests/test_m2d_responsive_ui.py`: 52 PASS / 0 FAIL
- `tests/test_m2d_mutation_runtime.js`: 35 PASS / 0 FAIL
- `tests/static_checks.py`: 32 PASS / 0 FAIL
- PHP Syntax: PASS
- JavaScript Syntax: PASS

`tests/test_v18b_stock_db.php`は実行環境にPDO SQLite Driverが無いためSKIP。V1.8-C固有のSQL構築はFake PDO Render Testで、Owner Bind・LIKE Bind・ORDER BYを確認した。

Clock / Calendar / Mini Game / Remember Login / RSS Parser全形式等の非関連Full Regressionは実施していない。
