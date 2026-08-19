# RSS Reader Modernization 1.17.2 Release Notes

## Overview

Version 1.17.2では、Dashboardへ**X Timeline Widget**を追加します。

指定した公開X Accountの最近の投稿をX APIからRead Onlyで取得し、RSS Reader内のCardとして表示します。X APIはServer側からだけ呼び出し、Bearer TokenをBrowserへ渡しません。

このWidgetはX Developer Platform、Pay Per Use Credit、Bearer TokenのServer設定が必要なため、通常Widgetとは分けて**上級者向け機能**として案内します。DB Table／Column／Migrationの追加変更はありません。

## X Timeline Widget

- 公開Accountのusernameを指定して最近の投稿を表示。
- 表示件数は3／5／10件。
- Replyを含める／除外する設定。
- Repostを含める／除外する設定。
- Widget Title、Header color、Width／Heightを既存Dashboard Widget設定として保存。
- 手動Refreshに対応。
- 設定変更／削除はページ全体Reloadを前提にせず、他Widgetの状態を不要に失わない。
- 投稿本文、投稿時刻、元投稿へのLinkをRSS Reader側のHTMLとして描画。

## Advanced configuration / Pay Per Use

X Timelineを利用するには、X Developer Platform側でApp／Projectを準備し、Pay Per UseのCreditを利用可能な状態にしたうえでBearer TokenをServerへ設定します。

```php
'APP_X_BEARER_TOKEN' => 'your-server-side-secret',
```

実Tokenは`config/local.php`またはEnvironment variableだけへ置き、Git、Release ZIP、Browser、Support用Screenshot等へ含めません。

Tokenを設定しない場合でも、X Timeline以外の既存機能は利用出来ます。

## Bearer Token state guidance

X Timeline追加Modalは、Raw TokenをBrowserへ渡さず次の状態だけを表示します。

- `missing`: Token未設定。目立つ警告を表示し、X Timeline追加を無効化。
- `invalid_format`: 改行／制御文字等を含むLocal設定不正。警告を表示し、追加を無効化。
- `unverified`: Tokenは設定済みだが、現在TokenによるX API認証成功をまだ確認していない。
- `verified`: 現在Tokenで直近のX API認証成功を確認済み。
- `auth_failed`: 現在TokenでHTTP 401を確認。Tokenの再発行、失効、Server設定を確認するよう案内。

Modal表示のためだけにX APIへ検証Requestは送りません。Pay Per Useの不要な消費を避け、実Timeline取得の認証結果を状態へ反映します。

Local connection stateにはTokenのSHA-256 fingerprintと状態／確認時刻だけを保存し、Raw Tokenは保存しません。Server設定のTokenが変わるとfingerprintが一致しなくなるため、古い確認状態は再利用しません。

## X API / Cache behavior

- username lookupとUser posts取得はServer側X API clientだけから実行。
- X API hostは固定し、任意URLをBrowser／User inputからProxyする構成にしない。
- 通常取得結果は短時間Cacheし、不要なAPI RequestとPay Per Use消費を抑制。
- 許可された一時障害では期限付きstale fallbackを利用。
- HTTP 401／403等の認証・権限Errorはstaleで隠さずfail closed。
- Browserから`api.x.com`へ直接接続せず、Bearer TokenをJavaScript、HTML、RSS Reader API responseへ含めない。

Defaultは`APP_X_CACHE_TTL_SECONDS=300`、`APP_X_STALE_MAX_AGE_SECONDS=3600`、`APP_X_TIMEOUT_MS=5000`です。

## Database and configuration

Version 1.17.2でDB Table／Column／Migrationの追加変更はありません。X Timelineの設定は既存`dashboard_widget.widget_config`へ保存します。

`APP_X_BEARER_TOKEN`はX Timelineを利用する場合だけ必要なOptional Secretです。利用しない環境では空のままで構いません。

Server固有`config/local.php`、`.env`、実DB、`var/`配下のRuntime Dataは更新対象外です。

## Security / privacy boundary

