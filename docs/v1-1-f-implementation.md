# V1.1-F / R1 Implementation

## Scope

V1.1-Dの共通Dashboard Widget基盤へClockを追加しました。Feedの取得処理とは分離し、Browserの現在時刻を表示します。

## Clock設定

- タイトル（1〜32文字）
- 12時間／24時間表示
- 日付表示ON/OFF
- 秒表示ON/OFF
- 見出し色
- 横幅1〜4
- 4タブの現在位置へ追加

複数のClockを追加出来ます。変更・削除と、V1.1-Eの同一タブ内並び替えにも対応します。

## 時刻更新

JavaScriptの1本の共有Timerで、表示中のClockを1秒ごとに更新します。時刻表示のためにServerや外部APIへ継続通信しません。表示TimezoneはBrowserを実行している端末の設定を使用します。

## 保存

`dashboard_widget.widget_type = clock`、`widget_reference_id = NULL`として保存します。Clock固有設定は`widget_config`へ、schema、title、hour_format、show_date、show_secondsを制限付きJSONで保存します。

## Security

Clock CRUDは既存の認証・CSRFを通り、Login Userのowner scopeでのみ処理します。変更・削除はownerと有効Flagを条件にLockし、Transaction内で更新します。Clientからowner指定は受け取りません。

## Database

Clock専用Table、Column、Index、Migrationは追加しません。新規DB用`database/schema.sql`も変更不要です。
