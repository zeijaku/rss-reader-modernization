# RSS Reader Modernization 1.53.0

GPT WidgetとCalendar拡張を正式化します。開発ブランチに含まれるSLGのStage 1・2対CPU戦、Cursor Fieldのタッチ操作、Mediaのコンパクト表示も含みます。

## Changes

- GPT Widgetの会話選択、モデル選択、Shift+Enter送信、Markdown・コードコピー・リンク表示、ファイル添付、対応ブラウザーの音声入力。
- RSS記事メニューから、Widgetがない場合も記事のタイトルとURLを添えて質問。
- 登録した基準残高とWidgetの利用記録から残高を概算表示。
- Calendarの日付マーク12種類と自由入力、単色Font Awesome表示、マークの編集・解除。
- Calendarの予定検索（タイトル・場所・メモの部分一致）と日付マーク検索。検索期間最大366日、結果は新しい日付順に最大50件。繰り返し予定とキャッシュ済み外部ICSも対象。
- DashboardとStockのCalendarヘッダーを共通化。PC・スマホで検索・編集ボタンを配置。
- SLGのStage 1・2、資源・生産・戦闘・対CPU戦、盤面アニメーションと行動メニュー、Cursor Fieldのタッチ対応、YouTube説明と操作行の省スペース化。

## Database migration

既存DBで日付マーク未導入なら、コード更新前に `database/migrations/036_v1_53_calendar_day_mark.sql` の `@table_prefix` を `config/local.php` の `DB_TABLE_PREFIX` に合わせて適用してください。dev.20以降で036を適用済みなら追加SQLは不要です。新規インストールは `database/schema.sql` のみで構築します。

GPT Widgetを利用する場合は `OPENAI_API_KEY` と `APP_OPENAI_USER_ID` をサーバー側で設定します。`APP_OPENAI_MODEL` で既定モデルを指定できます。詳しくは [GPT Widget設定](docs/v1.53.0-openai-widget-dev.md) を参照してください。

## Verification limits

- API残高表示は利用者が登録した基準残高からの概算です。OpenAIの請求画面の残高との自動同期は行いません。
- 音声入力はブラウザーの音声認識とマイク許可に依存します。既存のchatgpt.com会話・プロジェクトとの同期機能はありません。
- Calendar検索は指定期間内を対象とし、配信元のICSキャッシュに含まれない過去予定は取得できません。
- SLGは開発済みのStage 1・2と現行の保存・再開実装を含みます。全ステージの完成を示す版ではありません。
- 正式Release WorkflowでPHP 8.1・8.4回帰、秘密情報検査、Runtime／Complete Source ZIPとチェックサム、clean-room検証を実施します。

## Deployment and rollback

[1.53.0の更新手順](docs/v1.53.0-release.md)を参照してください。
