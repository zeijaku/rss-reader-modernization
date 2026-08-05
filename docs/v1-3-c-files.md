# V1.3-C Changed Files

## Application

- `app/version.php`
  - Versionを`1.3.0-dev.2`へ更新。
- `public/index.php`
  - Brand、現在地、外部Link、Menu Buttonを分離。
  - Navbar背景とContrast Schemeを分離。
  - Desktop／Smartphoneの共通Menu IconとARIAを整理。
- `public/css/dashboard.css`
  - 56px Header、Brand、現在地、Separator、外部Link、Menu Button、Focus、Responsiveを追加。
  - Theme固有のBorder、Font、横Overflowを吸収。

## Tests

### Added

- `tests/test_v13c_header_structure.py`
- `tests/test_v13c_header_browser.py`

### Updated

- `tests/run.sh`
- `tests/test_m2c_accessibility_structure.py`
- `tests/test_m2d_responsive_ui.py`
- `tests/test_v12b_architecture.py`
- `tests/test_v13b_drawer_structure.py`

旧Testは、V1.3-Cで意図的に変わったHeader Class、Menu Button Class、現在地表示、後続Version markerだけを現仕様へ同期した。Authentication、CSRF、API、Feed、Drawer動作の検査条件は変更していない。

## Documentation

- `CHANGELOG.md`
- `APPLY_NOTE_V1_3_C.md`
- `CHECKLIST_FOR_USER_V1_3_C.md`
- `UPDATED_FILES_V1_3_C.md`
- `docs/v1-3-c-implementation.md`
- `docs/v1-3-c-files.md`
- `docs/test-report-v1-3-c.md`

## Package-generated metadata

- `SOURCE_BUILD.txt`
- `SOURCE_MANIFEST.sha256`

## Unchanged

- `public/js/dashboard.js`
- `app/api.php`
- RSS Parser / Feed Fetch / Cache
- `database/`全体
- `config/`全体
- Root `.htaccess`
- `public/.htaccess`
- 外部Asset
