# M1-B — Feed Source model

Build: `RSS Engine M1-B / R1`

## Scope

M1-BはM1-03として、既存のowner-scoped `content` rowとFeed Engineの間に明示的なFeed Source modelを導入する。

対象:

- immutable `FeedSource`
- owner-scoped rowからの `FeedSourceMapper`
- `FeedFetcher` のFeedSource受け取り
- 不整合rowのfail-closed

対象外:

- DB schema / migration /専用Feed Source table
- 同一URL統合、canonical key
- server-side cache、重複Fetch抑制
- ETag / Last-Modified / HTTP 304
- fetch state / retry
- RSS/Atom Adapter分割、date normalization
- Frontend / API response shape変更

## Before

```text
authenticated content_id
  → find_owned_active_content()
  → app_validate_feed_url(content_value)
  → FeedFetcher::fetch(string $url)
  → FeedParser
```

Fetcher interfaceがURL stringを直接受け取り、Feed Engine内にsource identity/owner identityを表すmodelがなかった。

## After M1-B

```text
authenticated content_id
  → find_owned_active_content()             [owner-scoped active row]
  → app_validate_feed_url(content_value)    [stored URL validation]
  → FeedSourceMapper::fromOwnedContent()
       → FeedSource(sourceId, ownerId, url)
  → FeedFetcher::fetch(FeedSource)
       → app_safe_http_fetch(source.url)     [SB-09]
  → FeedParser / NormalizedItem
  → api_safe_feed_payload()                  [SB-10]
```

## FeedSource

```text
sourceId int     = current content_id
ownerId  int     = current content_owner
url      string  = app_validate_feed_url() passed value
```

Propertyはreadonly。`content_style`、`content_location`、表示順等のUI情報は含めない。

M1-Bでは新しいsource IDやtableを作らず、既存 `content_id` をruntime source identityとして使用する。

## Mapper boundary

`FeedSourceMapper` は次を行う。

1. `content_id` / `content_owner` をpositive integerとして厳密に解釈する。
2. rowのownerとauthenticated ownerが一致することを再確認する。
3. raw `content_value` を参照せず、API層で検証済みのURLだけをmodelへ渡す。
4. 欠損、型不正、overflow、owner mismatch、空URLでは `null` を返す。

通常は `find_owned_active_content()` がowner/active条件を保証するが、Engine boundaryでも再確認してsilent corruptionを防止する。

## Failure behavior

Mapperがsourceを生成できない場合、APIはoutbound fetchを行わず、server logへuser/content IDだけを記録し、次を返す。

```text
HTTP 500
error.code = internal_error
message    = Feed source could not be resolved.
```

DB rowの内部不整合をclientへ詳細開示しない。

## Security invariants

1. client requestからraw Feed URLを受け取らない。
2. owner-scoped active content lookupを先に行う。
3. stored URLは `app_validate_feed_url()` 後にmodel化する。
4. Fetcherは引き続き `app_safe_http_fetch()` だけをtransportとして使う。
5. DNS/IP/redirect/TLS/timeout/body limitを変更しない。
6. Parserの `LIBXML_NONET` を変更しない。
7. API outputは `api_safe_feed_payload()` を通す。
8. Browserの `result_feed.channel` / `result_feed.item[]` contractを変更しない。

## Changed files

### Product code

- `app/feed/feed_source.php` — new immutable model
- `app/feed/feed_source_mapper.php` — new owner-scoped mapping boundary
- `app/feed/feed_fetcher.php` — `FeedSource` parameterへ変更
- `app/api.php` — validate → map → fetch orchestration
- `app/bootstrap.php` — source modulesをdependency orderでload
- `app/version.php` — M1-B marker

### Tests / docs

- `tests/test_m1b_feed_source.php` — new executable model/mapper/transport checks
- `tests/test_m1b_architecture.py` — new architecture/static gate
- `tests/test_sb05_07_api.php` — malformed row fail-closed regression追加
- M1-A/SB static testsをFeedSource interfaceへ追従
- README / CHANGELOG / Roadmap / Modernization / checklist更新

## Next

M1-CではRSS 2.0 / RSS 1.0 / Atom Adapterの責務を整理し、既存date normalization behaviorをtestで固定した上で共通化する。
