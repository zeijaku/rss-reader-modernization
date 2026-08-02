# Rollback手順

## 先に分ける

Rollback前に、対象Releaseが次を変更したか確認します。

```text
Code only
Configuration required
DB schema / data migration
Runtime file format
Deleted files
```

CodeだけのReleaseとDB migrationを含むReleaseでは、戻し方が異なります。

## M4-C / R1

M4-CはDocumentation、設定example、Test、Version markerの更新です。

```text
DB変更             なし
必須設定追加       なし
Runtime format     変更なし
削除file           なし
```

問題があれば、M4-B / R1のCodeへ戻し、同じ `config/local.php` とDatabaseを継続利用できます。Database RestoreやCache clearは不要です。

## Deploymentを戻す

前Versionの配布物を別folderへ展開し、禁止fileとSHA-256を確認してから切替えます。Release directoryを世代管理している場合は、Web serverの参照先を前Versionへ戻す方法が安全です。

上書き運用の場合は、前Versionの完全なfile一覧を使用します。現在Releaseで追加されたfileが残る場合があるため、Releaseの削除 / 追加一覧を確認します。

## Git history

公開済みCommitを消すためのforce pushは行いません。Repository上で変更を取り消す場合は、対象Commitを確認してRevert commitを作ります。

```powershell
git log --oneline --decorate -10
git revert <commit-sha>
git push
```

Production deploymentだけを前Packageへ戻す場合は、Repository historyを動かさず、復旧後に原因修正を別Commitで行う方法もあります。

`git reset --hard` や `git push --force` を通常のRollback手順にしません。

## Configuration

- `APP_HASH_KEY` を前後で変えない
- `DB_TABLE_PREFIX` を実Tableと一致させる
- `config/local.php` を配布物で上書きしない
- Environment variableが `local.php` より優先されることを確認する

設定変更が原因なら、変更前のPrivate設定へ戻します。Secret実値をGitへCommitしません。

## Database

DB migrationを含まないReleaseでは、安易にDatabase dumpを戻しません。Code rollbackだけで戻せるかを先に確認します。

DB migrationを含むReleaseでは、Release NotesにDown migrationが明示されていない限り、Schemaを手作業で逆変更しません。検証済みBackupから別DatabaseへRestoreし、Applicationの接続先を切替える方法を優先します。

## Rollback後の確認

```powershell
php tools/healthcheck.php
php tools/db_sb13.php verify
bash tests/run.sh
```

Browser:

- Version表示
- Login / Logout
- 4タブ
- Feed CRUD
- Stock
- Settings
- RSS / Atom
- JavaScript Console

Rollback理由、対象Commit、Backup、確認結果を記録し、可能ならForward fixを準備します。
