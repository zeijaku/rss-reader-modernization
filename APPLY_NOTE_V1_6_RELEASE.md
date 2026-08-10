# Version 1.6.0 適用手順

## 対象

- Baseline: `rss-reader-modernization-v1-6-d-r1.zip`
- Baseline SHA-256: `a02fbc451f1e64984d55d7ff89b7d5d6142a0e3c257e63805db889b392f91de5`
- Application Version: `1.6.0`
- Application Label: `RSS Reader Modernization 1.6.0`
- Runtime ZIP: `rss-reader-modernization-1.6.0.zip`

## Version 1.6の内容

- Smartphone Tab Swipe方向Indicator
- 5×5 Lights Out
- Moves、Reset、新しい問題、Clear
- 状態保存・復元、Storage Fallback／Recovery
- User／Widget単位の状態分離
- Widget削除／Game種類変更時のStorage整理
- Keyboard、Focus、Screen Reader Label、Theme、Reduced Motion
- DB、API Route、Migration、SQL、必須設定、外部Library変更なし

## 配置

1. 現行Application、`config/local.php`、Server固有`.htaccess`、実DB、`var/`をBackupします。
2. Runtime ZIPのSHA-256と内部Manifestを確認します。
3. ZIPを本番Folderとは別のFolderへ展開します。
4. `config/local.php`、実DB、`var/`、Server固有`.htaccess`を上書きせずCodeを更新します。
5. SQL、Migration、`schema.sql`は実行しません。
6. BrowserをHard Reloadします。
7. Footerが`RSS Reader Modernization 1.6.0`であることを確認します。
8. Swipe Indicator、Lights Out、Icon Quest、Clock Timer、Widget Dragを確認します。

## Rollback

ApplicationをVersion 1.5.0のBackupへ戻します。DB変更はないためRollback SQLは不要です。Version 1.6で作成されたLights Out StorageはVersion 1.5 Runtimeでは参照されません。
