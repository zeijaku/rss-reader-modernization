# V1.5-C 適用手順

## 対象

- Baseline: RSS Reader Modernization V1.5-B / R1
- Checkpoint: V1.5-C / R1
- Application Version: `1.5.0-dev.2`

## 変更内容

Clock Timerの操作性・保存Recovery・複数Tab同期を仕上げます。

- local／session／Memoryの正常な最新Copyを優先
- 壊れたStorage Copyだけを除去
- すべて異常な場合の安全な初期化
- 複数Browser Tab間のTimer状態同期
- Focus／Page表示復帰／Background復帰時の即時再計算
- Key Repeatと短時間の同一操作連打を抑止
- Timer終了時の短い視覚強調
- 全8 Theme、360／420／1024px調整
- Reduced Motion、Focus、Contrast調整
- 終了音・Browser通知なし

## 配置

1. 現在のApplication、`config/local.php`、実DB、`var/`をBackupします。
2. ZIPを展開します。
3. Applicationファイルをサーバーへ上書きします。
4. SQLやMigrationは実行しません。
5. `config/local.php`と実DBは変更しません。
6. BrowserでHard Reloadします。
7. 2つのBrowser Tabで同じTimerを開き、開始／一時停止／Resetが同期することを確認します。

## Rollback

V1.5-B / R1のApplicationファイルへ戻してください。
V1.5-CはStorage Schemaを変更していないため、V1.5-Bで保存した正常なTimer状態はそのまま利用出来ます。
