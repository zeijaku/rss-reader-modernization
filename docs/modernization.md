# Modernization Record

## 方針

Modernizationは「古いコードを一度に書き換える」のではなく、まずSecure Baselineを作り、その後にRSS EngineとFrontendを段階的に刷新する方針です。

```text
Legacy freeze
→ Security boundary
→ Runtime / bug stabilization
→ DB integrity
→ Regression tests
→ Documentation / Initial Commit
→ Engine modernization
→ Frontend modernization
```

## SB-00 — Legacy freeze

- Primary evidenceのSHA-256を記録。
- Legacy treeを記録。
- Legacy sourceをModernized treeへ混在させず比較資料として保持。

## SB-01 — Public/private boundary

- `public/` を唯一のWeb公開領域に変更。
- Application codeを `app/`、secret configを `config/`、runtime stateを `var/` へ分離。
- DB dump、logs、Legacy session filesを配布Runtimeから除外。
- Production error detailsをpublicへ出さない境界を追加。

## SB-02 — PDO / database foundation

- PDO exception mode。
- associative fetch。
- native prepares。
- MySQL DSN `utf8mb4`。
- parameter binding。
- user + user_conf transaction。
- timestamp `Y-m-d H:i:s`。

## SB-03 — Session

- Session bootstrapを中央化。
- private `var/session/` storage。
- strict mode / cookie-only。
- HttpOnly / SameSite=Lax / HTTPS時Secure。
- Login成功時Session ID rotation。
- idle / absolute timeout。
- Session stateを最小化。
- LogoutをPOST + CSRFへ変更。

## SB-04 — Authentication

- `password_hash()` / `password_verify()`。
- Login identityをnormalized email + HMACで保存/検索。
- Duplicate active identityをambiguousとしてfail closed。
- `password_needs_rehash()`。
- Registration switch。
- Login throttle。
- Unknown Legacy credential formatは自動migrationしない。

## SB-05 — API contract

- `public/api_v1.php` をHTTP boundaryへ縮小。
- `app/api.php` にexplicit dispatcherを分離。
- POST-only、explicit `action`、JSON responseを統一。

Actions:

```text
content.create
content.update
content.delete
stock.create
settings.update
tabs.update
feed.fetch
```

## SB-06 — Authorization

- Resource ownerの根拠を認証済みSession `user_id` に固定。
- update/deleteはresource id + ownerでscope。
- settings/tabs/stockもSession owner固定。
- `feed.fetch` はURLをclientから受け取らず、owner-scoped `content_id` からserver-sideに解決。

## SB-07 — CSRF

- Login / Register / API / Feed fetch / LogoutをSession CSRF tokenで保護。

## SB-08 — Validation

- Resource IDのstrict positive integer。
- content location 0..3。
- theme/style/navbar/icon allowlist。
- length limit。
- URL protocol / userinfo / fragment等のpolicy。
- render時のLegacy invalid valueにsafe fallback。

## SB-09 — Safe outbound fetch

- HTTP/HTTPSのみ。
- port制限。
- DNS A/AAAA解決。
- loopback/private/link-local/reserved/special-use拒否。
- validated IPへのconnection pinning。
- automatic redirectを無効化し、各hopを再検証。
- TLS peer / hostname verification。
- connect / total timeout。
- response body上限。
- 2xxかつnon-emptyをsuccess条件にする。
- Stock時の記事ページ再Fetchを廃止。
- XML parseで `LIBXML_NONET`。

SB-14では特殊用途CIDRのmatrixを拡張し、built-in filterだけでは不足したrangeを明示拒否へ補強しました。

## SB-10 — XSS-safe output

- `htmlspecialchars(ENT_QUOTES | ENT_SUBSTITUTE, UTF-8)`をcentral helper化。
- UI class/path値をallowlistで正規化。
- URLをvalidateしてから `href` へ出力。
- `_blank` に `noopener noreferrer`。
- Feed payloadをbounded plain text + validated URLへ正規化。
- ClientはHTML文字列連結よりDOM APIを使用。

## SB-11 — Legacy functional bugs

- 4 Tab mappingを0/1/2/3へ統一。
- Feed failure / type判定を修正。
- 0件 / 5件未満Feedを安全に扱う。
- card row close。
- Settings/Tab submit/persistence/current value整理。
- Content style保持。
- malformed/unsupported Feedを架空成功扱いしない。

## SB-12 — PHP 8 stabilization / Atom

