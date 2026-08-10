# V1.8-D Targeted Test Report

V1.8-DではFull Regressionを実施していない。Stock Paginationと直接影響する検索・Sort・Stock解除・Stock描画・Responsive・API Ownership / CSRFに限定した。

## V1.8-D新規Test

- `tests/test_v18d_stock_pagination_static.py`: 22 PASS / 0 FAIL
- `tests/test_v18d_stock_pagination.php`: 17 PASS / 0 FAIL
- `tests/test_v18d_stock_page_clamp.php`: 6 PASS / 0 FAIL

合計: **45 PASS / 0 FAIL**

確認内容:

- 20件/Page
- Server-side `COUNT(*)`
- `LIMIT/OFFSET`
- `stock_owner` / `stock_flag`維持
- Search条件をCOUNT / SELECT双方へ適用
- `AI`検索とNative PDO向けunique placeholder維持
- 検索 / Sort条件をPagination Linkへ保持
- Search / Sort変更時のPage reset
- Page番号の省略表示
- 大きすぎるPage番号の最終Page補正
- Paginated Stock解除後の空Page回避
- Smartphone向け44px Touch target

## Direct Regression

実施対象:

- V1.8-C Stock検索 / Sort Static・Helper・Render
- V1.8-B Stock解除 Static / DB Test
- SB-05..07 API / Authorization
- M2-D Mutation Runtime
- M2-D Responsive UI
- V1.1-D Dashboard / Stock Render
- M2-D Dashboard Render / Layout Regression
- SB-10 Output / XSS Static
- SB-14 Surface Static
- `tests/static_checks.py`
- PHP Syntax (`common_db.php`, `index.php`, `version.php`, `api.php`)
- JavaScript Syntax (`dashboard.js`)

上記はV1.8-D実装後にPASSした。

`test_v18b_stock_db.php`は実行環境にPDO SQLite Driverが無い場合はTest自身がSKIP扱いとする。今回の実行環境でもPHPのPDO本体のみでSQLite Driverは存在しない。

Clock / Calendar / Mini Game / Remember Login / RSS Parser全形式等の非関連Full Regressionは実施していない。
