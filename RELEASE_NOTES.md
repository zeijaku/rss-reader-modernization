# RSS Reader Modernization 1.18.0 Release Notes

Release date: 2026-08-20

Version 1.18.0では、Dashboardへ**Connection Monitor Widget**を追加します。

Connection Monitorは一般的なInternet Speed TestやServer resource監視ではなく、**現在この画面を開いているBrowser／Deviceから、このRSS Reader自身までのHTTP応答状態**を確認するWidgetです。外部監視Serviceや任意URLへProbeせず、同一Originの軽量Endpointだけを利用します。

## Connection Monitor

- Add Widget → Information → Connection Monitorから追加。
- 現在のOnline／Offline／Probe ErrorとHTTP response latencyを表示。
- 30秒／60秒／5分のLatency historyをBrowser memoryで保持。
- 外部Graph libraryを追加せずinline SVGで履歴を描画。
- 選択期間のAvg／Max／Jitterを表示。
- Jitterは隣接する正常HTTP RTTの絶対差平均であり、ICMP Packet Jitterではありません。
- OfflineやBackground停止等の大きな空白をGraph／Jitter上で連結しません。

## Disconnect / recovery

単発のNetwork failureだけで即Offlineにはせず、2回連続で到達不能になった場合にOfflineを確定します。切断開始時刻は最初の失敗時刻を使用します。

- Last Disconnect
- Offline中に増加するDowntime
- 復旧後のLast Downtime
- 復旧直後のRecovered表示

HTTP 500等、RSS Readerへ到達した上でProbeが期待Responseにならなかった場合は、Network Offlineと区別してProbe Errorとして表示します。

## Connection quality

現在Latencyを次の目安で表示します。

- Excellent: 79ms以下
- Good: 80–149ms
- Fair: 150–299ms
- Slow: 300ms以上
- Offline: 2回連続で到達不能

直近5分の正常Sampleが5件以上ある場合は中央値をBaselineとして保持します。現在値がBaselineの2倍を超え、かつ50ms以上悪化した状態が2回連続した場合だけ「通常より遅い」を表示します。一時的な揺れで警告が頻発しないことを優先しています。

## Release candidate stability fixes

Git登録前の実機確認で、長時間開いたままのDashboardとSmartphone Calendarについて2点を追加修正しました。

- SessionのCSRF Token自体に独立した有効期限があるわけではありませんが、既定ではIdle Timeout 2時間／Absolute Timeout 12時間で認証Sessionが更新されます。Remember Meから自動復旧した場合は新しいCSRF TokenへRotateされるため、開いたままのPageが旧Tokenを送ると403になるCaseがありました。自動復旧時だけ旧Tokenを最大5分のGraceとして受け入れ、成功したAPI Responseの`X-CSRF-Token`からPage側を新Tokenへ同期します。Remember Meが無効で認証が本当に失効した場合はLogin画面へ戻ります。
- CalendarはSmartphone幅でも500pxのMinimum Widthを持っていたため、7列GridがCard幅を超えてPage全体の横幅へ影響するCaseがありました。575.98px以下ではCalendar GridをCard幅100%へ収め、狭いDesktop Cardで必要な横OverflowはCard内だけに閉じ込めます。

## Polling / load

Foregroundでは約5秒間隔でProbeします。前回Requestの完了後に次回を予約するため、遅延時もRequestを重ねません。

Connection Monitorを複数配置してもPage内では1本のProbe streamを共有します。Background tabでは定期Probeを停止し、表示へ戻った時点で即時Probeします。Refresh buttonを連打しても通信中は追加Requestを重ねません。

## Probe endpoint

`public/connection_probe.php`はConnection Monitor専用の軽量Endpointです。

- GETのみ
- 正常時HTTP 204
- Response body 0 byte
- Cache-Control: no-store
- Application bootstrapを読み込まない
- Sessionを開始しない
- DBへ接続しない
- 外部Networkへ接続しない
- User input／任意URLを受け付けない
- Client／Local IPを収集しない

## UI / responsive

PC／TabletではHeight 1をCompact表示、Height 2を詳細表示として整理しました。Height 1でも現在状態、品質、切断情報、Graph、Avg／Max／Jitterは残します。Height 2ではBaseline、経路、端末Online判定等も表示します。

