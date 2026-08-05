# RSS Reader Modernization 1.2.0 Release Notes

## Overview

Version 1.2.0は、Version 1.1.0のDashboard機能とDB構造を維持したまま、認証画面、記事表示、Search Feed、記事Actionsを改善したReleaseです。

既存のPHP / PDO / MySQL構成、4タブ、Feed CRUD、Stock、Memo、Task、Calendar、Account Settings、公開APIを残し、新しいFramework、外部SNS API、Build環境は追加していません。

## Version 1.2 main changes

### Authentication / notice / common error

- Login／Registrationを専用Layoutへ更新。
- 中立名Honeypot、二重送信防止、Password表示切替を追加。
- LogoutとSession expiryを1回限りの通知として区別。
- 403／404／500／503の共通Error画面を追加。

### Feed article display

- 記事Titleを内容に応じて最大2行表示。
- 実際に省略されたTitleだけHover／Keyboard Focusで全文表示。
- Feed内の`content`または`description`をPlain Textの概要として開閉。
- Feed Card単位の個別更新を追加し、既存Cache、ETag、Last-Modified、Retry、Backoffを再利用。

### Search Feed

- 検索語句を保存するSearch Feed Widgetを追加。
- 通常RSSと共通の記事描画・記事Actions処理を利用。
- 1段見出し、固定白Title、概要開閉、個別更新、通知の自動消去へ対応。

### Article Actions

- 記事左端のBookmarkを三点リーダーへ変更。
- 既存処理を再利用したStock保存。
- Clipboard APIとFallbackによる記事URL Copy。
- 外部APIを使わないX投稿画面。
- 記事Titleのみを既存Task Widgetへ追加。
- 外側Click、Esc、Scroll、Resize、記事再描画時のMenu Closeに対応。

### UI adjustments

- 三点リーダーの占有幅を抑え、記事Title領域を拡張。
- 概要「＋」の44px操作領域と位置を維持しながら、文字との余白を調整。
- 新着BellをTitle左上へ配置し、2行目の表示幅を回復。

## Database and configuration

Version 1.2によるDB構造変更はありません。

- Table追加: なし
- Column追加: なし
- Migration: なし
- SQL実行: 不要
- 必須設定追加: なし
- `config/local.php`変更: なし

Version 1.0系から直接更新する場合は、Version 1.1で追加されたMigration 002～006が必要です。Version 1.1.0適用済み環境からVersion 1.2.0へ更新する場合、追加Migrationはありません。

## Distribution files

- `rss-reader-modernization-1.2.0-complete.zip` — GitHub作業Folder相当。Source、Tests、Documentation、GitHub metadataを含む完全統合ZIP。
- `rss-reader-modernization-1.2.0.zip` — Server配置用Runtime ZIP。TestsとGitHub metadataを除外。
- 各ZIPの`.zip.sha256` — ZIP全体のSHA-256。
- ZIP内部Manifest — 各FileのSHA-256。

## Update notes

更新前にCode、`config/local.php`、実DB、Runtime DataをBackupしてください。ZIPは別Folderへ展開し、`config/local.php`、実DB、`var/`の生成Dataを上書きしないでください。

Version 1.1.0適用済み環境ではSQLを実行せず、Codeを更新してBrowser Cacheを更新します。Login、通常RSS、Search Feed、概要開閉、個別更新、新着Bell、記事Actions、Stock、URL Copy、X、Task追加を確認してください。

詳細は[`docs/update.md`](docs/update.md)、[`docs/installation.md`](docs/installation.md)、[`docs/deployment-checklist.md`](docs/deployment-checklist.md)を参照してください。

## Verification limits

自動TestではPHP / JavaScript / Python / Shell構文、Security境界、Authentication、Session、CSRF、RSS / Atom、Cache、Widget CRUD、Search Feed、記事概要、個別更新、記事Actions、Stock、Task、新着Bell、Responsive、Accessibility、Schema、Migration構造、Secret Pattern、ZIP CRC / Path Traversal、Manifest、Documentation Link、Version表記を確認しています。

この実行環境に実MySQL Serverまたは利用可能な`pdo_mysql`接続先がない場合、実DBへの接続、Hosting固有設定、実Feed到達性、実Mail配送、BackupからのRestoreは利用者環境での最終確認が必要です。Browser項目は同梱Testから利用可能な範囲で確認します。

## License

Project本体は`LICENSE`、外部Assetは`THIRD_PARTY_NOTICES.md`と`licenses/`を参照してください。
