# V1.1-G / R1 Overlay適用メモ

このZIPはV1.1-F / R1適用済みProjectへ上書きする差分ZIPです。Memo Widgetと`memo`Tableを追加します。

```text
1. Git作業Folder、config/local.php、APP_HASH_KEY、実DBをBackup
2. Overlayを別Folderへ展開し、V1.1-F / R1適用済みProjectへ上書き
3. php tools/db_v11g.php apply --backup-confirmed
4. php tools/db_v11g.php verify
5. php tools/db_sb13.php verify
6. php tools/db_v11c.php verify
7. php tools/db_v11d.php verify
8. bash tests/run.sh
9. BrowserをCtrl + F5で更新
10. Memo追加・変更・削除、改行、幅、見出し色を確認
11. Feed／Clock／Memoの並び替え、4タブ、8テーマ、Mobile表示を確認
12. Commit／Push／GitHub Actions確認
```

V1.1-GではSQL適用が必要です。CLIを利用できない場合は、RSS Readerの実Databaseを選択し、`database/migrations/004_v1_1_memo.sql`の`@table_prefix`を実環境へ合わせてphpMyAdminから実行します。
