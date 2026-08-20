# Version 1.18.0 Updated Files

## Application / runtime

- `app/version.php` — Version / Asset Revisionを1.18.0へ更新
- `app/bootstrap.php` — Connection Monitor persistence module読込
- `app/dashboard_widget.php` — `health_probe` Widget type追加
- `app/information_widget.php` — Information Widget allowlistへ`health_probe`追加
- `app/api.php` — Connection Monitor CRUD API追加
- `app/health_probe.php` — 新規。既存`dashboard_widget`への保存処理
- `public/index.php` — Connection Monitor JavaScript読込
- `public/connection_probe.php` — 新規。GET 204 lightweight probe endpoint
- `public/js/connection-monitor.js` — 新規。Polling、History、Graph、State、Quality、UI
- `public/js/calendar.js` — 正式Release Asset Revision 1.18.0へ更新
- `public/js/camera-video-streaming.js` — fallback CSS cache keyを1.18.0へ更新

## Release / tests / docs

- `README.md`
- `CHANGELOG.md`
- `RELEASE_NOTES.md`
- `docs/update.md`
- `docs/deployment-checklist.md`
- `docs/release-package.md`
- `docs/tag-and-github-release.md`
- `docs/versioning.md`
- `docs/v1-18-connection-monitor.md`
- `docs/release-gate-v1.18.0.md`
- `docs/V1.18-F_DESIGN_DECISION.md`
- `docs/V1.18-F_PRODUCTION_CHECKLIST.md`
- `docs/V1.18-F_TEST_NOTE.md`
- `tools/build_release_package.py`
- `tools/build_complete_package.py`
- `tools/verify_release_package.py`
- `tools/verify_complete_package.py`
- `.github/workflows/ci.yml`
- `.github/workflows/v1.18.0-release.yml`
- `tests/run-v118.sh`
- `tests/test_v118b_health_probe.py`
- `tests/test_v118c_health_probe_history.py`
- `tests/test_v118d_health_probe_state.py`
- `tests/test_v118e_health_probe_ui.py`
- `tests/test_v118f_health_probe_scope.py`
- `tests/test_v118g_release_contract.py`
- `tests/test_v1172b_release_gate.py` — later release compatibility contractへ調整

## Database

新規Migration、Table、Column、SQL変更はありません。
