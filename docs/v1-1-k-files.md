# V1.1-K Changed Files

V1.1-J / R2適用済みBaselineから、Version 1.1.0最終化で変更したFile一覧です。

## Application / Database

- `app/version.php` — Versionを`1.1.0`、Labelを`RSS Reader Modernization 1.1.0`へ確定。
- `database/schema.sql` — Version 1.1.0新規DB用Schemaであることを明記。
- `database/migrations/002_v1_1_feed_item_state.sql` — 既存DB用Default PrefixをMigration 003～006と同じ`ig_`へ統一。

Applicationの機能実装、公開API、認証処理、Widget処理は変更していません。

## Release / Documentation

- `README.md`
- `CHANGELOG.md`
- `RELEASE_NOTES.md`
- `APPLY_NOTE.md`
- `CHECKLIST_FOR_USER.md`
- `docs/README.md`
- `docs/installation.md`
- `docs/update.md`
- `docs/roadmap.md`
- `docs/versioning.md`
- `docs/release-package.md`
- `docs/tag-and-github-release.md`
- `docs/v1-1-k-implementation.md`
- `docs/v1-1-k-files.md`
- `docs/test-report-v1-1-k.md`
- `docs/release-gate-v1.1.0.md`
- `docs/release-artifact-inventory-v1.1.0.md`

## Package tools

- `tools/build_release_package.py` — Runtime ReleaseをVersion 1.1.0へ更新。
- `tools/verify_release_package.py` — Runtime ZIPのVersion、Manifest、秘密情報、CRC、Pathを検査。
- `tools/build_complete_package.py` — GitHub作業Folder相当の完全統合ZIPを決定的に生成。
- `tools/verify_complete_package.py` — 完全統合ZIPのManifest、秘密情報、Runtime Data、CRC、Pathを検査。

## Test runner / Release Gate

- `tests/run.sh`
- `tests/run-local-v1-1-j.sh`
- `tests/run-local-v1-1-k.sh`
- `tests/test_v11k_release.py`
- `tests/test_v11k_documentation.py`

## 現行実装へ同期した既存Test

V1.1で追加されたclass、Timer、Handler、Accessibility属性、正式Versionを許容するため、実装を戻さずTest側の古い完全一致条件を更新しました。

- `tests/test_m2a_dashboard_runtime.js`
- `tests/test_m2a_frontend_structure.py`
- `tests/test_m2b_feed_runtime.js`
- `tests/test_m2b_feed_structure.py`
- `tests/test_m2c_accessibility_runtime.js`
- `tests/test_m2c_accessibility_structure.py`
- `tests/test_m2d_mutation_runtime.js`
- `tests/test_m2d_responsive_ui.py`
- `tests/test_m2e_asset_inventory.py`
- `tests/test_m2f_dependency_inventory.py`
- `tests/test_m4c_config_inventory.py`
- `tests/test_m4c_healthcheck_contract.py`
- `tests/test_m4f_environment_probe.py`
- `tests/test_sb11_12_static.py`
- `tests/test_sb12_atom_link_static.py`
- `tests/test_sb13_sql.py`
- `tests/test_sb15_docs.py`
- `tests/test_v11b_architecture.py`
- `tests/test_v11c_architecture.py`
- `tests/test_v11d_architecture.py`
- `tests/test_v11d_dashboard_render.py`
- `tests/test_v11e_architecture.py`
- `tests/test_v11e_frontend_runtime.js`
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

## 削除した未使用Asset

- `public/js/jquery-3.3.1.min.js`
- `public/webfonts/fa-brands-400.eot`
- `public/webfonts/fa-brands-400.svg`
- `public/webfonts/fa-brands-400.woff`
- `public/webfonts/fa-regular-400.eot`
- `public/webfonts/fa-regular-400.svg`
- `public/webfonts/fa-regular-400.woff`
- `public/webfonts/fa-solid-900.eot`
- `public/webfonts/fa-solid-900.svg`
- `public/webfonts/fa-solid-900.woff`
- `licenses/fontawesome-5.3.1-LICENSE.txt`

Runtimeで使用するjQuery 3.7.1、Font Awesome Free 6.7.2のCSS、TTF、WOFF2は維持しています。

## 最終成果物から除外した生成Data

添付Baselineに含まれていた次の生成Dataは、Codeとして扱わず削除しました。各Folderの`.gitkeep`だけを残しています。

- `var/session/`のSession File
- `var/cache/feed/`のFeed Cache、Lock、State
- `var/security/login-throttle/`のThrottle State

`config/local.php`、`.env`、実DB、Log、Archiveも最終成果物へ含めません。
