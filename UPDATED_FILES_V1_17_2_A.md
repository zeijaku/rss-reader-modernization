# V1.17.2-A 更新ファイル一覧

## Runtimeで必要な変更ファイル

- `app/version.php` — staged Asset Revisionを`1.17.2-a`へ。
- `app/bootstrap.php` — X Widget server moduleを読込。
- `app/common/common_conf.php` — private Bearer Token／Cache／Timeout設定。
- `app/dashboard_widget.php` — `x_timeline` type登録とconfig normalize。
- `app/information_widget.php` — 既存の汎用Widget persistence helperで`x_timeline`を許可。
- `app/http_fetch.php` — 既存DNS pinning／TLS boundaryを維持したまま、明示Bearer header用の限定inputを追加。
- `app/api.php` — X Widget CRUDとtimeline fetch API routing／error mapping。
- `app/x_widget.php` — X API client、Validation、Cache、stale fallback、CRUD wrapper。
- `public/js/calendar.js` — X CSS／JavaScriptをstaged revisionでlazy load。
- `public/js/x-widget.js` — X Timeline Widget UI／CRUD／target-only refresh。
- `public/css/x-widget.css` — Theme-aware X Widget layout。

## Configuration example

- `config/local.php.example`
- `config/.env.example`

実`config/local.php`や実Tokenは含みません。

## Focused test

- `tests/run-v1172a.sh`
- `tests/test_v1172a_x_widget.php`
- `tests/test_v1172a_x_widget_persistence.php`
- `tests/test_v1172a_x_widget.py`

## Review documentation

- `APPLY_NOTE_V1_17_2_A.md`
- `UPDATED_FILES_V1_17_2_A.md`
- `CHECKLIST_FOR_USER_V1_17_2_A.md`
- `V1_17_2_A_TEST_REPORT.md`

## 明示的に変更していないもの

- DB schema／Migration／SQL
- `config/local.php`
- CSP／`.htaccess`
- Xへの投稿・Like・Follow等のWrite API
- OAuth user-context flow
- Camera／Video／Mail等の既存runtime code
