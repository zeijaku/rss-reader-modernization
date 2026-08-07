# V1.7-D Test Report

## 対象

- Static Asset Cache Header
- 動的HTML／API／Errorのno-store
- Security Header
- Session Cache Limiter整理
- V1.7-C Asset URL一元化回帰
- Login／Logout／Error／API回帰
- Theme／Timer／Game／Swipe回帰
- Secure Baseline～V1.7の分割Full Regression

## Result

```text
PASS 8,091
FAIL 0
SKIP 18
```

単一Runnerは実行時間上限へ達するため、`tests/run.sh`と同じ順序を維持して区間分割しました。重複区間を除外して集計しています。

## SKIP

- PDO SQLite Driver不足
- SimpleXML／mbstring不足
- Chromium Runtime依存不足
- Version 1.0～1.6の旧正式版専用Release Gate

V1.7-D専用Test、Session、Login、Logout、API、Error、Asset、Theme、Timer、Icon Quest、Lights Out、SwipeにはSKIPがありません。

## Dynamic Header Test

PHP組込みHTTP Serverで次を確認しました。

- Private HTML: `private, no-store, max-age=0`
- API: `no-store, max-age=0`
- Error: `no-store, max-age=0`
- `Pragma: no-cache`
- `Expires: 0`
- Errorの`X-Robots-Tag: noindex, nofollow`

## `.htaccess`

静的構造Testで次を確認しました。

- `mod_headers` Guard
- CSS／JavaScript 1年Cache＋immutable
- Font／画像7日Cache
- X-Content-Type-Options
- Referrer-Policy
- X-Frame-Options
- Permissions-Policy
- 限定CSP
- HSTS未追加
- 全面CSP未追加

実Rental ServerでのResponse Headerは利用者Checklistで確認します。
