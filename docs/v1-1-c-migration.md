# V1.1-C Database Migration

## Before applying

1. 実DB全体をBackupする。
2. `config/local.php`とAPP_HASH_KEYを安全な場所へBackupする。
3. `DB_TABLE_PREFIX`を確認する。
4. Version 1.0.0の既存4TableとSB-13 Indexがあることを確認する。
5. `database/audit/v1_1_c_preflight.sql`冒頭の`@table_prefix`を実環境と合わせて実行する。

## Apply

### CLIが使える場合

```bash
php tools/db_v11c.php apply --backup-confirmed
php tools/db_v11c.php verify
```

CLI Toolは`DB_TABLE_PREFIX`からTable名を決める。

### phpMyAdmin等で実行する場合

`database/migrations/002_v1_1_feed_item_state.sql`冒頭の`@table_prefix`を実環境と合わせて実行する。その後、`database/audit/v1_1_c_postflight.sql`を同じPrefixで実行する。

Migrationは既存Tableや既存Rowを変更せず、`CREATE TABLE IF NOT EXISTS`で新Tableだけを追加する。再実行時は既存Tableを上書きしないため、postflightまたはCLI verifyで定義を確認する。

## Rollback

Version 1.0.0/V1.1-BへCodeを戻しても、旧Codeは`feed_item_state`を参照しないためTableを残したままRollback出来る。これを基本Rollbackとする。

Tableを削除する場合は、先にExportしてから実Prefix付きTableを明示して行う。

```sql
RENAME TABLE `ig_feed_item_state` TO `ig_feed_item_state_v11c_backup`;
```

動作確認後にBackup Tableを削除する。Prefixを確認せずDROPしない。
