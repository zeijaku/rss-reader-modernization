# V1.2-A Test Report

## Summary

- PASS: **3,744**
- FAIL: **0**
- SKIP: **10**

`tests/run.sh`は単一Processで実行すると実行環境のCommand上限へ達したため、同じ順序を前半、V1.1-D以降、未完了のV1.1-C runner、V1.2-A Browserへ分割して完走した。分割によるTest内容の省略はない。

## V1.2-A focused checks

### Authentication / Honeypot

- Login／Registration専用HTML・CSS・JavaScript
- Bootstrap sample class非依存
- PC／Smartphone breakpoint
- Focus表示、Native Enter submit
- Password表示切替とARIA状態
- 二重送信防止
- Honeypot通常未入力
- Honeypot入力あり
- Neutral field name
- Keyboard、Screen Reader、Autofill除外
- CSRF不正が優先されること
- Login Throttle併用
- Honeypot値とRaw EmailがThrottle JSONへ残らないこと
- Registration generic error

### Session / Logout

- Login時Session ID変更
- Logout GET拒否
- Logout CSRF不正拒否
- Logout後に旧Session IDで認証復元不可
- Logout後に新匿名Session IDを発行
- Logout Flashが1回だけ表示
- Idle timeout後に認証解除
- Timeout時Session ID変更
- Timeout FlashがLogoutと別文言
- Direct anonymous accessはNoticeなし

### Common error

- 403／404／500／503のStatus維持
- 共通Design／基本文言
- `noindex,nofollow` Meta
- `X-Robots-Tag`
- RSS Readerへ戻るLink
- File Path、Stack、Password、例外Message非表示
- Runtime Configuration failureでも共通500
- 12桁Reference ID
- API Bootstrap／Configuration exceptionがJSONのまま
- Minimal Error RendererがDB／Session非依存
- `.htaccess`のErrorDocumentとRewrite保全
- Unknown routeが200ではなく404

### Regression

- PHP syntax
- JavaScript syntax
- Secure Baseline SB-00～15
- Authentication、Session、CSRF、Authorization
- SSRF、XSS、Validation、PDO static／fixture tests
- RSS 2.0／RSS 1.0／Atom adapter tests
- Feed Cache、ETag、304、Retry、Backoff、stale-if-error
- M2 Frontend／Accessibility／Responsive／Browser
- Tracking Parameter
- NEW state
- Dashboard Widget reorder
- Clock、Memo、Task、Calendar
- Mobile Swipe／Spinner／Task date layout
- Account Settings
- Stock／Feed／Dashboard render regression
- Secret pattern scan

## SKIP details

- PDO SQLite integration: 実行環境にPDO SQLite Driverなし
- SimpleXML／mbstringを必要とするLive parser tests: 実行環境にExtensionなし（5件）
- M2-F Chromium dependency smoke: DBus runtime dependency不足
- M2-G／M4-A～G: Version 1.0用の歴史的Release Gate
- V1.1-K: `APP_VERSION=1.1.0`専用Final Release Gate

V1.2-A独自Browser Testは利用可能なPlaywright＋Chromiumで実行し、9件すべてPASSした。

## Archive recheck

完成候補ZIPを新しいDirectoryへ再展開し、以下を再実行した。

- ZIP CRC／Path traversal／秘密情報Name Scan: PASS
- `SOURCE_MANIFEST.sha256`: PASS
- Application／Public／Tools PHP syntax: PASS
- `public/js/auth.js` syntax: PASS
- V1.2-A architecture: 40 PASS
- Authentication HTTP: PASS
- Session HTTP: PASS
- Common error HTTP: PASS
- Playwright＋Chromium Browser: 9 PASS
- Public HTTP smoke: PASS
- Documentation／Local link／Secret pattern: PASS
- `database/`と添付基準SourceのDiff: 0
- `config/local.php`: 非同梱

再展開TestによりSession／Error Log等のRuntime FileがTest展開先へ生成されるが、最終ZIPはTest前のClean Sourceから再構築し、Runtime Dataを含めていない。
