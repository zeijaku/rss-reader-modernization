# Version 1.4.0 Release変更File

## Application

- `app/version.php` — `1.4.0-dev.3`から正式版`1.4.0`へ確定。

V1.4-D / R2から正式化時にGame Logic、CSS、JavaScript、API、DB Runtimeの追加変更はありません。

## Documentation

- `README.md`
- `CHANGELOG.md`
- `RELEASE_NOTES.md`
- `APPLY_NOTE.md`
- `CHECKLIST_FOR_USER.md`
- `SOURCE_BUILD.txt`
- `SOURCE_MANIFEST.sha256`
- `docs/README.md`
- `docs/roadmap.md`
- `docs/versioning.md`
- `docs/update.md`
- `docs/deployment-checklist.md`
- `docs/release-package.md`
- `docs/tag-and-github-release.md`
- `docs/v1-4-release-implementation.md`
- `docs/v1-4-release-files.md`
- `docs/test-report-v1-4-release.md`
- `docs/release-gate-v1.4.0.md`
- `docs/release-artifact-inventory-v1.4.0.md`
- `APPLY_NOTE_V1_4_RELEASE.md`
- `CHECKLIST_FOR_USER_V1_4_RELEASE.md`
- `UPDATED_FILES_V1_4_RELEASE.md`

## Test／Package Tool

- `tests/run.sh`
- `tests/test_v14e_release.py`
- `tests/test_v14e_release_documentation.py`
- V1.4-B～DのVersion許容Test
- `tools/build_complete_package.py`
- `tools/verify_complete_package.py`
- `tools/build_release_package.py`
- `tools/verify_release_package.py`

## DB／設定／Runtime

- DB変更：なし
- SQL／Migration：なし
- `config/local.php`：変更・同梱なし
- Server固有`.htaccess`：追加変更なし
- Runtime Data：同梱なし
