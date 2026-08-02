# M4-D / R1 file changes

M4-C / R1からの変更fileは22件、新規fileは16件、削除fileは0件。

## 変更file

```text
.gitignore
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
tests/test_sb15_docs.py
```

## 新規file

```text
.github/ISSUE_TEMPLATE/bug_report.yml
.github/ISSUE_TEMPLATE/config.yml
.github/workflows/ci.yml
CONTRIBUTING.md
SECURITY.md
docs/ci.md
docs/github-publication.md
docs/m4-d-files.md
docs/m4-d-implementation.md
docs/package-manifest-m4-d-r1.txt
docs/portfolio.md
docs/test-report-m4-d.md
tests/test_m4d_ci_workflow.py
tests/test_m4d_package.py
tests/test_m4d_public_surface.py
tests/test_m4d_repository_docs.py
```

## 削除file

```text
なし
```

## 適用上の注意

- 削除fileはないため、M4-C / R1へ上書きで適用できる。
- DB migration、設定追加、Cache clearは不要。
- GitHub Actionsのhosted runとRepository Settingsはpush後に確認する。
