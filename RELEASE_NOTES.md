# RSS Reader Modernization 1.54.0

通常CalendarのICS Import / Exportを正式化します。Calendar管理に3つ目のタブを追加し、既存の通常Calendarと外部ICS購読を維持します。Memoの変更はありません。

## Changes

- 指定CalendarのICS出力と全CalendarのZIP出力。全期間では繰り返し・個別変更・取消を保持し、期間指定では個別予定に展開（最大366日）。
- 終日・時刻・複数日、毎日／毎週／毎月／毎年、対応リマインダー、場所・URL・色・締切強調・日付マーク等の入出力。独自属性は任意。
- ICSファイル選択、既存／新規Calendar、追加のみ（既定）／追加・更新、UID重複判定、登録前のプレビューと明示確認。ICSにない既存予定は削除しません。
- Owner分離、プレビュー後の変更検知、Transactionとロールバック。非対応の繰り返し条件等は通知します。
- PC／スマートフォン幅に対応するCalendar管理画面、Import後のCalendar更新。

## Database migration

v1.53.0の既存DBではコード更新前に `database/migrations/037_v1_54_calendar_ics_uid.sql` の `@table_prefix` を実環境の `DB_TABLE_PREFIX` に合わせて適用してください。未適用の過去Migrationがあれば先に適用します。新規インストールは `database/schema.sql` のみ。既存DBにschema.sqlを再実行しないでください。

## Verification limits

- ICSはUTF-8、1ファイル8 MiB・2,000 VEVENTまで。ZIPはExportのみ、合計ICS内容16 MiBまで。8 MiBの受信にはPHP post_max_size等の設定が必要です。
- 複雑な繰り返し、等価に変換できない夏時間付き系列、非対応アラームは移行対象外として通知します。外部購読、Task、Memo、通知履歴は移行対象外です。
- 期間指定では元の繰り返し系列を復元できません。他社サービスが独自属性や全機能を読み込む保証はありません。ChromiumのPC／360pxタッチ検証とHTTP/DB往復テストは実機iOS/AndroidやGoogle/Outlookでの実移行とは異なります。
- 正式Release WorkflowでPHP 8.1・8.4回帰、秘密情報検査、Runtime／Complete Source ZIP、チェックサム、clean-roomおよびprovenance検証を実施します。

## Deployment and rollback

[更新手順](docs/update.md#v1540-正式版へ更新) を参照してください。Import後のデータはコードを戻すだけでは復元されません。事前にDBをバックアップしてください。
