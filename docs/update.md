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


## V1.1-I / R3からV1.1-J / R1

V1.1-JはAccount Settingsを追加します。メールアドレスとパスワードは既存`user_info`のColumnを更新するため、DB構造変更はありません。

```text
DB schema / Migration       変更なし
Public API                  account.email.update / account.password.update
必須設定                    追加なし
Browser Cache               Ctrl + F5を推奨
削除file                    なし
```

Overlayを上書きした後、SQLやMigrationは実行しません。現在のパスワードを確認して変更し、成功後はSession IDとCSRF Tokenが自動的に更新されます。現在のメールアドレスはKeyed Identityで保存されているため画面へ表示しません。

確認時は、メールアドレス変更後にLogoutして新しいメールアドレスでLoginし、パスワード変更後に旧パスワードが拒否され新パスワードでLoginできることを確認してください。

## V1.1-I / R1からV1.1-I / R2

V1.1-I / R2はFrontendの操作性改善です。スマートフォン幅での左右スワイプによるタブ切り替えと、Feed／Calendar読込中のSpinnerを追加します。

```text
DB schema / Migration       変更なし
Public API                  変更なし
必須設定                    追加なし
Cache clear                 Browser Cache更新のみ
削除file                    なし
```

V1.1-I / R1適用済みProjectへOverlayを上書きし、Browserで`Ctrl + F5`を実行します。SQL、`db_v11i.php apply`、`schema.sql`の再実行は不要です。

スワイプはスマートフォン幅だけで有効です。Calendar、入力欄、Button、Link、Modal、Drawer、Widget並び替えHandle、画面端から始まる操作では動作しません。

## V1.1-H / R1からV1.1-I / R1

V1.1-IはCalendar Widgetと`calendar_event`Tableを追加します。Task期限は既存の`task`Tableを直接参照します。Codeだけ先に切り替えるとCalendar操作時に`calendar_event`Tableを参照するため、Backup後にMigrationを同じMaintenance内で適用してください。

```text
DB Table                    calendar_eventを追加
既存Column                  変更なし
Public API                  widget.calendar.create / update / delete
                            calendar.month.list
                            calendar.event.create / update / delete
必須設定                    追加なし
Cache clear                 不要
削除file                    なし
```

CLIを利用できる場合:

```powershell
php tools/db_v11i.php apply --backup-confirmed
php tools/db_v11i.php verify
```

phpMyAdminを利用する場合は、RSS Readerの実Databaseを選択し、次の順で実行します。各SQL冒頭の`@table_prefix`を`DB_TABLE_PREFIX`と同じ値へ変更してください。

```text
database/audit/v1_1_i_preflight.sql
database/migrations/006_v1_1_calendar_event.sql
database/audit/v1_1_i_postflight.sql
```

DB変更に必須なのは`006_v1_1_calendar_event.sql`です。preflightとpostflightは読取専用の確認SQLです。Rollback時はCodeとDBを同じBackup時点へ戻します。

## V1.1-G / R1からV1.1-H / R1

V1.1-HはTask Widgetと`task`Tableを追加します。Codeだけ先に切り替えるとDashboard queryが`task`Tableを参照するため、Backup後にMigrationを同じMaintenance内で適用してください。

```text
DB Table                    taskを追加
Column / Index              task Table内に追加
Public API                  widget.task.create / update / delete
                            task.item.create / update / toggle / delete
必須設定                    追加なし
Cache clear                 不要
削除file                    なし
```

CLIを利用できる場合:

```powershell
php tools/db_v11h.php apply --backup-confirmed
php tools/db_v11h.php verify
```

phpMyAdminを利用する場合は、RSS Readerの実Databaseを選択し、`database/migrations/005_v1_1_task.sql`冒頭の`@table_prefix`を`DB_TABLE_PREFIX`と同じ値へ変更してから実行します。その後、`database/audit/v1_1_h_postflight.sql`またはCLI verifyで確認します。

Rollback時はCodeとDBを同じBackup時点へ戻します。V1.1-Hで作成したTaskを保持したままCodeだけV1.1-Gへ戻す運用は行いません。

## V1.1-F / R1からV1.1-G / R1

V1.1-GはMemo Widgetと`memo`Tableを追加します。Codeだけ先に切り替えるとDashboard queryが`memo`Tableを参照するため、Backup後にMigrationを同じMaintenance内で適用してください。

```text
DB Table                    memoを追加
Column / Index              memo Table内に追加
Public API                  widget.memo.create / update / deleteを追加
必須設定                    追加なし
Cache clear                 不要
削除file                    なし
```

CLIを利用できる場合:

```powershell
php tools/db_v11g.php apply --backup-confirmed
php tools/db_v11g.php verify
```

phpMyAdminを利用する場合は、RSS Readerの実Databaseを選択し、`database/migrations/004_v1_1_memo.sql`冒頭の`@table_prefix`を`DB_TABLE_PREFIX`と同じ値へ変更してから実行します。その後、`database/audit/v1_1_g_postflight.sql`またはCLI verifyで確認します。

Rollback時はCodeとDBを同じBackup時点へ戻します。Migrationは既存Tableを変更しませんが、V1.1-Gで作成したMemoを保持したままCodeだけV1.1-Fへ戻す運用は行いません。

## M4-F / R1からM4-G / R1

M4-GはVersion、Release Notes、Final Package、Tag / GitHub Release手順の確定です。Application RuntimeはRC1から変更していません。

```text
DB schema / Migration       変更なし
Public API                  変更なし
必須設定                    追加なし
Frontend Runtime Asset      変更なし
Cache clear                 不要
削除file                    なし
```

既存`config/local.php`と実DBはそのまま使用できます。`schema.sql`やMigrationは実行しません。

## M4-D / R1からM4-E / R1

M4-EはRelease package builder、Verifier、Release Notes、Tag / GitHub Release手順、Version marker、Testの追加です。Application Runtimeは変更していません。

```text
DB schema / Migration       変更なし
Public API                  変更なし
必須設定                    追加なし
Frontend Runtime Asset      変更なし
Cache clear                 不要
削除file                    なし
```

既存 `config/local.php` と実DBはそのまま使用できます。M4-E適用時に `schema.sql` やMigrationを実行しません。

M4-EのPreview Release ZIPはPackaging確認用です。本番更新やGitHub Release公開には使用せず、M4-F / M4-Gで作り直します。

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

- Footerが現在CheckpointのVersion label
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
