# V1.1-F / R1 Overlay適用メモ

このZIPはV1.1-E / R7適用済みProjectへ上書きする差分ZIPです。Clock Widgetを追加します。

```text
1. Git作業Folder、config/local.php、APP_HASH_KEY、実DBをBackup
2. Overlayを別Folderへ展開し、V1.1-E / R7適用済みProjectへ上書き
3. php tools/db_sb13.php verify
4. php tools/db_v11c.php verify
5. php tools/db_v11d.php verify
6. bash tests/run.sh
7. BrowserをCtrl + F5で更新
8. Clock追加・変更・削除、12／24時間、日付・秒表示を確認
9. FeedとClockの並び替え、4タブ、8テーマ、Mobile表示を確認
10. Commit／Push／GitHub Actions確認
```

V1.1-FではDB Table、Column、Migrationを追加しません。Clock設定は既存の`dashboard_widget.widget_config`へ保存します。
