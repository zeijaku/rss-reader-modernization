# V1.4-B / R1 適用メモ

- Baseline: `rss-reader-modernization-1.3.0-complete.zip`
- Checkpoint: `RSS Reader Modernization V1.4-B / R1`
- Application Version: `1.4.0-dev.1`
- 対象: Mini Game Widget基盤、5×5 Mock盤面、Browser Storage Wrapper

## 配置

ZIPを展開し、既存環境へApplication変更ファイルを上書きしてください。

- `app/mini_game.php`は新規ファイルです。
- `public/css/mini-game.css`は新規ファイルです。
- `public/js/mini-game.js`は新規ファイルです。
- Browser Cacheの影響を避けるため、配置後はHard Reloadしてください。

## DB／設定

- DB変更: なし
- Table追加: なし
- Column追加: なし
- Migration: なし
- SQL実行: なし
- `config/local.php`変更: なし
- `.htaccess`変更: なし

Game Widgetの種類、配置、横幅、見出し色、見出しは既存`dashboard_widget` Tableへ保存します。盤面状態はServerへ送信せず、Browser Storageだけを利用します。

## V1.4-Bでまだ動かないもの

盤面はMock表示です。Player移動、Enemy移動、Treasure取得、Goal、勝敗、Score、途中盤面の本保存はV1.4-Cで実装します。盤面を押しても移動しない状態がV1.4-Bの正常動作です。
