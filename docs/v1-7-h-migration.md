# V1.7-H Migration 008

## Purpose

既存`dashboard_widget` Tableへ縦幅を追加する。

```sql
widget_height TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1..2'
```

## Files

```text
database/audit/v1_7_h_preflight.sql
database/migrations/008_v1_7_widget_height.sql
database/audit/v1_7_h_postflight.sql
```

すべてのSQLで`@table_prefix = 'ig_'`を実環境のPrefixへ合わせる。

## Order

1. DB Backup
2. Preflight
3. Migration 008
4. Postflight
5. Application配置

MigrationはColumn存在確認後に追加するため再実行可能で、既存のNULLまたは1／2以外の値は1へ正規化する。

## Rollback

ApplicationをV1.7-G以前へ戻すだけなら、追加Columnは残しても旧Applicationへ影響しない。Column削除はData Lossを伴うため通常のRollbackでは実行しない。
