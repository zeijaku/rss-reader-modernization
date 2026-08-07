# RSS Reader Modernization 1.7.0 Release Notes

Release date: 2026-08-07

Version 1.7.0は、Cache／Security Header、30日間ログイン維持、Widget縦2段、日本の祝日表示をまとめて正式化するReleaseです。V1.7-H/R4からApplication Runtimeの機能変更は行わず、Version MarkerとRelease／Repository整備だけを行っています。

## Asset cache / HTTP cache / security headers

- Local CSS／JavaScript／Theme／faviconのVersion Queryを`APP_VERSION`へ一元化
- Version付きCSS／JavaScriptを長期Cache、Font／画像は短めCache
- Dashboard／Loginは`private, no-store`、API／Errorは`no-store`
- `X-Frame-Options: SAMEORIGIN`
- `Permissions-Policy`でcamera／microphone／geolocation／paymentを無効化
- `frame-ancestors 'self'`、`base-uri 'self'`、`form-action 'self'`の限定CSP
- HSTSと全面CSPはHosting／既存Inline Assetへの影響を考慮して保留

## 30-day login persistence

- Login画面へ任意の「この端末で30日間ログイン状態を維持」を追加
- Sessionの2時間Idle／12時間Absolute期限は維持
- 固定30日期限のRemember TokenでSession切れ後に自動Login
- CookieはHttpOnly、SameSite=Lax、HTTPS時Secure
- Raw ValidatorをDBへ保存せずSHA-256 Hashだけを保持
- 自動Login成功時にValidator RotationとSession ID再生成
- Logoutで現在端末、Password変更でUser全端末Tokenを失効
- 不正／期限切れ／無効User／DB ErrorはFail Closed

## Widget vertical grid

- `dashboard_widget.widget_height`を1／2で保存
- Desktop 4列、Tablet 2列、Smartphone 1列
- 標準／縦2段を各Widget追加・編集画面から選択
- Smartphoneは固定Rowと縦Spanを解除して自動高
- 標準Rowは`minmax(320px, auto)`とし、Clock／Gameの主要操作を切らない
- Dense packingは使用せずDOM／Keyboard／保存順を一致
- RSSの自動表示は標準5件／縦2段10件
- RSSは1～30件の明示指定にも対応し、既存`widget_config`へ保存

## Japanese holiday calendar

- Calendarへ日本の祝日／休日を赤表示
- 祝日名をTooltipと`aria-label`へ付与
- 内閣府CSV URLを`APP_HOLIDAY_CSV_URL`として設定可能
- 既定60日Cache、5秒Timeout、512KiB上限
- Calendar表示を外部通信で待たせずBackground更新
- CSV破損／取得失敗時は既存Cacheを保持
- Cache未作成時は同梱2026／2027 SnapshotへFallback
- Holiday Runtime Cacheは`var/cache/japanese_holidays.json`へ保存し配布物／Gitから除外

## Database / migration

Version 1.7では既存DBへ次の2変更があります。

1. `database/migrations/007_v1_7_remember_token.sql` — Remember Token Table追加
2. `database/migrations/008_v1_7_widget_height.sql` — `dashboard_widget.widget_height`追加

すでにV1.7-H/R4まで適用済みの環境では両方とも再実行しません。特に008は`widget_height`が存在する状態で再実行するとDuplicate Columnになります。新規DBは`database/schema.sql`にVersion 1.7構造を含みます。

## Configuration

`config/local.php.example`には次を追加済みです。

```php
'APP_HOLIDAY_CSV_URL' => 'https://www8.cao.go.jp/chosei/shukujitsu/syukujitsu.csv',
'APP_HOLIDAY_CACHE_DAYS' => '60',
'APP_HOLIDAY_TIMEOUT_MS' => '5000',
```

既存`config/local.php`は配布ZIPに含まれないため、必要に応じて手元の設定へ追記します。URL変更時は`APP_HOLIDAY_CSV_URL`だけ変更できます。

## GitHub registration policy

