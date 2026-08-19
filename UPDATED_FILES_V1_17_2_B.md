# V1.17.2-B Updated Files

V1.17.2-Aを基準にしたV1.17.2-Bの主な差分です。Productionに必要な変更はRelease ZIPへ更新済み実ファイルとして収録します。

## Runtime / Production

- `app/api.php` — X config status API、create時のServer-side block、Timeline responseのstatus連携。
- `app/common/common_conf.php` — X API Optional configの正式1.17.2整理。
- `app/version.php` — Version／Label／Asset revisionを1.17.2へ確定。
- `app/x_widget.php` — Bearer Token状態、fingerprint Cache、401／2xx認証状態記録。
- `public/js/x-widget.js` — 上級者向け案内、Token状態表示、未設定／形式不正時のAdd block。
- `public/js/calendar.js` — X／V1.17系dynamic assetを1.17.2 revisionへ確定。
- `public/js/camera-video-streaming.js` — fallback stylesheetのactive revisionを1.17.2へ確定。
- `config/local.php.example` — X API PlaceholderとOptional config例。
- `config/.env.example` — X API PlaceholderとOptional config例。

## Release / Documentation

- `README.md`
- `CHANGELOG.md`
- `RELEASE_NOTES.md`
- `docs/configuration.md`
- `docs/release-package.md`
- `docs/tag-and-github-release.md`
- `APPLY_NOTE_V1_17_2_B.md`
- `CHECKLIST_FOR_USER_V1_17_2_B.md`
- `UPDATED_FILES_V1_17_2_B.md`
- `V1_17_2_B_TEST_REPORT.md`

## Tests / CI

- `.github/workflows/ci.yml` — V1.17.2 focused testsをDefault CIへ追加。
- `.github/workflows/v1.17.2-release.yml` — V1.17.2 Release Gate workflow。
- `tests/run-v1172.sh`
- `tests/test_v1172b_x_status.php`
- `tests/test_v1172b_x_status_missing.php`
- `tests/test_v1172b_x_status_invalid.php`
- `tests/test_v1172b_release_gate.py`
- V1.17／V1.17.1のVersion固定Testを、後続Releaseでも当時の機能契約を検証出来るcompatibility checkへ調整。

## Build / Verify

- `tools/build_release_package.py`
- `tools/build_complete_package.py`
- `tools/verify_release_package.py`
- `tools/verify_complete_package.py`

Release targetを1.17.2へ更新し、生成済みRuntime dataとして`var/cache/`全体を配布対象外にします。
