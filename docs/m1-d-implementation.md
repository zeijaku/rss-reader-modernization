# M1-D — Deterministic Feed Item identity

Build: `RSS Engine M1-D / R1`

## Scope

M1-DはM1-10 Item identityを実装します。ユーザー表示やDB保存を変えず、取得した記事を後続工程で安定して識別できる内部metadataを導入します。

対象:

- RSS 2.0 `<guid>`
- RSS 1.0 `rdf:about`
- Atom `<id>`
- ID欠損時の記事link fallback
- ID/link欠損時のcontent fingerprint fallback
- configured Feed URLによるscope
- `NormalizedItem`への内部identity付与

対象外:

- DB schema / migration
- Stockへのidentity保存
- duplicate Item削除
- new/unread判定
- Server-side cache / duplicate Fetch suppression
- ETag / Last-Modified / Retry
- Frontend / public API field追加

## Identity rule

候補は次の順です。

```text
1. source-id
   RSS 2.0 guid
   RSS 1.0 rdf:about
   Atom id

2. link
   browser-facing article URL

3. fingerprint
   title + date + description + content
```

Identity value:

```text
m1i:v1:<64 lowercase hex SHA-256>
```

Hash入力はversion、configured Feed URL、basis、candidateをJSON tupleとして組み立てます。delimiter連結ではないため、値中の区切り文字による曖昧性を避けます。

Fingerprint自体もversioned payloadをSHA-256化します。改行コードはCRLF/CRをLFへ統一し、外側whitespaceだけをtrimします。URL queryの並べ替え、tracking parameter除去、trailing slash統一等の推測的canonicalizationは行いません。

## Feed scope

Identity scopeには、`app_validate_feed_url()`を通過して`FeedSource`へ格納されたconfigured Feed URLを使用します。

含めない値:

- `content_id`
- owner ID
- redirect後のeffective URL

これにより同じFeed URLを複数の`content_id`で登録しても、同じItemは同じidentityになります。他Feedで同じ`guid`が使われてもFeed URLが異なるためidentityは分離されます。

## Internal model

`NormalizedItem`へ次を追加しました。

```text
sourceItemId: ?string
identity: ?ItemIdentity
```

`ItemIdentity`は次を保持します。

```text
value: m1i:v1:...
basis: source-id | link | fingerprint
```

`withIdentity()`は新しい`NormalizedItem`を返し、元のItemを変更しません。

## Public compatibility

`NormalizedItem::toArray()`は従来どおり次の5項目だけを返します。

```text
title
link
description
content
date
```

`sourceItemId`、`identity`、`basis`は公開APIとFrontendへ出しません。`parse_start()`、`rss_parse`、`result_feed.channel/item`、Stockのtitle/URL保存も変更していません。

旧integration向けに`FeedParser::parse_normalized()` / `parse_start()`の第2引数はoptionalです。source URLを渡さない旧呼び出しは従来shapeを維持し、identityは未解決のままです。通常の`feed.fetch` APIは必ず`$source->url`を渡します。

## Adapter extraction

### RSS 2.0

`guid` textをtrimし、`isPermaLink=true/false`にかかわらずopaque source IDとして扱います。URLとしてFetchしません。

### RSS 1.0

RDF namespace `http://www.w3.org/1999/02/22-rdf-syntax-ns#` の`about`属性を取得します。

### Atom

entryのdefault namespace内`id`を取得します。`tag:` URIやURL形式を区別せずopaque source IDとして扱います。

## Security invariants

- identity resolverはHTTP/DB/filesystem I/Oを行わない
- source IDをURLとしてFetchしない
- raw ID / article URL / title / contentをidentity valueへ露出しない
- raw candidateをlogへ出さない
- client supplied URLをscopeに使用しない
- FetcherのSSRF / TLS / redirect / timeout / size policyを変更しない
- XML `LIBXML_NONET`、API XSS-safe payloadを維持
- DB / Session / Authentication / Authorization / CSRFを変更しない

## Files

新規:

```text
app/feed/item_identity.php
app/feed/item_identity_resolver.php
tests/test_m1d_item_identity.php
tests/test_m1d_architecture.py
tests/test_m1d_fixture_shapes.py
tests/fixtures/rss2_identity.xml
tests/fixtures/atom_identity.xml
```

主な変更:

```text
app/feed/normalized_item.php
app/feed/feed_parser.php
app/feed/feed_xml_helper.php
app/feed/adapters/rss2_adapter.php
app/feed/adapters/rss1_adapter.php
app/feed/adapters/atom_adapter.php
app/api.php
app/bootstrap.php
app/version.php
tests/run.sh
README.md
CHANGELOG.md
docs/*
```

## Next

M1-EではServer-side cacheと重複Fetch抑制を扱います。M1-DのidentityはItem差分・重複判定に利用可能ですが、M1-Eでの具体的なcache keyやdedup policyは別途設計します。
