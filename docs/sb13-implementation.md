# SB-13 — Schema / Data Integrity + Table Prefix

Build: `Secure Baseline SB-13 / R2`

## R2 goal

新しいDBを構築できるタイミングで、Legacy固定名 `ig_*` へのRuntime依存をなくし、table prefixを設定可能にする。

## PHP configuration

`config/local.php`:

```php
'DB_TABLE_PREFIX' => 'rss_',
```

Environment:

```text
DB_TABLE_PREFIX=rss_
```

`DB_TABLE_PREFIX` は1〜40文字で先頭ASCII letter/underscore、以降letters/digits/underscoreのみ。PDO parametersはtable identifierをbindできないため、検証後のprefixだけをSQL identifierへ使用する。

`db_table_name()` は次のlogical nameだけを許可する。

```text
user_info
user_conf
content
content_stock
```

## Backward compatibility

R2を既存SB-13 R1 DBへ配置しただけでテーブル参照が変わらないよう、`DB_TABLE_PREFIX` 未設定時のfallbackは `ig_`。

新規環境のsample configと`schema.sql`は `rss_` を推奨値としている。

## SQL files

phpMyAdminで実行するSQLはPHP configを読めないため、各SQLに:

```sql
SET @table_prefix = 'rss_';
```

を持たせる。

`schema.sql` はMySQL user variable + prepared dynamic DDLで4テーブル名を生成する。

Audit SQLも動的table identifierを使うがread-onlyのまま。

Migration SQLは既存Legacy DB向け既定値のみ `ig_` とし、必要なら先頭1行を変更する。

## New DB path

データ保全不要ならALTER migrationより次を推奨する。

1. 新しいDB作成
2. PHPの`DB_NAME` / `DB_TABLE_PREFIX`設定
3. `schema.sql`の`@table_prefix`を同値にする
4. schema import
5. UIから新規登録
6. postflight + function regression

この経路ではLegacy data cleanupやcredential migrationは発生しない。

## Foreign keys

R2でもForeign Keyは追加しない。User deletion policy等は後段で明示的に決定する。
