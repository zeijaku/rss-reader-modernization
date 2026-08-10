# Version 1.7.0 適用手順

## Baseline

- Baseline: `rss-reader-modernization-v1-7-h-r4.zip`
- Baseline SHA-256: `c6bf8c6f8d2d3e3ea87bc5c55a2018bbe345f73179cb7fd7fe1befe6833f9d51`
- Application Version: `1.7.0`
- Application Label: `RSS Reader Modernization 1.7.0`

## すでにV1.7-H/R4を適用済みの環境

1. Application、`config/local.php`、Server固有`.htaccess`、実DB、`var/`をBackupします。
2. Runtime ZIPを本番とは別Folderへ展開します。
3. `config/local.php`、実DB、Runtime Dataを保持したままCodeを更新します。
4. Migration 007／008は再実行しません。
5. Footerの`RSS Reader Modernization 1.7.0`を確認します。
6. Login、Remember Login、RSS、Widget縦2、Clock／Game、Calendar祝日を確認します。

## 旧Versionから更新する環境

Version 1.7ではMigration 007／008があります。DB Backup後にPrefixを確認し、007を適用、008はPreflightで`widget_height`が存在しない場合だけ適用します。

## Holiday設定

既存`config/local.php`へ必要に応じて次を追加します。

```php
'APP_HOLIDAY_CSV_URL' => 'https://www8.cao.go.jp/chosei/shukujitsu/syukujitsu.csv',
'APP_HOLIDAY_CACHE_DAYS' => '60',
'APP_HOLIDAY_TIMEOUT_MS' => '5000',
```

Default値はApplication側にもあります。URL変更時は`APP_HOLIDAY_CSV_URL`だけ変更できます。

## GitHub

GitHub登録にはRuntime ZIPではなくComplete ZIPを使用します。詳細は[`docs/github-v1-7-powershell.md`](docs/github-v1-7-powershell.md)を参照してください。

## Rollback

Code、DB、`config/local.php`、`var/`を同じBackup時点へ戻します。Migration 007／008適用後に旧Versionへ戻す場合は、Column／Tableだけを個別削除するよりDB BackupをRestoreする方法を推奨します。
