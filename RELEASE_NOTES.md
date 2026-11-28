# RSS Reader Modernization 1.55.0

Memo機能拡張の正式版です。Calendar、RSS、その他Widgetの機能変更はありません。

## Changes

- Text／Markdown形式、本文上限16,000 Unicode文字、原文の空白保持、改行LF統一。既存MemoはTextのまま。
- 安全なMarkdown描画、基本装飾、見出し、リスト、チェックリスト、HTTP(S)リンク、コード、表。チェックリストは通常表示で明示操作した場合に保存。
- 入力支援ツールバー、全画面、編集／プレビュー切替、本文Undo／Redo最大100操作、日本語IMEイベント対応。通常本文は手動保存。
- 保存済みMemoの複製（同じタブ末尾、新規ID）、TXT／Markdown出力、最終更新日時表示。
- 本文・形式・タイトル・サイズ・見出し色の競合検知、異常応答時の下書き保持、Owner分離・CSRF・Transaction。

## Database migration

既存v1.54.0環境では、コード更新前に `database/migrations/038_v1_55_memo_format.sql` の `@table_prefix` を実環境の `DB_TABLE_PREFIX` に合わせて適用してください。既存行の本文・日時は書き換えず、形式列のみ追加します。未適用の過去Migrationは先に番号順に適用。新規インストールはschema.sqlのみ。必須Config追加・公開PHPエンドポイント追加・削除必須ファイルなし。

## Verification limits

- Markdownは基本構文の限定実装。複雑な入れ子、画像、HTML、参照リンクは対象外。プレビューは閲覧専用。自動保存・永続変更履歴・添付はありません。
- TXTは対応Markdown装飾を除去、MDは保存済み原文。Textは両形式とも原文。未保存下書きは出力対象外。
- 旧画面の設定保存はRevision不足により拒否されるため、更新後に再読み込みしてください。
- PHP 8.1／8.4、MariaDB、実HTTP、Chromium PC／360pxの統合検証を実施。実機Safari／iOS／Android、物理IME、本番環境は未検証。
- 公式Release WorkflowでRuntime／Complete Source ZIP、チェックサム、clean-room、秘密情報検査、provenanceを検証します。

## Deployment and rollback

[更新手順](docs/update.md#v1550-正式版へ更新) を参照してください。旧版へのコードのみの戻しでは、16,000文字のMemoやMarkdown表示を維持できません。事前DBバックアップを確保し、更新後データも保全してから復旧してください。
