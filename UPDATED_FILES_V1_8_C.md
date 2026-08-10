# V1.8-C Updated Files

## Application

- `app/common/common_db.php`
  - Stock検索LIKE Pattern Escape追加
  - Stock Sort whitelist / ORDER BY helper追加
  - `search_stock()` をQuery / Sort対応へ拡張
- `public/index.php`
  - GET `q` / `sort` Validation
  - Stock検索 / 並び替えForm追加
  - 検索結果件数、0件表示、条件クリア導線追加
- `app/version.php`
  - `1.8.0-dev.2` / `V1.8-C / R1`

## Test

- `tests/static_checks.py`
  - Stock ORDER BYの新しいwhitelist実装へ追従
- `tests/test_v18c_stock_search_static.py`
- `tests/test_v18c_stock_helpers.php`
- `tests/test_v18c_stock_render.php`

## Documentation

- `APPLY_NOTE_V1_8_C.md`
- `CHECKLIST_FOR_USER_V1_8_C.md`
- `UPDATED_FILES_V1_8_C.md`
- `docs/v1-8-c-implementation.md`
- `docs/test-report-v1-8-c.md`

DB Table / Column / Migration / 必須設定の追加はありません。
