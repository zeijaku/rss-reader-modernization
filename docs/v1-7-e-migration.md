# V1.7-E Remember Token Migration

## Migration

```text
database/migrations/007_v1_7_remember_token.sql
```

物理Table名は`DB_TABLE_PREFIX`＋`remember_token`です。

## Column

| Column | 用途 |
|---|---|
| `remember_token_id` | Primary Key |
| `remember_token_user_id` | `user_info.user_id` |
| `remember_token_selector` | 24文字の完全一致検索Key |
| `remember_token_validator_hash` | Validator SHA-256 |
| `remember_token_created_at` | 発行日時 |
| `remember_token_expires_at` | 固定有効期限 |
| `remember_token_last_used_at` | 最終Rotation日時 |

Legacy DataとUser削除方針を維持するため、初回はForeign Keyを追加しません。

## 実行

1. 実DatabaseをBackupします。
2. `database/audit/v1_7_e_preflight.sql`を実行します。
3. Migrationの`SET @table_prefix = 'ig_';`を実環境と一致させます。
4. Migrationを実行します。
5. `database/audit/v1_7_e_postflight.sql`を実行します。

`CREATE TABLE IF NOT EXISTS`のため再実行で既存行を削除しませんが、適用管理上は1回だけ実行してください。

## Rollback

V1.7-Fをまだ有効にしていない段階ではTableに実Tokenはありません。Rollbackが必要な場合は、対象DatabaseとPrefixを再確認し、Token件数が0であることを確認してからTableを削除します。

```sql
DROP TABLE `<prefix>remember_token`;
```
