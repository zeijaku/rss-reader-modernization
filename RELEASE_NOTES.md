# RSS Reader Modernization 1.12.0 Release Notes

## Overview

Version 1.12.0は、Version 1.11.0までのDashboard／Stock／Mail基盤を維持しながら、RSS HighlightとMail Widget Phase 2を追加したReleaseです。

既存のPHP / PDO / MySQL構成、4タブ、Feed CRUD、Stock、Memo、Task、Calendar、Search Feed、Account Settings、Dashboard Widget基盤を維持し、Mailについてはread-onlyの閲覧Widgetとして操作性を拡張しています。

## Version 1.12 main changes

### RSS Highlight

- ユーザーごとにHighlight Keywordを登録／削除。
- 複数Keyword、英字の大文字小文字差、日本語、`C++`や`a.b`のような記号を含むKeywordに対応。
- Keywordが重なる場合は長いKeywordを優先。
- RSS WidgetとSearch FeedのTitleで共通表示。
- Highlightは表示専用とし、Stock、Task、共有、Tooltip等では元Titleを維持。
- Keywordは最大50件、1件64文字まで。
- Keyword削除は論理削除とし、同じKeywordの再登録時は既存IDを再利用。

### Mail Widget Phase 2

- 選択Folder全体の未読件数を表示。
- `すべて / 未読のみ`の表示切替。
- 最終更新時刻を表示。
- 件名／Fromを対象としたIMAP検索。
- 現在取得済みMailに対する送信者Filter。
- IMAP Folder一覧取得とFolder切替。
- `\Noselect` / `\NonExistent` Folderを選択候補から除外。
- Folder選択を既存`dashboard_widget.widget_config`へ保存。
- 旧schema 1のMail Widgetは`INBOX`として互換維持。
- 本文取得は`widget_id + folder + UID`で整合を確認。

### Mail read-only boundary

Mail Phase 2でもMail Server側の状態変更は行いません。

- 一覧取得: read-only mailbox access
- 本文取得: peek相当のread-only取得
- 既読化: なし
- 未読化: なし
- 削除: なし
- 移動: なし
- コピー: なし
- 送信: なし
- Folder作成／削除: なし

## Database and configuration

Version 1.12で追加するMigrationは次の1件です。

- `database/migrations/012_v1_12_feed_keywords.sql`

このMigrationでRSS Highlight用`feed_keyword` Tableを追加します。

Mail Phase 2ではTable／Column追加はありません。Folder選択は既存`dashboard_widget.widget_config` JSONのschema 2として保存します。

Version 1.11.0適用済み環境からVersion 1.12.0へ更新する場合は、Migration 012を適用したうえでCodeを更新してください。

## Distribution files

- `rss-reader-modernization-1.12.0-complete.zip` — GitHub作業Folder相当の完全統合ZIP。
- `.zip.sha256` — ZIP全体のSHA-256。
- 配布物には`vendor`、`config/local.php`、`.env`、Runtime DB／Log／Cacheを含めません。

## Update notes

更新前にCode、`config/local.php`、実DB、Runtime DataをBackupしてください。

Version 1.11.0適用済み環境ではMigration 012を適用し、Code更新後にBrowser Cacheを更新してください。

主な確認項目:

- RSS Highlightが通常RSS／Search Feedで動作すること。
- Keyword追加／削除が反映されること。
- Mail Folder切替が動作すること。
- Mail未読件数、未読のみ表示、件名／From検索、送信者Filterが動作すること。
- Mail本文Previewを開いてもMail Server側の状態を変更しないこと。

## Verification limits

Focused regressionではRSS Highlightの一致処理、Keyword Validation、Mail検索、Folder Validation、schema互換、未読数取得、read-only境界、PHP／JavaScript構文、Migration整合、Secret／Runtime Data除外、ZIP整合性を確認しています。

実IMAP Server、実MySQL Server、Hosting固有設定、実Feed到達性については利用者環境での最終確認が必要です。

## License

既存ProjectのLicenseおよびThird-party noticeを維持します。
