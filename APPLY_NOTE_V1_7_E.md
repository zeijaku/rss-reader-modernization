# V1.7-E / R1 適用メモ

## 目的

30日間ログインの土台となるRemember Token TableとBackend処理を追加します。Login画面のCheckbox、Cookie発行、自動Login、Logout／Password変更との接続はV1.7-Fで行います。

## Application

- Version: `1.7.0-dev.4`
- Label: `RSS Reader Modernization V1.7-E / R1`
- DB Migration: `database/migrations/007_v1_7_remember_token.sql`
- API Route: 変更なし
- Login UI／Cookie／自動Login: まだ未有効
- 外部Library: なし

## 適用順

1. Applicationと実DatabaseをBackupします。
2. `database/audit/v1_7_e_preflight.sql`を実Databaseで実行します。
3. Migration先頭の`@table_prefix`を`DB_TABLE_PREFIX`と同じ値へ変更します。
4. `database/migrations/007_v1_7_remember_token.sql`を1回実行します。
5. `database/audit/v1_7_e_postflight.sql`でColumn、Index、Selector／Hash形式を確認します。
6. Application Codeを配置します。

V1.7-Eだけでは利用者のLogin画面やSession動作は変わりません。
