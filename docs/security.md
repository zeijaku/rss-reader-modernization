# Security Design

## Scope

この文書はSecure Baselineで導入したapplication-level security boundaryを説明します。Web server、OS、DB server、TLS certificate、hosting account等のinfrastructure securityを代替するものではありません。

## Public/private boundary

Web公開領域は `public/` のみとします。

```text
public/   Web公開
app/      private application code
config/   private configuration
var/      session/log/throttle/cache/migration runtime state
database/ sanitized schema/audit/migration/fake fixtures
```

`config/local.php`、real `.env`、DB dump、logs、sessions、backupはGitにもWeb公開領域にも置きません。

## Authentication

- Passwordは `password_hash(PASSWORD_DEFAULT)` で保存。
- Loginは `password_verify()`。
- successful login時に `password_needs_rehash()` を確認。
- Login identityはtrim/lowercaseしたemailをAPP_HASH_KEYでHMAC-SHA256化。
- Raw login emailをcredential identity columnへ保存しない設計。
- disabled userはlogin不可。
- duplicate active identityはambiguousとしてfail closed。
- Unknown Legacy password formatは推測・自動migrationしない。

### Password policy

Default minimum: 12。
Default maximum: 72 bytes。
値はprivate configから調整できます。

## Login throttling

Private file-backed stateを `var/security/login-throttle/` に保持します。

- identity + IP bucket
- IP bucket
- temporary block
- raw identity/IPをfilenameへ直接書かない
- `flock()` を使用

Hosting構成によっては複数Web node間でstate共有されません。Scale-outする場合はcentral storeへの移行が必要です。

## Session

- central Session bootstrap
- `var/session/` に保存
- strict mode
- cookie-only
- URL Session ID無効
- HttpOnly
- SameSite=Lax
- HTTPS時Secure
- browser-session cookie
- Login成功時ID rotation
- idle timeout
- absolute timeout
- LogoutでSession/Cookie破棄

Sessionにはuser ID、authenticated time、last activity、CSRF token等の最小情報だけを保持します。UI settingを長期Session cacheにしません。

## CSRF

Sessionにcryptographic tokenを持ち、次を保護します。

- Login
- Registration
- Logout
- API actions
- Feed fetch

APIではdispatcher境界でCSRFを検証します。

## Authorization / IDOR

Requestから送られたowner/user IDを信頼しません。

```text
Authenticated Session user_id
        ↓
resource owner
```

Content update/delete/fetchはresource IDだけでなくownerでscopeします。Settings/Tab/Stock targetもSession userへ固定します。

他user resourceへアクセスした場合はownership情報を不用意に明かさないnot-found behaviorを使用します。

## API contract

`public/api_v1.php` はHTTP boundary、`app/api.php` がdispatcherです。

- POST-only
- explicit action
- authenticated Session
- CSRF
- validation
- unified JSON error/success
- unexpected error detailsをpublicへ返さない

## SQL / database

- PDO exception mode
- native prepared statements
- parameter binding
- associative fetch
- MySQL `utf8mb4`
- user + user_conf creationはtransaction
- configurable table prefixはstrict identifier validation後だけ利用

Table identifiersはPDO parameterでbindできないため、prefixは英字/数字/underscoreに限定し、logical table名もallowlistから生成します。

## Input validation

主なvalidation:

- resource ID: positive integer
- content location: 0..3
- theme/style/navbar/icon: allowlist
- text: UTF-8 / control characters / length
- URL: protocol、userinfo、port、fragment等のpolicy

ValidationとHTML escapeは役割を分けています。

## SSRF / outbound HTTP

Feed fetchはserver-side requestを発生させるため、強い境界を設けています。

### URL / host

- HTTP / HTTPSのみ
- standard port 80 / 443
- userinfo拒否
- initial URLと全redirect targetを検証

### DNS / IP

A/AAAAを解決し、public destinationだけを許可します。

拒否対象には以下を含みます。

- loopback
- RFC1918 private
- link-local
- shared address
- documentation / TEST-NET
- benchmark
- multicast
- reserved
- IPv4-mapped special addresses
- IPv6 ULA / link-local / multicast / documentation等

DNS検証後はvalidated addressへconnectionをpinし、host nameはTLS SNI / certificate verification用に維持します。

### Redirect / TLS / limits

- cURL automatic redirect off
- manual redirect、各hopで再validation
- peer verification on
- hostname verification on
- connect timeout
- total timeout
- response size limit
- 2xx + non-empty responseのみ成功

## Server-side Feed cache

M1-EのCacheは `var/cache/feed/` に置き、`public/` 外かつGit管理外とします。Cache fileにはFeed本文、configured URL、redirect後のeffective URLが含まれるため、URL queryにtoken等がある場合もprivate runtime dataとして扱います。

