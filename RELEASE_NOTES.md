# RSS Reader Modernization 1.17.0 Release Notes

## Overview

Version 1.17.0は、DashboardへCamera / Video Widgetを追加するReleaseです。Snapshot、YouTube、Browser標準Video、MJPEG、HLSを1つのWidgetから扱い、Source TypeはAutoまたは手動指定に対応します。V1.17ではServer-side media proxyを追加せず、Mediaの取得・再生はBrowserから配信元へ直接行います。

## Main changes

- Camera / Video Widgetを追加。既存`dashboard_widget`へ設定を保存し、新しいDB Table／Columnは追加しません。
- Snapshot画像をBrowserの`<img>`で表示し、OFF／10秒／30秒／1分／5分／10分の更新間隔と手動「今すぐ更新」に対応。
- Snapshot更新失敗時は直前に正常表示出来た画像を残し、読込中／Error／最終成功時刻を表示。
- YouTubeのwatch／live／shorts／embed／youtu.be URLを既知Hostと11文字Video IDで検証し、YouTube標準Playerで表示。
- MP4／WebM等のVideo FileをBrowser標準`<video controls playsinline>`で再生。Codec対応可否はBrowser側へ委ねます。
- MJPEGを連続`<img>`として表示し、手動再接続に対応。通常Video PlayerのSeek等は行いません。
- HLSはNative HLSを利用出来るBrowserではNative再生し、それ以外ではhls.js 1.6.16を必要時だけ読込みます。hls.jsはVersion固定＋SRI付きです。
- HLSのFatal errorはNetwork recovery 1回、Media recovery 1回に限定し、無制限Retryは行いません。
- Auto判定はYouTube、Video extension、`.m3u8`、MJPEG extension／`/mjpeg` path／明示Query、Snapshot画像extensionを判定し、曖昧なURLはSnapshotへ決め打ちせず「判定不能」として手動選択を促します。
- SmartphoneでCamera / Videoの操作領域とModal余白を調整。
- 長期`immutable` Cache環境でも段階確認中のFrontend変更を確実に取得出来るよう、Application Versionとは別に`APP_ASSET_REVISION`を導入。正式Releaseでは`APP_VERSION`と同じ`1.17.0`へ確定。
- TEST-1／TEST-2でDefault CIを「現行Product Contract＋現行Version専用Focused Test」へ整理し、過去Version番号や過去Asset完全一致を固定する履歴Testを通常CIから分離。

## Camera / Video source types

- `Auto` — URL文字列だけから安全に判定出来る形式を選択。Network probingは行いません。
- `Snapshot` — JPEG／PNG／GIF／WebP／BMP／AVIF等の静止画。
- `YouTube` — YouTube標準埋め込みPlayer。
- `Video File` — Browser標準Video Playerで直接再生出来るMedia URL。
- `MJPEG` — BrowserのImageとして直接接続するMJPEG stream。
- `HLS` — `.m3u8` Playlist。Native HLSまたはhls.jsで再生。
- `iframe` — Version 1.17では未対応です。

Autoは配信元へProbeしないため、ExtensionやURL patternから判断出来ないEndpointは「判定不能」になります。その場合はSource Typeを手動指定してください。

## Network / security boundary

Camera / VideoのMedia URLはHTTP／HTTPSのみを受け付け、Credential付きURLや不正なURL形式を拒否します。V1.17ではMediaをServer側で代理取得しません。Snapshot／Video／MJPEG／HLS／YouTubeはBrowserから各配信元へ直接接続するため、CORS、Mixed Content、YouTubeの埋め込み制限、Browser Codec対応等は配信元・Browser側の制約を受けます。

HLSでhls.jsが必要な場合のみ、jsDelivrからVersion固定`1.6.16`をSubresource Integrity付きで遅延読込みします。Licenseは`licenses/hls.js-1.6.16-Apache-2.0.txt`と`THIRD_PARTY_NOTICES.md`へ記録しています。

## Database and configuration

DB Table／Column、Migration、SQL、必須configの追加変更はありません。Camera / Video設定は既存`dashboard_widget.widget_config`へ保存します。Version 1.16.0適用済み環境ではCode差し替えのみです。

## Distribution files

- `rss-reader-modernization-1.17.0.zip` — Server配置用Runtime成果物。
- `rss-reader-modernization-1.17.0.zip.sha256` — Runtime ZIPのSHA-256。
- `rss-reader-modernization-1.17.0-complete.zip` — Repository / Testsを含む完全Source成果物。
- `rss-reader-modernization-1.17.0-complete.zip.sha256` — 完全Source ZIPのSHA-256。
- `rss-reader-modernization-1.17.0-production-update.zip` — 1.16.0からのProduction更新差分。
- `rss-reader-modernization-1.17.0-production-update.zip.sha256` — Production Update ZIPのSHA-256。

Runtime配布物には`config/local.php`、`.env`、実DB、Log、Session、Cache、Release ZIP等を含めません。

## Update notes

Version 1.16.0からDB Migrationは不要です。更新前にCodeをBackupしてください。Server固有の`config/local.php`、DB、`var/`は置換しません。

主な確認項目:

- Version表示が`RSS Reader Modernization 1.17.0`であること。
- Camera / Videoの追加・変更・削除と既存Drag & Drop。
- Snapshotの表示、手動更新、自動更新、失敗時の直前画像維持。
- YouTubeと直接Videoの再生。
- MJPEGのAuto判定、表示、再接続。
- HLSのAuto判定と再生。
- Autoで判定出来ないURLが「判定不能」となり、手動Source Typeを選択出来ること。
- PC／SmartphoneでWidgetとModalに大きな表示崩れがないこと。
- Calendar等の既存Widgetが従来どおり利用出来ること。

## Verification

V1.17のCamera / Video各StageはGitHub ActionsのPHP 8.1／8.4でFocused TestとRegressionを確認しています。TEST-1／TEST-2ではDefault CIを現行Product Contract中心へ整理し、Release Candidate markerを`1.17.0`へ更新したCI Run #128でもPHP 8.1／8.4のCurrent RegressionとV1.17 focused testsがすべてPASSしました。

Productionでは1.16.0→1.17.0の13ファイル差分を適用し、Version表示、Camera / Videoの主要Source、Smartphoneを含むSmoke確認でReleaseを止める問題がないことを確認しました。細かな表示・個別配信元差異は今後の改善課題として扱います。

## Verification limits

Automated verification covers PHP 8.1／8.4 current regression, V1.17 focused contracts, package structure, manifests, checksums, and high-signal secret scans. External Camera／Video endpoint availability、CORS、Mixed Content、Codec、YouTube埋め込み可否、CDN availability、Browser／Device固有描画はGitHub Actionsでは完全再現出来ないため、Production環境で代表Sourceを確認します。

## License

Project LicenseおよびThird-party noticeを維持します。Version 1.17ではhls.js 1.6.16（Apache-2.0）を必要時に外部CDNから遅延読込みします。既存Frontend dependencyはBootstrap / Bootswatch 5.3.8、jQuery 3.7.1、Font Awesome Free 6.7.2を維持します。
