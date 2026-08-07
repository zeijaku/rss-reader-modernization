# Version 1.7.0正式化の変更File

## Application

- `app/version.php`

V1.7-H/R4からのApplication Runtime変更はVersion Markerだけです。

## Repository／Release

- `.gitignore`
- `README.md`
- `CHANGELOG.md`
- `RELEASE_NOTES.md`
- `SOURCE_BUILD.txt`
- `SOURCE_MANIFEST.sha256`
- `APPLY_NOTE.md`
- `APPLY_NOTE_V1_7_RELEASE.md`
- `CHECKLIST_FOR_USER.md`
- `CHECKLIST_FOR_USER_V1_7_RELEASE.md`
- `UPDATED_FILES_V1_7_RELEASE.md`
- `docs/README.md`
- `docs/roadmap.md`
- `docs/versioning.md`
- `docs/update.md`
- `docs/release-package.md`
- `docs/tag-and-github-release.md`
- `docs/github-v1-7-powershell.md`
- `docs/github-release-notes-v1.7.0.md`
- `docs/v1-7-release-implementation.md`
- `docs/v1-7-release-files.md`
- `docs/test-report-v1-7-release.md`
- `docs/release-gate-v1.7.0.md`
- `docs/release-artifact-inventory-v1.7.0.md`

## Package Tool

- `tools/build_complete_package.py`
- `tools/verify_complete_package.py`
- `tools/build_release_package.py`
- `tools/verify_release_package.py`

## Test

- `tests/run.sh`
- `tests/test_v17i_release.py`
- `tests/test_v17i_release_documentation.py`

## DB

正式化段階でDB Schema／Migration内容は変更しません。`.gitignore`へ既存Migration 007／008とAudit SQLを明示的にVersion管理するAllow ruleだけ追加します。
