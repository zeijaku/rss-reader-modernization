# M1-F — Conditional Feed request and HTTP 304 reuse

Build: `RSS Engine M1-F / R1`

## Scope

M1-FはM1-06として、M1-Eの期限切れCacheに保存したETag / Last-Modifiedを使い、Feed提供元へ条件付きRequestを送ります。DB、Frontend、公開API、Stock、Parser、Item identityは変更しません。

対象:

- HTTP responseのETag / Last-Modified取得
- `If-None-Match` / `If-Modified-Since`
- HTTP 304時のCache本文再利用
- 本文取得時刻と再確認時刻の分離
- M1-E Cache schema 1の読み込み互換
- redirect先へのValidator漏えい防止
- 条件付きRequestの個別無効化

対象外:

- stale-if-error
- Retry / Retry-After / exponential backoff
- Fetch status / error stateのDB保存
- upstream `Cache-Control` / `Expires`
- Frontend表示変更
- Item重複削除、新着・既読管理

## Runtime flow

```text
Fresh cache
  ↓
Parser → Adapter → Item identity

Stale cache
  ↓
Cache本文を現在のParserで確認
  ↓
ETag / Last-Modifiedあり
  ↓
URL単位Lockの内側でConditional Request
  ├─ HTTP 304 → Cache本文を再利用しvalidated_atだけ更新
  └─ HTTP 200 → 新本文をParseしてCacheを置換
```

Cacheが無効、Validatorがない、条件付きRequestが無効の場合は、M1-Eと同じ通常Fetchを行います。

## HTTP validator handling

追加した `app/feed/feed_http_headers.php` は小さな関数群だけで構成します。

```text
feed_clean_etag()
feed_clean_last_modified()
feed_conditional_request_headers()
```

ETagはquoted strong / weak形式だけを受け入れます。Last-Modifiedは安全なHTTP-dateへ正規化します。CR、LF、NUL、制御文字、過大な値は使用しません。

Fetcherへ自由なHTTP header配列を通す構造にはせず、生成できるRequest headerは次の2つだけです。

```text
If-None-Match
If-Modified-Since
```

## Redirect safety

Validatorは、前回Validatorを取得した `effective_url` と今回の送信先URLが完全一致するときだけ送ります。

```text
configured URL → redirect → prior effective URL
```

この場合、configured URLへの最初のRequestにはValidatorを付けず、redirect先が以前のeffective URLと一致したhopだけに付けます。redirect先が変わった場合はValidatorを送りません。

DNS/IP検査、manual redirect、TLS peer/hostname確認、`CURLOPT_RESOLVE`によるDNS pinning、timeout、本文上限はSB-09のまま維持します。

## HTTP 304

304はConditional headerを実際に送ったRequestでのみ成功として扱います。条件なし304は `unexpected_not_modified` として拒否します。

304時:

- Cache本文を変更しない
- `body_fetched_at` を変更しない
- `validated_at` を現在時刻へ更新
- responseに新しいETag / Last-Modifiedがあれば更新
- responseで省略されたValidatorは既存値を維持
- Cache writeに失敗しても、そのRequestでは検証済み本文を表示可能

外部Fetchが失敗した場合に期限切れ本文を表示する処理は追加していません。

## HTTP 200

Conditional Requestを提供元が無視して200を返しても正常です。本文をParserへ渡し、対応Feedとして成功した場合だけCacheを置換します。

200 responseにETag / Last-Modifiedがなければ、以前のValidatorは引き継ぎません。

## Cache schema

M1-FのCache schemaはversion 2です。

```text
schema: 2
source_url
effective_url
status
fetched_at          # validated_atと同じ。M1-E確認互換用
body_fetched_at
validated_at
etag
last_modified
body_base64
body_sha256
```

M1-E schema 1も読み込めます。schema 1にはValidatorがないため、期限切れ時は通常のHTTP 200取得を行い、その後schema 2へ更新します。

Cache freshnessは `validated_at` で判定します。本文そのものを最後に取得した時刻は `body_fetched_at` に残します。

## Configuration

初期値:

```text
APP_FEED_CACHE_ENABLED=true
APP_FEED_CONDITIONAL_REQUEST_ENABLED=true
APP_FEED_CACHE_TTL_SECONDS=60
APP_FEED_CACHE_LOCK_TIMEOUT_MS=9000
```

`APP_FEED_CONDITIONAL_REQUEST_ENABLED=false` にすると、Server-side cacheは使用したまま、TTL経過後だけ通常Fetchへ戻せます。

## Code style

M1-Fでは必要以上に抽象化しない方針としました。

- Validator専用のclass hierarchyは追加しない
- 既存 `FeedCacheEntry` と `FeedFetchService` を拡張
- HTTP header処理は小さな関数へ集約
- コメントはSecurity上の理由や分岐理由に限定
- 既存の配列結果と関数中心の流れを維持

## Compatibility and security invariants

維持するもの:

- owner lookup後にのみCache / HTTP取得へ進む
- clientからFeed URLやValidatorを指定できない
- `feed.fetch`の公開response shape
- API five-field Item array
- RSS 2.0 / RSS 1.0 / Atom Adapter
- Item identity規則
- Stock保存形式
- DB schema / Frontend
- SB-09 SSRF / DNS pinning / redirect / TLS / timeout / size
- `LIBXML_NONET`
- XSS-safe API payload

ETag、Last-Modified、Cache statusは公開APIへ返しません。

## Files

新規:

```text
app/feed/feed_http_headers.php
tests/test_m1f_http_conditional.php
tests/test_m1f_cache_revalidation.php
tests/test_m1f_architecture.py
tests/test_m1f_concurrency.py
docs/m1-f-implementation.md
docs/test-report-m1-f.md
```

主な変更:

```text
app/http_fetch.php
app/feed/feed_transport_interface.php
app/feed/feed_fetcher.php
app/feed/feed_cache_entry.php
app/feed/feed_cache.php
app/feed/feed_fetch_service.php
app/bootstrap.php
app/common/common_conf.php
app/version.php
config/.env.example
config/local.php.example
tests/run.sh
README.md
CHANGELOG.md
docs/*
```

## Next

M1-GではFetch status、last fetched、error state、Retry strategyを扱います。stale-if-errorを採用するかは、状態管理とRetryの方針を決めたうえで判断します。
