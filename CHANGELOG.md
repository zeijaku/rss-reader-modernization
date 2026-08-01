# Changelog

このChangelogはLegacy版そのもののリリース履歴ではなく、RSS Reader Modernization Projectの変更記録です。

## RSS Engine M1-C / R1 — 2026-08-01

### RSS / Atom adapters and date normalization

- `FeedParser` をsecure XML loadとAdapter dispatch中心へ縮小。
- `Rss2Adapter`、`Rss1Adapter`、`AtomAdapter` と共通 `FeedAdapterInterface` を追加。
- namespace、channel/entry/item、description、`content:encoded`、Dublin Core date等の形式別処理をAdapterへ分離。
- `FeedDateNormalizer` を追加し、既存 `Y-m-d H:i:s` 出力とsource timezone非変換を維持。
- Atomは従来の `updated` を優先し、未設定時に標準 `published` をfallbackとして使用。
- `FeedLinkSelector` と `FeedXmlHelper` を追加し、Qiita/Publickey型alternate link、text link、`url` fallbackを共通化。
- `rss_parse`、`parse_start()`、`rss_normalize_date()`、`rss_select_link_candidate()` の互換境界を維持。
- DB、Frontend、FeedSource、Fetcher、API response、SSRF/XSS、cache/ETag/Retryは変更なし。
- Adapter/Date/fixture/architecture/security regression testを追加。

## RSS Engine M1-B / R1 — 2026-08-01

### Feed Source model

- `FeedSource` を追加し、既存 `content_id` / `content_owner` / 検証済みURLをimmutableなFeed Engine modelとして表現。
- `FeedSourceMapper` を追加し、owner-scoped active content rowを認証済みownerと再照合してmodel化。
- Mapperはraw `content_value` を使用せず、`app_validate_feed_url()` 後のURLだけを受け取る構造に変更。
- `FeedFetcher` は任意URL文字列ではなく `FeedSource` のみを受け取るinterfaceへ変更。
- 不正・欠損DB rowはoutbound fetch前にfail-closedし、generic 500 responseとserver logへ分離。
- DB schema、Frontend、API response shape、SSRF/XSS boundary、cache/ETag/Retryは変更なし。
- M1-B専用のmodel/mapper/transport/API failure/static architecture testを追加。

## RSS Engine M1-A / R1 — 2026-08-01

### Fetcher / Parser responsibility split + Normalized Item

- `FeedFetcher` を追加し、`feed.fetch` のHTTP取得をSB-09 `app_safe_http_fetch()` 経由の明示的境界へ分離。
- `FeedParser` を `app/feed/` へ分離し、RSS 2.0 / RSS 1.0 / Atomの既存解析behaviorを維持。
- `NormalizedItem` を導入し、Parser内部では共通Item modelを生成。
- `parse_start()` と `rss_parse` compatibility aliasを残し、既存API array contractを維持。
- APIのowner lookup → stored URL validation → hardened fetch → parse → XSS-safe payloadの順序を維持。
- M1-A専用の実行test / architecture static testを追加。
- DB schema、Frontend、cache、ETag、retryは変更なし。

## Secure Baseline SB-15 / R3 — 2026-07-30

- PHP fallbackとDB schemaのUI defaultを統一。
- schemaのNavbar URL defaultを明示的HTTPSへ統一。
- Legacy hash evidenceからBuild環境固有の `/mnt/data/` pathを除去。
- `APP_HASH_KEY` の継続保持・安全なbackupに関する運用注意を追加。
- Version / tests / package manifest / Initial Commit資料をR3へ同期。

## Secure Baseline SB-15 / R2 — 2026-07-30

### Git pre-commit cleanup

- 未使用のLegacy暗号化関数削除後の状態を正式Checkpoint化。
- 削除済みTweet UIに対するdead JavaScript、空のlist item、古いコメントを削除。
- 未使用Vue 2.5.17 asset/runtime依存の削除済み状態へREADME/Roadmap等を同期。
- Visible version markerをSB-15 R2へ更新。
- Package manifestを最終内容から再生成。
- ProductのRSS/Auth/API/DB behavior変更なし。

## Secure Baseline SB-15 / R1 — 2026-07-30

### Documentation / Initial Commit gate

