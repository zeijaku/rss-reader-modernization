# RSS Reader Modernization 1.1.0 Release Notes

## Overview

Version 1.1.0は、Version 1.0.0のSecurity・RSS Engine・Frontend基盤を維持したまま、Dashboardを日常利用向けに拡張したReleaseです。

新機能を別Frameworkへ置き換えるのではなく、既存のPHP / PDO / MySQL構成、4タブ、Feed CRUD、Stock、Settings、公開APIの使い方を残しながら追加しています。

## Version 1.1 main changes

- 記事URLから既知のTracking Parameterを除去。
- Feed item identityを使った記事NEW状態と手動解除。
- Dashboard Widget配置基盤とタイトルバーDrag & Drop／Keyboard並び替え。
- Clock Widget、Memo Widget、Task Widget、Calendar Widget。
- Task期限をCalendarへ直接表示し、予定Tableへ重複保存しない連動方式。
- スマートフォンの左右Swipeによる4タブ切り替え。
- Feed／Calendar読込中のLoading Spinner。
- Account Settingsからのメールアドレス変更・パスワード変更。
- スマートフォン向けTask期限欄の2段配置。
- Feed、ClockなどのWidgetタイトル高さを44pxへ統一。

## Security and compatibility

- Authentication／Authorization、Session、CSRF、SSRF、XSS、PDO、Input Validation、Password HashのSecure Baselineを維持。
- Account Settingsは現在のパスワード確認、Transaction、owner scope、Throttle、Session ID／CSRF Token再生成を使用。
- `config/local.php`、`.env`、実DB、Log、Session、Cache、Login Throttle Dataは配布物へ含めない。
- PHP 8.1+、PDO + `pdo_mysql`、cURL、SimpleXML、mbstring、MySQL / MariaDBを対象。
- Bootstrap / Bootswatch 4.1.3、Drawer 3.2.2、iScroll 5.2.0-snapshotを維持。
- jQuery 3.7.1、Font Awesome Free 6.7.2を使用。

## Database

新規DBは`database/schema.sql`を使用します。Default Prefixは`rss_`で、設定した`DB_TABLE_PREFIX`と合わせてください。

Version 1.0系の既存DBは、Backup後に次を順番に適用します。

1. `database/migrations/002_v1_1_feed_item_state.sql`
2. `database/migrations/003_v1_1_dashboard_widget.sql`
3. `database/migrations/004_v1_1_memo.sql`
4. `database/migrations/005_v1_1_task.sql`
5. `database/migrations/006_v1_1_calendar_event.sql`

各Migrationの`@table_prefix`は実DBのPrefixへ合わせます。既定値はLegacy互換の`ig_`です。V1.1-J / R2まで適用済みのDBには、V1.1-Kによる追加Migrationはありません。

## Distribution files

- `rss-reader-modernization-1.1.0-complete.zip` — GitHub作業Folder相当。Source、Tests、Documentation、GitHub metadataを含む完全統合ZIP。
- `rss-reader-modernization-1.1.0.zip` — Server配置用Runtime ZIP。TestsとGitHub metadataを除外。
- 各ZIPの`.zip.sha256` — ZIP全体のSHA-256。
- ZIP内部Manifest — 各FileのSHA-256。

## Update notes

更新前にCode、`config/local.php`、実DB、Runtime DataをBackupしてください。ZIPは別Folderへ展開し、`config/local.php`、実DB、`var/`の生成Dataを上書きしないでください。更新後はBrowser Cacheを更新し、`tools/healthcheck.php`、DB verify、Browser確認を実施します。

詳細は[`docs/update.md`](docs/update.md)、[`docs/installation.md`](docs/installation.md)、[`docs/deployment-checklist.md`](docs/deployment-checklist.md)を参照してください。

## Verification limits

自動TestではPHP / JavaScript / Python / Shell構文、Security境界、RSS / Atom、Widget CRUD、Drag & Drop、Clock、Memo、Task、Calendar、Task連動、Swipe、Spinner、4タブ、8テーマ、Prefix、Migration構造、Schema、Secret Pattern、ZIP CRC / Path Traversal、Manifest、Documentation Link、Version表記を確認しています。

この実行環境には実MySQL Serverと`pdo_mysql`接続先がないため、実DBへのMigration適用、Hosting固有設定、実Feed到達性、実Mail配送、BackupからのRestoreは利用者環境での最終確認が必要です。Browser項目は同梱Playwright / Chromiumで可能な範囲を確認しています。

## License

Project本体は`LICENSE`、外部Assetは`THIRD_PARTY_NOTICES.md`と`licenses/`を参照してください。
