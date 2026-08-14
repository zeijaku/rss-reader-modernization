# RSS Reader Modernization 1.14.0 Release Notes

## Overview

Version 1.14.0は、既存機能・既存データを維持したままFrontend dependencyを現行Bootstrap 5系へ移行するReleaseです。

Bootstrap / Bootswatchを5.3.8へ更新し、Bootstrap 4時代のmarkup / Data APIを5系へ移行しました。右メニューはjquery-drawerからBootstrap Offcanvasへ置換し、移行完了後に不要となった旧Frontend dependencyとBootstrap 4旧配布Assetを削除しています。

jQuery 3.7.1、Font Awesome Free 6.7.2、既存のPHP / PDO / MySQL構成、Feed CRUD、Stock、Search Feed、Memo、Task、Calendar、Mail、Account Settings、Dashboard Widget基盤は維持します。

## Version 1.14 main changes

### Bootstrap / Bootswatch 5.3.8

- Bootstrap 5.3.8 CSSと`bootstrap.bundle-5.3.8.min.js`へruntimeを切替。
- Normal + Yeti / Minty / Flatly / Journal / Sketchy / Solar / Slateの全8 ThemeをBootswatch 5.3.8系へ統一。
- Bootstrap 4時代の`data-toggle` / `data-target`等を`data-bs-*`へ移行。
- Form、Utility、Badge、Modal等のBootstrap 5互換markupへ整理。
- Bootstrap bundleを使用し、standalone Popper runtimeは廃止。

### DrawerからBootstrap Offcanvasへ

- 右メニューをjquery-drawerから`offcanvas-end`へ置換。
- 既存メニュー内容と右側配置を維持。
- Offcanvas表示中はSmartphoneのDashboard swipeを抑止。
- Drawer内ActionからModalを開く場合は、Offcanvasを閉じてからModalを開き、BackdropやScroll lockの競合を回避。
- jquery-drawer、iScroll、standalone Popperを配布物から削除。

### Theme / Responsive finishing

- PC / Smartphoneと全8 ThemeでNavbar、Modal、OffcanvasのcontrastとSpacingを調整。
- Stock、Memo、Task、Calendar、Mail、Links、Weather等の独自surfaceをBootstrap / Bootswatch Theme変数へ追従。
- Solar / Slateを含むDark ThemeでForm label、Calendar、Modal close icon等の視認性を調整。
- SmartphoneのOffcanvas幅とModal footer間隔を調整。

### Card header contrast

- 通常RSS、Search Feed、Clock、Game、Memo、Task、Links、Weather、Calendar、Mailの見出しをBootstrap 5の`text-bg-*`へ統一。
- Card背景色に応じてタイトル、編集／更新Icon、Drag handle等の文字色をTheme側で自動選択。
- Search Feedは背景色をTable rowではなくHeader cellへ適用し、Bootstrap 5 Table背景に隠れる問題を修正。

### Legacy asset cleanup

Version 1.14.0ではruntime参照がないことを確認したうえで、次の旧配布物を削除します。

- jquery-drawer CSS / JavaScript
- iScroll JavaScript
- standalone Popper JavaScript
- Bootstrap 4.1.3の旧CSS / JavaScript / Source Map
- Bootswatch 4.1.3の旧Theme CSS

## Database and configuration

Version 1.14ではDB Table／Column、Migration、SQL、必須configの追加変更はありません。

Version 1.13.0適用済み環境では、DB Migrationを実行せずCodeをVersion 1.14.0へ差し替えます。`config/local.php`、`var/`、実DBはそのまま維持してください。

## Distribution files

- `rss-reader-modernization-1.14.0.zip` — Server配置用Runtime成果物。
- `rss-reader-modernization-1.14.0.zip.sha256` — Runtime ZIPのSHA-256。
- `rss-reader-modernization-1.14.0-complete.zip` — Repository / Testsを含む完全Source成果物。
- `rss-reader-modernization-1.14.0-complete.zip.sha256` — 完全Source ZIPのSHA-256。

Runtime配布物には`config/local.php`、`.env`、実DB、Log、Session、Cache、Release ZIP等を含めません。

## Update notes

更新前にCode、`config/local.php`、実DB、Runtime DataをBackupしてください。

Version 1.13.0から更新する場合は、Server上の旧Frontend Assetを残さないため、`app/`と`public/`をBackup後に入れ替える方法を推奨します。`.htaccess`、`config/`、`var/`、DBは既存環境を維持してください。

Version番号が1.14.0へ変わるためAsset queryも更新されますが、更新直後はBrowserの強制再読込を行うと確実です。

主な確認項目:

- Dashboard / Stock / Settingsが表示できること。
- 右Offcanvasが開閉でき、Drawer内ActionからModalが正常に開くこと。
- 通常RSS / Search Feedの取得、更新、記事Actionが従来どおり動作すること。
- Stock、Memo、Task、Calendar、Mail等の主要Widget操作が従来どおり動作すること。
- Card見出し色変更時に文字とIconが読みやすい色へ追従すること。
- NetworkでBootstrap 5.3.8 bundleが読み込まれ、旧Bootstrap 4 / Drawer / iScroll / standalone Popperが取得されないこと。

## Verification limits

GitHub ActionsではPHP 8.1 / 8.4の`tests/run.sh`全Regression、PHP / JavaScript構文、Bootstrap / Bootswatch 5.3.8 asset checksum、全8 Theme resolver、legacy dependency不存在、Release package builder / verifier、SHA-256 / Manifest整合を確認します。

実Hosting Server、実MySQL Server、外部Feed到達性、実IMAP Server、各Browser / Device固有の描画差については利用環境での最終確認が必要です。

## License

Project LicenseおよびThird-party noticeを維持します。現行Frontend dependencyの詳細は`THIRD_PARTY_NOTICES.md`と`docs/dependencies.md`を参照してください。
