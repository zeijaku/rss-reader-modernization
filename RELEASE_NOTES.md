# RSS Reader Modernization 1.51.0

v1.51.0は、既存の正式版v1.49.1へ、利用者が動作確認したICS購読の開発版（dev.1〜dev.4）だけを統合するCalendar拡張版です。**開発中のv1.50 Precision Sniper等は含みません。** APP_VERSIONとAPP_ASSET_REVISIONはともに `1.51.0` です。

## Changes

- Google Calendar／Outlook等の非公開HTTPS ICS URLを購読登録・編集・削除し、取得状態をCalendar管理画面の専用タブで確認。非公開URLのパス／クエリは画面やAPIに返しません。
- 保存済みICSの予定を月・週・日表示に統合し、Calendarごとの表示フィルターに追加。外部予定は**読み取り専用**で、通常Calendarの編集・削除・ドラッグ対象にはなりません。
- DTSTART／DTEND、終日、主要タイムゾーン、説明、場所、HTTPS URL、日次／週次／月次／年次の主要RRULE、EXDATE／RDATE、RECURRENCE-IDの個別変更・取消を処理。
- **30分期限のアクセス連動型自動更新**：Calendar表示中に期限確認を行い、期限切れICSを取得。Calendarの更新ボタンやICS管理からの手動取得は最短60秒間隔です。ページを閉じている間の常駐・cron更新は行いません。
- 取得成功時には新しいICS内容へ差し替え、削除された予定も反映。通信失敗時は最後に正常取得したICSを保持し、失敗状態を表示。
- SSRF・HTTPS・TLS確認、サイズ1MiB制限、Owner／CSRF、APIレスポンスからの秘密URL隔離を維持。通常Calendarの保存API、Mail／RSS／Remote Files／Gameなど既存領域を維持します。

## Database migration

**v1.49.1などICS未導入の既存環境から更新する場合だけ**、アプリ更新前に `database/migrations/035_v1_51_calendar_ics_subscription.sql` を適用してください。SQL内`@table_prefix`を`DB_TABLE_PREFIX`へ合わせます。Migration 035は新しい`calendar_ics_subscription`テーブルを追加するだけで、既存のCalendar予定は移動・変更しません。

**v1.51.0-dev.1以降で035を適用済みなら再実行しないでください。** Fresh Installは新しい`database/schema.sql`のみを使用し、Migrationを追加実行しません。必須の新しい設定値はありません。

## Verification limits

- ユーザーが開発版でGoogle CalendarのICS登録・予定表示・更新を確認済みです。各種Google／Outlookアカウント・環境・ブラウザ・通信障害のすべてを検証したものではありません。
- Google／Outlook側のICS配信遅延により、更新実行後でも新しい状態の公開まで時間がかかることがあります。30分は閲覧中の再取得条件であって、画面を閉じている間の処理を保証するものではありません。
- RFC 5545の特殊な再帰ルール（例：BYWEEKNO／BYHOUR／独自VTIMEZONEの一部）は対象外。ICS公開URLの発行と双方向編集は含みません。
- GitHub ActionsのPHP 8.1／8.4回帰・Apache隔離・正式Release Workflowのソース検証・秘密情報検査・両ZIPのManifest／SHA-256／clean-room検査が正式版のRelease Gateです。

## Deployment and rollback

[正式版v1.51.0の適用・動作確認・復旧手順](docs/v1.51.0-release.md)を参照してください。ZIP適用前にアプリケーションコード／vendor／`.htaccess`／`config/local.php`／DB／`var/`／アップロード済みデータをバックアップしてください。既存DBへ`schema.sql`を再実行しないでください。不具合時は旧アプリコードを復元し、Migration 035で作った独立テーブルを利用者の判断なく削除しないでください。
