# V1.8-D Updated Files

## Application

- `app/common/common_db.php`
  - `count_stock()`追加
  - `search_stock()`へoptional `limit / offset`追加
- `app/version.php`
  - `1.8.0-dev.3 / V1.8-D / R1`
- `public/index.php`
  - Page Parameter Validation
  - COUNT / 20件取得
  - Pagination UI / Query保持 / Page補正
- `public/js/dashboard.js`
  - Pagination中の最後の表示Card解除時だけ空Pageを回避
- `public/css/dashboard.css`
  - Pagination折返し / 44px Touch target

## Test

- `tests/test_v18d_stock_pagination_static.py` 新規
- `tests/test_v18d_stock_pagination.php` 新規
- `tests/test_v18d_stock_page_clamp.php` 新規
- `tests/test_v18c_stock_search_static.py` V1.8-D互換化
- `tests/test_v18c_stock_render.php` COUNT/Pagination対応Fixtureへ更新
- `tests/test_v11d_dashboard_render.py` COUNT対応Fixtureへ更新

## Documentation

- `APPLY_NOTE_V1_8_D.md`
- `CHECKLIST_FOR_USER_V1_8_D.md`
- `UPDATED_FILES_V1_8_D.md`
- `docs/v1-8-d-implementation.md`
- `docs/test-report-v1-8-d.md`

## DB

変更なし。
