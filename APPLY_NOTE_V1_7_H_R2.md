# V1.7-H / R2 適用メモ

## 目的

V1.7-H / R1で導入したWidget Gridを実機確認結果に合わせて調整します。

- 全Widgetへ一律指定していた`overflow:auto`を撤去
- 横Scrollbarを原則表示しない
- RSS Feedは通常時ScrollbarなしでCard高へ自動的に表示件数を合わせる
- RSS Feedは1～30件の表示件数を任意指定可能にする
- V1.7-H Migration／Audit SQLを`information_schema`非依存、Table Prefix対応へ修正

## Application

- Version: `1.7.0-dev.8`
- Label: `RSS Reader Modernization V1.7-H / R2`
- R2による新しいDB Column／Table: なし
- RSS表示件数: 既存`dashboard_widget.widget_config`へ保存
- API Route: 追加なし
- Config: 追加なし

## V1.7-H / R1を既に適用済みの環境

`dashboard_widget.widget_height`が既に存在する場合、Migration 008は再実行しません。
R2 Application Fileだけを配置してください。

今回の実環境は手動ALTERによって`widget_height`が追加済みであることを確認しているため、008の再実行は不要です。

必要なら、R2版`database/audit/v1_7_h_postflight.sql`だけを実行してColumn定義と値を確認できます。

## V1.7-G以前から新規にV1.7-H/R2へ更新する環境

1. Code、`config/local.php`、実DB、`var/`、Server固有`.htaccess`をBackupする。
2. 3 SQL Fileの`@table_prefix`を実環境の`DB_TABLE_PREFIX`へ合わせる。
3. `database/audit/v1_7_h_preflight.sql`を実行する。
4. Preflightの`SHOW COLUMNS`で`widget_height`が存在しないことを確認する。
5. 存在しない場合だけ`database/migrations/008_v1_7_widget_height.sql`を一度実行する。
6. `database/audit/v1_7_h_postflight.sql`を実行する。
7. Application Fileを配置する。

R2 SQLは`information_schema`を参照せず、`@table_prefix + dashboard_widget`から対象Tableを生成します。
Migration 008自体は再実行可能なSQLではないため、`widget_height`が既にある環境では実行しません。

## RSS表示件数

RSS追加／編集画面の「表示件数」は次の仕様です。

- 空欄: 自動
- 指定: 1～30件
- 既存RSS: 自動として扱う

自動ではDesktop／Tabletの実際のCard高さを使い、収まる記事行だけ残します。縦2段では通常より多くの記事を表示出来ます。
明示した件数がCardへ収まらない場合だけFeed内の縦Scrollを許可します。

## Cache

`dashboard.css`と`dashboard.js`を変更しているため`APP_VERSION`を`1.7.0-dev.8`へ更新しています。V1.7-Dのimmutable Cache環境でもAsset URLが変わります。
