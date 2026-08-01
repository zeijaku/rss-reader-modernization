# M1-C — RSS / Atom Adapter split + Date normalization

Build: `RSS Engine M1-C / R1`

## Scope

M1-CはM1-11とM1-09を同一work unitとして扱います。

- RSS 2.0 / RSS 1.0 / Atomの形式別責務をAdapterへ分離
- Feed date normalizationを一か所へ集約
- M1-A/BのFetcher、FeedSource、NormalizedItem、API compatibilityを維持

次はscope外です。

- DB schema / migration
- Server-side cache / duplicate fetch suppression
- ETag / Last-Modified / HTTP 304
- Fetch status / Retry
- Item identity
- JSON Feed / HTML source
- Frontend / API response shapeの変更

## Before M1-C

```text
FeedParser
  ├ encoding normalization / secure XML load
  ├ RSS 2.0 detection + extraction
  ├ RSS 1.0 detection + extraction
  ├ Atom detection + extraction
  ├ link selection
  ├ content/date extraction
  └ NormalizedItem generation
```

形式判定と形式固有のfield extractionが同じclassに集中していました。

## After M1-C

```text
FeedSource
  ↓
FeedFetcher
  ↓
FeedParser
  ├ encoding normalization
  ├ control-character cleanup
  ├ SimpleXML + LIBXML_NONET
  └ FeedAdapterInterface dispatch
       ├ Rss2Adapter
       ├ Rss1Adapter
       └ AtomAdapter
             ↓
       NormalizedItem[]
             ↓
       parse_start compatibility array
```

共有処理:

```text
FeedXmlHelper
  ├ default namespace view
  ├ title
  ├ browser-facing link
  ├ description
  ├ content / content:encoded
  └ adapter-specified date fields + dc:date

FeedLinkSelector
FeedDateNormalizer
```

## Adapter responsibilities

### Rss2Adapter

- `<rss><channel><item>`
- text-body `<link>`
- `description`
- `content:encoded`
- `pubDate`等
- Dublin Core date fallback
- 0件Feed

### Rss1Adapter

- RDF root
- `http://purl.org/rss/1.0/` namespace
- `channel` / `item`
- `dc:date`
- missing channelのcontrolled error

### AtomAdapter

- default Atom namespace
- `feed` / `entry`
- `summary` / `content`
- multiple `<link>` relation priority
- `updated`
- `published` fallback

Atom date priorityは既存behaviorを優先し、次の順です。

```text
created → updated → published → modified → issued → pubDate → lastBuildDate → dc:date
```

`published`はM1-Cで追加したfallbackです。`updated`がある場合は従来どおり`updated`を使用します。

## Date contract

`FeedDateNormalizer` は次を維持します。

- non-string / empty / invalidは`null`
- valid valueは`Y-m-d H:i:s`
- sourceに含まれるtimezoneをUTCへ強制変換しない
- warning/exceptionをAPIへ漏らさない

例:

```text
2026-08-01T11:00:00+09:00 → 2026-08-01 11:00:00
2026-08-01T02:00:00Z      → 2026-08-01 02:00:00
```

## Compatibility boundaries

以下を削除・変更していません。

- `class rss_parse extends FeedParser`
- `FeedParser::parse_start()` のarray shape
- `FeedParser::parse_normalized()` のNormalizedItem shape
- `rss_normalize_date()`
- `rss_select_link_candidate()`
- API `result_feed.channel` / `result_feed.item[]`
- `api_safe_feed_payload()`

## Security invariants

- XML parseは`LIBXML_NONET`を維持
- `simplexml_load_string()`を`@`で隠さない
- Parser/Adapter層はHTTP通信を行わない
- outbound HTTPはFeedFetcher → `app_safe_http_fetch()`だけ
- client supplied URLを使用しない
- Feed HTML/URLは従来どおりAPI safe payloadでsanitize/validate

## Files

新規:

```text
app/feed/feed_date_normalizer.php
app/feed/feed_link_selector.php
app/feed/feed_xml_helper.php
app/feed/adapters/feed_adapter_interface.php
app/feed/adapters/rss2_adapter.php
app/feed/adapters/rss1_adapter.php
app/feed/adapters/atom_adapter.php
tests/test_m1c_feed_adapters.php
tests/test_m1c_architecture.py
tests/test_m1c_fixture_shapes.py
```

主な変更:

```text
app/feed/feed_parser.php
app/bootstrap.php
app/version.php
tests/run.sh
README.md
CHANGELOG.md
docs/modernization.md
docs/roadmap.md
docs/versioning.md
```

## Next

M1-DではGUID / Atom id / URL / fallback hash等のItem identityを扱います。M1-C Adapterのpublic output contractは原則維持します。
