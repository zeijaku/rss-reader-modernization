# V1.1-I / R1 適用メモ

このZIPはV1.1-H / R1適用済みProjectへ上書きするOverlayです。

1. Code、`config/local.php`、`APP_HASH_KEY`、DatabaseをBackupする。
2. ZIPを別Folderへ展開し、Projectへ上書きする。ZIPにないFileは削除しない。
3. Dashboardを開く前にCalendar Migrationを適用する。
4. CLIの場合は次を実行する。

```powershell
php tools/db_v11i.php apply --backup-confirmed
php tools/db_v11i.php verify
```

5. phpMyAdminの場合はpreflight → `006_v1_1_calendar_event.sql` → postflightの順に実行する。
6. `php tools/healthcheck.php`、`bash tests/run.sh`、Browser確認を行う。
7. Browser Cacheを`Ctrl + F5`で更新する。
