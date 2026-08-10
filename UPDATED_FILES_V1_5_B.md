# V1.5-B 更新ファイル

## Application

### 更新

- `app/version.php`
- `public/index.php`
- `public/js/dashboard.js`

### 新規

- `public/js/clock-timer.js`
- `public/css/clock-timer.css`

## Test

### 新規

- `tests/test_v15b_clock_timer_runtime.js`
- `tests/test_v15b_architecture.py`
- `tests/test_v15b_dashboard_render.py`
- `tests/test_v15b_browser.py`

### 更新

- `tests/run.sh`
- `tests/test_m2e_asset_inventory.py`
- `tests/test_v14b_architecture.py`
- `tests/test_v14c_architecture.py`
- `tests/test_v14d_architecture.py`
- `tests/test_v14d_r2_game_header.py`

既存TestのVersion判定とAsset inventoryをV1.5-Bへ追従させています。既存機能の期待値は緩めていません。

## Documentation／Package metadata

- `CHANGELOG.md`
- `APPLY_NOTE_V1_5_B.md`
- `CHECKLIST_FOR_USER_V1_5_B.md`
- `UPDATED_FILES_V1_5_B.md`
- `docs/v1-5-b-implementation.md`
- `docs/v1-5-b-files.md`
- `docs/test-report-v1-5-b.md`
- `SOURCE_BUILD.txt`
- `SOURCE_MANIFEST.sha256`

## 変更なし

- `app/dashboard_widget.php`
- `app/api.php`
- `database/`
- `config/`
- `.htaccess`
