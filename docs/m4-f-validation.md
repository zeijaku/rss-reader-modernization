# M4-F Release Candidate validation

## 目的

M4-Fでは `1.0.0-rc1` を作成し、自動Regressionと実環境確認を分けて記録します。

自動TestがPASSしても、実MySQL、実Feed、実Browser、BackupからのRestoreが未確認なら正式Releaseへ進みません。逆に、実環境確認のためにCredentialや実Feed URLをRepositoryへ保存しません。

## 対象

```text
APP_VERSION       1.0.0-rc1
APP_VERSION_LABEL RSS Reader Modernization 1.0.0-RC1
Package status    RELEASE_CANDIDATE
publishable       no
```

RC ZIPは正式版候補の検証用です。通常のGitHub Releaseへ正式版として公開しません。必要がある場合もPre-release扱いにし、M4-Gで作り直すFinal ZIPとは分けます。

## 1. Local automated regression

```powershell
php -v
php tools/m4f_environment_probe.php
php tools/healthcheck.php
php tools/db_sb13.php verify
bash tests/run.sh
```

`m4f_environment_probe.php` はDBへ接続しません。PHP Version、必須Extension、PDO driver、Runtime directoryだけをJSONで表示します。

実環境の必須条件を満たすかExit codeでも確認する場合:

```powershell
php tools/m4f_environment_probe.php --require-ready
```

- `0`: Probe範囲はPASS
- `2`: 必須ExtensionまたはRuntime directoryが不足
- `1`: Probe自体のerror

## 2. RC Package

```powershell
python tools/build_release_package.py --mode rc --output-dir ..\release-output
python tools/verify_release_package.py `
  ..\release-output\rss-reader-modernization-1.0.0-rc1.zip `
  ..\release-output\rss-reader-modernization-1.0.0-rc1.zip.sha256
```

ZIP内の `RELEASE_BUILD.txt` を確認します。

```text
package_status=RELEASE_CANDIDATE
application_version=1.0.0-rc1
publishable=no
intended_release=1.0.0
intended_tag=v1.0.0
```

## 3. Evidence file

TemplateをPrivateなRuntime directoryへCopyします。

```powershell
Copy-Item .\docs\m4-f-validation-template.json `
  .\var\m4f-evidence\m4-f-result.json
```

`var/m4f-evidence/` の結果fileはGit対象外です。次を記録しません。

- DB password、`APP_HASH_KEY`
- Cookie、Session ID、Authorization header
- 実Feed URL、個人情報
- Private Server名、内部IP
- Screenshotに表示されたCredential

Evidenceには「PASSした理由が後から分かる短い記録」を入れます。例は`GitHub Actions run #123`、`PHP 8.4.16 / MySQL 8.0.x`、`Test DBへRestore後にLogin/Feed/Stock確認`程度で十分です。

構造確認:

```powershell
python tools/m4f_evidence_gate.py .\var\m4f-evidence\m4-f-result.json
```

正式Releaseへ進める状態か確認:

```powershell
python tools/m4f_evidence_gate.py `
  .\var\m4f-evidence\m4-f-result.json --require-pass
```

- `0`: 全必須項目PASS
- `2`: PENDINGまたはBLOCKEDがありHOLD
- `1`: FAILまたはEvidence形式不正

## 4. MySQL / deployment

### 更新環境

1. Code、`config/local.php`、`APP_HASH_KEY`、DBをBackupする。
2. RCをTest環境へ配置する。
3. M4-Eからの更新ではDB Migrationを実行しない。
4. `php tools/db_sb13.php verify` を実行する。
5. Login、Feed、Stock、Settingsを確認する。

### 新規空DB

新規設置を確認する場合だけ `database/schema.sql` を使用します。既存DBへ再投入しません。`DB_TABLE_PREFIX` と `@table_prefix` を一致させます。

## 5. Feed

RSS 2.0、RSS 1.0、Atomは、管理下のFixtureまたは負荷をかけないTest Feedで確認します。第三者Siteへ短時間に繰り返しRequestしません。

最低限確認する内容:

- Title、Link、Dateの表示
- 0件と取得失敗の表示
- Feed再読込
- 一時失敗後のRecovery
- Cache / 304 / Retryは確認可能な範囲を記録
- Security errorではstaleを使わない既存RegressionがPASS

## 6. Browser

- Desktop: Chrome系と、利用可能ならFirefox系
- Responsive: 320px、375px、768px、992px、1280px
- Theme: Normal、Yeti、Minty、Flatly、Journal、Sketchy、Solar、Slate
- Login / Logout / Session
- 4タブ、Feed CRUD、Stock、Settings
- Drawer、Modal、Popover、Page Top
- Keyboard、Focus、ARIA
- CSS、JavaScript、WebFont、faviconがHTTP 200
- JavaScript Console errorなし

Screenshotを残す場合は実Feed URL、User名、Cookie、Server情報を隠します。

## 7. Backup / Restore / Rollback

Backupを作成しただけではPASSにしません。別DBまたは隔離したTest環境へRestoreし、Login、Feed設定、Stockが戻ることを確認します。

RollbackはCode-only rollbackとDB復旧を分けます。M4-FはDB schemaを変更しないため、通常はCode rollbackだけで戻せます。実DBを戻す場合は、Backup取得後に発生したDataが失われる点を確認します。

## 8. M4-F判定

| 状態 | 判定 |
|---|---|
| 自動Regression / RC Package PASS、実環境Evidence PENDING | HOLD |
| 必須項目にFAIL | FAIL |
| 全必須項目PASS | M4-Gへ進行可 |

M4-FのCheckpoint ZIPをGitへ反映しただけでは、実環境GateはPASSになりません。
