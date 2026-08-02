# M4-G / R1 Files

M4-F / R1からM4-G / R1への変更一覧です。

## Changed — 33 files

```text
CHANGELOG.md
CHECKLIST_FOR_USER.md
README.md
RELEASE_NOTES.md
app/version.php
docs/README.md
docs/package-manifest.txt
docs/release-artifact-inventory-v1.0.0.md
docs/release-gate-v1.0.0.md
docs/release-package.md
docs/roadmap.md
docs/tag-and-github-release.md
docs/update.md
docs/versioning.md
tests/run.sh
tests/test_m2c_dashboard_render.py
tests/test_m2d_dashboard_render.py
tests/test_m2g_final_regression.py
tests/test_m4a_release_baseline.py
tests/test_m4a_release_gate.py
tests/test_m4b_documentation.py
tests/test_m4c_healthcheck_contract.py
tests/test_m4c_operations_docs.py
tests/test_m4d_repository_docs.py
tests/test_m4e_release_builder.py
tests/test_m4e_release_docs.py
tests/test_m4e_release_process.py
tests/test_m4f_documentation.py
tests/test_m4f_environment_probe.py
tests/test_m4f_release_candidate.py
tests/test_sb15_docs.py
tools/build_release_package.py
tools/verify_release_package.py
```

過去工程Testの変更は、現在Versionを`1.0.0`へ進めながら、M4-F RC1とHOLD / PENDING Evidenceの履歴を維持するための追従です。Security、DB、API、Feed、Frontendの検査は削除していません。

## Added — 8 files

```text
docs/m4-g-files.md
docs/m4-g-implementation.md
docs/package-manifest-m4-g-r1.txt
docs/test-report-m4-g.md
tests/test_m4g_checkpoint_package.py
tests/test_m4g_documentation.py
tests/test_m4g_final_release.py
tests/test_m4g_release_process.py
```

## Deleted

```text
なし
```

## RC1からFinalのRuntime差分

```text
app/version.php             Version markerのみ変更
public/                     変更なし
config/                     変更なし
database/                   変更なし
```

## Runtime compatibility

```text
DB schema / Migration      変更なし
Public API                 変更なし
Authentication / Session  変更なし
Feed Engine                変更なし
Frontend Runtime Asset     変更なし
必須設定                   追加なし
Cache clear                不要
```
