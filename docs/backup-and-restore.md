# Backup・復旧手順

## Backupの範囲

最低限、次を同じ復旧点として扱います。

1. Application CodeのVersion / Commit / 配布ZIP
2. `config/local.php` またはEnvironment variable設定
3. `APP_HASH_KEY`
4. MySQL Database dump
5. Web serverのDocumentRoot、PHP Version、Extension、write permission記録

Session、Login throttle、Feed cache、Lock、Fetch stateは通常の永続Backup対象にしません。復旧時に空directoryから再生成できます。Logは運用方針と個人情報保護方針に従って別管理します。

## 保存先

- Web DocumentRoot外
- Git作業folder外
- Hosting account障害時にも取得できる別Storage
- Access制限と暗号化を行う
- File名にPasswordやTokenを含めない

BackupをProject folderの下へ置くと、誤ってGitや配布ZIPへ入るため避けます。

## Database Backup

MySQL CLI例です。PasswordはCommand lineへ書かず、`-p` のPromptで入力します。

```powershell
mysqldump --single-transaction --default-character-set=utf8mb4 --routines --triggers -h <db-host> -P 3306 -u <db-user> -p <db-name> > rss-reader-db-YYYYMMDD-HHMMSS.sql
```

Database userにDumpで必要な権限がない場合は、HostingのBackup機能またはphpMyAdmin Exportを使用します。

Backup後:

```powershell
Get-Item .
ss-reader-db-YYYYMMDD-HHMMSS.sql
Get-FileHash .
ss-reader-db-YYYYMMDD-HHMMSS.sql -Algorithm SHA256
```

Linux例:

```bash
ls -l rss-reader-db-YYYYMMDD-HHMMSS.sql
sha256sum rss-reader-db-YYYYMMDD-HHMMSS.sql
```

0 byteでないこと、Command errorがないこと、SHA-256を記録したことだけでは完全な復旧確認になりません。定期的に別DatabaseへRestore testを行います。

## Private設定Backup

`config/local.php` は秘密情報を含むため、通常のSource archiveとは分けます。

```powershell
Copy-Item .\config\local.php <private-backup-path>\local.php
Get-FileHash <private-backup-path>\local.php -Algorithm SHA256
```

環境変数の場合は、Hosting control panelやPHP-FPM設定を安全な方法でExportまたは記録します。Screenshotを共有場所へ置かないでください。

## Code Backup

Gitを使用している場合は、次を記録します。

```powershell
git rev-parse HEAD
git status --short
git log -1 --oneline
```

配布ZIPを使用した場合は、ZIPと公式SHA-256を保存します。Runtime dataやPrivate設定を混ぜた独自ZIPをGitHub Releaseへ上げないでください。

## Restore test

Production DBへ直接Restoreせず、空の検証Databaseを作って試します。

```powershell
mysql -h <db-host> -P 3306 -u <db-user> -p <empty-test-db> < .
ss-reader-db-YYYYMMDD-HHMMSS.sql
```

検証用 `config/local.php` を作り、同じTable prefixを設定して次を実行します。

```powershell
php tools/db_sb13.php verify
```

Row count、4 table、Collation、Index、Duplicate / orphanの結果をBackup時の記録と比較します。

## Production復旧

1. 障害範囲を確認し、書込みを止める。
2. 復旧対象Versionを決める。
3. 現在の壊れた状態も調査用に退避する。
4. Codeを対象Versionへ戻す。
5. Private設定を復元する。
6. 必要な場合だけDatabaseを空DBへRestoreする。
7. `APP_HASH_KEY` がBackup時と同じことを確認する。
8. Runtime directoryを作成して書込み権限を設定する。
9. `php tools/healthcheck.php` と `php tools/db_sb13.php verify` を実行する。
10. BrowserでLogin、Feed、Stock、Settingsを確認する。

Database Restore例:

```powershell
mysql -h <db-host> -P 3306 -u <db-user> -p <restore-db> < .
ss-reader-db-YYYYMMDD-HHMMSS.sql
```

既存Databaseへ上書きRestoreする前に、Hosting側のSnapshot、Current dump、切替方法を確認してください。

## Runtime directoryの復旧

次は空で開始できます。

```text
var/session/
var/security/login-throttle/
var/cache/feed/
var/log/
var/db-migration/
```

Sessionを戻さない場合、利用者は再Loginになります。Cacheを戻さない場合、次回表示時にFeedを再取得します。古いSession / Cache / Lockを無理に復元するより安全です。

## 復旧記録

- Backup日時
- Code Version / Commit
- Database dump file / Size / SHA-256
- Table prefix
- Restore test日時と結果
- `APP_HASH_KEY`保管場所の確認。実値は記録しない
- 復旧手順の実施者と結果
