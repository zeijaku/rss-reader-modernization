# V1.7-H / R2 Migration compatibility note

## R2 application itself

V1.7-H/R2のScrollbar／RSS表示件数修正による新しいDB変更はありません。RSS表示件数は既存`dashboard_widget.widget_config`へ保存します。

## Migration 008 revision

V1.7-H/R1で追加した`widget_height`用の3 SQL Fileだけ、共有Hosting互換性のためR2へ差し替えています。

- `database/audit/v1_7_h_preflight.sql`
- `database/migrations/008_v1_7_widget_height.sql`
- `database/audit/v1_7_h_postflight.sql`

R1のSQLが使用していた`information_schema`参照は、共有Hostingで`#1044`になる環境が確認されたため使用しません。

3 Fileとも`@table_prefix`から対象Tableを組み立てます。

```sql
SET @table_prefix = 'ig_';
```

この値は実環境の`DB_TABLE_PREFIX`と同じ値へ合わせます。

## Already migrated

Preflightで`widget_height`が表示される場合はMigration 008を再実行しません。`ADD COLUMN`を再実行するとMySQL／MariaDBの`#1060 Duplicate column`になります。

R1を適用済みの環境ではPostflightだけを任意実行し、次を確認します。

- `widget_height`が存在
- `TINYINT UNSIGNED NOT NULL DEFAULT 1`
- 値は1または2だけ
- `invalid_widget_height_rows = 0`

## Not migrated

`widget_height`が存在しない場合だけ、Preflight → Migration 008 → Postflightの順に一度実行します。