Version 1.7.0を現在の完成版としてGitHubへ登録します。Version 1.5／1.6の欠けている履歴を後から作り直すことはRelease条件にしません。Complete ZIPを`feature/v1.7-modernization`へ反映し、Fast-forward可能なことを確認して`main`へ統合し、最後にAnnotated Tag `v1.7.0`を作成します。Force pushは使用しません。

## Verification limits

Automated regression、Package Manifest／CRC／SHA-256、PHP／JavaScript syntax、V1.7 focused testsを実施します。実MySQL／MariaDB Server、実HostingのApache module、内閣府CSVへのLive通信、全Browser／全Themeの実機表示は配布環境に依存するため、Release Package内のChecklistに従って最終確認してください。V1.7-H/R4の実機Calendar祝日表示は利用環境で確認済みですが、その結果そのものをPrivate evidenceとして配布物へ収録しません。

# V1.7-H / R4

Application Version `1.7.0-dev.10`。Calendarへ日本の祝日／休日表示を追加しました。内閣府CSVを設定可能URLから60日CacheでBackground更新し、取得失敗／CSV破損時は既存Cacheを維持します。初回取得前や通信失敗時は2026年／2027年の同梱SnapshotへFallbackします。祝日は赤表示し、祝日名をTooltip／`aria-label`へ付与します。R4によるDB Migrationはありません。

# V1.7-H / R3

Application Version `1.7.0-dev.9`。V1.7-H/R2の実機確認を受け、標準Rowを220pxから320px下限へ拡大しました。通常RSSの自動表示は標準5件／縦2は10件へ固定し、R2の実高さ測定Trimを廃止しています。Clock／Icon Quest／Lights Outは高さ1でも内容が320pxを超える場合にRow自体を自然拡張し、操作部を切りません。R3によるDB Migrationはありません。

# V1.7-H / R2

Application Version `1.7.0-dev.8`。V1.7-H/R1の実機確認で見つかった不要な縦横Scrollbarを整理し、通常RSSへ「自動／1～30件」の表示件数設定を追加しました。自動ではCardの実高さへ収まる記事数を表示し、縦2段の空き領域を活用します。RSS件数設定のDB Migrationはありません。Migration 008／Audit SQLは`information_schema`非依存・Table Prefix対応へ差し替えています。R1で`widget_height`適用済みの環境では008を再実行しません。

# V1.7-H / R1

Application Version `1.7.0-dev.7`。Widgetごとの縦幅を正式実装し、Desktop／Tabletで標準または縦2段を選択出来ます。既存Widgetは高さ1のままです。Migration 008をApplication配置前に適用してください。Smartphoneは1列の自動高を維持します。

# V1.7-D / R1

Application Version `1.7.0-dev.3`。V1.7-CでVersion付きに統一したCSS／JavaScriptへ長期Cacheを設定し、Font／画像は短めのCacheとしました。Dashboard／Loginは`private, no-store`、API／Errorは`no-store`を明示しています。`X-Frame-Options`、限定Permissions Policy、`frame-ancestors`／`base-uri`／`form-action`だけのCSPを追加し、HSTSと全面CSPは保留しています。

# V1.7-C / R1

Application Version `1.7.0-dev.2`。Local Asset URLを共通Helperへ統一し、Theme、Dashboard、Auth、Clock、Game、Calendar、faviconのCache Bustingを同じApplication Versionで管理します。HTTP Cache HeaderとSecurity Headerは次Stageへ分離しています。

# V1.7-B / R1 Development Checkpoint

Version 1.6.0 Complete版をV1.7開発Baselineとして取り込み、`1.7.0-dev.1`へ進めるCheckpointです。Application機能、DB、API、設定、外部Libraryは変更していません。GitHubでは`feature/v1.7-modernization`だけを使用し、main、Tag、Releaseは正式化まで変更しません。

# RSS Reader Modernization 1.6.0 Release Notes

Release date: 2026-08-07

Version 1.6.0は、SmartphoneのTab Swipeを視覚的に分かりやすくし、第二のMini Game Widgetとして5×5の「Lights Out」を追加するReleaseです。

