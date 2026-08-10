# V1.3-B Changed Files

## Application

- `app/version.php`
  - Versionを`1.3.0-dev.1`へ更新。
- `public/index.php`
  - DrawerのGroup、順序、共通Item構造、現在地、Responsive Link表示を整理。
  - Header外部Linkの不適切な`active`指定を削除。
- `public/css/dashboard.css`
  - DrawerのGroup見出し、Icon列、Row高、Hover、Focus、Current、Logout、Scroll、Responsive Link表示を追加。

## Tests

### Added

- `tests/test_v13b_drawer_structure.py`
- `tests/test_v13b_drawer_browser.py`

### Updated

- `tests/run.sh`
- `tests/test_m2c_accessibility_structure.py`
- `tests/test_m2d_r2_layout_regression.py`
- `tests/test_v11j_architecture.py`
- `tests/test_v12b_architecture.py`
- `tests/test_v12d_article_actions.py`

旧Testは、V1.3-Bで意図的に変更したDrawerの40px Row、Group構成、Account配置、後続Version markerだけを現仕様へ同期した。Authentication、CSRF、API、Feed、Widgetの検査条件は変更していない。

## Documentation

- `CHANGELOG.md`
- `APPLY_NOTE_V1_3_B.md`
- `CHECKLIST_FOR_USER_V1_3_B.md`
- `UPDATED_FILES_V1_3_B.md`
- `docs/v1-3-b-implementation.md`
- `docs/v1-3-b-files.md`
- `docs/test-report-v1-3-b.md`

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
