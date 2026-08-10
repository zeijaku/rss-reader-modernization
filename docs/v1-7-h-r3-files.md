# V1.7-H / R3 files

## Runtime change from V1.7-H / R2

```text
app/version.php
public/css/dashboard.css
public/js/dashboard.js
```

## Test／Documentation

```text
tests/test_v17h_r3_architecture.py
tests/run.sh
V1.7-H以降を許可する既存Version／Asset Test
APPLY_NOTE_V1_7_H_R3.md
CHECKLIST_FOR_USER_V1_7_H_R3.md
UPDATED_FILES_V1_7_H_R3.md
docs/v1-7-h-r3-implementation.md
docs/v1-7-h-r3-files.md
docs/test-report-v1-7-h-r3.md
```

## DB boundary

R3による新しいDB変更はありません。

```text
database/schema.sql                      変更なし
database/migrations/008_v1_7_widget_height.sql  R2版を維持
database/audit/v1_7_h_preflight.sql      R2版を維持
database/audit/v1_7_h_postflight.sql     R2版を維持
Migration 009                            追加なし
```

RSS表示件数は既存`dashboard_widget.widget_config`を継続利用します。
