# RSS Reader Modernization 1.15.0 Release Notes

## Overview

Version 1.15.0は、Dashboardへ「すぐ見たい生活・環境情報」を追加し、Widget追加UIをCatalog化するReleaseです。既存Widgetと既存データを維持し、Information WidgetとしてEarthquake、Sun / Moon、Air Quality / UVを追加します。

## Main changes

- DrawerのAdd WidgetをRSS／Information／Utility／Gameへ分類し、RSS／Informationを初期展開、Utility／Gameを折りたたみ表示。
- Earthquake Widgetを追加。気象庁防災情報XMLを利用し、発生時刻、震源、最大震度、Magnitude、深さ、津波情報を表示。高頻度Feedに対象情報がない場合は長期Feedへfallback。
- Sun / Moon Widgetを追加。Weatherと同じ地域検索を利用し、日の出、日の入り、月齢、月相、照度目安、次の満月を表示。
- Air Quality / UV Widgetを追加。Open-Meteo Air Quality APIからUS AQI、PM2.5、PM10、UV Indexを取得し、15分Fresh Cacheと最大24時間のstale fallbackを使用。
- Weatherを含むInformation Widget共通処理を整理し、Location Validation、Widget保存、単純JSON Cache、FrontendのLoading／Error／Source／更新時刻を共通化。
- PC／Smartphone、Height 1／2、Normal／Solar／Slateを中心にHeader 44px、操作領域、本文Scroll、Footer、Responsive Modalを調整。
- Dashboard空白領域のMouse focus outlineだけを抑制し、Keyboard focus表示は維持。

## Data sources

- Earthquake: 気象庁 防災情報XML。津波表示はMagnitude等から推測せず、気象庁電文に明示された文言のみ使用します。
- Weather / Location search / Air Quality: Open-Meteo。Air QualityのSource表記はOpen-Meteo / CAMSとします。
- Sun / Moon: 日照情報はPHP標準`date_sun_info()`、月齢／月相はDashboard表示向けのローカル近似計算です。

## Database and configuration

DB Table／Column、Migration、SQL、必須configの追加変更はありません。Version 1.14.1適用済み環境ではCode差し替えのみです。

## Distribution files

- `rss-reader-modernization-1.15.0.zip` — Server配置用Runtime成果物。
- `rss-reader-modernization-1.15.0.zip.sha256` — Runtime ZIPのSHA-256。
- `rss-reader-modernization-1.15.0-complete.zip` — Repository / Testsを含む完全Source成果物。
- `rss-reader-modernization-1.15.0-complete.zip.sha256` — 完全Source ZIPのSHA-256。
- `rss-reader-modernization-1.15.0-production-update.zip` — 1.14.1本番確認用の`app/`＋`public/`更新差分。

Runtime配布物には`config/local.php`、`.env`、実DB、Log、Session、Cache、Release ZIP等を含めません。

## Update notes

Version 1.14.1からはDB Migration不要です。更新前にCodeをBackupし、Production Update ZIPを使う場合は`app/`と`public/`のみ上書きしてください。`config/`、`var/`、DB、Server固有設定は既存環境を維持します。更新後はBrowserの強制再読込を推奨します。

主な確認項目:

- DrawerのAdd WidgetがRSS／Information／Utility／Gameへ整理されていること。
- Earthquakeが気象庁の実データを取得して表示できること。
- Sun / Moonを地域指定で追加・編集でき、日の出／日の入り／月情報が表示されること。
- Air QualityでUS AQI／PM2.5／PM10／UVが表示されること。
- Weatherを含む4つのInformation Widgetで追加／編集／削除／更新／D&D、Height 1／2が維持されること。
- SmartphoneおよびSolar／Slateで本文・補助文字・操作Buttonが読めること。
- Dashboard空白領域のMouse Clickで不要な青枠が出ず、Keyboard focusは視認できること。

## Verification limits

Release作成環境ではPHP 8.4で既存Regression範囲を区間分割して実行し、GitHub CIと同様にPlaywright Browser testをPlaywright unavailableとしてskipしたうえで非Browser Regressionを完走しました。Version 1.15専用Focused regressionはBackend 32件、Static / Contract 104件をPASSし、Package builder / verifierもRelease Gateで実施します。PHP 8.1はこのローカル実行環境にBinaryがないため、GitHub ActionsのPHP 8.1 matrixを正式公開前の必須Gateとします。一部Browser testのTimeout／削除済みBootstrap 4 asset参照は同じ正式v1.14.1 Artifactでも再現するため、V1.15 Regressionとは分離して本番Browser確認項目として残します。実Hosting Server、実MySQL、外部Feed、IMAP、各Browser / Device固有の描画差は利用環境での最終確認が必要です。

## License

Project LicenseおよびThird-party noticeを維持します。Frontend dependencyはVersion 1.14.1と同じBootstrap / Bootswatch 5.3.8、jQuery 3.7.1、Font Awesome Free 6.7.2です。