Smartphoneでは既存Dashboard GridのHeight差が小さいため、Height 1でも詳細情報を省略しません。Bootstrap／BootswatchのTheme変数を利用し、Normal／Solar／Slate等でも状態Badge、Graph、補助情報の可読性を維持します。

## V1.18 scope decision

V1.18では次を追加しません。

- 任意Probe URL
- Google／Cloudflare／Public DNS等への固定外部Probe
- ICMP pingを行っているという表現
- Local／Client IP探索
- WebRTCによるAddress discovery
- 自動／手動Speed Test
- 外部Chart／Monitoring library
- DB／localStorageへのHistory保存

外部ProbeはBrowser→第三者Serviceを測る別機能になり、Speed Testは通信量、Server帯域、Mobile data、Rate limit等の別設計が必要になるためです。将来追加する場合もConnection MonitorのBackground pollingとは分離した明示的なManual featureとして検討します。

## Database / configuration

Version 1.18.0でDB Table／Column／Migrationの追加変更はありません。Connection Monitorの配置情報は既存`dashboard_widget`を利用します。

新しい必須Secret、API Key、`config/local.php`項目はありません。

Version 1.17.2からの更新はCode差し替えだけです。`config/local.php`、実DB、`var/`のRuntime dataを上書きせず、SQL／Migrationは実行しません。

Git登録前の最終実機確認でCalendarのMobile CSS修正が旧`immutable` Cacheに残るCaseを確認したため、Application Versionは`1.18.0`のまま、Asset Cache keyだけ`1.18.0-r2`へ更新しています。これにより`dashboard.css`／`dashboard.js`および動的読込Assetは新しいURLとして取得されます。

## Release artifacts

- `rss-reader-modernization-1.18.0.zip` — Server配置用Runtime成果物。
- `rss-reader-modernization-1.18.0.zip.sha256` — Runtime ZIPのSHA-256。
- `rss-reader-modernization-1.18.0-complete.zip` — Repository／Testsを含む完全Source成果物。
- `rss-reader-modernization-1.18.0-complete.zip.sha256` — 完全Source ZIPのSHA-256。

## Production confirmation

1. Backup後、Runtime ZIPを別Folderへ展開してCodeを更新する。
2. `config/local.php`、実DB、`var/`の生成Dataを上書きしない。
3. SQL／Migrationは実行しない。
4. Footerが`RSS Reader Modernization 1.18.0`になっていることを確認する。
5. Add Widget → Information → Connection Monitorが表示されることを確認する。
6. Connection Monitorを追加し、OnlineとLatencyが約5秒ごとに更新されることを確認する。
7. 30s／60s／5m、Avg／Max／Jitter、Quality表示を確認する。
8. DevTools Networkで`connection_probe.php`が同じRSS Reader OriginへHTTP 204で送られることを確認する。
9. DevTools Offline等で2回連続失敗後にOfflineとなり、Downtime／Last Disconnectが表示されることを確認する。
10. Networkを戻し、Recovered後にOnlineへ戻ることを確認する。
11. Connection Monitorを2個以上配置してもProbeがPage全体で約5秒に1回であることを確認する。
12. 別tabへ移動中はProbeが停止し、戻ると即時再開することを確認する。
13. PC Height 1／2、Smartphone、利用Themeで表示崩れがないことを確認する。
14. Connection Monitor起因でGoogle／Cloudflare／Fast.com等への定期通信やSpeed Test downloadが発生しないことを確認する。

## Verification limits

Release GateではRepository上のCurrent Regression、V1.17／V1.17.1／V1.17.2 compatibility tests、V1.18 focused tests、PHP／JavaScript syntax、Package builder／verifier、Secret scanを実行します。

自動Testは実際の利用者Network品質や全Browser／全Themeの見た目を完全には再現しません。Connection Monitorの実際のLatency値、Offline→Recovery、Background pause、複数Widget shared polling、Responsive／Theme表示はProduction／StagingのBrowserでも確認してください。

Connection MonitorはBrowserからRSS ReaderまでのHTTP応答時間を測るもので、ICMP ping、ISP全体の品質、Wi-Fi radio状態、Server CPU／NIC負荷、Internet throughputを直接測定するものではありません。
