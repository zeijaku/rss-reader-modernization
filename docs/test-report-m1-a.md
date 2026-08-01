# Test Report — RSS Engine M1-A / R1

## Objective

Fetcher / Parser分離とNormalized Item導入によって、Secure Baseline SB-15 / R3のSecurity behavior、API contract、RSS/Atom behaviorを壊していないことを確認する。

## Dedicated M1-A checks

`tests/test_m1a_feed_engine.php`

- NormalizedItemの5field shape
- NormalizedItem → SB-10 API sanitization / URL validation boundary
- nullable field保持
- readonly immutability
- FeedFetcher success path
- effective URL / status / body保持
- validated DNS IPのtransport引継ぎ
- response size / connect timeout / total timeout引継ぎ
- loopback解決時のSSRF block
- blockされたtargetがtransportへ到達しないこと
- FeedParser null / empty body failure behavior
- `rss_parse` compatibility subclass
- SimpleXML + mbstringがある環境ではRSS2/RSS1/Atomをnormalized objectで追加検証

`tests/test_m1a_architecture.py`

- Bootstrap module order
- Parser implementationが `common_func.php` から分離されていること
- APIがFeedFetcher / FeedParser boundaryを使用すること
- owner lookup → stored URL validation → Fetcherのordering
- client raw Feed URL非受理
- blocked error classification維持
- `api_safe_feed_payload()` boundary維持
- `LIBXML_NONET` 維持
- RSS2/RSS1/Atom recognition維持
- M1-Aでcache/ETagへscope creepしていないこと

## Existing regression suite

`tests/run.sh` でSB-00〜SB-15の既存testを継続実行する。

M1-Aでparser file locationが変わったため、static testは「旧fileに実装があること」ではなく「新FeedParser moduleで同じsecurity/behavior invariantが成立すること」を検証するよう更新した。

## Build environment limitation

この生成環境のPHP CLIにはSimpleXML、mbstring、PDO SQLite、cURL extensionがない。

そのため既存suiteとM1-A専用suiteのうち、これらのextensionが必要なlive parser / SQLite integrationはSKIPされる。FetcherはSB-09のtest resolver / transportを用いてnetwork policyを実行検証する。

配布先では `tools/healthcheck.php` と実RSS 2.0 / RSS 1.0 / Atomによるsmoke testを追加実施すること。

## Source-tree final result

`tests/run.sh` final run:

```text
PASS lines: 793
FAIL lines: 0
SKIP lines: 4
```

SKIP内訳:

- PDO SQLite integration: build environmentにdriverなし
- live SimpleXML fixture parsing: SimpleXML / mbstringなし
- SB-14 live parser matrix: SimpleXML / mbstringなし
- M1-A live normalized parser checks: SimpleXML / mbstringなし

SKIP対象以外のSecurity / API / Session / Auth / SSRF / XSS / DB static/integrity / repository scan / M1-A architecture testはPASSしている。
