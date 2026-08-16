#!/usr/bin/env python3
from pathlib import Path


def update_readme() -> None:
    path = Path('README.md')
    text = path.read_text(encoding='utf-8')
    text = text.replace(
        '**Stable release:** `RSS Reader Modernization 1.15.0`\nRelease tag: `v1.15.0`',
        '**Stable release:** `RSS Reader Modernization 1.16.0`\nRelease tag: `v1.16.0`',
        1,
    )
    marker = 'Version 1.15.0では、'
    intro = (
        'Version 1.16.0では、UtilityへCalculator Widgetを追加し、InformationへBlind Spot / Discovery Widgetを追加しました。'
        'Blind Spotは国内向け40 Feedを20カテゴリに分け、直前カテゴリ回避と24時間・最大18件の最近記事履歴で同じ内容の連続表示を抑えます。'
        '記事概要の展開とStock／URL Copy／X／Taskの既存Article Actionsも再利用します。'
        'Dashboard Widget HeaderとDrag Handleの操作領域も共通化しました。DB schema、Migration、必須configの追加変更はありません。\n\n'
    )
    if intro not in text:
        text = text.replace(marker, intro + marker, 1)
    path.write_text(text, encoding='utf-8')


def update_changelog() -> None:
    path = Path('CHANGELOG.md')
    text = path.read_text(encoding='utf-8')
    entry = '''## RSS Reader Modernization 1.16.0 — 2026-08-17

### Calculator / Blind Spot Discovery / Dashboard UI

- UtilityへCalculator Widgetを追加。四則演算、Decimal、Percent、Sign、Backspace、Keyboard操作に対応し、計算はBrowser側のみで行い`eval()`は使用しない。
- Dashboard WidgetのTitle Barを44pxへ揃え、Drag Handleの実操作領域を44pxへ統一。
- InformationへBlind Spot / Discovery Widgetを追加し、20カテゴリ・国内向け40 Feedから普段見ない分野の記事を最大3件表示。
- Blind Spotは直前カテゴリを避け、24時間・最大18件の最近記事履歴で同一記事の連続表示を抑制。既存のSSRF Validation、FeedFetchService、Cache、Parserを再利用。
- Blind Spotの記事概要を「＋／－」で展開し、既存RSSと同じ右端配置へ統一。本文は`content`優先、未取得時は`description`を使用。
- Blind Spotへ既存Article Actionsを接続し、Stock保存、URL Copy、X投稿、Task追加を共通処理で利用。
- Smartphone、Height 1／2、Width 1〜4、Solar／Slateを含むThemeでBlind Spotの操作領域、内部Scroll、Title行数、Focus表示を調整。
- V1.16-F Full RegressionでClock Timer単体runtimeのjQuery非依存契約を確認し、Calculator／Blind Spot追加部をjQuery未定義環境では安全にskipするよう修正。
- DB Table／Column、Migration、SQL、必須configの追加変更はなし。Application Versionを`1.16.0`へ更新。

'''
    if entry not in text:
        text = text.replace('# Changelog\n\n', '# Changelog\n\n' + entry, 1)
    path.write_text(text, encoding='utf-8')


def update_release_notes() -> None:
    Path('RELEASE_NOTES.md').write_text('''# RSS Reader Modernization 1.16.0 Release Notes

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

## License

Project LicenseおよびThird-party noticeを維持します。Frontend dependencyはVersion 1.15.0と同じBootstrap / Bootswatch 5.3.8、jQuery 3.7.1、Font Awesome Free 6.7.2です。
''', encoding='utf-8')


def update_package_tools() -> None:
    for rel in [
        'tools/build_release_package.py',
        'tools/verify_release_package.py',
        'tools/build_complete_package.py',
        'tools/verify_complete_package.py',
    ]:
        path = Path(rel)
        source = path.read_text(encoding='utf-8')
        source = source.replace('v1.15.0', 'v1.16.0').replace('1.15.0', '1.16.0')
        path.write_text(source, encoding='utf-8')


if __name__ == '__main__':
    update_readme()
    update_changelog()
    update_release_notes()
    update_package_tools()
