# M1-F Test Report

Build: `RSS Engine M1-F / R1`

## Result

Source treeの全回帰と、配布ZIPを別directoryへ再展開した同一Gateの結果です。

```text
PASS: 1256
FAIL: 0
SKIP: 6
```

PHP構文確認:

```text
PHP files:    68
Syntax error: 0
```

## M1-F dedicated coverage

```text
HTTP validator / safe fetch: 21 PASS
Cache revalidation:          21 PASS
Architecture / static:       48 PASS
Process concurrency:         11 PASS
```

### HTTP validator and safe fetch

- strong / weak ETag
- Last-ModifiedのHTTP-date正規化
- CR/LF、NUL、制御文字、過大値の拒否
- `strtotime()`が解釈可能でもHTTP-dateではない文字列の拒否
- 許可Headerを`If-None-Match` / `If-Modified-Since`へ限定
- Validatorのeffective URL完全一致
- redirect元への非送信
- redirect先変更時の非送信
- 条件付きRequestだけHTTP 304を許可
- 条件なし304の拒否
- invalid response Validatorの破棄
- SSRF / redirect / TLS / DNS pinning継承

### Cache revalidation

- HTTP 200で本文とValidator保存
- Fresh Cacheで通信なし
- TTL境界後のHTTP 304
- 304で本文・body hash・body取得時刻を維持
- 304でvalidation時刻だけ更新
- 304後のFresh hit
- HTTP 200で本文を置換
- Validatorなし200で旧Validatorを消去
- M1-E schema 1読み込み互換
- schema 1からschema 2への更新
- 条件付きRequestのみ無効化
- stale-if-errorを追加していないこと
- current Parserで読めないstale Cacheを304で延命しないこと
- invalid cached Validatorの拒否

### Process concurrency

5つのPHP processを同時開始し、同一の期限切れCacheへアクセスしました。

```text
conditional upstream call: 1
revalidated result:        1
fresh cache hit:           4
successful result:         5
body_fetched_at:           unchanged
validated_at:              updated
temporary leftovers:       0
```

### Architecture and compatibility

- Validator処理は小さなhelper関数で実装
- Validator専用class hierarchyを追加しない
- owner lookupをCacheより前に維持
- 公開API / FrontendへValidatorを出さない
- DB / Stock / Parser / Adapter / Item identity変更なし
- Retry / stale-if-error / Cache-Control / Expires未実装
- Runtime CacheはGit管理外
- M1-A〜M1-Eの既存Gateを継続

## Protected-area comparison

M1-E / R1とのSHA-256 / directory比較で、次は完全一致しています。

```text
public/
database/
app/auth.php
app/session.php
app/session_storage.php
app/login_throttle.php
app/validation.php
app/common/common_db.php
app/feed/feed_source.php
app/feed/feed_source_mapper.php
app/feed/feed_parser.php
app/feed/adapters/
app/feed/item_identity.php
app/feed/item_identity_resolver.php
app/feed/normalized_item.php
```

## Build-environment limitations

Build環境にSimpleXML / mbstring / PDO SQLiteがないため、従来のlive Parser / integration test 6件はSKIPしました。M1-FのHTTP transport fake、Cache、filesystem、multi-process `flock()` testは該当extensionなしでも実行しています。

## Distribution verification

```text
ZIP file count:       316
Manifest entries:     315
Manifest missing:     0
Manifest extra:       0
Hash mismatch:        0
ZIP-reexpanded PASS:  1256
ZIP-reexpanded FAIL:  0
ZIP-reexpanded SKIP:  6
```

`docs/package-manifest.txt` 自身は自己参照になるためmanifest hash対象から除外し、残る315ファイルを照合します。

`rss.sql`、Legacy ZIP、`config/local.php`、実DB、logs、Session、runtime Cache JSON/Lockが配布物へ含まれていないことも確認しました。
