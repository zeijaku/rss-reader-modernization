# V1.19-E Updated Files

V1.19-EはRelease Candidate化、Compatibility Test maintenance、Release Gate、Package契約の更新が中心です。新機能は追加していません。

## Runtime / release metadata

- `app/version.php` — `1.19.0-rc1` / `RSS Reader Modernization 1.19.0-RC1` / Asset Revision `1.19.0-rc1`
- `public/js/calendar.js` — 現在のlazy-loaded assetsをRC revisionへ統一
- `public/js/camera-video-streaming.js` — fallback CSS revisionをRCへ統一。V1.19-Cで確定したhls.js SRI値は維持

## Package tooling

- `tools/build_release_package.py`
- `tools/verify_release_package.py`
- `tools/build_complete_package.py`
- `tools/verify_complete_package.py`

V1.19.0をintended stable releaseとし、RC1 packageを`RELEASE_CANDIDATE / publishable=no`としてBuild/Verify出来るよう更新。

## Test / CI

- `tests/test_v119e_release_candidate.py`
- `tests/run-v119e.sh`
- `.github/workflows/ci.yml`
- Bで分割したAPI ModuleとEのRC Versionを正しく検証するためのhistorical compatibility/static tests

Static test maintenanceは、Facade + Modulesを論理API Sourceとして検査する変更、およびlater compatible version / `-rcN`を受け入れる変更が中心。Behavior/Security assertionは維持。

## Documentation

- `README.md`
- `CHANGELOG.md`
- `RELEASE_NOTES.md`
- `docs/versioning.md`
- `docs/release-package.md`
- `docs/update.md`
- `docs/tag-and-github-release.md`
- `docs/README.md`
- `docs/v1-19-e-release-candidate.md`
- `docs/v1-19-e-production-checklist.md`
- `docs/v1-19-e-updated-files.md`
- `docs/test-report-v1-19-e.md`
- `APPLY_NOTE_V1_19_E.md`
- `CHECKLIST_FOR_USER_V1_19_E.md`
- `UPDATED_FILES_V1_19_E.md`
- `V1_19_E_TEST_REPORT.md`

DB schema / migration / SQL、新規必須Config / Secretの変更はありません。
