# RSS Reader Modernization 1.4.0 Release Notes

## Overview

Version 1.4.0は、RSS Reader内で短時間遊べるMini Game Widgetを追加するReleaseです。第一ゲームとして、Font Awesomeを使った5×5 Icon戦略Game「Icon Quest」を実装しました。

既存のDashboard Widget登録・削除・並べ替え・Tab配置・Header・Themeを再利用し、ゲームの盤面、進行、Best手数、勝敗数はBrowser Storageへ保存します。Server側へScoreや進行状態を送信せず、DB Table／Column、Migration、SQL、外部API、外部Library、Framework、Build環境は追加していません。

## Icon Quest

- 5×5固定盤面、固定4 Level。
- Player、Enemy、Treasure、Goal、Wallを既存Font Awesomeで表示。
- Treasure取得後にGoalへ到達するとClear。
- Enemy接触または20手到達でGame Over。
- EnemyはPlayerの有効移動2回ごとに、固定順の最短経路で1マス移動。
- 矢印Key、WASD、隣接マスTap／Click、上下左右Buttonへ対応。
- Level、Moves、Best、勝利数、敗北数、途中状態を表示・保存。
- New Game、Reset、Widget単位の記録削除へ対応。

## Widget and storage integration

- Drawerの「Widget追加」からGame Widgetを追加。
- 既存4 Tabへの配置、並べ替え、横幅、Header色、Title変更、削除へ対応。
- 同一Userが複数Game Widgetを置いても状態をWidget IDごとに分離。
- Storage KeyへGame Version、User ID、Widget IDを含める。
- `localStorage`、`sessionStorage`、Memoryの順にFallback。
- JSON Parse、Schema、Game Version、Level ID、位置、数値範囲、保存状態を検証。
- 複数Storage Copyがある場合は正常な最新Copyを採用し、壊れたCopyだけを削除。
- Widget削除成功時は、そのWidgetのBrowser Storageも削除。
- Logout時はUser IDで分離した記録を保持。

## Accessibility and responsive behavior

- 盤面へGrid／Grid CellのARIA属性を設定。
- Font Awesomeだけに意味を依存せず、各Cellへ文字Labelを提供。
- Player位置へFocusを追従し、Tabで盤面外へ移動可能。
- 重要な変化だけをLive Regionで通知。
- 44pxの操作領域、Esc、Focus表示、Keyboard操作へ対応。
- `prefers-reduced-motion`時はTransitionを抑制。
- DashboardのTab SwipeとGame操作の競合を回避。
- 360／420／1024pxと全8 Themeを確認。
- Solar／SlateではGame面をDark Surfaceへ調整。
- V1.4-D / R2でGame Headerの左余白を既存Widgetと統一。

## Database and configuration

Version 1.4による新しいDB構造はありません。

- Table追加: なし
- Column追加: なし
- Migration: なし
- SQL実行: 不要
- 必須設定追加: なし
- `config/local.php`変更: なし
- 外部API／Library: なし

Game Widgetの種類、配置、並び順、横幅、Header色、Title、Game種類は既存`dashboard_widget` Tableへ保存します。盤面、Score、勝敗、Tutorial状態はDBへ保存しません。

Version 1.3.0からVersion 1.4.0への更新は、Codeを更新してBrowserをHard Reloadします。Browser StorageはUser IDとWidget IDで分離され、V1.4-C／Dの正常な途中状態を引き継げます。

## Distribution files

- `rss-reader-modernization-1.4.0-complete.zip` — Source、Tests、Documentation、GitHub metadataを含む完全統合ZIP。
- `rss-reader-modernization-1.4.0.zip` — Server配置用Runtime ZIP。TestsとGitHub metadataを除外。
- 各ZIPの`.zip.sha256` — ZIP全体のSHA-256。
- ZIP内部Manifest — 各FileのSHA-256。

## Update notes

更新前にCode、`config/local.php`、Server固有`.htaccess`、実DB、`var/`をBackupしてください。ZIPは別Folderへ展開し、Private設定、実DB、Runtime Dataを不用意に上書きしないでください。SQLやMigrationは実行しません。

配置後はBrowserをHard Reloadし、通常RSS、Search Feed、記事Actions、Stock、Memo、Task、Clock、Calendar、Account Settingsに加え、Game Widgetの追加、操作、保存、復元、記録削除、並べ替え、全Themeを確認してください。

詳細は[`docs/update.md`](docs/update.md)、[`docs/installation.md`](docs/installation.md)、[`docs/deployment-checklist.md`](docs/deployment-checklist.md)を参照してください。

## Verification limits

自動TestではPHP／JavaScript／Python／Shell構文、Security境界、Authentication、Session、CSRF、RSS／Atom、Cache、Widget CRUD、Search Feed、記事Actions、既存Widget、Icon Quest、Browser Storage、Keyboard、Touch、Responsive、Accessibility、全8 Theme、Schema、Secret Pattern、ZIP CRC／Path、Manifest、Documentation Link、Version表記を確認します。

この実行環境に実MySQL Serverまたは利用可能な`pdo_mysql`接続先がない場合、実DB接続、Hosting固有設定、実Feed到達性、実Mail配送、BackupからのRestoreは利用者環境での最終確認が必要です。Browser StorageのPrivate Mode挙動はFallback Testで確認していますが、実端末固有の制限は配置環境で確認してください。

## License

Project本体は`LICENSE`、外部Assetは`THIRD_PARTY_NOTICES.md`と`licenses/`を参照してください。
