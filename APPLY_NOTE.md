# V1.1-C / R1 Overlay適用メモ

このZIPは、V1.1-B / R1を適用済みのGitHub最新mainへ上書きする差分ZIPです。
Project folderを削除して置き換えず、ZIPにない既存File、M2/M4 Test、`.github`を残してください。

V1.1-CはDB Migrationが必要です。Overlayを完全なProject作業Copyへ適用してMigration Toolを追加した後、更新CodeをBrowserから利用する前にDB Migrationを完了します。

```text
1. Git作業folder、config/local.php、APP_HASH_KEY、実DBをBackup
2. Overlayを別folderへ展開し、V1.1-B適用済みProjectへ上書き
3. php tools/db_sb13.php verify
4. php tools/db_v11c.php apply --backup-confirmed
5. php tools/db_v11c.php verify
6. bash tests/run.sh
7. Updated Codeを配置／Browser確認
8. Commit / Push / GitHub Actions確認
```

`db_sb13.php verify`がFAILする場合は、V1.1-C Migrationを先へ進めず、既存4TableとIndexの状態を確認してください。
phpMyAdminで適用する場合は`docs/v1-1-c-migration.md`を参照してください。