- Cache / Lock filenameはconfigured URLのSHA-256で、raw URLを露出しない
- JSON + strict Base64 + body SHA-256
- PHP `serialize()` / `unserialize()`不使用
- successful safe Fetch + supported Feed Parse後だけ保存
- best-effort directory `0700`、file `0600`
- symlink targetを拒否
- Cache hitでも`FeedParser` / Item identity / XSS-safe API boundaryを通す
- ownership確認はCache lookupより前
- runtime Cache / Lock fileをrepository scanで除外

Cacheやfilesystemの失敗はSecurity boundaryを迂回せず、従来のSB-09 hardened Fetchへfallbackします。Cache自体を信頼して外部URLへ追加通信することはありません。

## XML

SimpleXML parsingでは `LIBXML_NONET` を使用し、XML parser自身がsecondary network fetch pathにならないようにします。

## XSS / output

- HTML text/attributeは `htmlspecialchars(ENT_QUOTES | ENT_SUBSTITUTE, UTF-8)`。
- Feed title/description等はtrusted HTMLとして扱わない。
- Feed item URLはvalidateしてからAPI payload/UIへ渡す。
- ClientはDOM APIでtextを設定。
- DB由来tab/navbar/stock等もescape/normalization。
- `_blank` linkは `noopener noreferrer`。

## Stock

Legacy版はStock登録時に記事ページをserver-side fetchしてtitleを抽出していました。Secure Baselineではこの追加outbound requestを廃止し、すでに取得・表示したFeed item title + validated URLを保存します。

## Error handling / logging

Productionは `APP_DEBUG=false`。

- unexpected exceptionはgeneric response
- detailはprivate error logへ
- APIもgeneric JSON 500
- DB exception bodyをpublicへ出さない

Logsを有効にする場合もDocumentRoot外へ保存します。

## Database integrity decisions

- `utf8mb4_unicode_ci`
- relationship ID UNSIGNED
- query indexes
- `user_conf.user_id` UNIQUE
- Foreign KeyはBaselineでは追加しない

Foreign Keyを入れないのはSecurity omissionではなく、Legacy orphan/user deletion policyを自動決定しないための意図的な判断です。

## Repository / secret protection

`.gitignore` とSB-14 repository scanで次を除外します。

- local config
- real env
- DB dumps/backups
- Legacy archive
- logs
- sessions
- throttle state
- migration snapshots
- runtime Feed cache / lock files
- obvious private key/cloud key patterns

Version管理するSQLはreview済みschema/audit/migration/fake fixtureだけです。

## V1.13 Security review

### New entry points

V1.13で追加した `public/stock.php` と `public/settings.php` も、既存のSecurity boundaryを維持します。

- `stock.php` は中央Session bootstrapを使用し、未認証時はStock dataを取得せずLogin viewで終了。
- `settings.php` はSession userがない場合DashboardへRedirect。
- Mutationは引き続き `public/api_v1.php` のPOST / Session / CSRF / Validation boundaryを通す。
- Browserからowner/user IDをSecurity authorityとして受け取らず、Session userでscopeする。
- Stock/Settings分離のための新しいDB access pathや独自HTTP fetch pathは追加していない。

### CSP

現行CSPは `frame-ancestors 'self'`、`base-uri 'self'`、`form-action 'self'` を強制しています。Dashboardには既存のinline script/styleとLegacy frontend dependencyが残るため、V1.13では `default-src` / `script-src` / `style-src` 等を推測で追加しません。

より厳格なCSPは、inline依存を棚卸しし、nonce/hashまたは外部file化を進めた後に段階導入します。Security headerを増やすこと自体より、既存画面を壊さず検証可能なPolicyにすることを優先します。

### HTTPS / HSTS

ApplicationはHTTPS request時にSession cookieへ `Secure` を付与します。HSTSはBrowserへ長期間残るhost-level policyのため、Repositoryから本番HTTPS強制状態を確認できない現段階では自動追加しません。

Productionでは次をHosting / Web server側で確認します。

- HTTPからHTTPSへ確実にRedirectされる。
- 全運用URLがHTTPSで利用できる。
- 証明書更新が継続できる。
- 上記を確認した後にだけHSTS導入を判断する。

### Runtime configuration check

`php tools/healthcheck.php` は秘密値そのものを表示せず、Productionで重要なRuntime stateを確認します。

- `APP_ENV` / `APP_DEBUG`
- `APP_HASH_KEY` が設定済みか、32文字以上か
- Session / Feed cache / Login throttleの保存先と書込み可否
- 各Runtime storageが `public/` 外か
- `config/local.php` の有無と `public/` 外配置
- Registration設定

