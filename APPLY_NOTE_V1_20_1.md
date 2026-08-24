# V1.20.1-E Apply Note

V1.20.1-EはVersion 1.20.1の最終統合工程です。

- Production ZIP: `rss-reader-modernization-1.20.1.zip`
- Complete Source ZIP: `rss-reader-modernization-1.20.1-complete.zip`
- DB Migration: `database/migrations/013_v1_20_1_calendar_event_color.sql`
- 新規必須Config / Secret: なし
- Intended stable tag: `v1.20.1`

既存V1.20.0から更新する場合は、Code上書き前にDB Backupを取得し、Migration冒頭の`@table_prefix`を実環境と合わせて実行してください。A〜D3の機能はProduction確認済みですが、Eの正式Version / Asset revision / schema統合はTag / GitHub Release前にProduction packageで最終確認します。
