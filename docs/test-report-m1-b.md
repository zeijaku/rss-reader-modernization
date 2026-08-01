# M1-B Test Report

Build: `RSS Engine M1-B / R1`

## Scope

M1-B release gateは次を対象にした。

- FeedSource model / immutability / minimal field shape
- FeedSourceMapperのtype/overflow/owner validation
- raw DB `content_value` bypass防止
- FeedFetcherのFeedSource interface
- DNS pinning / timeout / body limit / private address block継承
- malformed owned DB rowのfail-closedとno outbound fetch
- M1-A Fetcher / Parser / Normalized Item regression
- SB-00〜15 Security / Auth / Session / CSRF / SSRF / XSS / PDO / Validation / PHP 8 / DB integrity / docs regression
- repository leak / secret pattern / package consistency

## Automated result

`tests/run.sh` の順序を、実行ツールの1command時間上限を避けるため2segmentに分けて最後まで実行した。

```text
PASS: 858
FAIL: 0
SKIP: 4
```

M1-B専用内訳:

```text
Executable FeedSource checks: 34 PASS
Architecture/static checks:    29 PASS
SB-05..07 API checks:           33 PASS
```

SB-05..07ではM1-B追加分として、malformed owned rowを500 `internal_error` でfail-closedし、outbound fetchが発生しないことを2check追加した。

## SKIP

```text
PDO SQLite integration tests
live SimpleXML fixture parsing
SB-14 live parser matrix
M1-A live normalized parser checks
```

Build環境にPDO driver、SimpleXML、mbstringがないため。M1-B自体のFeedSource/Mapper/Fetcher testはFake resolver/transportを使用して全て実行した。

## Healthcheck

```text
PHP: 8.4.16
Build marker: RSS Engine M1-B / R1
STATUS: NOT READY
```

このBuild環境にはproduction用private config、PDO MySQL、cURL、SimpleXML、mbstringがないため、healthcheckは意図どおりNOT READY。配置先ではこれらを有効にして実RSS/Atom smoke testを行う。

## Security / scope audit

M1-A / R1との比較でProduct code差分は次だけ。

```text
app/api.php
app/bootstrap.php
app/feed/feed_fetcher.php
app/feed/feed_source.php             new
app/feed/feed_source_mapper.php      new
app/version.php
```

次はM1-Aとbyte-equivalentであることを確認した。

```text
public/
database/
app/http_fetch.php
app/validation.php
app/auth.php
app/session.php
app/session_storage.php
app/login_throttle.php
app/common/common_db.php
app/common/common_conf.php
app/common/common_func.php
```

したがってM1-BではFrontend、DB schema、SSRF transport実装、Auth/Session/Validationを変更していない。

## Acceptance gate

- PHP syntax errorなし
- M1-B model/mapper/transport/API test FAIL 0
- M1-A regression FAIL 0
- SB security/regression FAIL 0
- repository leak scan PASS
- secret pattern scan PASS
- docs/version marker PASS
- protected product scope audit PASS
- ZIP integrity PASS
- checkpoint manifest: expected 266 / actual 266 / missing 0 / extra 0 / mismatch 0
- ZIP再展開後の全回帰: PASS 858 / FAIL 0 / SKIP 4
