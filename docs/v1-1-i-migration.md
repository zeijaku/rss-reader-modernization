# V1.1-I Migration

## 対象

既存DBへ`calendar_event`Tableを追加します。

```text
database/migrations/006_v1_1_calendar_event.sql
```

既存TableのALTERや既存Data変換は行いません。V1.1-Hまで適用済みで、`dashboard_widget`と`task`が存在することを前提にします。

## CLI

Database全体をBackupした後に実行します。

```powershell
php tools/db_v11i.php apply --backup-confirmed
php tools/db_v11i.php verify
```

## phpMyAdmin

RSS Readerの実Databaseを選択し、各SQL冒頭の`@table_prefix`を`DB_TABLE_PREFIX`と同じ値へ変更します。

```text
1. database/audit/v1_1_i_preflight.sql
2. database/migrations/006_v1_1_calendar_event.sql
3. database/audit/v1_1_i_postflight.sql
```

DB変更に必須なのは2番だけです。1番と3番は読取専用です。

## 確認

Prefixが`rss_`の場合、次が存在します。

```text
rss_calendar_event
```

CLIの正常例:

```text
V1.1-I table: rss_calendar_event
Schema/data verification: PASS
```

## Rollback

問題がある場合はCodeとDBを同じBackup時点へ戻します。Calendar予定を保持したままCodeだけV1.1-Hへ戻す運用は行いません。
