# V1.7-H / R4 適用メモ

## 目的

Calendar Widgetへ日本の祝日表示を追加します。

- 内閣府の「国民の祝日」CSVを正本として使用
- Calendarの日付を祝日は赤表示
- 祝日名をTooltip／`aria-label`へ付与
- Calendar表示を外部通信で待たせない
- 60日を超えた場合だけ認証済みAPIからBackground更新
- 取得成功時だけ`var/cache/japanese_holidays.json`をAtomic置換
- 取得失敗／CSV破損時は既存Cacheを維持
- Cacheがない場合は同梱SnapshotへFallback

## Application

- Version: `1.7.0-dev.10`
- Label: `RSS Reader Modernization V1.7-H / R4`
- Baseline: `rss-reader-modernization-v1-7-h-r3.zip`
- DB Table／Column追加: なし
- Migration: なし
- API Route追加: `calendar.holiday.refresh`
- 外部Library追加: なし

## config/local.php

既存の`config/local.php`へ次を追加してください。省略した場合も同じDefault値で動作しますが、将来URLが変更されたときに設定だけで切り替えられるよう、明示しておくことを推奨します。

```php
'APP_HOLIDAY_CSV_URL' => 'https://www8.cao.go.jp/chosei/shukujitsu/syukujitsu.csv',
'APP_HOLIDAY_CACHE_DAYS' => '60',
'APP_HOLIDAY_TIMEOUT_MS' => '5000',
```

`APP_HOLIDAY_CSV_URL`はHTTPS URLのみ受け付けます。外部通信時は既存の公開IP判定を再利用し、Private／Loopback Addressを拒否します。Redirect先も毎Hop検証し、HTTPSからHTTPへのDowngradeを拒否します。

## Runtime Cache

```text
var/cache/japanese_holidays.json
var/cache/japanese_holidays.lock
```

両FileはRuntime生成物です。Git／配布ZIPには含めません。

`var/cache/`へPHP Processが書き込めることを確認してください。書込み出来ない場合でも同梱Snapshotによる祝日表示は継続しますが、自動更新Cacheは保存出来ません。

## Snapshot

```text
app/data/japanese_holidays_snapshot.json
```

R4には内閣府公開情報から2026年・2027年の祝日／休日をSnapshotとして同梱します。初回の外部通信に失敗しても、この期間は祝日表示を継続出来ます。

## 更新Flow

```text
calendar.month.list
    ↓
既存Cacheがあれば即使用
    ↓
CacheがなければSnapshotを即使用
    ↓
Calendarを描画
    ↓
Cacheが60日超過／未作成ならBackground refreshを1回要求
    ↓
取得・CSV検証に成功 → CacheをAtomic置換 → Calendarを1回再読込
取得失敗／CSV異常       → 現在表示を維持、既存Cacheを消さない
```

Calendarを開くたびに外部通信する構成ではありません。

## DB

R4ではSQLを実行しません。V1.7-H/R3まで適用済みの環境では、Migration 008も再実行しないでください。
