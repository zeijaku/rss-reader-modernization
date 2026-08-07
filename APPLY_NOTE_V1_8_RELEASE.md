# Version 1.8.0 適用手順

## Baseline

- Baseline: `V1.8-E / R1`
- Application Version: `1.8.0`
- Application Label: `RSS Reader Modernization 1.8.0`
- Version 1.7.0からVersion 1.8.0へのDB Migration: なし

## Version 1.8-E / R1適用済み環境

1. Application、`config/local.php`、Server固有`.htaccess`、実DB、`var/`をBackupします。
2. Runtime ZIPを本番とは別Folderへ展開します。
3. `config/local.php`、実DB、Session／Log／Cache等のRuntime Dataを保持したままCodeを更新します。
4. SQL／Migrationは実行しません。Version 1.7のMigration 007／008も再実行しません。
5. Footerの`RSS Reader Modernization 1.8.0`を確認します。
6. Stock保存、解除、検索、Sort、Pagination、Domain、Actions、Smartphone表示を確認します。

## Version 1.7.0から更新する環境

Version 1.8ではDB Table／Column／Index／Migrationを追加しません。Version 1.7.0で正常動作しているDBをそのまま使用します。

## GitHub

GitHub登録にはRuntime ZIPではなくComplete ZIPを使用します。詳細は[`docs/github-v1-8-powershell.md`](docs/github-v1-8-powershell.md)を参照してください。

## Rollback

Version 1.8ではDB Migrationがないため、問題がある場合はCodeをVersion 1.7.0 Backupへ戻します。Stock解除で`stock_flag=1`になった既存行を自動的に再有効化するRollbackは行いません。必要な場合はDB BackupからのRestoreを優先してください。
