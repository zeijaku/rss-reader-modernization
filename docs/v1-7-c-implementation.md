# V1.7-C Asset Cache Busting centralization

## Baseline

- V1.7-B / R1
- Application Version `1.7.0-dev.1`
- Baseline SHA-256 `aabc4942f85ebe397b3ab738643c75ee1f763b15508de8bccd453702bcfa5014`

## Problem

V1.6までは一部Assetだけに`dashboard.js?v=1.6-d-r1`のようなStage別Queryを手動追加していました。Theme、認証画面、Clock、Calendar、faviconなどはVersion Queryがなく、更新漏れや古いCacheが残る原因になっていました。

## Implementation

`app/asset.php`へ次の共通Helperを追加しました。

```php
app_asset_url('js/dashboard.js')
```

出力例：

```text
./js/dashboard.js?v=1.7.0-dev.2
```

Version Tokenは`APP_VERSION`だけを使用します。`filemtime()`やRuntime Hashは使わないため、ZIP展開日時やServerのTimestampに依存しません。

## Security boundary

Helperは次を拒否します。

- 外部URLとProtocol-relative URL
- Absolute Path
- `../`、`.`、空Segment
- Backslash Path
- 既存Query／Fragment
- `css/`、`js/`、`favicon.png`以外

Themeは既存`resolve_theme_stylesheet()`のAllowlistで解決した後、Helperへ渡します。

## Scope

対象：

- 8 Theme CSS
- Font Awesome CSS
- Drawer CSS／JavaScript
- Dashboard CSS／JavaScript
- Mini Game／Lights Out
- Clock Timer
- Calendar
- Auth CSS／JavaScript
- jQuery／Popper／Bootstrap／iScroll
- favicon

非対象：

- CSS内のFont URL
- HTTP Cache Header
- `.htaccess`
- Service Worker
- DB／API／Config

HTTP Cache HeaderはV1.7-Dで、Version付きURLが揃った後に追加します。