## Smartphone Tab Swipe Indicator

- 左Swipeで次のTabへ移動する場合、右端へ左向き矢印を表示
- 右Swipeで前のTabへ移動する場合、左端へ右向き矢印を表示
- Swipe移動量に応じた表示、成立時の短い強調、不成立時の静かな消去
- `pointer-events: none`、Smartphone限定、Safe Area、Reduced Motion対応
- 既存64px閾値、左右24px画面端除外、縦Scroll判定を維持
- Link、Button、Form、Timer、Game、Widget Drag、横Scroll領域の除外を維持

Swipe可能領域は無条件に画面全体へ広げず、既存操作との競合を避けています。

## Lights Out

- 5×5盤面
- Tap／Click、Enter／Space操作
- 押したマスと上下左右のON／OFF反転
- Moves表示
- Reset
- 新しい問題
- 全消灯時のClear表示
- Arrow Key、Home、Endによる盤面Focus移動
- 44px操作領域、Focus表示、Screen Reader向けLabel
- Smartphone、Light／Dark Theme、Reduced Motion対応

問題は全消灯状態から有効操作を複数回適用して生成するため、解けない盤面を作りません。Solver、Hint、難易度評価、最短手数は追加していません。

## 状態保存とRecovery

Lights Outの現在盤面、初期盤面、Moves、Clear状態をUser IDとWidget IDで分離して保存します。

```text
localStorage → sessionStorage → Memory
```

保存CopyはSchema、Game Version、盤面Size、値、Moves、Clear状態を検証します。壊れたCopyだけを除去し、正常なCopyが複数ある場合は`savedAt`が新しいものを採用します。すべて異常な場合は安全な新規問題へ復旧します。

Game Widget削除またはGame種類変更が成功した場合は、対象Widgetの旧Game Storageを整理します。

## DB／API／設定

Version 1.6による新しいDB構造はありません。

- Table／Column追加：なし
- Migration／SQL：なし
- API Route変更：なし
- 必須設定追加：なし
- `config/local.php`変更：なし
- 外部Library／Framework追加：なし
- Serverへの盤面保存：なし
- 音、Vibration、Browser通知：なし

Lights Out Widgetの登録は既存`dashboard_widget` Tableと既存Game Widget CRUDを利用し、進行状態はBrowser Storageへ保存します。

## Cache

V1.6で変更したCSS／JavaScriptは個別Cache Bustingを維持しています。Asset Cache Bustingの一元管理、HTTP Cache Headerの全面整理はこのReleaseへ含めていません。

## Update

Version 1.5.0からVersion 1.6.0への更新は、Codeを更新してBrowserをHard Reloadします。SQLやMigrationは実行しません。`config/local.php`、Server固有`.htaccess`、実DB、`var/`の生成Dataは不用意に上書きしないでください。

## Artifacts

- `rss-reader-modernization-1.6.0-complete.zip` — Source、Tests、Documentation、GitHub metadataを含む完全統合ZIP。
- `rss-reader-modernization-1.6.0.zip` — Server配置用Runtime ZIP。TestsとGitHub metadataを除外。
- 各ZIPに対応する`.zip.sha256` — 外部SHA-256 Sidecar。
- ZIP内部の`SOURCE_MANIFEST.sha256`または`RELEASE_MANIFEST.sha256` — File単位のSHA-256 Manifest。

## Verification limits

Automated Full Regression、Package CRC、重複Entry、危険Path、Manifest、秘密情報／実DB／Runtime Data除外、再展開後Testを実施しています。

この実行環境から実施できないため、次は利用者側で確認してください。

- iPhone Safari実機の画面端戻るGesture
- Android Chrome実機のGesture Navigation
- 本番ServerのPHP／MySQL／Web Server構成
- 本番Databaseを使った更新・Rollback
- GitHub main、Release Commit、Tag、GitHub Actions

Git Tag `v1.6.0`は、本番確認後にmainのVersion 1.6.0 Release Commitへ作成してください。正式化前または確認前にTagを作成しないでください。
