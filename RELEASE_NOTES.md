# RSS Reader Modernization 1.14.1 Release Notes

## Overview

Version 1.14.1は、Version 1.14.0のBootstrap / Bootswatch 5.3.8移行後に確認したTheme表示の補正Releaseです。機能追加やDB変更は行わず、独自UIに残っていた固定色をBootstrap 5 Theme変数へ追従させます。

## Main changes

- 通常RSS／Search Feedの記事Title・概要の本文色と背景をThemeへ追従。
- 記事Actions、Task、Stock、Calendar、Mail、Links、Weather、Clock Timerの中立Surface・本文色・補助色・BorderをTheme変数へ整理。
- Stock Tag管理PanelとSmartphoneのRSS概要IconをTheme連動へ修正。
- Mini Game／Lights Outは通常Surface、空セル、壁、補助文字、OFF状態のみTheme変数へ寄せ、ゲーム上の意味を持つ色は維持。
- Keyword Highlightの黄色＋暗色文字、休日／週末、Timer終了、Player／敵／宝／Goal、Lights Out ON、Swipe Indicator、常時LightのOffcanvas Drawer等は意味・視認性を優先して従来色を維持。

## Database and configuration

DB Table／Column、Migration、SQL、必須configの追加変更はありません。Version 1.14.0適用済み環境ではCode差し替えのみです。

## Distribution files

- `rss-reader-modernization-1.14.1.zip` — Server配置用Runtime成果物。
- `rss-reader-modernization-1.14.1.zip.sha256` — Runtime ZIPのSHA-256。
- `rss-reader-modernization-1.14.1-complete.zip` — Repository / Testsを含む完全Source成果物。
- `rss-reader-modernization-1.14.1-complete.zip.sha256` — 完全Source ZIPのSHA-256。

Runtime配布物には`config/local.php`、`.env`、実DB、Log、Session、Cache、Release ZIP等を含めません。

## Update notes

Version 1.14.0からはDB Migration不要です。更新前にCodeをBackupし、配布物の`app/`と`public/`を反映してください。`config/`、`var/`、DB、Server固有の`.htaccess`は既存環境を維持します。Version番号更新によりAsset queryも変わりますが、更新直後はBrowserの強制再読込を推奨します。

主な確認項目:

- Normal系ThemeとSolar／Slate等Dark ThemeでRSS本文が読めること。
- Task、Stock Tag管理、Mail、Clock Timer、Mini Game等の中立SurfaceがThemeに馴染むこと。
- Keyword Highlight、休日、Timer終了、Game状態色等の意味色が従来どおり識別できること。
- Dashboard / Stock / Settings / Offcanvas / Modal等の既存操作が維持されること。

## Verification limits

GitHub ActionsではPHP 8.1 / 8.4の`tests/run.sh`全RegressionとRelease package builder / verifierを確認します。実Hosting Server、実MySQL、外部Feed、IMAP、各Browser / Device固有の描画差は利用環境での最終確認が必要です。

## License

Project LicenseおよびThird-party noticeを維持します。Frontend dependencyはVersion 1.14.0と同じBootstrap / Bootswatch 5.3.8、jQuery 3.7.1、Font Awesome Free 6.7.2です。