- READMEをSecure Baseline完成時点へ更新。
- Legacy解析、Modernization、Security、Change map、Roadmap、Initial Commit gateを文書化。
- Production deployment / new DB / table prefix手順を整理。
- Secure Baselineで意図的に残した制約を明示。
- GitHub Initial Commit対象からsecret/data/log/session等を除外する条件を再確認。
- Runtime機能変更なし。Visible version markerのみSB-15へ更新。

## Secure Baseline SB-14 / R1 — 2026-07-30

### Final regression/security matrix

- Authentication transaction rollback試験を追加。
- SSRF special-use address matrixを拡張。
- XSS、Parser fixture、CSRF surface、4-tab、repository leak scanを横断確認。
- 拡張試験で検出した特殊用途IPv4/IPv6判定の不足を明示CIDR拒否で補強。
- ZIP再展開後の全回帰試験・secret scan・manifest照合をRelease Gate化。

## Secure Baseline SB-13 / R2 — 2026-07-30

### Schema / data integrity / table prefix

- MySQL 8向けsanitized schemaを整備。
- `utf8mb4_unicode_ci`、relationship IDのUNSIGNED化、query pattern用Index、`user_conf.user_id` UNIQUEを定義。
- `DB_TABLE_PREFIX` を導入し、Runtimeの固定 `ig_*` 依存を除去。
- `schema.sql` / audit / migration / fixtureをprefix対応。
- 新しい空DBから開始する経路を追加。
- Existing Legacy DB向けpreflight / migration / postflightを用意。
- Legacy duplicate/orphanを自動削除・統合しない方針を維持。

## Secure Baseline SB-12 / R2 — 2026-07-30

### Atom link hotfix

- Atomの `<link href="...">`、`rel="alternate"`、複数linkを安全に選択する処理を修正。
- Qiita型 / Publickey型のAtom fixtureを追加。

## Secure Baseline SB-11〜12 / R1 — 2026-07-30

### Legacy bug fixes / PHP 8 stabilization

- 4タブlocationを0/1/2/3へ統一。
- Feed 0件・5件未満・4の倍数以外の表示不具合を修正。
- Feed type判定、設定値保持、二重submit、HTML構造等のLegacy不具合を整理。
- PHP 8.1+をRuntime最低要件として明示。
- Warning/Notice/Deprecated/TypeErrorにつながる境界を整理。
- RSS 2.0 / RSS 1.0 / Atom parserの失敗扱いを改善。

## Secure Baseline SB-08〜10 / R1 — 2026-07-30

### Validation / SSRF / XSS

- ID、enum、length、URLのstrict validationを追加。
- Feed fetchをserver-side registered URLから実行。
- HTTP/HTTPS限定、DNS/IP検証、redirect再検証、TLS verification、timeout、body上限を導入。
- Stock保存時の記事ページ再Fetchを廃止。
- Feed/DB/UI出力をescapeし、Feed payloadをplain text + validated URLへ正規化。

## Secure Baseline SB-05〜07 / R1 — 2026-07-30

### API / authorization / CSRF

- `public/api_v1.php` をthin HTTP boundaryへ縮小し、`app/api.php` にdispatcherを分離。
- POST-only explicit action APIへ整理。
- ownerはrequest値ではなく認証済みSessionから決定。
- Content/settings/tabs/stock/feed fetchへownership enforcementを追加。
- Login / Register / API / LogoutへCSRFを適用。

## Secure Baseline SB-03〜04 / R2 — 2026-07-29

### Session / authentication

- Session処理を中央化し、private `var/session/` へ保存。
- strict mode、cookie-only、HttpOnly、SameSite=Lax、HTTPS時Secureを設定。
- Login時Session IDを再生成。
- idle / absolute timeoutを導入。
- `password_hash()` / `password_verify()` へ移行。
- normalized email identity + HMACを採用。
- Duplicate identityをfail closed。
- Registration switchとLogin throttleを追加。
- Legacy credential自動移行は行わない方針へ確定。

## Secure Baseline SB-00〜02 — 2026-07-29

### Legacy freeze / boundary / PDO foundation

- Legacy sourceのhash / treeを凍結記録。
- `public/` を唯一のWeb公開領域へ分離。
- secrets / DB dump / logs / sessionを公開対象から分離。
- PDO exception mode、native prepare、assoc fetch、MySQL `utf8mb4`を設定。
- SQL parameter bindingを導入。
- user + user_conf作成をtransaction化。
- 日時formatを `Y-m-d H:i:s` へ修正。
