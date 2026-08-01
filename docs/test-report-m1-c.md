# M1-C Test Report

Build: `RSS Engine M1-C / R1`

Date: 2026-08-01 JST

## Scope

M1-C release gateは次を対象にした。

- RSS 2.0 / RSS 1.0 / Atom Adapter dispatch
- Date normalization and Atom `published` fallback
- namespace / alternate link / text link / `content:encoded` / Dublin Core date
- malformed / unsupported XML / missing channel
- XML network prohibition (`LIBXML_NONET`)
- M1-A Fetcher / Parser / Normalized Item regression
- M1-B FeedSource / Mapper / SSRF regression
- SB-00〜15 full regression
- repository artifact / secret scan
- M1-Bとのprotected boundary比較
- ZIP再展開後のmanifestとfull regression

## Source tree result

`tests/run.sh` の全commandを同じ順序・条件でsection単位に実行した。
Build toolの単一command実行時間制限を避けるためsection分割したが、test commandの省略はない。

```text
PASS: 954
FAIL: 0
SKIP: 5
```

PHP syntax lintも全PHP fileで成功した。

## Dedicated M1-C checks

### Executable PHP

```text
test_m1c_feed_adapters.php
PASS: 27
FAIL: 0
SKIP: 1 live adapter matrix
```

確認内容:

- null / boolean / integer / empty / invalid / long date
- RFC822 / RFC3339 / Z / numeric offset / date-only
- warningなしのDateTimeImmutable boundary
- Legacy date/link wrapper compatibility
- alternate/self/related link priority
- Adapter final/interface contract
- invalid Adapter injection rejection
- empty/null parser boundary

### Architecture / static

```text
test_m1c_architecture.py
PASS: 50
FAIL: 0
```

確認内容:

- `FeedParser` がsecure XML load + Adapter dispatchだけを担当
- RSS 2.0 / RSS 1.0 / Atom固有処理が各Adapterに存在
- `NormalizedItem`生成がAdapter側に移動
- DateTimeImmutable使用箇所が`FeedDateNormalizer`一か所
- Atom `updated` → `published` priority
- RSS AdapterへAtom-only fieldが混入していない
- Parser/Adapter層にHTTP通信処理がない
- FeedFetcher / FeedSource / API safe payloadを維持
- DB / Frontend / cache / ETag scope creepなし

### Independent fixture shapes

```text
test_m1c_fixture_shapes.py
PASS: 18
FAIL: 0
```

Python標準XML parserを使い、PHP SimpleXMLと独立して以下を確認した。

- Atom updated/published priority fixture
- Atom 0件
- RSS 2.0 `content:encoded` / invalid pubDate / dc:date fallback
- RSS 1.0 missing channel
- RSS 2.0 missing channel
- well-formed unsupported XML
- external network entity sentinel
- Qiita published-only / alternate link
- Publickey updated

## SKIP 5

Build環境にはSimpleXML、mbstring、PDO driverがないため、次をSKIPした。

1. PDO SQLite integration
2. SB-12 live Atom fixture parsing
3. SB-14 live parser matrix
4. M1-A live normalized parser matrix
5. M1-C live Adapter matrix

cURL extensionもBuild環境にはないが、SSRF/DNS pinning/redirect/timeout/size testsはFake resolver/transport経由で実行され、SKIPしていない。

配置先ではSimpleXML + mbstring有効環境で`tests/run.sh`を実行し、2〜5がSKIPされないことを推奨する。

## Product boundary audit

M1-BとのSHA-256比較で、次の143 protected filesは一致した。

```text
public/
database/
app/api.php
app/http_fetch.php
app/feed/feed_source.php
app/feed/feed_source_mapper.php
app/feed/feed_fetcher.php
app/auth.php
app/session.php
app/session_storage.php
app/login_throttle.php
app/validation.php
app/common/common_db.php
app/common/common_conf.php
app/common/common_func.php
```

Differing protected files: `0`

M1-C product code差分は次に限定される。

```text
app/bootstrap.php
app/version.php
app/feed/feed_parser.php
app/feed/feed_date_normalizer.php
app/feed/feed_link_selector.php
app/feed/feed_xml_helper.php
app/feed/adapters/*
```

## Security regression

- owner lookup → stored URL validation → FeedSource → FeedFetcher順序維持
- client supplied Feed URLなし
- FeedFetcher → `app_safe_http_fetch()`維持
- `LIBXML_NONET`維持
- SimpleXML errorを`@`で隠さない
- Parser/Adapter層からoutbound HTTPなし
- `api_safe_feed_payload()`維持
- repository secret/runtime artifact scan PASS

## ZIP result

Final ZIPを別directoryへ再展開し、checkpoint manifestを照合した。

```text
Manifest expected entries: 286
Manifest actual entries:   286
Missing:                   0
Extra:                     0
SHA-256/size mismatch:     0
```

再展開treeに対してsource treeと同じ全test commandを実行した。

```text
PASS: 954
FAIL: 0
SKIP: 5
```

SKIP理由はsource treeと同一で、ZIP化による追加SKIP・失敗・file欠落はなかった。Documentation結果反映後のFinal ZIPでも同じmanifest/full regression gateを再実行し、結果が一致した。