- PHP 8.1+をminimum runtimeに設定。
- `E_ALL`を前提とするerror policy。
- null/false/array-key/type boundaryを整理。
- RSS 2.0 / RSS 1.0 / Atom parser pathを安定化。
- Atom default namespace、`link href`、`rel=alternate`、複数linkを扱う。

## SB-13 — Schema / integrity / table prefix

- sanitized schemaを新規作成。
- MySQL / MariaDB、InnoDB、`utf8mb4_unicode_ci`。
- owner relationship IDをUNSIGNEDへ統一。
- query pattern用Index。
- `{prefix}user_conf.user_id` UNIQUE。
- Foreign KeyはLegacy data/user deletion policyが未確定のため追加しない。
- `DB_TABLE_PREFIX` と `db_table_name()` を導入。
- Runtime固定 `ig_*` 依存を除去。
- new DB pathとexisting DB migration pathを分離。

## SB-14 — Final test matrix

- Auth / Registration / Session
- Authorization / IDOR
- CSRF
- SSRF / fetch failure / TLS invariants
- XSS
- RSS 2.0 / RSS 1.0 / Atom fixtures
- 4-tab regression
- DB schema / integrity / prefix
- PHP 8 runtime
- repository leak scan
- ZIP再展開後の回帰test

詳細: [`sb14-test-matrix.md`](sb14-test-matrix.md)

## SB-15 — Documentation / Initial Commit gate

- README / CHANGELOG / Legacy analysis / Modernization / Securityを確定。
- Legacy issue → work unit → files → testsのchange mapを追加。
- Production deployment条件とknown limitationsを明示。
- Initial Commit条件を最終判定。

SB-15ではproduct behaviorを変更しません。Visible version markerとdocumentation/test gateのみを更新します。

## M1-A — Fetcher / Parser responsibility split + Normalized Item

- `app/feed/feed_fetcher.php` を追加し、Feed HTTP取得の責務を明示化。
- Fetcherは新しいnetwork implementationを持たず、SB-09 `app_safe_http_fetch()` を唯一のhardened transportとして継続利用。
- `app/feed/feed_parser.php` へRSS/Atom parser implementationを移し、`app/common/common_func.php` から解析責務を除去。
- `NormalizedItem` を導入し、RSS 2.0 / RSS 1.0 / AtomのItemを共通shapeへ変換。
- 既存 `rss_parse` 名と `parse_start()` array shapeはcompatibility boundaryとして維持。
- `feed.fetch` のowner scope、stored URL validation、SSRF protection、XSS-safe payload contractは変更しない。
- Cache、Feed Source、ETag、Retry、Frontend、DB schemaはM1-Aのscope外。

詳細: [`m1-a-implementation.md`](m1-a-implementation.md)

## M1-B — Feed Source model

- `FeedSource` を追加し、現在の `content_id` をsource identity、`content_owner` をowner identity、検証済み `content_value` をendpointとして表現。
- `FeedSourceMapper` がowner-scoped active content rowを認証済みownerと再照合し、UI専用columnをFeed Engineへ持ち込まない。
- URLはDB rowから直接model化せず、従来どおり `app_validate_feed_url()` を通過した値だけをMapperへ渡す。
- `FeedFetcher` はstring URLではなく `FeedSource` を受け取り、SB-09 `app_safe_http_fetch()` へsource URLをdelegationする。
- 欠損・不整合rowはoutbound fetch前にfail-closedする。
- 新table、DB migration、duplicate source統合、cache、ETag、Retry、Frontend/API response変更はM1-Bのscope外。

詳細: [`m1-b-implementation.md`](m1-b-implementation.md)

## M1-C — RSS / Atom Adapter split + Date normalization

- `FeedParser` はencoding normalization、control-character cleanup、`LIBXML_NONET`付きXML load、Adapter dispatchを担当。
- `Rss2Adapter`、`Rss1Adapter`、`AtomAdapter` が各形式のchannel/item/entry/namespace差分を `NormalizedItem` へ変換。
- `FeedXmlHelper` がformat-neutralなtitle/link/description/content/date抽出を共有し、形式ごとのfield priorityはAdapter側に保持。
- `FeedDateNormalizer` へDateTimeImmutable処理を集約し、既存 `Y-m-d H:i:s` とsource timezoneの壁時計時刻を維持。
- Atomは既存priorityを壊さず `updated` の直後に `published` fallbackを追加。
- Qiita/Publickey型alternate link、RSS text link、RSS 1.0 namespace、Dublin Core、`content:encoded`、0件Feedをfixtureで固定。
- `rss_parse` / `parse_start()` / `rss_normalize_date()` / `rss_select_link_candidate()` はcompatibility boundaryとして維持。
- DB、Frontend、API response、FeedSource/Fetcher、cache、ETag、Retry、Item identityはM1-Cのscope外。

