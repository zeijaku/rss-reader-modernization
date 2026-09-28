# 更新手順 / Update Guide

この文書は、**既存環境を現在のReleaseへ更新するための共通手順**をまとめたCurrent Guideです。

Version固有の過去Migration / Config変更は [Historical Update / Migration History](update-history.md) に分離しています。古い手順を現在環境へ一律適用しないでください。

現在の更新では、最初に次を確認します。

- `../app/version.php` — Current Application / Asset Revision
- [Release Notes](../RELEASE_NOTES.md) — 現在Releaseで必要な更新事項
- [CHANGELOG](../CHANGELOG.md) — 経由するReleaseの変更内容
- [Historical Update / Migration History](update-history.md) — 過去Version固有Migrationの記録
- [Backup and Restore](backup-and-restore.md) — Backup / Restore
- [Rollback](rollback.md) — 問題発生時のRollback

## 1. 更新前に確認すること

1. 現在のApplication VersionとCommitを記録する。
2. 更新先Releaseの `RELEASE_NOTES.md` と、現在Versionから更新先までの `CHANGELOG.md` を確認する。
3. DB Migration、必須Config、削除file、Runtime cache更新の有無を確認する。
4. Code、`config/local.php`、Database、必要なprivate runtime dataをBackupする。
5. BackupのSizeとSHA-256を確認する。
6. 配布ZIPを使用する場合はRelease AssetとSHA-256を確認する。
7. 可能なら別環境で更新Testを行う。

```powershell
git status --short
git log -1 --oneline
php tools/healthcheck.php
```

Production serverでLocal変更がある場合は、その内容を確認するまで更新を進めません。

## 2. DB Migrationが必要か判断する

**既存Databaseへ `database/schema.sql` を再実行しません。**

`schema.sql` はFresh Install用のCurrent完成形で、Fresh Installでは追加Migrationを実行しません。既存環境では、現在Versionから更新先Versionまでに追加されたMigrationのうち、**未適用のものだけ**を番号順に適用します。

判断手順:

1. 現在Versionを確認する。
2. 更新先までの `CHANGELOG.md` / `RELEASE_NOTES.md` を確認する。
3. DB変更があるReleaseだけ [Historical Update / Migration History](update-history.md) でMigration名と順序を確認する。
4. すでにCheckpoint / RC / Production確認で適用済みのMigrationは、正式Release化だけを理由に再実行しない。
5. 各Migrationの `SET @table_prefix` を実環境の `DB_TABLE_PREFIX` と一致させる。
6. 適用前Backupを確保してから実行する。

Fresh Installは [Installation](installation.md) の手順を使用してください。

## 3. Migration適用時の共通ルール

- Migrationは番号順に適用する。
- 未適用であることを確認してから1回だけ実行する。
- Migration SQLの対象Table / Columnと実DBの状態が食い違う場合は、推測で再実行しない。
- `config/local.php`、暗号鍵、OAuth Credential、2FA Secret等のprivate設定をMigrationに合わせて上書きしない。
- DB変更を伴うReleaseでRollbackする場合は、Codeだけを古いVersionへ戻すのではなく、DB互換性を確認する。
- Migration適用後は必要なTable / Column / IndexとApplicationのSmoke Testを確認する。

過去Migrationの具体名・対象Version・注意点は [Historical Update / Migration History](update-history.md) に保持します。

## 4. Gitで更新する場合

Production serverで直接編集していないことを先に確認します。

```powershell
git status --short
git fetch origin
git pull --ff-only
```

`git pull --ff-only` が失敗した場合は、強制Resetで合わせず、Local変更やBranch差分を確認します。

## 5. ZIPで更新する場合

1. ZIPのSHA-256を照合する。
2. 本番Directory外の別folderへ展開する。
3. Top-level directoryと内容を確認する。
4. `config/local.php`、実DB、Secret、生成済みprivate runtime dataが上書きされないことを確認する。
5. Application Rootへ相対PathでCodeを更新する。
6. Release Notesに削除fileが明示されている場合だけ、その一覧を確認して削除する。

単純な上書きでは旧fileが残る場合があります。削除対象と確認できないfileをCleanup目的で一括削除しません。

## 6. 更新後のCurrent Gate

RepositoryのCurrent Gateは次です。

```powershell
php tools/healthcheck.php
php tools/db_current.php verify
bash tests/run-ci.sh
node --check public/js/dashboard.js
```

`db_current.php verify` はCurrentに必要なTable / 重要Column / IndexをRead-onlyで確認します。Legacy DBのSB-13 audit / migration確認が必要な場合だけ `db_sb13.php` を追加使用します。

通常更新では `tests/run-ci.sh` をCurrent Gateとします。Historical Version固有の調査が必要な場合だけ、当時の `tests/run.sh` / `tests/run-v*.sh` を追加で参照します。

GitHub ActionsのCurrent CI / Release verification Runtimeは [CI](ci.md) を正とします。

## 7. Browser Smoke Test

更新内容に応じて必要範囲を確認します。

- Login / Logout / Session / 2FA
- Dashboard表示、Widget追加・編集・並び替え
- RSS / Search Feed / Reader Mode
- Stock
- Calendar / Notification Center
- Mail
- File Library / Remote Files
- Settings
- PC / Smartphoneの主要Modal / Drawer
- JavaScript Console errorなし
- FooterのApplication Version label

変更Scope外の機能をすべて手動確認する必要はありませんが、認証・Dashboard・主要Data表示は最低限のSmoke Test対象とします。

## 8. 問題が発生した場合

1. 最初のFAILまたは最初に確認できた不具合を記録する。
2. DB Migrationの有無を確認する。
3. Code / Config / DBのどこまで戻す必要があるかを切り分ける。
4. [Rollback](rollback.md) に従い、Backupと同じ時点へ戻す。
5. Migrationを伴う場合は、旧Codeと新DB schemaの互換性を推測しない。

## 9. 更新完了記録

最低限、次を残します。

```text
更新前Version / Commit
更新後Version / Commit
配布ZIP SHA-256
適用Migration
Config変更
Backup fileとSHA-256
実施日時
実施者
Current Gate結果
Browser確認結果
問題と対応
```

## Historical Version固有手順

過去Releaseで必要だったMigration、Config、Checkpoint固有の手順は [Historical Update / Migration History](update-history.md) に分離しています。

Historical文書は過去環境の調査・段階Upgradeの確認用です。現在の更新では、Current Release Notes / CHANGELOGを先に確認し、必要な範囲だけ参照してください。
