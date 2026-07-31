# SB-13 R2 Test Report

Build: `Secure Baseline SB-13 / R2`

## R2 scope

SB-13 R1のschema/integrity基盤に、次を追加した。

- PHP runtime `DB_TABLE_PREFIX`
- strict prefix validation
- runtime DB SQLの固定 `ig_*` 排除
- DB integrity / CLI migration helperのprefix対応
- phpMyAdmin用SQLの `@table_prefix`
- new DB向け既定prefix `rss_`

## Environment

- PHP CLI: 8.4.16
- PDO core: available
- PDO database drivers: unavailable in this sandbox
- `pdo_mysql`: unavailable
- cURL: unavailable
- SimpleXML: unavailable
- mbstring: unavailable

そのため、実MySQL 8へ `schema.sql` をImportする試験と、real MySQL/MariaDB DDL実行はこの環境では未実施。配置先phpMyAdminでのschema import / application smoke testをRelease Gateとする。

## Full regression suite

`./tests/run.sh`

Final source-tree result:

- PHP syntax: **37 files OK**
- explicit PASS checks: **612**
- FAIL: **0**
- expected environment SKIP: **2**
  - PDO SQLite integration unavailable because no PDO driver is installed
  - live SimpleXML fixture parsing unavailable because SimpleXML/mbstring are missing

## Package re-extraction verification

Clean runtime artifacts were removed before packaging. The ZIP was then integrity-tested, extracted into a separate directory, and the complete suite was rerun from the extracted copy:

- PHP syntax: **37 files OK**
- PASS: **612**
- FAIL: **0**
- expected SKIP: **2**

The packaged tree was also checked to contain no real `config/local.php`, session files, error logs, or DB migration snapshots.

## Prefix-specific coverage

### Runtime

Custom test prefix:

```text
rss_test_
```

でDB APIを実行し、代表的な全SQLが:

```text
rss_test_user_info
rss_test_user_conf
rss_test_content
rss_test_content_stock
```

を参照することを確認した。

同テストではRuntime SQLにLegacy固定名 `ig_*` が残らないことも確認した。

### Prefix validation

次を確認した。

- empty prefix: rejected
- prefix starting with ASCII letter/underscore and containing letters/digits/underscore: accepted
- 40 characters: accepted
- 41 characters: rejected
- leading digit: rejected
- hyphen: rejected
- space: rejected
- backtick: rejected
- non-ASCII prefix: rejected
- unknown logical table: rejected

Runtime defaultは、既存R1 DBを壊さないため `ig_`。新規設定例は `rss_`。

### SQL artifacts

次の5 SQLすべてに編集可能な `@table_prefix` を持たせた。

- `database/schema.sql`
- `database/audit/preflight.sql`
- `database/audit/postflight.sql`
- `database/migrations/001_sb13_integrity.sql`
- `database/fixtures/sample.sql`

`schema.sql` のdynamic `CONCAT()`をテスト側で `rss_` としてrenderし、生成される4 CREATE TABLE文について:

- physical table names
- InnoDB
- utf8mb4
- utf8mb4_unicode_ci
- URL default quote
- Stock title default quote

を確認した。

実際のMySQL parserによる実行確認だけは、DB driver/serverがないため未実施。

### Integrity / safety regression

- duplicate user_conf gate
- negative relationship ID gate
- orphan / duplicate identity preservation
- target collation
- unsigned relationship columns
- required indexes
- no Foreign Key
- no row cleanup DML
- before/after count and distribution logic
- backup-confirmed CLI guard

はR2でも通過。

## Deployment-side mandatory test for the new DB

1. MySQL 8に空DB作成
2. `config/local.php` の `DB_NAME` を新DBへ変更
3. `DB_TABLE_PREFIX='rss_'`
4. `schema.sql` の `SET @table_prefix='rss_'`
5. phpMyAdminでschema import
6. `rss_*` 4テーブル確認
7. UIで新規登録
8. Login / Feed / Atom / Stock / Settings / Logout
9. `rss_*` へデータ保存されることをphpMyAdminで確認
10. optional `postflight.sql`

ここまで通った段階でSB-13 R2を確定できる。
