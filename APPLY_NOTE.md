# V1.1-I / R2 適用メモ

このZIPはV1.1-I / R1適用済みProjectへ上書きするFrontend Overlayです。

1. Codeと`config/local.php`をBackupする。
2. ZIPを別Folderへ展開し、Projectへ上書きする。ZIPにないFileは削除しない。
3. SQL、Migration、`schema.sql`は実行しない。
4. `php tools/healthcheck.php`、`bash tests/run.sh`、Browser確認を行う。
5. Browser Cacheを`Ctrl + F5`で更新する。
6. スマートフォンで左右スワイプ、Calendar横Scroll、Widget並び替えを確認する。
7. FeedとCalendarの読込中にSpinnerが表示され、成功・失敗後に消えることを確認する。
