# V1.18.0 final pre-Git updated files

V1.17.2からV1.18.0最終版までの主な追加・変更に加え、Git前R2修正を含みます。

Git前R2での本番Code変更:
- `app/session.php`
- `app/persistent_login.php`
- `app/api.php`
- `public/api_v1.php`
- `public/js/dashboard.js`
- `public/css/dashboard.css`

最終Cache Revision確定で追加変更:
- `app/version.php` — `APP_ASSET_REVISION=1.18.0-r2`
- `public/js/calendar.js` — 動的CSS/JSの`?v=`を`1.18.0-r2`へ統一
- `public/js/camera-video-streaming.js` — fallback CSS Revisionを`1.18.0-r2`へ統一
- `.github/workflows/v1.18.0-release.yml` — Release Gate markerを最終Revisionへ更新
- `tests/test_v118g_release_contract.py` / `tests/test_v1180_prerelease_fixes.py` — 最終Revision契約を固定
- `CHANGELOG.md` / `RELEASE_NOTES.md` / `docs/update.md` — 最終配布手順を更新

DB Table / Column / Migration / SQL / 必須config変更はありません。
