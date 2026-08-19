# RSS Reader Modernization 1.17.1 Release Notes

## Overview

Version 1.17.1は、Version 1.17.0で追加したCamera / Video Widgetと、Dashboard上で同時に動くMail／Information Widget／各種設定変更の安定性を改善するMaintenance Releaseです。

新しいDB TableやColumnは追加せず、既存の機能・データ構造を維持したまま、Session lock、Timeout時の復旧、Widget設定保存時の画面更新、通知表示、hls.js読込みを整理しています。

## Main changes

- APIでAuthentication、CSRF、Action validationを完了した後、通常Actionではfile-backed PHP Session lockを早期解放し、RSS／Mail／Weather等の遅いI/Oによって別のDashboard API Requestが直列待ちしにくい構成へ変更。
- Account email／password変更はSession IDとCSRF Tokenを更新するため、従来どおりSessionを開いた状態で処理。
- Session lock解放処理をAPIの`Throwable` boundary内へ移し、稀な`session_write_close()`失敗時も通常のJSON 500 responseとReference IDへ収めるよう修正。
- Camera / VideoへClient-side watchdogを追加。Snapshotは12秒、Video metadataは15秒、MJPEGは12秒を目安に、固まった表示を復旧可能な状態へ戻す。
- Mail Widgetへ13.5秒のClient-side watchdogを追加し、`aria-busy`、Spinner、本文Loading等が残り続けた場合に更新／再試行可能な状態へ戻す。
- Earthquakeは10.5秒、Sun / Moonは6.5秒、Air Qualityは8.5秒のClient-side watchdogを追加し、Server／XHR側のbounded timeout後もLoading表示だけが残り続ける状態を回避。
- RSS、Clock、Game、Memo、Task、Search Feed、Links、Weather、Earthquake、Sun / Moon、Air Quality、Calendar、Camera / Video、Mailの設定変更を、ページ全体Reloadではなく対象Card中心の更新へ変更。
- Weatherの見出し色／Title／Width／Heightだけを変更した場合はWeather dataを再取得せず、表示だけ更新。地域／表示日数を変更した場合のみDataを再取得。
- Camera / Video設定変更時は対象Camera Cardだけを置換し、無関係なCardを並べ直さない。Mail設定変更も対象Mail Cardだけを更新。
- 他Widgetの設定変更時に、再生中のYouTube iframe等をページReloadで作り直して再生停止させる問題を解消。
- Dashboard共通通知を`success: 約2.5秒`、`info: 約3秒`、`danger: 約6秒`で自動消去し、「設定を更新しました」が残り続ける問題を修正。
- hls.js 1.6.16の固定Versionとanonymous CORSを維持しつつ、Browserで不一致となっていたSubresource Integrity値を正しいSHA-384へ修正。
- `APP_VERSION`、`APP_VERSION_LABEL`、`APP_ASSET_REVISION`を正式な`1.17.1`へ統一。

## Database and configuration

Version 1.17.1でDB Table／Column、Migration、SQLの追加変更はありません。

必須Configurationの追加もありません。Server固有の`config/local.php`、`.env`、実DB、`var/`配下のRuntime Dataは更新対象外です。

Version 1.17.0適用済み環境ではCode差し替えのみです。

## Session / API behavior

Dashboardは起動時やWidget更新時に複数API Requestを並行して送る場合があります。file-backed PHP Sessionを外部I/O中も保持すると、1つの遅いRequestによって他Requestまで待たされるため、Version 1.17.1ではAuthentication／CSRF／Action validation後に通常ActionのSession lockを解放します。

Account email／password変更はSession stateを変更するため例外としてSessionを維持します。Session解放自体が失敗した場合もAPI exception boundaryで処理し、HTML errorへ崩さずJSON API contractを維持します。

## Widget settings update behavior

設定変更は、対象Widgetだけを更新することを基本とします。

- RSS／Clock／Game／Memo／Task／Search Feed／Calendar: 保存後に対象Cardを再取得・差し替え。
- Links／Weather／Earthquake／Sun / Moon／Air Quality: 表示設定を対象Cardへ反映し、Data条件が変わった場合だけ既存Refresh経路を利用。
- Camera / Video: 対象Camera Cardだけを再構築。無関係なYouTube／Video Cardへ触れない。
- Mail: 対象Mail Cardの設定を更新し、そのCardの既存Refresh経路を利用。

