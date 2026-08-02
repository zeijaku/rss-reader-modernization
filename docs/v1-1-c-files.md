# V1.1-C / R1 Files

V1.1-B / R1との差分。削除Fileはない。

## Added — 17

```text
APPLY_NOTE.md
app/feed/feed_item_state.php
database/audit/v1_1_c_postflight.sql
database/audit/v1_1_c_preflight.sql
database/migrations/002_v1_1_feed_item_state.sql
docs/test-report-v1-1-c.md
docs/v1-1-c-files.md
docs/v1-1-c-implementation.md
docs/v1-1-c-migration.md
docs/v1-1-c-overlay-manifest.txt
tests/run-local-v1-1-c.sh
tests/test_v11c_architecture.py
tests/test_v11c_checkpoint_package.py
tests/test_v11c_feed_item_state.php
tests/test_v11c_runner.py
tests/test_v11c_sql.py
tools/db_v11c.php
```

## Modified — 34

```text
CHANGELOG.md
CHECKLIST_FOR_USER.md
README.md
app/api.php
app/bootstrap.php
app/common/common_conf.php
app/feed/feed_fetch_service.php
app/feed/feed_parser.php
app/feed/normalized_item.php
app/version.php
config/.env.example
config/local.php.example
database/schema.sql
docs/configuration.md
public/css/dashboard.css
public/js/dashboard.js
tests/run.sh
tests/test_m1a_architecture.py
tests/test_m1b_architecture.py
tests/test_m1c_architecture.py
tests/test_m1d_architecture.py
tests/test_m1e_architecture.py
tests/test_m1e_concurrency.py
tests/test_m1e_feed_cache.php
tests/test_m1f_cache_revalidation.php
tests/test_m1f_concurrency.py
tests/test_m1g_concurrency.py
tests/test_m1g_fetch_resilience.php
tests/test_sb05_07_static.py
tests/test_sb12_atom_link_static.py
tests/test_sb13_sql.py
tests/test_sb14_surface_static.py
tests/test_sb15_docs.py
tests/test_v11b_architecture.py
```

## Deleted — 0

```text
なし
```

Test変更は、Identity付き内部PayloadとV1.1開発Versionを認識させるための追従で、Security検査や既存機能検査を削除していない。
