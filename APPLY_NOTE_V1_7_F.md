# V1.7-F / R1 適用メモ

## 目的

Migration 007で追加したRemember TokenをLogin UI、Cookie、Session、Logout、Password変更へ接続し、任意の30日間ログイン維持を有効にします。

## Application

- Version: `1.7.0-dev.5`
- Label: `RSS Reader Modernization V1.7-F / R1`
- DB Migration: V1.7-Eの`007_v1_7_remember_token.sql`が必須
- 新規Migration: なし
- API Route: 変更なし
- 外部Library: なし

## 適用順

1. Applicationと実DatabaseをBackupします。
2. Migration 007が未適用の場合は、V1.7-EのPreflight／Migration／Postflightを先に実行します。
3. V1.7-F Application Codeを配置します。
4. Login画面に「この端末で30日間ログイン状態を維持」が表示されることを確認します。
5. 選択してLoginし、Session期限切れ相当後も自動Loginされることを確認します。
6. LogoutとPassword変更後に旧Remember Cookieで自動Loginされないことを確認します。

共用端末ではCheckboxを選択しないでください。