新規追加、削除、並び替え等のすべてを無理に同じ仕組みへ変更せず、Version 1.17.1は設定更新の全画面Reload解消に範囲を限定しています。

## Camera / Video stability

Snapshot／Video File／MJPEG／HLS／YouTubeというVersion 1.17.0のSource Type構成は変更しません。

Client-side watchdogは、Browserや外部配信元が応答しない場合に画面が永久にLoading／disabledのまま残ることを避けるための補助です。無制限Retryは行いません。

hls.jsはVersion 1.17.0と同じ1.6.16を必要時だけjsDelivrから遅延読込みします。SRIと`crossorigin="anonymous"`を維持します。

## Distribution files

- `rss-reader-modernization-1.17.1.zip` — Server配置用Runtime成果物。
- `rss-reader-modernization-1.17.1.zip.sha256` — Runtime ZIPのSHA-256。
- `rss-reader-modernization-1.17.1-complete.zip` — Repository／Testsを含む完全Source成果物。
- `rss-reader-modernization-1.17.1-complete.zip.sha256` — 完全Source ZIPのSHA-256。

Runtime配布物には`config/local.php`、`.env`、実DB、Dump、Backup、Log、Session、Cache、Throttle Data、GitHub metadata、Testsを含めません。

完全Source成果物にもPrivate設定、実DB、Runtime Data、別Release ZIP等を含めません。

## Update notes

Version 1.17.0からDB Migrationは不要です。

更新前にCodeをBackupし、Server固有の`config/local.php`、DB、`var/`を置換しないでください。Runtime ZIPは必要なApplication fileを実ファイルとして含み、Production側でPHP／Python／PowerShell等の適用Scriptを実行して完成させる方式は使用しません。

更新後はBrowserを強制再読込し、少なくとも次を確認してください。

- Footer等のVersion表示が`RSS Reader Modernization 1.17.1`。
- YouTube再生中にWeatherの見出し色を変更してもページ全体がReloadされず、YouTube再生が継続する。
- Clock Timer等の動作中に別Widgetの設定を変更しても、その状態が不要に失われない。
- 「設定を更新しました」が約2.5秒後に消える。
- Camera / Video／Mail／Earthquake／Sun / Moon／Air Qualityが失敗時に永久Loadingへ残らず再試行可能になる。
- HLS利用時、hls.jsの`integrity` mismatchがConsoleへ出ない。
- 既存のRSS、Stock、Task、Calendar、Mail等に大きな回帰がない。

## Verification

Version 1.17.1 Release Gateでは、GitHub Actions上のPHP 8.1／8.4 MatrixでCurrent Regression、Version 1.17 focused tests、Version 1.17.1 focused testsを実行する構成とします。

Focused testsではSession release policy、Camera／Mail watchdog、Information Widget watchdog、設定変更のno-reload contract、hls.js SRI、Version／Asset revision、PHP／JavaScript syntaxを確認します。

Runtime／Complete packageはdeterministic builderで生成し、SHA-256 sidecar、ZIP CRC、Path traversal、重複Path、Manifest、Version marker、Private file／Runtime data除外、高Signal Secret patternをVerifierで確認します。

## Verification limits

Automated verificationでは、外部RSS／Camera／Video／Mail providerの可用性、配信元CORS、Mixed Content、Codec、YouTube埋め込み可否、CDNの将来可用性、Browser／Device固有描画、実Production DB／Network条件を完全には再現出来ません。

そのため、外部Serviceを伴う代表Sourceと、今回修正した「他Widget設定変更中のYouTube再生継続」「通知自動消去」はProduction環境でのSmoke確認を併用します。

## License

Project LicenseおよびThird-party noticeを維持します。Version 1.17.1でもhls.js 1.6.16（Apache-2.0）、Bootstrap / Bootswatch 5.3.8、jQuery 3.7.1、Font Awesome Free 6.7.2の既存Dependency構成を維持します。
