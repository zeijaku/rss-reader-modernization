# V1.2-D / R1 変更ファイル

## Application

- `app/version.php`
- `public/index.php`
- `public/css/dashboard.css`
- `public/js/dashboard.js`

## Documentation / Package metadata

- `CHANGELOG.md`
- `APPLY_NOTE_V1_2_D.md`（追加）
- `CHECKLIST_FOR_USER_V1_2_D.md`（追加）
- `UPDATED_FILES_V1_2_D.md`（追加）
- `SOURCE_BUILD.txt`
- `SOURCE_MANIFEST.sha256`

## Regression tests

- `tests/run.sh`
- `tests/test_m2b_feed_runtime.js`
- `tests/test_m2c_accessibility_structure.py`
- `tests/test_v11b_architecture.py`
- `tests/test_v11c_architecture.py`
- `tests/test_v11d_architecture.py`
- `tests/test_v11d_dashboard_render.py`
- `tests/test_v11e_architecture.py`
- `tests/test_v11f_architecture.py`
- `tests/test_v11f_dashboard_render.py`
- `tests/test_v11g_architecture.py`
- `tests/test_v11g_dashboard_render.py`
- `tests/test_v11h_architecture.py`
- `tests/test_v11h_dashboard_render.py`
- `tests/test_v11i_architecture.py`
- `tests/test_v11i_dashboard_render.py`
- `tests/test_v11i_r2_architecture.py`
- `tests/test_v11j_architecture.py`
- `tests/test_v11j_dashboard_render.py`
- `tests/test_v12b_browser.py`
- `tests/test_v12c_r4_small_fixes.py`
- `tests/test_v12d_article_actions.py`（追加）
- `tests/test_v12d_article_actions_browser.py`（追加）

V1.1系のArchitecture／Render testは、過去Checkpointの機能を保ったまま現在の`1.2.0-dev.4`も許可するVersion判定へ更新しています。

## 変更なし

- `database/`配下のSQL／Migration
- `.htaccess`
- `config/local.php`
- `app/api.php`、`public/api_v1.php`
- `var/`配下のRuntime Data
- 外部Library、Framework、Build環境
