# V1.13-B R2 Focused Test Report

## 目的

V1.13-B R1のStock分離を維持したまま、公開URLだけを `stock.php` から `/stock` へ変更する。
DB/API/Stock Mutationは変更しない。

## 結果

- PHP Syntax: `public/index.php` PASS
- PHP Syntax: `public/stock.php` PASS
- V1.13-B Stock split: PASS 85 / FAIL 0
- V1.13-B Extensionless Route: PASS 15 / FAIL 0
- V1.8 Stock search / sort / pagination / page clamp / UI / Task target / render: PASS
- V1.1-D Dashboard render: PASS
- M2-D responsive/UI + Dashboard render: PASS
- SB-10 XSS/output static: PASS 35 / FAIL 0
- SB-11/SB-12 static: PASS 47 / FAIL 0
- V1.3-C Header render (Dashboard + Stock, dark/primary/light): PASS 69 / FAIL 0
- V1.2-A architecture / `.htaccess` error routing: PASS 40 / FAIL 0
- V1.7-D cache/security header checks: PASS 34 / FAIL 0
- Session layout checks: PASS 11 / FAIL 0
- UTF-8 BOMなし / LF維持 / trailing whitespaceなし
- DB Migration追加なし

## Apache 2.4 Rewrite実動確認

Application Root DocumentRoot:
- `/stock?sort=oldest` -> 200（内部 `public/stock.php`）
- `/stock.php?sort=oldest` -> 302 `/stock?sort=oldest`
- `/public/stock.php?q=AI` -> 302 `/stock?q=AI`

`public/` DocumentRoot:
- `/stock?sort=oldest` -> 200（内部 `stock.php`）
- `/stock.php?sort=oldest` -> 302 `/stock?sort=oldest`

Subdirectory設置:
- `/rss/stock?sort=oldest` -> 200
- `/rss/stock.php?sort=oldest` -> 302 `/rss/stock?sort=oldest`
- `/rss/public/stock.php?q=AI` -> 302 `/rss/stock?q=AI`

`THE_REQUEST` を条件にしているため、`/stock` の内部RewriteでRedirect Loopは発生しない。

## Test Policy

R2はURL Rewriteの小変更のため、V1.13全体の `tests/run.sh` Full Regressionは繰り返していない。
変更箇所とStock分離で影響する過去RegressionをまとめてFocused実行した。
Full RegressionはV1.13最終Checkpointで実施する。