詳細: [`m1-c-implementation.md`](m1-c-implementation.md)

## M1-D — Deterministic Item identity

- RSS 2.0 `guid`、RSS 1.0 `rdf:about`、Atom `id` を各Adapterで内部 `sourceItemId` へ抽出。
- `ItemIdentityResolver` が `source-id → link → fingerprint` の順で候補を選択。
- 検証済みのconfigured Feed URLをscopeとして、versioned SHA-256 identityを生成。
- `content_id` とowner IDはscopeへ含めず、同一Feedのduplicate registrationでもidentityを安定させる。
- 生のsource ID、article URL、title、contentは公開identity値へ埋め込まない。
- `parse_start()` とAPIの5項目array contractは維持し、identityはEngine内部だけに保持。
- DB / Stock / Frontend / duplicate item removal / cache / ETag / RetryはM1-Dのscope外。

詳細: [`m1-d-implementation.md`](m1-d-implementation.md)

## M1-E — Server-side cache + duplicate Fetch suppression

- `FeedFetchService` が `FeedSource → Cache → Lock → FeedFetcher → FeedParser` の順序を管理。
- Cache keyとLock keyは検証済みconfigured Feed URLのSHA-256。`content_id` / owner ID / raw URLをファイル名へ含めない。
- `var/cache/feed/` に正常Parse済みFeed本文だけをversioned JSON + strict Base64 + SHA-256で保存。
- Cache hitでもParser / Adapter / Item identityを毎回通し、解析結果そのものは永続化しない。
- TTL初期値60秒。Cache無効化とLock timeoutを環境変数 / `config/local.php` で設定可能。
- 同一URLの同時RequestはURL単位 `flock()` とdouble-checkで1回のupstream Fetchへ抑制。異なるURLは別Lock。
- Cache破損、directory作成失敗、Lock timeoutはcontrolled miss/bypassとしてSB-09 hardened Fetchへ戻す。
- stale-if-error、ETag / Last-Modified / HTTP 304、Fetch status / Retry、DB / Frontend変更はM1-Eのscope外。

詳細: [`m1-e-implementation.md`](m1-e-implementation.md)


## M1-F — ETag / Last-Modified / HTTP 304

- M1-Eのstale CacheへETag / Last-Modifiedを保存し、TTL経過後に条件付きRequestを行う。
- Validatorは以前のeffective URLと今回の送信先が完全一致するときだけ送信する。
- HTTP 304ではCache本文と `body_fetched_at` を維持し、`validated_at` だけ更新する。
- HTTP 200では新本文をParse成功後に置換し、responseにない旧Validatorは引き継がない。
- M1-E schema 1を互換読み込みし、Validatorなしの通常Fetch後にschema 2へ更新する。
- 条件付きRequestだけを無効化できる `APP_FEED_CONDITIONAL_REQUEST_ENABLED` を追加する。
- DB / Frontend / Stock / Parser / Adapter / Item identityは変更しない。
- stale-if-error、Fetch status、Retry、Cache-Control / ExpiresはM1-Fのscope外。

詳細: [`m1-f-implementation.md`](m1-f-implementation.md)

## M1-G — Fetch state / Retry / stale-if-error

- `feed_retry.php` の小さな関数群でRetry-After検証、失敗分類、Backoff計算を行う。
- Fetch stateは `var/cache/feed/*.state.json` へ保存し、raw Feed URLやtransport messageは含めない。
- transient errorは60秒→300秒→900秒→最大3600秒でBackoffする。
- HTTP 429 / 503の有効なRetry-AfterはApplication側Backoffより優先する。
- 最後の正常確認から `APP_FEED_STALE_MAX_AGE_SECONDS` 以内だけstale Cacheを利用する。
- TLS、private address、invalid redirect、response size超過等のSecurity errorはstaleで隠さない。
- DB / Frontend / Stock / Parser / Adapter / Item identityは変更しない。

詳細: [`m1-g-implementation.md`](m1-g-implementation.md)

## Result

Secure Baseline終了時点で、Legacy版の主要機能を維持しつつ、次のEngine/Frontend改修を安全に積み上げるための境界・DB・test・docsが揃いました。
