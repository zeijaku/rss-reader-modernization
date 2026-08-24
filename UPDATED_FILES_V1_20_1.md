# V1.20.1 Updated Files

正式V1.20.0 Complete Sourceとの差分は47 pathsです。

## Runtime / DB

- `app/calendar_color.php`
- `app/mini_game.php`
- `app/version.php`
- `database/migrations/013_v1_20_1_calendar_event_color.sql`
- `database/schema.sql`
- `public/.htaccess`
- `public/calendar_color_api.php`
- `public/css/block-collapse.css`
- `public/css/calendar-colors.css`
- `public/css/memo-refresh.css`
- `public/css/utility-widgets.css`
- `public/js/block-collapse.js`
- `public/js/calendar-colors.js`
- `public/js/calendar.js`
- `public/js/camera-video-streaming.js`
- `public/js/memo-refresh.js`

## Release / CI / Package tooling

- `.github/workflows/ci.yml`
- `.github/workflows/v1.20.1-release.yml`
- `tools/build_complete_package.py`
- `tools/build_release_package.py`
- `tools/verify_complete_package.py`
- `tools/verify_release_package.py`
- `tests/run-v1201e.sh`
- `tests/test_v1201e_final.py`

## Compatibility test maintenance

- `tests/test_v118g_release_contract.py`
- `tests/test_v119b_modular_architecture.py`
- `tests/test_v119c_security_hardening.py`
- `tests/test_v119d_cleanup_docs.py`
- `tests/test_v16c_game_widget.php`

既存Compatibility testの意図を維持しつつ、V1.20.1のVersion marker、Block Collapse追加、Calendar色専用Public Endpoint追加に合わせて固定Inventory/Version条件だけを更新しています。

## Documentation

- `APPLY_NOTE_V1_20_1.md`
- `CHANGELOG.md`
- `CHECKLIST_FOR_USER_V1_20_1.md`
- `README.md`
- `RELEASE_NOTES.md`
- `UPDATED_FILES_V1_20_1.md`
- `V1_20_1_TEST_REPORT.md`
- `docs/README.md`
- `docs/deployment-checklist.md`
- `docs/installation.md`
- `docs/test-report-v1-20-1.md`
- `docs/update.md`
- `docs/v1-19-public-endpoint-matrix.csv`
- `docs/v1-19-public-endpoints.md`
- `docs/v1-19-security-boundary.md`
- `docs/v1-20-1-final-release.md`
- `docs/v1-20-1-production-checklist.md`
- `docs/v1-20-1-updated-files.md`

## Scope note

- 新規必須Config / Secret: なし
- DB変更: `calendar_event.calendar_event_color` 1 Column
- Task / Memo / Block CollapseのDB schema変更: なし
- Widget下端完全統一: V1.20.1では保留
