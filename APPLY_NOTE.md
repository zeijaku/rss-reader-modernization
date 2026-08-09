# Version 1.9.0 適用手順

## Baseline

- GitHub Baseline: Version 1.8.0 `main`
- Baseline Commit: `3b729e7274f9561a9ce2aa10b1572b50f2ca882d`
- Application Version: `1.9.0`
- Application Label: `RSS Reader Modernization 1.9.0`
- Version 1.8.0からVersion 1.9.0へのDB変更: Migration 009で`mail_account` Tableを追加
- 既存TableへのALTER: なし

## V1.9-F / Final Overlayを検証済みの環境

すでにV1.9-FからFinal Overlayまで適用し、Mail Account登録・接続・一覧・本文表示・D&D・Account管理を確認済みの場合、Application Runtimeはそのまま使用できます。正式版ではVersion markerとRelease metadataを`1.9.0`として固定します。

## Version 1.8.0から更新する環境

1. Application、実DB、`config/local.php`、`var/`をBackupします。
2. Version 1.9.0 Runtime ZIPを本番とは別Folderへ展開します。
3. `config/local.php`、実DB、Session／Log／Cache等のRuntime Dataを保持したままCodeを更新します。
4. Mail Credential Keyをprivate configへ設定します。`APP_HASH_KEY`は流用しません。
5. DB Table Prefixを確認して、Migration 009が未適用の場合だけpreflight -> migration -> postflightの順で実行します。
6. BrowserをHard Reloadし、Footerが`RSS Reader Modernization 1.9.0`であることを確認します。
7. RSS／Stock／既存WidgetとMail Widgetを確認します。

## Mail private config

```php
'APP_MAIL_CREDENTIAL_KEY_ID' => 'primary',
'APP_MAIL_CREDENTIAL_KEY_B64' => '<32-byte random key encoded as Base64>',
'APP_MAIL_IMAP_TIMEOUT_SECONDS' => '5',
```

Credential KeyはGitへCommitせず、第三者へ共有しません。Keyを失うと保存済みMail Passwordを復号できないため、Password再入力が必要です。

## Migration 009

未適用の場合のみ次を実行します。

1. `database/audit/v1_9_b_preflight.sql`
2. `database/migrations/009_v1_9_mail_account.sql`
3. `database/audit/v1_9_b_postflight.sql`

各SQLの`SET @table_prefix = 'ig_';`は実環境の`DB_TABLE_PREFIX`と一致させます。`mail_account`がすでに存在する環境ではMigration 009を再実行しません。

## Rollback

CodeとDB Backupを同じBackup時点へ戻してください。Migration 009適用後にVersion 1.8.0へ戻す場合は、Tableを手作業で削除するよりDB BackupからのRestoreを優先します。
