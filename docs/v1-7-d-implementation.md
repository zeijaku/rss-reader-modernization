# V1.7-D HTTP Cache／Security Header実装

## 目的

V1.7-Cで全Local Asset URLを`APP_VERSION`付きへ統一した後、Static Assetを安全にCacheし、Sessionや利用者情報を含む動的ResponseをBrowser／Proxy Cacheへ残さない構成へ整理します。

## Static Asset Cache

`public/.htaccess`の`mod_headers`内で、次を設定します。

```text
CSS／JavaScript: public, max-age=31536000, immutable
Font／画像:      public, max-age=604800
```

CSS／JavaScriptはURLへApplication Versionが付くため、内容更新時は別URLとして読み込まれます。Font AwesomeのFontや一部画像はCSS内部参照でQuery一元化の対象外なので、`immutable`を付けず7日間に限定します。

## Dynamic Response Cache

`app/response_cache.php`を追加します。

- Dashboard／Login／Logout／Redirect: `private, no-store, max-age=0`
- API／共通Error／Unhandled JSON Error: `no-store, max-age=0`
- 両方に`Pragma: no-cache`と`Expires: 0`

PHP Sessionの自動`session.cache_limiter`は無効にし、ApplicationがResponse種別ごとに明示します。Session期限やCookie属性は変更しません。

## Security Header

既存Headerを維持し、次を追加します。

```text
X-Frame-Options: SAMEORIGIN
Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()
Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; form-action 'self'
```

全面的な`default-src`／`script-src`／`style-src`は、Inline Script、Inline Style、Theme内Google Fontsなどの調査が必要なため追加しません。

## HSTS

HTTPS専用運用、Subdomain、Rollback手段が本番で確定していないため、V1.7-Dでは追加しません。

## 配置構成

Headerを`public/.htaccess`へ集約し、Application Rootから`public/`へ内部Rewriteする構成と、DocumentRootを直接`public/`へ設定する構成の両方を対象にします。`<IfModule mod_headers.c>`内に置き、Moduleがない環境でApplication全体が500にならないようにします。
