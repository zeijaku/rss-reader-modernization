# V1.1-H / R1 Task Migration

## 対象

V1.1-Gまで適用済みで、`dashboard_widget`Tableが存在する既存DBへ`task`Tableを追加します。新規の空DBでは`database/schema.sql`を使用するため、個別Migrationは不要です。

## Backup

Migration前にDatabase全体と`config/local.php`、`APP_HASH_KEY`をBackupします。

## CLI

```powershell
php tools/db_v11h.php apply --backup-confirmed
php tools/db_v11h.php verify
```

CLIは`config/local.php`の`DB_TABLE_PREFIX`を使用します。`apply`はBackup確認Optionがない場合は拒否します。

## phpMyAdmin

1. 左側からRSS Readerの実Databaseを選択する。
2. 必要に応じて`database/audit/v1_1_h_preflight.sql`を実行する。
3. `database/migrations/005_v1_1_task.sql`を開く。
4. `SET @table_prefix = 'ig_';`を実環境の`DB_TABLE_PREFIX`へ合わせる。
5. SQL全体を実行する。
6. 同じDatabaseとPrefixで`database/audit/v1_1_h_postflight.sql`を実行する。

Prefixが`rss_`の場合:

```sql
SET @table_prefix = 'rss_';
```

DB変更に必須なのは`005_v1_1_task.sql`です。preflightとpostflightは読取専用の確認SQLです。

## 追加内容

```text
task_id
task_date
task_updated_at
task_flag
task_owner
task_widget_id
task_title
task_due_date
task_priority
task_completed
task_completed_at
task_sort_order
```

Index:

```text
PRIMARY (task_id)
idx_task_owner_widget_flag_order
    (task_owner, task_widget_id, task_flag, task_sort_order, task_id)
idx_task_owner_due
    (task_owner, task_flag, task_completed, task_due_date)
```

既存DataのINSERT、UPDATE、DELETEは行いません。Migrationは`CREATE TABLE IF NOT EXISTS`のため再実行できますが、実行前後のverifyは省略しません。

## Rollback

問題がある場合は、CodeとDatabaseをMigration前のBackupへ戻します。`task`Tableだけを手動削除する方法は、V1.1-Hで作成したTaskを失うため通常のRollback手順にはしません。
