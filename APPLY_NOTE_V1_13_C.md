# V1.13-C 適用メモ — Settings画面分離

## 目的

V1.13-B R2をBaselineとして、Dashboard / Stockへ埋め込まれていた以下の設定UIを独立したSettings画面へ分離します。

- 表示設定
- タブ表示変更
- RSS Highlight

Account SettingsはV1.13-Cの対象外です。従来のAccount Settings Modalを維持します。

## Canonical URL

Settingsの公開URLは拡張子を表示しません。

- `/settings`
- `/settings#display`
- `/settings#tabs`
- `/settings#highlight`

実体は `public/settings.php` です。

`/settings.php` またはApplication Root配下の `/public/settings.php` が直接要求された場合は、`.htaccess` により302で `/settings` へ戻します。V1.13開発中のためPermanent Redirectにはしていません。

## DB / Migration

変更ありません。

- 新規Table: なし
- Column変更: なし
- Migration追加: なし
- `config/local.php` 変更: なし

## Mutation経路

Settings画面自身はDB更新SQLを持ちません。変更処理は従来どおり以下の経路を使用します。

- `settings.update`
- `tabs.update`
- `feed.keyword.create`
- `feed.keyword.delete`

すべて `public/api_v1.php` の認証済みPOST + CSRF確認 + `app/api.php` Dispatcher経由です。

## UI分離

`public/index.php` と `public/stock.php` から以下のModalを削除しました。

- `changeConf`
- `tabContent`
- `rssHighlightSettings`

Drawerの3項目はModal Buttonではなく、Settings画面の各Sectionへ移動するLinkへ変更しました。

Dashboard側のRSS Highlight描画にはKeyword JSONが必要なため、`public/index.php` のKeyword読込と `rssHighlightKeywordData` は残しています。

## Settings画面のAsset

SettingsではGame / Clock / Calendar等を表示しないため、以下のDashboard専用Assetは読み込みません。

- mini-game.js / mini-game.css
- lights-out.js
- clock-timer.js / clock-timer.css
- utility-widgets.js / utility-widgets.css
- calendar.js

Settings更新・Drawer・Account Settings等の既存Interactionを変えないため、`dashboard.js` は共有利用しています。

## 適用

V1.13-B R2へ、このZIP全体を上書き適用してください。

DB作業はありません。

本番適用前に現在のV1.13-B R2一式をBackupしてください。
