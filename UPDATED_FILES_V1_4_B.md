# V1.4-B / R1 更新ファイル

## Application

- `app/version.php`
- `app/bootstrap.php`
- `app/dashboard_widget.php`
- `app/mini_game.php`（新規）
- `app/api.php`
- `public/index.php`
- `public/css/mini-game.css`（新規）
- `public/js/dashboard.js`
- `public/js/mini-game.js`（新規）

## Documentation／Package Metadata

- `CHANGELOG.md`
- `APPLY_NOTE_V1_4_B.md`
- `CHECKLIST_FOR_USER_V1_4_B.md`
- `UPDATED_FILES_V1_4_B.md`
- `docs/v1-4-b-implementation.md`
- `docs/v1-4-b-files.md`
- `docs/test-report-v1-4-b.md`
- `SOURCE_BUILD.txt`
- `SOURCE_MANIFEST.sha256`

## Test

- `tests/run.sh`
- `tests/run-local-v1-4-b.sh`（新規）
- `tests/test_v14b_game_widget.php`（新規）
- `tests/test_v14b_architecture.py`（新規）
- `tests/test_v14b_storage_runtime.js`（新規）
- `tests/test_v14b_dashboard_render.py`（新規）
- `tests/test_m2e_asset_inventory.py`
- `tests/test_sb12_atom_link_static.py`
- `tests/test_sb13_sql.py`
- `tests/test_sb15_docs.py`
- `tests/test_version_marker.py`
- `tests/test_v11d_dashboard_widget.php`
- `tests/test_v11d_architecture.py`
- `tests/test_v12b_architecture.py`
- `tests/test_v12d_article_actions.py`
- `tests/test_v13b_drawer_structure.py`
- `tests/test_v13c_header_structure.py`
- `tests/test_v13d_spacing_structure.py`

## 変更していない主要領域

- `database/`
- `config/`
- `.htaccess`
- 認証／Session／CSRF／SSRF／RSS解析／Cache
- Search Feed／Stock／Memo／Task／Clock／Calendar
- `var/` Runtime Data
