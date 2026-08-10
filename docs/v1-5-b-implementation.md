# V1.5-B Clock Timer実装

## 目的

既存Clock Widgetへ、RSS閲覧を妨げない小型のカウントダウンTimerを追加します。Timerを別Widget種類にはせず、Clockの追加・削除・配置・並べ替え・見出し設定をそのまま利用します。

## UI

Clock Widget本文の先頭に「時計」「タイマー」の切替Buttonを配置します。Timerには次を表示します。

- `HH:MM:SS`形式の残り時間
- 1／3／5／10／25分Preset
- 1～1440分の任意入力
- 開始／一時停止／再開／Reset
- 状態Message

Buttonと入力欄は44px以上の操作領域を維持し、Timer本文ではDashboardの左右Swipeを開始しません。

## 時間計算

毎秒単純に数値を減算する方式ではなく、開始・再開時に終了予定日時`endAt`を保存します。

```text
remainingSeconds = ceil((endAt - Date.now()) / 1000)
```

これにより、Tabが非表示になった場合やReload後も、現在時刻との差から残り時間を復元できます。複数Timerが動作しても更新Intervalは1本です。Storageへの書込みは開始・停止・設定変更・Reset・完了時だけで、毎Tickは行いません。

## Storage

```text
rssReader.clockTimer.v1.user.{userId}.widget.{widgetId}
```

保存形式：

```json
{
  "schema": 1,
  "view": "timer",
  "status": "running",
  "durationSeconds": 1500,
  "remainingSeconds": 1500,
  "endAt": 1785970800000,
  "savedAt": 1785969300000
}
```

値はJSON Parse後にSchema、状態、時間範囲、Timestampを検証します。Storage値は`innerHTML`へ挿入しません。

保存先は次の順でFallbackします。

```text
localStorage → sessionStorage → Memory
```

V1.5-Bでは基本的な保存・復元とFallbackを実装します。複数Browser Tabの同期や複数Storage Copyの高度なRecoveryはV1.5-C対象です。

## Clock削除

既存のOwner限定Clock削除APIが成功した後に限り、対象Widget IDのTimer Storageを削除します。API失敗時にはStorageを削除しません。

## Accessibility

- Native Button／Number Input
- Clock／Timer切替に`aria-pressed`
- 状態Messageに`aria-live="polite"`と`aria-atomic="true"`
- Countdown表示自体はLive Regionにしない
- 同一Messageを毎Tick再設定しない
- Focus Trapなし
- `prefers-reduced-motion`対応

## 音

V1.5-BではAudio、Browser Notification、自動再生を追加しません。Timer終了は`00:00:00`と文字Messageで通知します。

## DB

Table、Column、Migration、SQLは追加しません。Clockの種類・配置・見出し設定は既存`dashboard_widget`を利用し、Timerの実行状態はBrowser Storageだけに保存します。
