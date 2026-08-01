# M2-C / R2 Test Report

## 対象

- doctype / language / landmark / heading
- Skip link / Page Top
- Form / Button / Label / fieldset / legend
- Feed region / aria-busy / live status
- Drawer aria-expanded / Escape / Tab / Focus return
- Modal Focus return
- visible focus / reduced motion
- Keyboard submit
- XSS-safe DOM / API / CSRF回帰
- SB-00〜15 / M1-A〜G / M2-A〜B回帰
- Repository / secret / Runtime artifact scan

## Baseline

M2-B / R1の展開直後に `tests/run.sh` を実行しました。

```text
PASS: 1575
FAIL: 0
SKIP: 6
```

## M2-C最終結果

```text
PASS: 1714
FAIL: 0
SKIP: 6
PHP syntax checked: 71 files
JavaScript syntax: PASS
```

M2-Cで追加・拡張した主な確認は次のとおりです。

- M2-C accessibility structure: 72 checks
- M2-C accessibility runtime: 19 checks
- M2-C Login layout regression: 5 checks
- M2-C authenticated Dashboard render: 29 checks
- Public HTTP smokeへdoctype / lang / Skip link / main / footer確認を追加
- M2-B Feed runtimeへaria-busy / role / semantic Stock button確認を追加
- M2-A runtimeをForm submit構造へ追従

## M2-C専用test

### `test_m2c_accessibility_structure.py`

- HTML / PHP / JavaScript / CSSを横断し、landmark、Button、Form、Label、ARIA、Focus処理、安全なDOM、API契約を確認。

### `test_m2c_accessibility_runtime.js`

小さなDOM / jQuery互換Harnessで次を実行しています。

- Drawer Openで `aria-expanded=true` とlabel更新。
- Drawer Open時に最初の項目へFocus。
- EscapeでDrawer closeを呼ぶ。
- Shift+Tabで先頭から末尾、Tabで末尾から先頭へ循環。
- Drawer Close後に起動ButtonへFocusを戻す。
- Modal Close後に起動元へFocusを戻す。
- Page Topでdefault jumpを止め、mainへFocusを移す。
- RSS追加・変更Formのsubmit handlerが存在する。

### Feed runtime拡張

- Loading中の `aria-busy=true`。
- Ready / Empty / Errorで `aria-busy=false`。
- Loading rowはstatus、Error rowはalert。
- 動的Stock操作がButtonで、記事titleをaccessible nameへ含む。

## Dashboard描画確認

Fake PDOと実際の `public/index.php` を使用して、認証済みDashboardをPHPで描画しました。8枚のFeed card、unique id、Form / Label、Drawer Button、landmark、Version、warningなしをHTML parserで確認しています。

Browser固有の見た目、contrast、実際のTab順序は配置先Checklistで確認します。Build環境ではHeadless Chromiumからlocalhostへの接続が制限されていたため、Login中央配置はCSS構造の回帰testと配置先での目視確認を併用します。

## Backend非変更確認

M2-B / R1とのfile comparisonで、次が同一であることを確認します。

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

既存どおりFake PDO / transport、fixture、static invariantで補完し、配置先ではMySQL、実Feed、Keyboard、各Themeを確認します。


## Login layout regression

- Login / Registerを包む `main.login-main` が幅100%であることを静的回帰テストへ追加。
- Build環境ではlocalhostへのBrowser接続制限があるため、配置先でLogin / Register両画面の中央配置を目視確認する。
