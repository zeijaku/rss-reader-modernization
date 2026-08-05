# RSS Reader Modernization 1.3.0 Release Notes

## Overview

Version 1.3.0は、Version 1.2.0の認証、Feed、Search Feed、記事Actions、Widget機能を維持したまま、Header、Drawer、Widget見出し、記事操作部分の共通UIを整理したReleaseです。

新しい大規模機能、Framework、外部Library、Build環境、DB構造、RSS解析Engineは追加していません。既存の雰囲気を残しながら、PCとSmartphoneで導線、余白、Focus、操作領域を揃えています。

## Version 1.3 main changes

### Drawer organization

- Menuを「表示／Widget追加／カスタマイズ／リンク／Account」の5区分へ整理。
- 現在TabまたはStockへ`aria-current="page"`と選択中表示を追加。
- Link、Modal Button、LogoutのIcon列、文字位置、Row高、Hover、Focusを共通化。
- PCでは外部LinkをHeader、991px以下ではDrawerへ表示し、重複導線を解消。
- Account SettingsとLogoutをAccount区分へまとめ、表示設定をカスタマイズへ分離。
- Esc、外側Click、Tab循環、Focus復帰は既存JavaScriptを変更せず維持。

### Header organization

- HeaderをBrand、現在地、設定済み外部Link、Menuの役割へ分離。
- Brand Linkから現在Tab名を切り離し、長い現在地を一行Ellipsisで表示。
- Header高を56pxへ統一し、Menu ButtonはPC／Smartphoneとも44pxを維持。
- Dark／Primary／LightのNavbar Schemeを整理し、Primary背景でも文字とIconのContrastを確保。
- 8 Theme、3 Navbar色、360／420／1024pxの組合せを確認。

### Common spacing and widget titles

- Bootstrap Table Cell既定余白より強いSelectorを使用し、記事Titleの意図した余白を実表示へ反映。
- 三点リーダー列の余分なCell Paddingを除去。
- PCでは36pxのコンパクト幅、Touch端末では44pxの操作幅を確保。
- 通常RSS、Search Feed、Clock、Memo、Task、Calendarの見出し開始位置、文字Size、Ellipsisを統一。
- 長いTitle、新着Bell、概要Button、記事Actionsの表示と操作領域を維持。

## Database and configuration

Version 1.3によるDB構造変更はありません。

- Table追加: なし
- Column追加: なし
- Migration: なし
- SQL実行: 不要
- API変更: なし
- 必須設定追加: なし
- `config/local.php`変更: なし
- Feed Cache削除: 不要

Version 1.2.0適用済み環境からVersion 1.3.0へ更新する場合、Codeを更新してBrowser CacheをHard Reloadします。Version 1.0系から直接更新する場合だけ、Version 1.1で追加されたMigration 002～006が必要です。

## Distribution files

- `rss-reader-modernization-1.3.0-complete.zip` — GitHub作業Folder相当。Source、Tests、Documentation、GitHub metadataを含む完全統合ZIP。
- `rss-reader-modernization-1.3.0.zip` — Server配置用Runtime ZIP。TestsとGitHub metadataを除外。
- 各ZIPの`.zip.sha256` — ZIP全体のSHA-256。
- ZIP内部Manifest — 各FileのSHA-256。

## Update notes

更新前にCode、`config/local.php`、実DB、Runtime DataをBackupしてください。ZIPは別Folderへ展開し、`config/local.php`、実DB、`var/`の生成Data、Server固有`.htaccess`を不用意に上書きしないでください。

配置後はBrowserをHard Reloadし、Header、Drawer、現在地、外部Link、Account Settings、Logout、通常RSS、Search Feed、記事Actions、各WidgetをPC／Smartphoneで確認してください。

詳細は[`docs/update.md`](docs/update.md)、[`docs/installation.md`](docs/installation.md)、[`docs/deployment-checklist.md`](docs/deployment-checklist.md)を参照してください。

## Verification limits

自動TestではPHP／JavaScript／Python／Shell構文、Security境界、Authentication、Session、CSRF、RSS／Atom、Cache、Widget CRUD、Search Feed、記事概要、個別更新、記事Actions、Stock、Task、新着Bell、Header、Drawer、Responsive、Accessibility、Schema、Migration構造、Secret Pattern、ZIP CRC／Path Traversal、Manifest、Documentation Link、Version表記を確認しています。

この実行環境に実MySQL Serverまたは利用可能な`pdo_mysql`接続先がない場合、実DB接続、Hosting固有設定、実Feed到達性、実Mail配送、BackupからのRestoreは利用者環境での最終確認が必要です。Browser項目は同梱Testから利用可能な範囲で確認します。

## License

Project本体は`LICENSE`、外部Assetは`THIRD_PARTY_NOTICES.md`と`licenses/`を参照してください。
