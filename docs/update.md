# 更新手順

## 基本方針

更新は、現在のCode、`config/local.php`、Database、必要なRuntime dataを先にBackupしてから行います。配布ZIPを本番folderへ直接解凍しません。

Releaseごとに次を確認します。

- 変更file
- 新規file
- 削除file
- DB migrationの有無
- 必須設定追加の有無
- Runtime cache削除の要否
- Release NotesとSHA-256

## M4-C / R1からM4-D / R1

M4-DはGitHub公開資料、Security / Contribution文書、Issue template、GitHub Actions CI、Version marker、Testの追加です。

```text
DB schema / Migration       変更なし
Public API                  変更なし
必須設定                    追加なし
Frontend Runtime Asset      変更なし
Cache clear                 不要
削除file                    なし
```

既存 `config/local.php` と実DBはそのまま使用できます。M4-D適用時に `schema.sql` やMigrationを実行しません。

GitHubへpushした後、ActionsのPHP 8.1 / 8.4 JobとRepository Settingsを確認します。

## M4-B / R1からM4-C / R1

この更新では次の変更はありません。

```text
DB schema / Migration       変更なし
Public API                  変更なし
必須設定項目                追加なし
Runtime Cache format        変更なし
削除file                    なし
```

`config/local.php.example` と `config/.env.example` は、既にRuntimeが対応していた設定を一覧として補完しています。実環境の `config/local.php` へ新しい項目を追加しなくても従来のDefaultで動作します。

M4-Cで `schema.sql`、`001_sb13_integrity.sql` を実行しないでください。Cache clearも不要です。

## 更新前

1. Maintenance時間を決める。
2. 現在VersionとCommitを記録する。
3. `git status` が想定どおりか確認する。
4. Code、Private設定、DatabaseをBackupする。
5. BackupのSizeとSHA-256を確認する。
6. 可能なら別環境で更新Testを行う。

```powershell
git status --short
git log -1 --oneline
php tools/healthcheck.php
```

Backupは [`backup-and-restore.md`](backup-and-restore.md) を参照してください。

## Gitで更新する場合

Production serverで直接編集していないことを先に確認します。

```powershell
git status --short
git fetch origin
git pull --ff-only
```

`git pull --ff-only` が失敗した場合は、強制Resetで合わせず、Local変更やBranch差分を確認します。

## ZIPで更新する場合

1. ZIPのSHA-256を照合する。
2. 別folderへ展開する。
3. 禁止file、入れ子ZIP、Top-level directoryを確認する。
4. `config/local.php` とRuntime dataがZIPに含まれないことを確認する。
5. 展開内容をProjectへ上書きする。
6. Releaseに削除一覧がある場合だけ、その一覧を確認して削除する。

単純な上書きでは旧fileが残る場合があります。削除一覧がないfileを「きれいにするため」に一括削除しないでください。

M4-Cの削除fileはありません。

## 更新後

```powershell
php tools/healthcheck.php
php tools/db_sb13.php verify
bash tests/run.sh
node --check public/js/dashboard.js
```

Browserでは次を確認します。

- Footerが `Release M4-C / R1`
- Login / Logout / Session
- Feed CRUD / Stock / Settings / 4タブ
- RSS 2.0 / RSS 1.0 / Atom
- Drawer / Modal / Keyboard / Focus / ARIA
- JavaScript Console errorなし

更新後に問題がある場合、Database変更の有無を先に確認してから [`rollback.md`](rollback.md) に従います。

## 更新完了記録

最低限、次を残します。

```text
更新前Version / Commit
更新後Version / Commit
配布ZIP SHA-256
Backup fileとSHA-256
実施日時
実施者
Test結果
Browser確認結果
問題と対応
```
