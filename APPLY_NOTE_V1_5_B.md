# V1.5-B 適用手順

## 対象

- Baseline: RSS Reader Modernization 1.4.0
- Checkpoint: V1.5-B / R1
- Application Version: `1.5.0-dev.1`

## 変更内容

既存Clock WidgetへカウントダウンTimerを追加します。

- 時計／タイマー表示切替
- 1／3／5／10／25分Preset
- 1～1440分の任意設定
- 開始、一時停止、再開、Reset
- `endAt`を使った再読込後の復元
- User ID／Widget IDごとのBrowser Storage分離
- `localStorage`、`sessionStorage`、MemoryのFallback
- Clock Widget削除成功後のTimer状態削除
- Keyboard操作、44px操作領域、ARIA Live Region
- 終了音・Browser通知なし

## 配置

1. 現在のApplication、`config/local.php`、実DB、`var/`をBackupします。
2. ZIPを展開します。
3. Applicationファイルをサーバーへ上書きします。
4. 新規ファイルが配置されていることを確認します。

```text
public/js/clock-timer.js
public/css/clock-timer.css
```

5. SQLやMigrationは実行しません。
6. `config/local.php`と実DBは変更しません。
7. BrowserでHard Reloadします。

## Rollback

V1.4.0のApplicationファイルへ戻してください。
Browserに残ったTimer状態はApplicationから参照されなくなります。必要な場合はBrowser Storageから`rssReader.clockTimer.v1`で始まるKeyを削除します。
