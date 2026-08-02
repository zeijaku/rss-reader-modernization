# V1.1-D / R1 Files

V1.1-C / R1との差分。削除Fileはない。

## Added — 17

```text
app/dashboard_widget.php
database/audit/v1_1_d_postflight.sql
database/audit/v1_1_d_preflight.sql
database/migrations/003_v1_1_dashboard_widget.sql
docs/test-report-v1-1-d.md
docs/v1-1-d-files.md
docs/v1-1-d-implementation.md
docs/v1-1-d-migration.md
docs/v1-1-d-overlay-manifest.txt
tests/run-local-v1-1-d.sh
tests/test_v11d_architecture.py
tests/test_v11d_checkpoint_package.py
tests/test_v11d_dashboard_render.py
tests/test_v11d_dashboard_widget.php
tests/test_v11d_runner.py
tests/test_v11d_sql.py
tools/db_v11d.php
```

## Modified — 20

```text
APPLY_NOTE.md
CHANGELOG.md
CHECKLIST_FOR_USER.md
README.md
app/api.php
app/bootstrap.php
app/common/common_conf.php
app/version.php
database/schema.sql
docs/README.md
docs/versioning.md
public/css/dashboard.css
public/index.php
tests/run.sh
tests/test_m2c_dashboard_render.py
tests/test_m2d_dashboard_render.py
tests/test_sb05_07_api.php
tests/test_sb05_07_static.py
tests/test_sb13_sql.py
tests/test_v11c_architecture.py
```

## Deleted — 0

```text
なし
```

M2-C / M2-DのTest変更は、旧`content`直接QueryのFake DBをWidget-backed Dashboardへ追従させたもの。Accessibility、Responsive、Touch、Stock、ARIAの検査を削除していない。
