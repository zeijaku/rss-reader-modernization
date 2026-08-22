# APPLY NOTE — V1.19-C Security Hardening R1

V1.19-Cは、V1.18.0 Production＋V1.19-Bの互換性を維持したまま、公開境界へ小さなHardeningを追加するPhaseです。

## 変更内容

1. **API Request Size Guard**
   - `APP_API_MAX_REQUEST_BYTES`を追加。Defaultは1 MiB (`1048576`)。
   - Authentication / CSRF確認後、Content-Lengthが上限を超えるAPI POSTをHTTP 413 / `request_too_large`で停止。
   - PHPは通常POSTをuserland実行前にParseするため、Server/PHP側の`post_max_size`等の上限も別途必要です。今回`.htaccess`へApache固有の`LimitRequestBody`は強制していません。

2. **Registration IP Throttle**
   - Login Throttleと同じPrivate file store/HMAC path方式を使い、新規登録専用のIP Bucketを追加。
   - Default: 15分Window / IPあたり10試行 / Block 15分。
   - Raw IP、Email、Password、Honeypot値はThrottle fileへ保存しません。
   - Throttle発動時も既存のGeneric registration errorを維持し、状態を利用者へ開示しません。

3. **CSP hardening**
   - 既存CSPへ `object-src 'none'` を追加。
   - `script-src` / `style-src`の全面制限は、既存Inline/Dynamic Styleとの互換確認が必要なため本Phaseでは追加しません。

4. **Public PHP Endpoint Whitelist**
   - `public/`配下で直接実行可能なPHPを現在の7 Endpointへ固定。
   - `api_v1.php`, `connection_probe.php`, `error.php`, `index.php`, `logout.php`, `settings.php`, `stock.php`
   - 将来PHP Endpointを追加するときは、Method/Auth/CSRF/Authorization/Direct Access方針を確認したうえでWhitelistへ明示追加する必要があります。

## Compatibility

- DB Migration: **なし**
- SQL実行: **不要**
- 新規必須Config/Secret: **なし**
- `config/local.php`変更: **不要**（新項目は安全なDefaultあり）
- `APP_VERSION`: **1.18.0のまま**
- `APP_ASSET_REVISION`: **1.18.0-r4**（Camera/HLS SRI修正を確実に取得させるためr2→r4）
- JavaScript Application Asset: **Camera/HLSのSRI値修正のみ**。CSS変更なし
- HSTS: **未追加**（常時HTTPS確定後に検討）
- Trusted Proxy / `X-Forwarded-Proto`: **未変更**（Proxy構成確定なしに信頼しない）

## Optional configuration

必要な場合のみ既存`config/local.php`またはServer Environmentへ追加できます。追加しなくてもDefaultで動作します。

```text
REGISTRATION_RATE_WINDOW=900
REGISTRATION_RATE_MAX_IP=10
REGISTRATION_RATE_BLOCK_SECONDS=900
APP_API_MAX_REQUEST_BYTES=1048576
```

## Package usage

Production ZIPはV1.18.0正式Runtimeを基準に、V1.19-B＋V1.19-Cを累積で含みます。
V1.19-B R1を既に反映済みでも、そのまま同じ相対Pathへ上書きして問題ありません。

**上書きしないもの:**
- `config/local.php`
- 実DB
- `var/` Runtime data


### R2/R3 SRI follow-up

本番Console確認でhls.js 1.6.16のSRI不一致を検出したため、Browserが実際に取得したCDN bytesからSHA-384を再計算し、`public/js/camera-video-streaming.js`のIntegrity値を完全一致させました。誤ったr3 Assetが長期`immutable` Cacheへ残らないよう、現在の`APP_ASSET_REVISION`は`1.18.0-r4`です。
