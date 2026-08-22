# V1.19-F Updated Files

V1.19-Fは正式Release metadata、Package、Documentation、Test / CIの確定が中心です。V1.19.0-RC1から新機能は追加していません。

## Runtime / release metadata

- `app/version.php`
- `public/js/calendar.js`
- `public/js/camera-video-streaming.js`

RC1から正式`1.19.0` / Asset Revision `1.19.0`へ確定。

## Package tooling

- `tools/build_complete_package.py`
- `tools/verify_complete_package.py`
- Runtime builder / verifierは既存Final modeを使用。

## Test / CI

- `tests/test_v119f_release_final.py`
- `tests/run-v119f.sh`
- `tests/test_v119e_release_candidate.py`（final compatibility対応）
- `tests/test_v118g_release_contract.py`（final complete package対応）
- `.github/workflows/ci.yml`
- `.github/workflows/v1.19.0-release.yml`

## Documentation

- `README.md`
- `CHANGELOG.md`
- `RELEASE_NOTES.md`
- `docs/versioning.md`
- `docs/release-package.md`
- `docs/tag-and-github-release.md`
- `docs/update.md`
- `docs/README.md`
- `docs/v1-19-f-final-release.md`
- `docs/v1-19-f-production-checklist.md`
- `docs/v1-19-f-updated-files.md`
- `docs/test-report-v1-19-f.md`
- Root handoff files: `APPLY_NOTE_V1_19_F.md`、`CHECKLIST_FOR_USER_V1_19_F.md`、`UPDATED_FILES_V1_19_F.md`、`V1_19_F_TEST_REPORT.md`

## Removed source-only temporary markers

正式`v1.18.0` Tagには存在せず、Release観測用Branchだけにあった次の6 filesをComplete Sourceから除外します。

- `.github/.v118-publish-pr-note`
- `.github/.v118-publish-pr-instructions`
- `.github/.v118-publish-pr-marker`
- `.github/.v118-publish-do-not-merge`
- `.github/.v118-publish-trigger-2`
- `.github/.v118-publish-final-marker`

Runtime packageには元々含まれないため、本番Application behaviorへの影響はありません。
