# V1.1-D / R1 Overlay適用メモ

このZIPは、V1.1-C / R1を適用済みのGitHub最新mainへ上書きする差分ZIPです。
Project folderを削除して置き換えず、ZIPにない既存File、M2/M4 Test、`.github`を残してください。

V1.1-Dは`dashboard_widget`TableのMigrationが必要です。更新Codeを本番Browserから利用する前にMigrationを完了します。

```text
1. Git作業Folder、config/local.php、APP_HASH_KEY、実DBをBackup
2. Overlayを別Folderへ展開し、V1.1-C適用済みProjectへ上書き
3. php tools/db_sb13.php verify
4. php tools/db_v11c.php verify
5. php tools/db_v11d.php apply --backup-confirmed
6. php tools/db_v11d.php verify
7. bash tests/run.sh
8. Updated Codeを配置／Browser確認
9. Commit / Push / GitHub Actions確認
```

phpMyAdminで適用する場合は`docs/v1-1-d-migration.md`を参照してください。
Migration前にCodeを公開するとDashboard Widget取得が失敗するため、適用順を入れ替えないでください。
