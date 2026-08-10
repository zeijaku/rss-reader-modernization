# V1.6-B 適用手順

## 対象

- Baseline: `rss-reader-modernization-1.5.0-complete.zip`
- Baseline SHA-256: `3155ed47c86d5c3f9acf65667088109841d6c42392662fd0e7c565eb18208c70`
- Checkpoint: V1.6-B / R1
- Application Version: `1.6.0-dev.1`
- Application Label: `RSS Reader Modernization V1.6-B / R1`

## 変更内容

スマートフォンでDashboard Tabを左右Swipeした際、移動方向を画面端の矢印で短く表示します。

- 左Swipeで次のTabへ進む場合、右端に左向き矢印
- 右Swipeで前のTabへ戻る場合、左端に右向き矢印
- Swipe距離に応じて表示を強くする
- 成立時は短く強調し、160ms後に従来のTab URLへ移動
- 不成立、縦Scroll、Touch cancelでは静かに消去
- `pointer-events: none`
- 767.98px以下だけで表示
- 左右24pxの画面端除外を維持
- Link、Button、Form、Timer、Game、Calendar、Drag、横Scroll領域の除外を維持
- Reduced Motionでは横方向Animationを抑止

## Cache Busting

変更したAssetだけを更新しています。

- `dashboard.css?v=1.6-b-r1`
- `dashboard.js?v=1.6-b-r1`

一元的なAsset Version管理は追加していません。

## 配置

1. 現在のApplication、`config/local.php`、Server固有`.htaccess`、実DB、`var/`をBackupします。
2. ZIPを別Folderへ展開します。
3. `UPDATED_FILES_V1_6_B.md`を確認します。
4. Applicationファイルをサーバーへ上書きします。
5. SQLやMigrationは実行しません。
6. `config/local.php`、実DB、`var/`のRuntime Dataは上書きしません。
7. Browserを再読み込みします。古い表示が残る場合はHard Reloadします。
8. 実機Smartphoneで左右Swipe、縦Scroll、Timer、Icon Quest、Widget Dragを確認します。

## Rollback

V1.5.0のApplicationファイルへ戻してください。
DB、API、Storage Schemaは変更していないため、Rollback用SQLやData変換はありません。