`APP_HASH_KEY`、DB password、Mail credential等の実値はHealthcheckへ出力しません。

## V1.19 Security boundary update

### Deployment boundary

最も単純で安全な構成は、従来どおりWeb ServerのDocumentRootを`public/`へ向ける構成です。この場合、`app/`、`config/`、`var/`、`database/`、`tests/`、`tools/`、`docs/`はURL空間へ直接出しません。

既存のRental Server互換としてApplication RootがWebから見える構成も維持しています。この構成ではRoot `.htaccess` がPrivate pathを拒否し、実在する公開Assetだけを`public/`へ内部Rewriteします。この互換防御はApache / `.htaccess` / `mod_rewrite`が有効であることが前提です。Nginx等へ移す場合はRuleを自動継承しないため、同等の拒否RuleをServer設定へ移してください。

`public/.htaccess`では、直接実行可能なPHPを次の7 Endpointへ明示的に限定します。

- `index.php`
- `stock.php`
- `settings.php`
- `api_v1.php`
- `logout.php`
- `connection_probe.php`
- `error.php`

新しいPHP Endpointを追加する場合は、実装Fileを置くだけでは公開されません。Method、Anonymous可否、Authentication、CSRF、Authorization、DB / External accessを確認してPublic Endpoint MatrixとWhitelistを同時に更新します。

### API request size

`public/api_v1.php`は`APP_API_MAX_REQUEST_BYTES`（Default 1 MiB）をApplication-level guardとして使用します。AuthenticationとCSRFを先に確認するため、未認証RequestやInvalid CSRFへSize情報を優先して返しません。

PHPは通常のPOST bodyをApplication code実行前にParseするため、このGuardだけでRequest-body DoSを完全には防げません。ProductionではHosting / PHPの`post_max_size`とWeb Server側上限も別途確認します。

### Registration abuse

Registrationが有効な環境では、IP単位のfile-backed throttleを`var/security/login-throttle/`配下で使用します。Defaultは15分Window、IPあたり10試行、到達後15分Blockです。Raw IP、Email、Password、Honeypot値をThrottle stateへ保存しません。

限定利用で新規登録が不要な環境は、`REGISTRATION_ENABLED=false`を運用設定として選択できます。Repository側Defaultは既存互換のため`true`のままです。

### Browser security headers

CSPは段階導入を維持し、現在は次を強制します。

- `frame-ancestors 'self'`
- `base-uri 'self'`
- `form-action 'self'`
- `object-src 'none'`

`script-src` / `style-src` / `connect-src`の全面制限は、既存Inline Style、動的Style、CDN HLS、各Widget通信の棚卸しなしには追加しません。HSTSも完全HTTPS運用をHosting側で確認してから導入します。

### HTTPS detection / proxy

Session / Remember Me cookieの`Secure`判定はApplicationが直接認識するHTTPS状態を基準にします。Reverse Proxy配下だからという理由だけで、任意の`X-Forwarded-Proto`を信頼する変更は行いません。TLS termination構成を採用する場合は、信頼するProxy範囲とHeader上書き保証をServer側で確定してからApplication側対応を検討します。

Public Endpoint一覧は[`v1-19-public-endpoints.md`](v1-19-public-endpoints.md)、今後の追加機能Reviewは[`v1-19-security-checklist.md`](v1-19-security-checklist.md)を参照してください。

## Production checklist

- HTTPSを使用。
- `APP_DEBUG=false`。
- `APP_HASH_KEY` を32文字以上の十分にランダムなsecretにする。
- `APP_HASH_KEY` は既存ユーザーのログインIdentity生成に使用するため、運用開始後は安易に変更しない。
- `APP_HASH_KEY` を紛失すると既存メールアドレスから同じIdentityを再生成できないため、安全な場所へバックアップする。
- `config/local.php` をprivateに保持。
- DB accountへ必要最小限の権限を付与。
- `REGISTRATION_ENABLED` を運用方針に合わせる。
- Session/throttle/cache directoriesのwrite permissionを確認。
- PHP / MySQL / dependenciesのsecurity updateを継続する。
- Backup/restore手順を別途保持する。

## Known limitations

- File-based login throttlingはsingle/shared filesystem前提。
- Application layerだけではreverse proxy/CDN/hosting network policyを保証できない。
- Foreign Key未導入。
- Legacy frontend dependenciesは後段で刷新予定。
- Feed cacheはsingle/shared filesystem前提。複数Web nodeでは共有storageまたは別cache backendが必要。
- ETag / Last-Modified / HTTP 304とbounded stale-if-errorを実装済み。Security errorはstale表示やBackoffで隠さない。

SecurityはSB-15で完了ではなく、今後のEngine/Frontend変更でも回帰testを維持する前提です。
