# M4-F / R1 Files

M4-E / R1からM4-F / R1への変更一覧です。

## Changed — 32 files

```text
.gitignore
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
tests/test_sb11_12_static.py
tests/test_sb12_atom_link_static.py
tests/test_sb13_sql.py
tests/test_sb15_docs.py
tests/test_version_marker.py
tools/build_release_package.py
tools/verify_release_package.py
```

過去工程Testの変更は、現在Checkpointが`1.0.0-rc1`へ進んだことと、Release markerが`M4-*`からSemVer RCへ変わったことへの追従です。Security、DB、API、Feed、Frontendの検査を削除していません。

## Added — 14 files

```text
docs/m4-f-files.md
docs/m4-f-implementation.md
docs/m4-f-validation-template.json
docs/m4-f-validation.md
docs/package-manifest-m4-f-r1.txt
docs/test-report-m4-f.md
tests/test_m4f_checkpoint_package.py
tests/test_m4f_documentation.py
tests/test_m4f_environment_probe.py
tests/test_m4f_evidence_gate.py
tests/test_m4f_release_candidate.py
tools/m4f_environment_probe.php
tools/m4f_evidence_gate.py
var/m4f-evidence/.gitkeep
```

## Deleted

```text
なし
```

## Runtime compatibility

```text
DB schema / Migration     変更なし
Public API                変更なし
Authentication / Session 変更なし
Feed Engine               変更なし
Frontend Runtime Asset    変更なし
必須設定                  追加なし
Cache clear               不要
```
