# V1.1-D Database Migration

## Migration order

V1.1-DはV1.1-C適用済みDBを前提とする。

```text
001_sb13_integrity.sql
002_v1_1_feed_item_state.sql
003_v1_1_dashboard_widget.sql
```

既に適用済みのMigrationを推測で再投入せず、各verify結果を確認する。

## Before applying

1. Git作業Folder、`config/local.php`、APP_HASH_KEYをBackupする。
2. 実DB全体をBackupし、Restore可能な形式であることを確認する。
3. `DB_TABLE_PREFIX`を確認する。
4. `php tools/db_sb13.php verify`を実行する。
5. `php tools/db_v11c.php verify`を実行する。
6. `database/audit/v1_1_d_preflight.sql`を実Prefixで確認する。

SB-13またはV1.1-C verifyがFAILする場合は、V1.1-Dを適用せず既存DBを確認する。

## Apply with CLI

```bash
php tools/db_v11d.php apply --backup-confirmed
php tools/db_v11d.php verify
```

CLI Toolは`config/local.php`の`DB_TABLE_PREFIX`から実Table名を決定する。

成功例：

```text
V1.1-D table: ig_dashboard_widget
Schema/data verification: PASS
```

## Apply with phpMyAdmin

次の順に実行する。

1. `database/audit/v1_1_d_preflight.sql`
2. `database/migrations/003_v1_1_dashboard_widget.sql`
3. `database/audit/v1_1_d_postflight.sql`

各SQL冒頭の値を実環境に合わせる。

```sql
SET @table_prefix = 'ig_';
```

Migrationは`CREATE TABLE IF NOT EXISTS`とFeed Backfillで構成する。既存Content、Stock、User、Feed item stateを削除・変更しない。

## Re-run behavior

再実行時はUnique Indexで同じFeed Widgetの重複を防ぐ。

- active Feedのlocation / style / flagは同期する。
- `widget_sort_order`は更新しない。
- Widget幅や設定も上書きしない。
- 同名TableのColumnやIndexが誤っている場合、CLI verifyをFAILにして停止する。

## Deployment order

Widget Tableが無い状態でV1.1-D Codeを本番利用しない。

```text
Backup
→ V1.1-D Overlayを作業Copyへ適用
→ SB-13 / V1.1-C verify
→ V1.1-D Migration
→ V1.1-D verify
→ Test
→ Updated Codeを配置／公開
```

## Rollback

基本RollbackはApplication CodeをV1.1-Cへ戻し、`dashboard_widget`Tableを残す。

Feed本体は`content`Tableに残るため、V1.1-Cは従来どおりFeedを表示出来る。Widget Tableを残しても旧Codeから参照されない。

Tableを外す必要がある場合は、先にExportし、実Prefixを確認してRenameする。

```sql
RENAME TABLE
    `ig_dashboard_widget`
TO
    `ig_dashboard_widget_v11d_backup`;
```

V1.1-D以降で作成したMemo、Task、Calendar Widget設定を含む可能性があるため、将来工程では安易にDROPしない。
