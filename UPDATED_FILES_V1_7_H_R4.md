# V1.7-H / R4 File boundary

## Runtime changes from V1.7-H / R3

```text
.gitignore
app/version.php
app/bootstrap.php
app/common/common_conf.php
app/http_fetch.php
app/holiday.php                              new
app/calendar.php
app/api.php
app/data/japanese_holidays_snapshot.json     new
public/css/dashboard.css
public/js/calendar.js
config/local.php.example
config/.env.example
```

## Test changes

```text
tests/test_v17h_r4_holiday.php               new
tests/test_v17h_r4_architecture.py            new
tests/test_v17h_r4_browser.py                 new
tests/test_v11i_calendar_widget.php
tests/test_v11i_browser.py
tests/test_v11d_dashboard_render.py           nondeterministic fixture check correction
tests/test_v11i_dashboard_render.py
tests/test_v16c_architecture.py
tests/test_v16c_dashboard_render.py
tests/test_v16d_architecture.py
tests/test_v17b_github_baseline.py
tests/test_v17c_asset_inventory.py
tests/test_v17c_asset_render.py
tests/test_v17d_cache_security.py
tests/test_v17e_architecture.py
tests/test_v17f_architecture.py
tests/test_v17g_architecture.py
tests/test_v17h_architecture.py
tests/test_v17h_dashboard_render.py
tests/test_v17h_r2_architecture.py
tests/test_v17h_r3_architecture.py
tests/run.sh
```

Historical tests listed above only accept the later R4 Version marker／baseline where required; their feature expectations are retained.

## Documentation

```text
APPLY_NOTE.md
APPLY_NOTE_V1_7_H_R4.md
CHECKLIST_FOR_USER.md
CHECKLIST_FOR_USER_V1_7_H_R4.md
UPDATED_FILES_V1_7_H_R4.md
README.md
CHANGELOG.md
RELEASE_NOTES.md
SOURCE_BUILD.txt
docs/README.md
docs/roadmap.md
docs/v1-7-h-r4-implementation.md
docs/v1-7-h-r4-files.md
docs/test-report-v1-7-h-r4.md
```

## DB boundary

R4によるDB変更はない。

```text
database/        R3から変更なし
Migration 009    追加なし
```

祝日取得Cacheは`var/cache/`のRuntime FileでありDBへ保存しない。
