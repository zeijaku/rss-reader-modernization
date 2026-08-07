# V1.7-H / R3 適用メモ

## 目的

V1.7-H / R2の実機確認で判明した、標準220pxではRSSの情報量が減り、Clock／Gameの操作部が切れる問題を調整します。

- Desktop／Tabletの標準Rowを220pxから320px下限へ拡大
- 通常RSSの自動表示を高さ1=5件、高さ2=10件へ単純化
- R2の実高さ測定による記事Trimを廃止
- Clock／Icon Quest／Lights Outは高さ1でも内容が320pxを超える場合にRowを自然拡張
- R2で追加したRSS表示件数1～30件の任意指定は維持
- Smartphoneは従来どおり1列・自動高

## Application

- Version: `1.7.0-dev.9`
- Label: `RSS Reader Modernization V1.7-H / R3`
- R3によるDB Column／Table: 追加なし
- R3 Migration: 追加なし
- API Route: 追加なし
- Config: 追加なし

## 現在のV1.7-H/R2適用済み環境

`dashboard_widget.widget_height`が既に存在する環境ではSQLを再実行しません。
R3はApplication Fileだけを配置します。

R3で変更するRuntime Fileは次の3 Fileです。

```text
app/version.php
public/css/dashboard.css
public/js/dashboard.js
```

`APP_VERSION`を更新しているため、V1.7-Dのimmutable Cache環境でも新しいCSS／JavaScript URLへ切り替わります。

## V1.7-G以前から新規にV1.7-H/R3へ更新する環境

V1.7-HのDB変更自体は必要です。R2で修正済みの次の順番を使用します。

1. `database/audit/v1_7_h_preflight.sql`
2. `widget_height`が存在しないことを確認
3. `database/migrations/008_v1_7_widget_height.sql`を一度だけ実行
4. `database/audit/v1_7_h_postflight.sql`
5. R3 Applicationを配置

3 SQL Fileの`@table_prefix`は実環境の`DB_TABLE_PREFIX`へ合わせます。
既に`widget_height`が存在する環境では008を再実行しません。

## RSS表示件数

通常RSSの「表示件数」はR2仕様を維持します。

```text
空欄 / 自動   高さ1=5件、高さ2=10件
1 .. 30       指定件数
```

手動指定件数がCardへ収まらない場合だけFeed内の縦Scrollを許可します。概要展開中も必要な縦Scrollを許可します。

## Clock／Game

高さ1のClock／Gameは標準320pxを下限とします。内容が320pxを超える場合は`overflow:hidden`で切らず、縦2導入前と同様にGrid Row自体を必要な高さまで伸ばします。

高さ2は引き続き2 Row Spanです。
