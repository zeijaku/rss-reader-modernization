# V1.7-H / R1 適用メモ

## 目的

Dashboard Widgetへ横幅とは独立した縦幅を追加し、Desktopでは標準／縦2段を選択出来るようにします。Smartphoneは従来どおり1列の自動高です。

## Application

- Version: `1.7.0-dev.7`
- Label: `RSS Reader Modernization V1.7-H / R1`
- DB: `dashboard_widget.widget_height`を追加
- Migration: `008_v1_7_widget_height.sql`
- API Route: 追加なし
- Config: 追加なし

## 適用順序

1. Application、`config/local.php`、実DB、`var/`、Server固有`.htaccess`をBackupする。
2. `database/audit/v1_7_h_preflight.sql`を実行する。
3. `database/migrations/008_v1_7_widget_height.sql`を実行する。
4. `database/audit/v1_7_h_postflight.sql`を実行する。
5. Application Fileを配置する。
6. Browserを再読み込みし、各Widget編集画面の縦幅とDashboard配置を確認する。

SQL内の`@table_prefix = 'ig_'`は、実環境の`DB_TABLE_PREFIX`へ合わせてください。

Migration 008を適用する前にV1.7-H Applicationを配置すると、Dashboard Widget取得SQLが`widget_height`を参照するため画面を表示出来ません。DBを先に更新してください。
