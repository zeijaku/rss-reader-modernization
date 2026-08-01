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

## Result

Secure Baseline終了時点で、Legacy版の主要機能を維持しつつ、次のEngine/Frontend改修を安全に積み上げるための境界・DB・test・docsが揃いました。
