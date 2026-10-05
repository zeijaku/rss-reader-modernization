# RSS Reader Modernization 1.44.0

V1.44.0はCalendarの複数Calendar化と場所情報を正式配布するFeature Releaseです。Application Version / Asset Revision は `1.44.0`。V1.43.1からDatabase Migration 033 / 034が必要です。Google Maps / Places API Key、Google OAuth、Google Calendar連携は不要です。

## Multiple Calendar

- Userごとに複数のLocal Calendarを作成し、名称・識別色を変更出来ます。
- 既存予定はOwnerごとの「既定Calendar」へ安全に割り当てます。
- Calendar WidgetのFilterで複数Calendarの表示／非表示を切り替えます。Task表示には影響しません。
- 新規予定でCalendarを選択し、既存予定も別Calendarへ移動出来ます。
- 既定Calendarは削除出来ません。ほかのCalendarを削除した場合、その予定は既定Calendarへ移動して保持します。
- Calendar識別はFilterと予定欄で同じlayer-group iconを使用し、Calendarごとの色を維持します。

## Location + Google Maps

- 予定へ任意の「場所」を保存出来ます。空欄はNULLとして扱います。
- Locationは通常予定、繰り返しSeries、Occurrence override、Copy、Drag & Drop、Range responseで維持します。
- 場所がある場合だけGoogle Maps検索Linkを表示し、検索文字列はURL encodeします。
- Maps Linkは `target="_blank"` / `rel="noopener noreferrer"` で開きます。
- Google Maps API / Places API / API Key / Google OAuth / embedded mapは使用しません。

## Calendar UI polish

- DesktopではCalendar Filterを前月／今月／日週月／次月／更新／追加と同じToolbar上へ整理します。
- 後から読み込まれるDeadline CSSが古いGridを上書きしてFilterを2段目へ送る問題を解消し、Filter + 次月 + 更新 + 追加を右側の1 action groupとして扱います。
- 予定のCalendar識別markerを円からlayer-group iconへ変更します。
- 予定追加／変更Modalは既存の項目・説明文・保存形式を維持したまま、終日／時刻／繰り返し／Reminder／期日強調を1つのSchedule Gridへ整理します。Desktopは2列、Smartphoneは1列です。
- 「詳細（場所・URL・メモ）」の開閉と既存のスクロール可能Modal Footer動作は維持します。

## Database migration

V1.43.1など既存Databaseから更新する場合は、Applicationの `DB_TABLE_PREFIX` とSQL内の `@table_prefix` を一致させて、次の順で実行します。

1. `database/migrations/033_v1_44_calendar_source.sql`
2. `database/migrations/034_v1_44_calendar_location.sql`

`database/schema.sql` はFresh Install用です。既存Databaseへ上書き実行しません。

033 / 034は共有Hostingの制限を考慮し、DDL存在確認で `information_schema` を読みません。Migration完了状態は通常表示されない owner=0 / flag=255 のCalendar-source marker rowで記録し、成功後の再実行でも同じDDLを重複実行しない構成です。

## Compatibility

- PHP 8.1以上。
- MySQL / MariaDB。
- Config変更なし。
- Google API設定追加なし。
- RSS、Stock、Mail、Remote Files、Account Security、Gameの既存機能を変更しません。
- CalendarのReminder、Deadline、Color、URL/Memo、日／週／月、部分更新、Task表示、繰り返し／Occurrenceを維持します。

## Verification limits

- PHP 8.1 / PHP 8.4 Current regressionを通過させます。
- V1.44-A / BのContract、UI helper、MySQL/MariaDB Migration TestでMultiple CalendarとLocationの保存／互換性を確認します。
- Shared-host向けFocused MySQL 8 Testでは、`rss_calendar_source`だけ作成済みで追加Column/Indexが未作成の途中状態から033 → 034を2回実行し、復旧・再実行安全性・既存予定保持を確認しています。
- User Browser TestではMultiple Calendar表示、Toolbar 1段化、layer icon、Location、Google Maps、予定Modal Grid整理を確認済みです。
- Production DeploymentはGitHub Release公開とは別工程です。

## Release

Tag: `v1.44.0`

Runtime package: `rss-reader-modernization-1.44.0.zip`

Complete Source package: `rss-reader-modernization-1.44.0-complete.zip`
