# M1-E — Server-side Feed cache and duplicate Fetch suppression

Build: `RSS Engine M1-E / R1`

## Scope

M1-EはM1-04 Server-side cacheとM1-05重複Fetch抑制を実装します。DB、Frontend、公開API、Stock、Item identityを変えず、同じFeed URLを短時間または同時に何度も外部取得しない内部boundaryを追加します。

対象:

- 正常Feed本文のprivate filesystem cache
- configured Feed URL単位のcache key / lock key
- TTLによるfresh/stale判定
- 同一URL同時Requestのsingle upstream Fetch
- Cache破損・書込み不能・Lock timeout時のsafe fallback
- Runtime configurationとGit除外

対象外:

- ETag / Last-Modified / HTTP 304
- stale-if-error
- Fetch status / error state / Retry
- DB cache table
- Redis / Memcached
- Browser / Service Worker cache
- Frontend表示変更
- Item deduplication、既読、新着判定

## Runtime flow

```text
Authenticated feed.fetch
  ↓
owner-scoped active content lookup
  ↓
stored URL validation
  ↓
FeedSource
  ↓
FeedFetchService
  ├─ Fresh cache → Parser → Adapter → Item identity
  └─ Miss / stale
       ↓
     URL-specific lock
       ↓
     Cache recheck
       ↓
     FeedFetcher → SB-09 app_safe_http_fetch()
       ↓
     FeedParser
       ↓
     Parse success only → atomic cache write
```

Ownership確認とstored URL validationはCacheアクセスより前に維持します。Cacheを共有しても、他ユーザーの`content_id`を指定して取得することはできません。

## Cache key and location

保存先:

```text
var/cache/feed/
```

DocumentRoot `public/` の外です。Runtime fileは`.gitignore`対象で、directory placeholderの`.gitkeep`だけをVersion管理します。

Cache key / Lock key:

```text
SHA-256(configured FeedSource URL)
```

例:

```text
feed-v1-<64 lowercase hex>.json
feed-v1-<64 lowercase hex>.lock
```

raw Feed URL、host、query token、`content_id`、owner IDはファイル名へ含めません。同じURLを別の`content_id`やownerが登録した場合は同じCacheを共有します。query stringが異なるURLは別keyです。

## Stored representation

CacheはPHP objectや解析済みItem arrayではなく、安全FetchしたFeed本文を保持します。

```text
schema
source_url
effective_url
HTTP status
fetched_at
body_base64
body_sha256
```

- versioned JSON
- strict Base64 decode
- SHA-256 integrity validation
- body size limitは`APP_HTTP_MAX_BYTES`を継承
- `serialize()` / `unserialize()`は使用しない

Cache hitでも本文を`FeedParser`へ渡します。そのためAdapterやItem identity修正後も、古い解析済みobjectが残りません。

## Persistence condition

Cacheへ保存する条件は両方必須です。

1. `FeedFetcher` / `app_safe_http_fetch()`が成功
2. RSS 2.0 / RSS 1.0 / Atomとして`FeedParser`が成功

次は保存しません。

- HTTP / DNS / timeout / TLS / SSRF error
- empty body
- HTML
- malformed XML
- unsupported XML
- body size超過

## TTL and configuration

初期値:

```text
APP_FEED_CACHE_ENABLED=true
APP_FEED_CACHE_TTL_SECONDS=60
APP_FEED_CACHE_LOCK_TIMEOUT_MS=9000
```

TTLは1〜86400秒、Lock timeoutは0〜30000msへ制限します。Cacheを無効化すると既存Cacheを参照せず、従来どおり毎回hardened Fetchを実行します。

TTL経過時のCache fileはM1-Eでは削除せず、画面にも返しません。M1-FでETag / Last-Modified metadataを利用できる余地を残します。外部Fetch失敗時にstale Cacheを表示する`stale-if-error`はM1-Gと合わせて判断します。

## Duplicate Fetch suppression

`flock(LOCK_EX | LOCK_NB)`をURL単位で取得し、待機はmonotonic clockで上限管理します。

```text
Request A: miss → lock → Fetch → Parse → write
Request B: miss → lock wait → cache recheck → hit
Request C: miss → lock wait → cache recheck → hit
```

異なるURLは異なるLock fileを使用します。Lock timeoutやfilesystem failure時はApplication availabilityを優先し、Cache書込みを行わないuncached safe Fetchへfallbackします。

Lockは`finally`とguard destructorで必ず解放します。空の`.lock` fileが残ること自体はLock保持を意味しません。

## Filesystem safety

- Cache directory: best-effort `0700`
- Cache / Lock file: best-effort `0600`
- same-directory temporary fileへ`LOCK_EX`付きwrite
- renameで置換
- symlink Cache target / Lock target / Cache directoryを拒否
- invalid JSON / schema / Base64 / hash / source URL / timestamp / sizeはCache missとして削除または無視
- Cache障害を公開errorへ変換せず、安全Fetchへfallback

WindowsではPOSIX permission bitが完全には適用されない場合があります。Linux productionではWeb/PHP実行userだけが読み書きできることを確認します。

## Compatibility and security invariants

維持するもの:

- `feed.fetch`の公開response shape
- `result_feed.channel` / `result_feed.item[]`
- API five-field Item array
- RSS 2.0 / RSS 1.0 / Atom Adapter
- configured Feed URLを使ったItem identity scope
- owner lookup / Authorization / CSRF
- SB-09 DNS/IP/redirect/TLS/timeout/size policy
- `LIBXML_NONET`
- XSS-safe payload
- DB schema / Stock / Frontend

Cache status（hit/miss/disabled/bypass）は内部resultだけに保持し、公開APIへ出しません。

## Files

新規:

```text
app/feed/feed_transport_interface.php
app/feed/feed_cache_entry.php
app/feed/feed_cache_lock.php
app/feed/feed_cache.php
app/feed/feed_fetch_service.php
tests/test_m1e_feed_cache.php
tests/test_m1e_architecture.py
tests/test_m1e_concurrency.py
var/cache/.gitkeep
var/cache/feed/.gitkeep
```

主な変更:

```text
app/api.php
app/bootstrap.php
app/common/common_conf.php
app/feed/feed_fetcher.php
app/version.php
config/.env.example
config/local.php.example
.gitignore
tests/run.sh
README.md
CHANGELOG.md
docs/*
```

## Next

M1-Fでは、M1-Eで保持したstale Cache entryへETag / Last-Modified metadataを追加し、conditional requestとHTTP 304による本文再利用を実装します。
