# V1.1-G / R1 Memo Migration

## 対象

V1.1-Fまで適用済みで、`dashboard_widget`Tableが存在する既存DBへ`memo`Tableを追加します。新規の空DBでは`database/schema.sql`を使用するため、個別Migrationは不要です。

## Backup

Migration前にDatabase全体と`config/local.php`、`APP_HASH_KEY`をBackupします。

## CLI

```powershell
php tools/db_v11g.php apply --backup-confirmed
php tools/db_v11g.php verify
```

CLIは`config/local.php`の`DB_TABLE_PREFIX`を使用します。`apply`はBackup確認Optionがない場合は拒否します。

## phpMyAdmin

1. 左側からRSS Readerの実Databaseを選択する。
2. `database/migrations/004_v1_1_memo.sql`を開く。
3. `SET @table_prefix = 'ig_';`を実環境の`DB_TABLE_PREFIX`へ合わせる。
4. SQL全体を実行する。
5. 同じDatabaseとPrefixで`database/audit/v1_1_g_postflight.sql`を実行する。

Prefixが`rss_`の場合:

```sql
SET @table_prefix = 'rss_';
```

## 追加内容

```text
memo_id
memo_date
memo_updated_at
memo_flag
memo_owner
memo_title
memo_body
```

Index:

```text
PRIMARY (memo_id)
idx_memo_owner_flag_id (memo_owner, memo_flag, memo_id)
```

既存DataのINSERT、UPDATE、DELETEは行いません。Migrationは`CREATE TABLE IF NOT EXISTS`のため再実行できますが、実行前後のverifyは省略しません。

## Rollback

問題がある場合は、CodeとDatabaseをMigration前のBackupへ戻します。`memo`Tableだけを手動削除する方法は、V1.1-Gで作成したMemoを失うため通常のRollback手順にはしません。
