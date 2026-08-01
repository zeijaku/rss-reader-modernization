# M1-D Test Report

Build: `RSS Engine M1-D / R1`

## Result

Source treeの分割全回帰と、最終配布ZIP再展開後の同一gateで確認した結果です。

```text
PASS: 1050
FAIL: 0
SKIP: 6
```

## M1-D dedicated coverage

### Executable identity tests

- ItemIdentity value/basis validation
- readonly model
- source-id → link → fingerprint priority
- same item stability
- Feed URL scope separation
- duplicate registration semantics（content_id/owner非依存）
- title/link/content/date change behavior
- no aggressive URL canonicalization
- CRLF/LF fingerprint normalization
- empty item deterministic fallback
- long Unicode ID
- invalid UTF-8 substitution
- malicious URL-like source ID as opaque input
- public five-field array compatibility

### Adapter / fixture coverage

- RSS 2.0 guid、blank guid、isPermaLink true/false
- RSS 1.0 RDF `about`
- Atom `id`、blank id
- link fallback
- fingerprint fallback
- optional live SimpleXML parser matrix

### Architecture / security coverage

- API uses validated configured `FeedSource::url` as scope
- client URL and redirect effective URL are not scope sources
- raw identity metadata is absent from Frontend/public array
- no DB identity column or Stock persistence
- resolver has no network, DB, filesystem, logging, retry or cache behavior
- existing Fetcher/SSRF/XSS/Auth/Session/CSRF boundaries remain under full regression

## Build-environment limitations

Build環境にSimpleXML / mbstring / PDO SQLiteがない場合、extension依存のlive parser/integration testsはSKIPします。Static fixture、resolver executable tests、API/architecture testsはSKIPせず実行します。配置先では`bash tests/run.sh`を実行し、M1-D live identity adapter matrixがSKIPされないことを推奨します。
