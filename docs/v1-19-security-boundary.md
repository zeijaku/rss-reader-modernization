# V1.19 Security Boundary

V1.19-A〜Cで確認・強化した境界を、運用時に確認しやすい形でまとめます。詳細実装は[`security.md`](security.md)を参照してください。

## 1. Preferred deployment

```text
Internet
  -> Web Server / TLS
      -> DocumentRoot = public/
          -> index.php / stock.php / settings.php
          -> api_v1.php / logout.php
          -> connection_probe.php / error.php

Repository private area:
  app/ config/ var/ database/ tests/ tools/ docs/
```

DocumentRoot=`public/`が推奨です。

## 2. Rental-server compatibility

Application RootがWeb公開される既存構成も維持しています。この場合はRoot `.htaccess`がPrivate pathを403にし、公開Fileだけを`public/`へ内部Rewriteします。

この互換構成はApacheの`.htaccess`が実際に有効であることがSecurity前提です。Nginx / IISへ置き換えた場合にRuleは自動移植されません。

## 3. Public PHP boundary

`public/.htaccess`は直接実行PHPを7本へ限定します。新規PHPは明示的にWhitelistへ加えるまで403です。Current listは[`v1-19-public-endpoints.md`](v1-19-public-endpoints.md)を正本とします。

## 4. Authentication / CSRF / owner scope

- Login identityはRaw emailを保存せずHMAC-SHA256 identityを使用。
- Passwordは`password_hash()` / `password_verify()`。
- Login成功時にSession ID rotation。
- API mutationはPOST + Authentication + CSRF + Action validation。
- Requestのowner/user IDをauthorityとして信頼せず、Session `user_id`でscope。
- Account credential更新はSessionを開いたまま処理し、その他の遅いAPIはSecurity確認後にSession lockを解放。

## 5. Abuse controls

- Login: identity+IP / IP throttle。
- Registration: IP throttle。Default 15分 / 10試行 / 15分Block。
- API request: Default 1 MiBのApplication-level size guard。
- Feed/outbound HTTP: timeout / response-size / redirect回数上限。
- External WidgetはFeature固有Cache / timeoutを持つものがある。

## 6. SSRF / external request

User supplied URLをServer-side fetchする経路は`http_fetch.php`のSSRF boundaryを再利用します。HTTP/HTTPS、Port、DNS/IP、Redirect、TLS、Size/Timeoutを検証し、private/special addressを拒否します。

Browserから直接接続するCamera/Video等はServer-side SSRFとは別境界です。CSP、Browser mixed-content/CORS、URL validation、SRI等をFeatureごとに確認します。

## 7. Browser headers

Current Apache header policy:

```text
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
X-Frame-Options: SAMEORIGIN
Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()
Content-Security-Policy:
  frame-ancestors 'self';
  base-uri 'self';
  form-action 'self';
  object-src 'none'
```

`script-src` / `style-src` / `connect-src`の全面導入とHSTSは、Compatibility / Hosting確認なしに追加しません。

## 8. Secret / runtime data

- `config/local.php`, real `.env`, APP_HASH_KEY, DB password, Mail/X credentialはGit / Runtime ZIPへ含めない。
- Session / logs / throttle / cacheは`var/`のPrivate runtime data。
- API exception detailはServer logへ、ClientへはReference付きgeneric error。

## 9. Immutable asset note

CSS/JSは長期`immutable` Cacheを使用します。既存Asset内容を変更するときは`APP_ASSET_REVISION`と動的Asset loaderのrevisionも同時に確認します。

外部CDN AssetへSRIを付ける場合は、文字列の目視転記ではなく**実際にBrowser/CLIが取得したbytesからdigestを計算して完全一致を確認**します。
