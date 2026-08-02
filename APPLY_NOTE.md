# V1.1-H / R1 Overlay適用メモ

このZIPはV1.1-G / R1適用済みProjectへ上書きする差分ZIPです。Task Widgetと`task`Tableを追加します。

```text
1. Git作業Folder、config/local.php、APP_HASH_KEY、実DBをBackup
2. Overlayを別Folderへ展開し、V1.1-G / R1適用済みProjectへ上書き
3. php tools/db_v11h.php apply --backup-confirmed
4. php tools/db_v11h.php verify
5. php tools/db_sb13.php verify
6. php tools/db_v11c.php verify
7. php tools/db_v11d.php verify
8. php tools/db_v11g.php verify
9. bash tests/run.sh
10. BrowserをCtrl + F5で更新
11. Task WidgetとTaskの追加・変更・完了切替・削除を確認
12. 期限、優先度、幅、見出し色を確認
13. Feed／Clock／Memo／Taskの並び替え、4タブ、8テーマ、Mobile表示を確認
14. Commit／Push／GitHub Actions確認
```

V1.1-HではSQL適用が必要です。CLIを利用できない場合は、RSS Readerの実Databaseを選択し、`database/migrations/005_v1_1_task.sql`の`@table_prefix`を実環境へ合わせてphpMyAdminから実行します。