- Bearer TokenはServer-side Secretとしてのみ使用。
- Raw Token／fingerprintをBrowser APIへ返さない。
- connection status CacheにもRaw Tokenを保存しない。
- X API responseをそのままHTMLとして挿入せず、必要なFieldを既存Frontend境界で描画。
- Widget ownershipは既存の認証済み`user_id` scopeを維持。
- X Timeline追加時もServer側でToken未設定／Local形式不正を拒否。
- `config/local.php`、実Token、`var/cache/x/`をRuntime／Complete Release ZIPへ含めない。

## Distribution files

- `rss-reader-modernization-1.17.2.zip` — Server配置用Runtime成果物。
- `rss-reader-modernization-1.17.2.zip.sha256` — Runtime ZIPのSHA-256。
- `rss-reader-modernization-1.17.2-complete.zip` — Repository／Testsを含む完全Source成果物。
- `rss-reader-modernization-1.17.2-complete.zip.sha256` — 完全Source ZIPのSHA-256。

Runtime ZIPはProductionで必要なApplication fileを更新済み実ファイルとして含みます。Production側でPHP／Python／PowerShell等のPatch適用Scriptを実行して完成させる方式は使用しません。

## Update notes from Version 1.17.1

DB Migrationは不要です。

更新前にCodeをBackupし、Server固有の`config/local.php`、DB、`var/`を置換しないでください。

X Timelineを使う場合だけ、Server固有設定へ`APP_X_BEARER_TOKEN`を追加してください。実TokenをGitや配布ZIPへ入れないでください。

更新後はBrowserを強制再読込し、少なくとも次を確認してください。

- Footer等のVersion表示が`RSS Reader Modernization 1.17.2`。
- Widget追加CatalogからX Timeline Modalを開くと「上級者向け機能」の案内が表示される。
- Token未設定では赤い警告となり、X Timelineを追加出来ない。
- 有効なTokenで公開Accountの投稿を取得出来る。
- 実取得成功後は現在Tokenが確認済みと表示される。
- 無効なTokenでHTTP 401を受けた場合は認証失敗の案内になる。
- X設定変更中も無関係なYouTube再生やClock Timer等が不要に停止しない。
- Browser NetworkでX APIへ直接接続せず、Bearer TokenがHTML／JavaScript／RSS Reader API responseへ出ていない。
- 既存RSS、Stock、Task、Calendar、Mail、Camera / Video等に大きな回帰がない。

## Verification

Version 1.17.2 Release Gateでは、Current Regression、Version 1.17 focused tests、Version 1.17.1 compatibility tests、Version 1.17.2 focused testsを実行します。

V1.17.2 focused testsではX API request boundary、Cache／stale、Validation、owner scope、Frontend／Server contractに加え、Bearer Tokenのmissing／invalid format／unverified／verified／auth failed、Token fingerprint、Browser非露出、Release Version／Package contractを確認します。

Runtime／Complete packageはdeterministic builderで生成し、SHA-256 sidecar、ZIP CRC、Path traversal、重複Path、Manifest、Version marker、Private file／Runtime data除外、高Signal Secret patternをVerifierで確認します。

## Verification limits

Automated Testへ実Bearer Tokenは登録しません。そのため、X側のPay Per Use残高、実Account／Postの可用性、X API側の将来仕様変更、Rate limit、権限条件はProduction／StagingのSmoke確認を併用します。

## Deferred: X For You / Home Timeline

Version 1.17.2は指定した公開AccountのTimelineだけを対象とします。

X本体の「おすすめ / For You」画面と同じRecommendation結果は公式APIからそのまま取得出来ないため実装しません。また、自分のHome Timelineを扱うUser Context OAuthはApp-only Bearer Tokenより認証／Token管理の範囲が大きくなるため、将来Versionの検討事項へ分離します。

## License

Project LicenseおよびThird-party noticeを維持します。Bootstrap / Bootswatch 5.3.8、jQuery 3.7.1、Font Awesome Free 6.7.2、hls.js 1.6.16等の既存Dependency構成を維持します。
