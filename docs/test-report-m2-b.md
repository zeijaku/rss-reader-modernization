# M2-B / R1 Test Report

## 対象

- Feed取得とDOM描画の責務分離
- Loading / Ready / Empty / Error状態
- 0件 / 空Feed
- 不正・不足API Response
- Timeout / 404 / upstream HTTP 502
- 欠損Channel / Item title
- 長い日本語 / Unicode / 絵文字title
- HTMLを含むtitleと危険なlink
- 最大5件表示
- Feed Request pending guard
- faviconのHTTPS-safeな明示参照
- SB-00〜15 / M1-A〜G / M2-A回帰
- Repository / secret / Runtime artifact scan

## Baseline

M2-A / R1の展開直後に `tests/run.sh` を実行しました。

```text
PASS: 1491
FAIL: 0
SKIP: 6
```


## M2-B最終結果

```text
PASS: 1575
FAIL: 0
SKIP: 6
PHP syntax checked: 71 files
JavaScript syntax: PASS
```

M2-Bで追加・拡張した主な確認数は次のとおりです。

- M2-B Feed structure: 47 checks
- M2-B Feed runtime: 35 checks
- Public HTTP smoke: favicon link / asset HTTP 200を追加

## PHP Dashboard描画確認

Fake PDOを使用したDashboardをPHP Built-in Serverで描画し、次を確認しました。

```text
Dashboard: HTTP 200
Feed cards: 8
Initial loading states: 8
Loading messages: 8
favicon.png: HTTP 200
dashboard.js: HTTP 200
dashboard.css: HTTP 200
Visible version: Frontend M2-B / R1
Application inline scripts: 0
```

## M2-B専用test

- `test_m2b_feed_structure.py`
  - Feed関数分離、状態、DOM安全性、API契約、favicon、依存追加なしを静的確認。
- `test_m2b_feed_runtime.js`
  - 小さなDOM / jQuery互換HarnessでFeed cardを実際に初期化し、各Response pathを実行。

Runtime testでは次を確認しています。

- cardごとに `feed.fetch` が1回だけ開始される。
- Dashboard JSを二重評価してもFeed Requestが増えない。
- 全RequestへCSRF tokenと `content_id` が付く。
- LoadingからReady / Empty / Errorへ遷移する。
- 正常Feedは5件だけ描画し、6件目は表示しない。
- `<script>` や `<b>` を含むtitleがHTMLとして実行されない。
- `javascript:` linkはAnchor / Stock buttonを作らない。
- 65個の絵文字を64個の完全な文字 + `...` として省略する。
- Channel / Item title欠損時にfallbackを表示する。
- 0件FeedでChannel linkを保持したままEmpty messageを表示する。
- 不正Response、Timeout、404、HTTP 502をcard単位のErrorへ変換する。
- 全Response pathでpending flagを解除する。

## Backend非変更確認

M2-A / R1とのfile comparisonで、次が同一であることを確認しました。

- `app/feed/`
- `app/api.php`
- `app/auth.php`
- `app/session.php`
- `app/http_fetch.php`
- `app/validation.php`
- `app/common/common_conf.php`
- `app/common/common_db.php`
- `public/api_v1.php`
- `public/logout.php`
- `database/`
- `config/`

## SKIP

```text
PDO SQLite integration tests: driver unavailable
SimpleXML / mbstringを必要とするlive parser / adapter / identity tests
```

既存どおりFake PDO / transport、fixture、static invariantで補完し、配置先ではMySQLと実Feedを使って確認します。
