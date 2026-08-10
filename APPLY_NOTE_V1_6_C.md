# V1.6-C 適用手順

## 対象

- Baseline: `rss-reader-modernization-v1-6-b-r1.zip`
- Baseline SHA-256: `f2fcb03fa0fc8749c258bdc97d7cf331852ee93ad4f83a2b8ec474443ca67cda`
- Checkpoint: V1.6-C / R1
- Application Version: `1.6.0-dev.2`
- Application Label: `RSS Reader Modernization V1.6-C / R1`

## 変更内容

既存Game Widgetへ5×5 Lights Outを追加します。

- Game追加／変更画面でLights Outを選択
- Tap／Clickで押したマスと上下左右を反転
- Moves、Reset、新しい問題、Clear
- 全消灯から有効操作を適用して解ける問題を生成
- Icon Questとは別Runtimeとして動作
- DB、API、Migration、SQL、Config変更なし

## Cache Busting

- `mini-game.css?v=1.6-c-r1`
- `mini-game.js?v=1.6-c-r1`
- `lights-out.js?v=1.6-c-r1`
- `dashboard.js?v=1.6-c-r1`

## 配置

1. 現行Application、`config/local.php`、Server固有`.htaccess`、実DB、`var/`をBackupします。
2. ZIPを別Folderへ展開します。
3. `UPDATED_FILES_V1_6_C.md`のApplicationファイルを配置します。
4. `config/local.php`、実DB、`var/`、Server固有`.htaccess`は上書きしません。
5. SQLやMigrationは実行しません。
6. BrowserをHard Reloadします。
7. Game追加からLights Outを選び、盤面、Reset、新しい問題、Clearを確認します。
8. Icon Quest、Timer、Swipe、Widget Dragも従来どおり動くことを確認します。

## Rollback

V1.6-B / R1のApplicationファイルへ戻してください。DB変更とStorage Schema追加はないためRollback SQLは不要です。
