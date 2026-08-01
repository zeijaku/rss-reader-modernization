# M2-A / R1 Test Report

## 対象

- Dashboard固有JavaScript / CSSの外部Asset化
- PHP生成JavaScriptの廃止
- API Request helper / CSRF
- Event登録と二重登録防止
- State-changing requestの二重送信防止
- Feed DOM selectorとXSS-safe描画
- SB-00〜15 / M1-A〜G回帰
- Repository / secret / Runtime artifact scan

## Baseline

M1-G / R1の展開直後に `tests/run.sh` を実行しました。

```text
PASS: 1423
FAIL: 0
SKIP: 6
```

## M2-A最終結果

```text
PASS: 1491
FAIL: 0
SKIP: 6
PHP syntax checked: 71 files
JavaScript syntax: PASS
```

M2-Aでは既存testを外部 `dashboard.js` 構成へ追従させ、次の専用testを追加しました。

- `test_m2a_frontend_structure.py`: 51 checks
- `test_m2a_dashboard_runtime.js`: 14 checks
- Public HTTP smokeへのDashboard CSS / JavaScript確認: 3 checks

## M2-A専用確認

- `dashboard.css` / `dashboard.js` の存在と読込順序
- `index.php` にApplication inline JavaScriptが残っていないこと
- PHPが `fetch_content()` 呼出しを生成しないこと
- Feed cardの `data-feed-content-id` 初期化
- API endpoint、POST、JSON、CSRF contract維持
- `feed.fetch` が `content_id` だけを送信すること
- Event namespace、`off()` → `on()`、初期化済みguard
- Dashboard scriptを2回評価してもHandler数が増えないこと
- 連続clickでpending中のAPI Requestが1回になること
- timeout後にButtonが再有効化されること
- 成功時の既存page reload behavior維持
- `.html()` / `innerHTML` / `insertAdjacentHTML` / `eval()` 等を使用しないこと
- Feed textの `.text()` 挿入、linkの `noopener noreferrer`、最大5件維持

## PHP画面描画確認

Fake PDOを使用した検証用DashboardをPHP Built-in Serverで描画しました。

```text
Feed cards: 8
Application inline scripts: 0
dashboard.js reference: 1
dashboard.css reference: 1
Visible version: Frontend M2-A / R1
GET /js/dashboard.js: HTTP 200
GET /css/dashboard.css: HTTP 200
```

## Backend非変更確認

M1-G / R1とfile comparisonを行い、次が同一であることを確認しました。

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

既存方針どおりfixture、Fake PDO / transport、static invariantで補完しています。配置先ではMySQL環境でLogin、Feed CRUD、Stock、Settings、4タブ、実RSS / Atomを確認してください。
