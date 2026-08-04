# V1.2-B Changed Files

## Runtime Application

### Updated

- `app/version.php`
  - `1.2.0-dev.2`へ更新。
- `public/index.php`
  - Feed HeaderをDrag／Title／Edit／Refreshへ整理。
  - 記事TableをTitle列とAction列へ整理。
- `public/css/dashboard.css`
  - Header Action、記事Title Ellipsis、Tooltip、Accordion、Touch／Focus Styleを追加。
- `public/js/dashboard.js`
  - 実寸省略判定、Tooltip、概要Accordion、Card個別更新、Article Action構造を追加。

## Tests

### Added

- `tests/test_v12b_feed_payload.php`
- `tests/test_v12b_architecture.py`
- `tests/test_v12b_browser.py`

### Updated

- `tests/run.sh`
- `tests/test_m2b_feed_structure.py`
- `tests/test_m2b_feed_runtime.js`
- `tests/test_m2c_accessibility_structure.py`
- `tests/test_m2d_r2_layout_regression.py`
- `tests/test_m2d_dashboard_render.py`
- `tests/test_v11e_architecture.py`
- V1.1-B～JのVersion／Dashboard marker回帰Test

M2／V1.1のTestは旧固定64文字切り詰め、旧Stock左列、旧Header幅を期待していた箇所を、V1.2-Bの正当な構造へ同期した。既存Security、owner、Widget、Stock、NEWの検査条件は維持している。

## Documentation

- `README.md`
- `CHANGELOG.md`
- `docs/v1-2-b-implementation.md`
- `docs/v1-2-b-files.md`
- `docs/test-report-v1-2-b.md`
- `CHECKLIST_FOR_USER_V1_2_B.md`
- `APPLY_NOTE_V1_2_B.md`
- `SOURCE_BUILD.txt`
- `SOURCE_MANIFEST.sha256`

## Unchanged

- `.htaccess`
- `public/.htaccess`
- `app/api.php`
- Feed Fetch／Cache／Retry実装
- Stock API／DB処理
- `database/`全体
- `config/`全体

## Database／Settings

- DB変更: なし
- SQL実行: 不要
- `config/local.php`追加: なし
- Feed Cache削除: 不要
