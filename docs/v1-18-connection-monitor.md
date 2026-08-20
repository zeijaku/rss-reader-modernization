# Version 1.18 Connection Monitor

## Purpose

Connection Monitorは、Browser／DeviceからこのRSS Reader自身までのHTTP接続状態をDashboard上で確認するWidgetです。Server CPU／NIC監視、一般的なInternet Speed Test、第三者Service監視ではありません。

## Architecture

- Widget type: `health_probe`
- UI label: `Connection Monitor`
- Probe endpoint: `public/connection_probe.php`
- Widget CRUD API: `widget.healthprobe.create` / `update` / `delete`
- Persistence: existing `dashboard_widget`
- History persistence: none; Browser memory only
- Poll interval: approximately 5 seconds in foreground
- Timeout: 4 seconds
- Multiple widgets: one page-level shared probe stream
- Hidden tab: polling paused; immediate probe on visibility return

## Endpoint boundary

`connection_probe.php`はApplication bootstrapを読み込まず、Session／DB／outbound networkを使いません。GETへ204 empty bodyを返し、Cacheを禁止します。任意URLやUser dataは受け付けません。

## History / statistics

30s／60s／5mを切り替えます。Historyは最大5分／最大120 sampleです。Avg／Maxは成功RTTだけを対象にし、Jitterは隣接する成功HTTP RTTの絶対差平均です。Offlineや大きな時間Gapを跨いでJitterを計算せず、Graph lineも分割します。

## Disconnect state

到達不能が2回連続した場合にOfflineを確定します。Downtime開始は最初の失敗時刻です。正常Probeで復旧し、Recoveredを約15秒表示します。HTTP 500等は到達可能なProbe ErrorとしてOfflineと分離します。

## Quality

- Excellent: <= 79ms
- Good: 80..149ms
- Fair: 150..299ms
- Slow: >= 300ms
- Offline: confirmed unreachable

Recent 5-minute successful RTT medianをBaselineにします。5 sample以上で学習成立とし、現在値がBaselineの2倍超かつ+50ms以上の悪化を2回連続で検出した場合だけRelative slow表示を出します。

## Scope lock

V1.18では外部Internet Probe、任意Probe URL、Google／Cloudflare等の固定Public target、Speed Test、Client／Local IP discovery、WebRTC address discovery、外部Chart library、HistoryのDB／localStorage保存を行いません。

## Database / configuration

DB schema／Migration／SQL／必須config追加はありません。既存`dashboard_widget`を利用します。
