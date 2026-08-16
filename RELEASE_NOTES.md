# RSS Reader Modernization 1.16.0 Release Notes

## Overview

Version 1.16.0は、DashboardへCalculator WidgetとBlind Spot / Discovery Widgetを追加し、既存WidgetのHeader／Drag Handleを揃えるReleaseです。Blind Spotは「普段選ばない分野の記事に偶然出会う」ことを目的にし、既存RSS取得・SSRF対策・Cache・Parser・Article Actionsを再利用します。

## Main changes

- Calculator WidgetをUtilityへ追加。四則演算、Decimal、Percent、Sign、Backspace、Clear、Keyboard操作に対応。計算履歴やServer保存は行わず、`eval()`も使用しません。
- Dashboard WidgetのTitle Barを44pxへ統一し、Drag Handleの見た目と44px操作領域を共通化。
- Blind Spot / Discovery WidgetをInformationへ追加。20カテゴリ・国内向け40 Feedからカテゴリを選び、最大2 Feedを試行して最大3記事を表示。
- 直前カテゴリを次回候補から外し、24時間・最大18件の最近記事履歴を`dashboard_widget.widget_config`へ保存して同一記事の連続表示を抑制。
- Blind Spot記事は「＋／－」で`content`または`description`を展開。既存RSSに合わせて展開Buttonを右端へ配置。
- 既存Article Actionsを再利用し、Stock保存、URL Copy、X投稿、Task追加に対応。
- Smartphone、Width 1〜4、Height 1／2、Default／Solar／Slateを含むThemeで操作領域、内部Scroll、Title行数、Focus表示を調整。
- Full Regressionで検出したClock Timer単体runtime互換を修正し、jQueryが存在しないテスト環境ではCalculator／Blind Spot追加部だけを安全にskip。

## Blind Spot feed catalog

Discovery用Feedは20カテゴリ×2件の40 Feedです。JAXA、JST、NIMS、産総研、JAMSTEC、気象庁、国土交通省、国土地理院、総務省統計局、JICA、e-Gov、J-STAGE等の国内向けFeedを中心に構成します。Search Feedの既存Common Feed 5件はDiscovery Feedを除外するため、従来のSearch Feedカテゴリ／Source数を維持します。

## Database and configuration

DB Table／Column、Migration、SQL、必須configの追加変更はありません。Version 1.15.0適用済み環境ではCode差し替えのみです。Blind Spotのローテーション履歴は既存`dashboard_widget.widget_config`へ保存します。

## Distribution files

- `rss-reader-modernization-1.16.0.zip` — Server配置用Runtime成果物。
- `rss-reader-modernization-1.16.0.zip.sha256` — Runtime ZIPのSHA-256。
- `rss-reader-modernization-1.16.0-complete.zip` — Repository / Testsを含む完全Source成果物。
- `rss-reader-modernization-1.16.0-complete.zip.sha256` — 完全Source ZIPのSHA-256。
- `rss-reader-modernization-1.16.0-production-update.zip` — 1.15.0からのProduction更新差分。
- `rss-reader-modernization-1.16.0-production-update.zip.sha256` — Production Update ZIPのSHA-256。

Runtime配布物には`config/local.php`、`.env`、実DB、Log、Session、Cache、Release ZIP等を含めません。

## Update notes

Version 1.15.0からDB Migrationは不要です。更新前にCodeをBackupしてください。Production Updateでは`app/version.php`、`app/dashboard_widget.php`、`app/api.php`、`app/search_feed.php`、`config/common_feeds.php`、`public/js/clock-timer.js`を更新します。Server固有の`config/local.php`、DB、`var/`は置換しません。更新後はBrowserの強制再読込を推奨します。

主な確認項目:

- Calculatorの四則演算、Percent、Backspace、Clear、Keyboard操作。
- Blind Spotの記事表示、Refreshによるカテゴリ切替、最近記事の重複抑制。
- Blind Spotの「＋／－」概要展開とStock／URL Copy／X／Task Actions。
- Search FeedにDiscovery用40 Feedが混入せず、従来Common Feedを維持すること。
- Width 1〜4、Height 1／2、Smartphone、Solar／Slateで表示崩れがないこと。
- Clock Timerを含む既存Widgetが従来どおり動作すること。

## Verification

V1.16正式候補はGitHub ActionsでPHP 8.1／8.4の両方についてV1.16専用Contractと`tests/run.sh` Full RegressionをPASSしています。mainへのsquash merge後も通常CI Run #95でPHP 8.1／8.4ともFull RegressionがGREENであることを確認しています。実Hosting Server、外部Feed、Browser／Device固有の描画差はProduction環境で最終確認しています。

## Verification limits

Automated verification covers PHP 8.1／8.4 regression, package structure, manifests, checksums, and high-signal secret scans. External RSS endpoint availability, hosting-provider differences, browser rendering, and device-specific behavior are not fully reproducible in GitHub Actions and are confirmed separately in the Production environment.

## License

Project LicenseおよびThird-party noticeを維持します。Frontend dependencyはVersion 1.15.0と同じBootstrap / Bootswatch 5.3.8、jQuery 3.7.1、Font Awesome Free 6.7.2です。
