# V1.7-C / R1 適用メモ

## 目的

CSS、JavaScript、Theme、認証画面、faviconのCache Bustingを`app_asset_url()`へ一元化します。

## Application

- Version: `1.7.0-dev.2`
- Label: `RSS Reader Modernization V1.7-C / R1`
- DB／Migration／SQL: なし
- API／設定／外部Library: なし
- `.htaccess`／HTTP Cache Header: 変更なし

## 配置対象

- `app/version.php`
- `app/asset.php`
- `app/bootstrap.php`
- `app/common/common_login.php`
- `public/index.php`

配置後はBrowserを再読み込みし、読み込まれるCSS／JavaScript／faviconのURLがすべて`?v=1.7.0-dev.2`になっていることを確認してください。
