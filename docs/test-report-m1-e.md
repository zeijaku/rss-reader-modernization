# M1-E Test Report

Build: `RSS Engine M1-E / R1`

## Result

Source treeの分割全回帰と、配布ZIPを別directoryへ再展開した同一gateで確認した結果です。

```text
PASS: 1156
FAIL: 0
SKIP: 6
```

## M1-E dedicated coverage

```text
Executable cache lifecycle: 42 PASS
Architecture/static:        52 PASS
Process concurrency:        10 PASS
```

### Executable cache lifecycle

- URL SHA-256 keyとquery差分
- content ID / owner非依存の同一URL共有
- cache miss / fresh hit / TTL直前 / TTL境界
- Cache disabled
- valid Fetch + valid Parseだけ保存
- HTTP failure / parse failure / oversized body非保存
- stale-if-errorを行わないこと
- invalid JSON / Base64 / SHA-256 / source scope / future timestamp復旧
- current Parserで読めないCache本文のinvalidate + refresh
- effective URL metadata
- directory/file permission
- symlink target拒否
- filesystem setup failure時のbypass
- Lock timeout時のuncached Fetchと非書込み
- parse failure後のLock解放

### Process concurrency

5つのPHP processをbarrierで同時開始し、同一URLへアクセスします。

Expected:

```text
upstream transport call: 1
cache miss result:       1
cache hit result:        4
successful result:       5
cache document:          1
temporary leftovers:     0
```

### Architecture / security

- owner lookupがCache serviceより前
- raw client URLをCache keyに使用しない
- FetcherがSB-09 `app_safe_http_fetch()`を継続利用
- Cache hit / network missの両方でParserとItem identityを実行
- Parse成功後だけatomic write
- JSON / Base64 / SHA-256、PHP object serialization不使用
- CacheがDocumentRoot外かつGit ignore
- DB / Frontend / Stock / public API field変更なし
- ETag / Last-Modified / Retry / stale-if-error未実装
- runtime Cache artifactのrepository scan

## Build-environment limitations

Build環境にSimpleXML / mbstring / PDO SQLiteがない場合、従来のlive parser/integration testはSKIPします。M1-EのFake Parser/Transport、filesystem、multi-process `flock()` testは該当extensionなしでも実行します。

## Distribution verification

```text
Manifest expected: 308
Manifest actual:   308
Missing:           0
Extra:             0
Hash mismatch:     0

ZIP-reexpanded PASS: 1156
ZIP-reexpanded FAIL: 0
ZIP-reexpanded SKIP: 6
```

`rss.sql`、Legacy ZIP、`config/local.php`、実DB、logs、Session、runtime Cache JSON/Lockが配布物へ含まれていないことも確認しました。
