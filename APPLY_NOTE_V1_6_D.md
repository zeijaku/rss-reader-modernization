# V1.6-D 適用手順

## 対象

- Baseline: `rss-reader-modernization-v1-6-c-r1.zip`
- Baseline SHA-256: `b87d0bc068d8e2ca315362245c012529504c7f871b1fc1eb0da7f92e0e1a1490`
- Checkpoint: V1.6-D / R1
- Application Version: `1.6.0-dev.3`
- Application Label: `RSS Reader Modernization V1.6-D / R1`

## 変更内容

- Lights Outの盤面、初期盤面、Moves、Clear状態をBrowser Storageへ保存
- localStorage → sessionStorage → memory Fallback
- 壊れたStorage Copyの除去と安全な復旧
- User／Widget単位のStorage Key分離
- Game Widget削除／種類変更時の旧Storage Cleanup
- Arrow Key、Home、End、Roving tabindex
- Focus、Dark Theme、Reduced Motion調整
- DB、API Route、Migration、SQL、Config変更なし

## Cache Busting

- `mini-game.css?v=1.6-d-r1`
- `mini-game.js?v=1.6-d-r1`
- `lights-out.js?v=1.6-d-r1`
- `dashboard.js?v=1.6-d-r1`

## 配置

1. 現行Application、`config/local.php`、Server固有`.htaccess`、実DB、`var/`をBackupします。
2. ZIPを別Folderへ展開します。
3. `UPDATED_FILES_V1_6_D.md`のApplicationファイルを配置します。
4. `config/local.php`、実DB、`var/`、Server固有`.htaccess`は上書きしません。
5. SQLやMigrationは実行しません。
6. BrowserをHard Reloadします。
7. Lights Outで数手進め、Reload後に盤面とMovesが復元されることを確認します。
8. Reset、新しい問題、Clear復元、Keyboard操作を確認します。
9. Icon Quest、Timer、Swipe、Widget Dragも従来どおり動くことを確認します。

## Rollback

V1.6-C / R1のApplicationファイルへ戻してください。DB変更はないためRollback SQLは不要です。V1.6-Dで作成されたLights Out Storageは、旧Runtimeでは参照されません。
