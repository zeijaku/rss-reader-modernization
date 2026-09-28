# 配置確認Checklist

このChecklistは現在の正式ReleaseをProductionへ配置する際の共通確認用です。Version固有のMigration、Config変更、既知の制限は [`../RELEASE_NOTES.md`](../RELEASE_NOTES.md)、[`../CHANGELOG.md`](../CHANGELOG.md)、[`update.md`](update.md) を先に確認してください。

## 配置前

- [ ] 対象Version、Commit、Release tagを記録した
- [ ] Runtime ZIPとSHA-256を配置対象として記録した
- [ ] Release NotesとCHANGELOGを確認した
- [ ] DB Migrationと必須Config変更の有無・適用順を確認した
- [ ] `config/local.php`、`APP_HASH_KEY`、DatabaseをBackupした
- [ ] Mail / Remote Files等を利用する場合は対応するCredential key / SecretのBackup方針を確認した
- [ ] Database dumpのSizeとSHA-256を確認した
- [ ] Rollback先Versionを確保した
- [ ] Maintenance時間と連絡方法を決めた
- [ ] 別環境または本番Directory外でPackageを確認した

## Package

- [ ] GitHub Releaseの正式Assetを使用している
- [ ] Runtime ZIPと`.sha256`の組合せが一致している
- [ ] Top-level directoryが1つ
- [ ] ZIP path traversal / absolute path / duplicate entryなし
- [ ] 入れ子ZIPなし
- [ ] `config/local.php`、real `.env`、実DB、Log、Session、Cacheなし
- [ ] `LICENSE`、`THIRD_PARTY_NOTICES.md`、`licenses/`あり
- [ ] GitHub CLIを使用できる場合はArtifact Attestationも確認した

Release Packageの構成とAttestation確認方法は [`release-package.md`](release-package.md) を参照してください。

## 配置

- [ ] **推奨構成**ではDocumentRootを `public/` にしている
- [ ] Application RootをWeb公開する互換構成の場合、Root `.htaccess` が有効で `app/` / `config/` / `tools/` / `var/` への直接Accessが拒否される
- [ ] Apacheでは必要なModuleが有効。Nginx等ではPrivate path拒否・Security Header・Public PHP whitelistをServer側へ設定した
- [ ] `public/`の直接実行PHPはPublic Endpoint Matrixの明示Whitelistだけ
- [ ] Private runtime data、Secret、DB dump、LogをWeb公開領域へ置いていない
- [ ] `config/local.php`を上書きしていない
- [ ] 削除一覧がある場合だけ対象Fileを削除した
- [ ] `var/session/`が書込み可能
- [ ] `var/security/login-throttle/`が書込み可能
- [ ] `var/cache/`が書込み可能（Feed / Reader / Weather / X等のPrivate Cacheを含む）
- [ ] Log有効時は`var/log/`または指定Pathが書込み可能
- [ ] 無条件な`777`を設定していない

## CLI / Current Gate

Production Runtime ZIPでは次を確認します。

- [ ] `php -v`
- [ ] `php tools/healthcheck.php`
- [ ] `php tools/db_current.php verify`
- [ ] Release固有のDB verify手順がある場合は実行した

Repository cloneまたはComplete Source Packageで `tests/` が存在する場合だけ、追加でCurrent Gateを実行します。

- [ ] `bash tests/run-ci.sh`

`healthcheck.php`だけではDatabase接続を確認しません。Fresh Install / Current SchemaはRead-onlyの `db_current.php verify` で確認します。Legacy DBを古いVersionから更新する場合だけ、Update Guideに従って `db_sb13.php audit/verify` 等の対象Migration確認を行います。

## Browser

### Authentication / Account

- [ ] HTTPS
- [ ] Version表示が対象Releaseと一致する
- [ ] Registration方針
- [ ] Login / Logout / Session
- [ ] Remember Me
- [ ] TOTP 2FA / trusted-browser behaviorを利用している場合は正常
- [ ] Account Settings

### RSS / Reader / Stock

- [ ] 4タブ
- [ ] Feed追加 / 変更 / 削除 / 再読込
- [ ] RSS 2.0 / RSS 1.0 / Atom
- [ ] Feed Card個別更新
- [ ] Search Feed / 全RSS新着
- [ ] RSS Management / Feed Health / RSS Rulesを利用している場合は正常
- [ ] 記事Actions
- [ ] Reader Mode / Full Textを利用している場合は正常
- [ ] Stock保存 / 一覧 / Filter / 状態変更

### Productivity

- [ ] Clock / Memo / Task
- [ ] Calendarの日／週／月表示
- [ ] 通常予定 / 複数日 / 繰り返し / Occurrence操作
- [ ] Calendar Reminder
- [ ] Notification Center
- [ ] RSS / StockからCalendar予定作成

### Mail / Files / Optional Widgets

- [ ] Mailを利用する場合は受信 / Folder / 検索 / 送信 / Reply / Sent / 添付
- [ ] Gmail OAuth2を利用する場合は再接続を含め認証状態が正常
- [ ] File LibraryのUpload / Preview / Download / Delete
- [ ] Remote Filesを利用する場合は接続 / File操作を利用する場合は接続 / Directory / Upload / Download / Editor / Permission
- [ ] Information / Media / Game Widgetを利用している場合は表示・操作が正常
- [ ] X Timelineを利用する場合はToken状態と投稿取得が正常

### UI / Frontend

- [ ] Settings / Navbar / Tab名
- [ ] Drawer / Modal / Page Top
- [ ] Keyboard / Focus / ARIA
- [ ] 使用Theme
- [ ] Smartphone / Tablet / Desktopの主要幅
- [ ] JavaScript Console errorなし
- [ ] CSS / JS / WebFont / faviconがHTTP 200

## 配置後

- [ ] Error logを確認した
- [ ] Database row countに異常がない
- [ ] Backupと実施記録を安全な場所へ保存した
- [ ] GitHub Release Assetと配置物のVersionが一致する
- [ ] 問題がある場合のRollback判断者を決めた

## 配置記録

最低限、次を残します。

- 対象Version / Commit / Release tag
- Runtime ZIP SHA-256
- 実PHP / MySQL Version
- 有効Extension
- healthcheck / DB verify / Current Gate結果
- Browser / Responsive確認結果
- Backup / Restore確認
- 実施日時
- 既知の制限事項
