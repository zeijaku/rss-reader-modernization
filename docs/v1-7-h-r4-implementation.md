# V1.7-H / R4 Calendar Japanese holiday support

## Scope

Calendar Widgetへ日本の国民の祝日／休日表示を追加する。R3のWidget Grid、RSS件数、Clock／Game高さ互換には変更を加えない。

## Source and configuration

Default source:

```text
https://www8.cao.go.jp/chosei/shukujitsu/syukujitsu.csv
```

Runtime configuration:

```text
APP_HOLIDAY_CSV_URL
APP_HOLIDAY_CACHE_DAYS=60
APP_HOLIDAY_TIMEOUT_MS=5000
```

Environment variable > `config/local.php` > safe defaultの既存優先順位を使用する。URL変更時はApplication Codeを変更せず設定値だけを切り替えられる。

## Display flow

`calendar.month.list`は祝日Mapと`holiday_refresh_due`を返す。Calendar JavaScriptは月表示を先に描画し、祝日は`.calendar-day-holiday`を付ける。

- 日付数字: 赤
- `title`: 祝日名
- `aria-label`: ISO日付＋祝日名＋予定追加Action

日曜日／土曜日の既存色は維持し、祝日Classを後段で優先する。

## Background refresh

Calendar描画後、Cacheが60日を超えている場合だけ`calendar.holiday.refresh`を1 Pageにつき1回要求する。更新成功後は表示中Calendarを1回だけ再取得する。

更新失敗はCalendar表示のErrorにしない。次回Page Loadで再試行出来る。

## Cache and fallback

Runtime Cache:

```text
var/cache/japanese_holidays.json
```

Lock:

```text
var/cache/japanese_holidays.lock
```

CSVはUTF-8／Shift_JIS系を正規化し、日付・名称・件数・最大年を検証する。検証済みJSONをTemporary Fileへ書き、`rename`で置換する。取得失敗やCSV破損では既存Cacheを変更しない。

Cacheが存在しない場合は`app/data/japanese_holidays_snapshot.json`を使用する。R4 Snapshotは内閣府公開情報に基づく2026年／2027年分を含む。

## Outbound security

- Configured URLはHTTPSのみ
- Userinfo／Fragment禁止
- DNS Resolve後のPrivate／Loopback Address拒否
- IP pinningを利用
- Redirect先を毎Hop再検証
- HTTPS→HTTP Downgrade拒否
- Redirect最大3回
- Timeout 5秒
- CSV最大512 KiB
- 外部API Keyなし

## DB／Migration

DB変更なし。祝日はDBへ保存しない。Migration 008再実行も不要。
