# M1-A — Fetcher / Parser責務分離 + Normalized Item

Build: `RSS Engine M1-A / R1`

## Scope

M1-AはM1-01とM1-02を同一work unitとして扱う。

- Fetcher / Parserの責務分離
- Source非依存のNormalized Item model導入

次は対象外。

- Feed Source model
- Server-side cache
- 重複Fetch抑制
- ETag / Last-Modified / HTTP 304
- Fetch state / Retry
- Adapterの形式別class分割
- Frontend / DB schema変更

## Before

```text
api_feed_fetch()
  → app_safe_http_fetch()
  → rss_parse (common_func.php)
  → array item[]
  → api_safe_feed_payload()
```

Parser implementation、汎用UI helper、access log等が `common_func.php` に同居していた。

## After M1-A

```text
api_feed_fetch()
  → owner-scoped content lookup
  → stored Feed URL validation
  → FeedFetcher
       → app_safe_http_fetch()  [SB-09 security boundary]
  → FeedParser
       → NormalizedItem[]
       → parse_start() compatibility array
  → api_safe_feed_payload()     [SB-10 output boundary]
```

## Security invariants

M1-Aでは次を変更しない。

1. Feed URLはclient requestから取得せず、認証済みuserが所有する `content_id` からDB解決する。
2. `app_validate_feed_url()` 後にFetchする。
3. `FeedFetcher` は `app_safe_http_fetch()` をdelegationし、DNS/IP/redirect/TLS/timeout/body limitを再実装しない。
4. XML parseは `LIBXML_NONET` を維持する。
5. API responseは `api_safe_feed_payload()` を通す。
6. Browserの `result_feed.channel` / `result_feed.item[]` contractを維持する。

## NormalizedItem

M1-Aでは既存behaviorを変えないため、既存Itemの5fieldだけをmodel化した。

```text
title       string
link        ?string
description ?string
content     ?string
date        ?string
```

Fieldはreadonly。Parser内部ではobjectを生成し、現在のAPI boundaryでは `toArray()` でSecure Baseline互換shapeへ戻す。

M1-Dでitem identityを扱うまでGUID/hash等は追加しない。

## Compatibility

- `FeedParser::parse_normalized()` がM1内部model path。
- `FeedParser::parse_start()` は既存array contractを返すcompatibility path。
- `rss_parse extends FeedParser` を残し、既存test/helper consumerを即時破壊しない。

Compatibility layerは「Legacy Runtimeへ戻す」ためではなく、M1を1 work unitずつ安全に移行するためのtemporary boundaryである。

## Changed files

### Product code

- `app/feed/normalized_item.php` — new
- `app/feed/feed_fetcher.php` — new
- `app/feed/feed_parser.php` — new
- `app/common/common_func.php` — parser implementationを分離
- `app/bootstrap.php` — Feed modulesをcentral load
- `app/api.php` — FeedFetcher / FeedParser boundaryを利用
- `app/version.php` — M1-A marker

### Tests / docs

- `tests/test_m1a_feed_engine.php` — new
- `tests/test_m1a_architecture.py` — new
- 既存static testsを新parser module locationへ追従
- `tests/run.sh` にM1-A gateを追加
- README / CHANGELOG / Roadmap / Modernization record更新

## Next

M1-BではFeed Source modelを扱う。M1-Aで追加したFetcher/Parser/Item境界のpublic behaviorは原則維持する。
