# RSS Reader Modernization 1.49.1

V1.49.1は、既存の正式版V1.49.0へCalendarの予定追加・変更UIだけを統合する小規模改善版です。ユーザーが確認したv1.50.0-dev.16を元に、Calendar関連の変更だけをmainへ移植しました。未正式化のPrecision Sniperや他のv1.50開発機能は含みません。APP_VERSIONとAPP_ASSET_REVISIONはともに`1.49.1`です。DB Migration、既存API変更、新規必須設定はありません。

## Changes

- 予定追加／変更モーダルにBootstrapテーマに沿うヘッダー、Calendar選択と色のグループ、日時の明確な区切り、場所・URL・メモの詳細表示を用意。
- 時刻指定の所要時間30分／1時間／2時間のショートカット。終了日が翌日に跨る場合も対応。終日・手動設定と既存の終了時刻連動を保持。
- 予定色の5選択肢に落ち着いた15pxの四角い色マーカー。Bootstrap Dropdownで表示し、元のselectと保存値を維持。Dropdown非対応時はnative selectへフォールバック。
- 繰り返し・Occurrence単位の変更、通知、締切強調、Calendarの所属、場所／URL／メモ、日／週／月、既存のドラッグ・コピーなどのAPI／DBは変更していません。
- v1.49.0で提供済みのFeed・Reader Mode、Remote Files、Mail、Game、認証・Security境界は維持。

## Verification limits

- Dev16に対してユーザーから画面・動作に問題なしとの報告があります。全OS／ブラウザ／テーマ／操作経路を網羅した検証ではありません。
- GitHub ActionsのPHP 8.1／8.4回帰、Apache隔離、release-ready・secret scan、Runtime／Complete Source ZIP・SHA-256・clean-roomチェックが正式Releaseのゲートです。
- 色マーカーの表示はブラウザのCSSサポートの範囲で保証されます。Bootstrap Dropdownがない場合は標準selectを使用します。

## Deployment and rollback

[正式版の更新・確認・復旧手順](docs/v1.49.1-release.md)を参照してください。リリースZIPを使用する際、既存のコード／vendor／隠しファイル／設定／DB／稼働データをバックアップしてください。config/local.phpやvar/、アップロード済みデータを上書き・削除しないでください。DB Migrationはありません。問題が発生した場合はv1.49.0のコード一式を戻します。
