# M4-E / R1 file changes

## Changed

この一覧はM4-D / R1との差分として最終Package生成後に確認する。

```text
CHANGELOG.md
CHECKLIST_FOR_USER.md
README.md
app/version.php
docs/README.md
docs/package-manifest.txt
docs/release-artifact-inventory-v1.0.0.md
docs/release-gate-v1.0.0.md
docs/roadmap.md
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
tests/test_sb15_docs.py
```

## Added

```text
RELEASE_NOTES.md
docs/m4-e-files.md
docs/m4-e-implementation.md
docs/package-manifest-m4-e-r1.txt
docs/release-package.md
docs/tag-and-github-release.md
docs/test-report-m4-e.md
tests/test_m4e_checkpoint_package.py
tests/test_m4e_release_builder.py
tests/test_m4e_release_docs.py
tests/test_m4e_release_process.py
tools/build_release_package.py
tools/verify_release_package.py
```

## Deleted

```text
none
```

Runtime Release ZIPはCheckpoint ZIPとは別に生成する。Preview ZIPをProject内へ入れず、入れ子ZIPを作らない。
