# Version 1.3.0正式化の変更File

## Application

- `app/version.php` — `1.3.0-dev.3`から正式版`1.3.0`へ確定。

V1.3-Dから正式化時にHeader、Drawer、記事表示、JavaScript、API、DB Runtimeの追加変更はありません。

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
- `docs/v1-3-release-implementation.md`
- `docs/v1-3-release-files.md`
- `docs/test-report-v1-3-release.md`
- `docs/release-gate-v1.3.0.md`
- `docs/release-artifact-inventory-v1.3.0.md`
- `APPLY_NOTE_V1_3_RELEASE.md`
- `CHECKLIST_FOR_USER_V1_3_RELEASE.md`
- `UPDATED_FILES_V1_3_RELEASE.md`

## Test／Package Tool

- `tests/run.sh`
- `tests/test_v13e_release.py`
- `tests/test_v13e_release_documentation.py`
- V1.3-B～DおよびV1.2関連のVersion許容Test
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
