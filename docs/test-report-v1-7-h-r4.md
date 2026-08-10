# V1.7-H / R4 Test Report

## Result

Secure BaselineからV1.7-H/R4まで、`tests/run.sh`の順序を維持して確認した。
単一実行は600秒の実行上限でM2-F途中に達したため、未実行区間を重複しない範囲へ分割して継続した。
M2-F Browser SmokeのみChromium Runtime依存不足のためSKIPとなった。

```text
PASS  6389
FAIL     0
SKIP    15
```

SKIPは以下の環境・履歴Gateによる。

- SimpleXML／mbstringを必要とする一部Live Parser Matrix
- Chromium Runtime依存不足のBrowser Smoke
- M2-G／M4-A..G Version 1.0正式版専用Gate
- V1.1／1.2／1.3／1.4／1.5／1.6正式版専用Release Gate

V1.7-H/R4専用のHoliday TestにはSKIPはない。

## Holiday focused tests

確認項目:

- 2026／2027 Snapshot
- CSV Parse／日付正規化
- 小さすぎる／破損CSV拒否
- Cache初回生成
- 60日Fresh判定
- Fresh時の外部通信抑制
- 更新失敗時の既存Cache維持
- HTTPS限定
- HTTPS→HTTP Redirect拒否
- Redirect毎のSSRF再検証構造
- Atomic Cache置換
- Calendar APIの祝日Map
- 祝日赤表示／Tooltip／aria-label
- Background refresh 1回制御
- 更新成功後のCalendar 1回再読込
- RSS HTTP取得回帰
- Calendar Event／Task期限回帰

R4専用結果:

```text
Holiday backend     PASS 17 / FAIL 0 / SKIP 0
Architecture        PASS 20 / FAIL 0 / SKIP 0
Holiday Browser     PASS  3 / FAIL 0 / SKIP 0
```

## Syntax

```text
PHP         114 files PASS
JavaScript    7 files PASS
```

JavaScriptは`public/js/`の非Minify Application Scriptを`node --check`で確認した。

## Database boundary

V1.7-H/R3との`database/`比較結果は差分0件。
R4によるTable／Column／Migration追加はない。

## Test stability correction

長時間回帰中、旧`test_v11d_dashboard_render.py`がHTML全体に単なる文字列`999`が含まれないことを確認していたため、RandomなCSRF Token等に偶然`999`が含まれると別Owner Widgetと誤判定する可能性を確認した。

機能期待値は変えず、以下の実際のWidget識別子／Fixture URLが存在しないことを確認する判定へ変更した。

- `data-dashboard-widget-id="99"`
- `feed999.xml`

Clean状態でV1.1-D Dashboard FixtureとM2-C Dashboard Renderを再実行しPASSを確認した。

## External CSV note

Build環境から内閣府CSV本体へのLive Downloadは環境制約により成功確認していない。
したがってLive取得成功をTest済みとは扱わない。

代わりに以下を確認した。

- 内閣府公開ページの2026／2027祝日一覧を基にSnapshotを作成
- 公式CSV形式を模したFixtureによるParser／Cache更新
- HTTPS／Redirect／SSRF／Timeout／Size Limitの実装境界
- 取得成功／失敗を差し替え可能なTransportでの更新動作
- Browser側Background refresh制御

実サーバー配置後は、`var/cache/japanese_holidays.json`が生成されることを最終確認する。
