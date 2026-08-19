# V1.17.2-A X API Widget Test Report

実施日: 2026-08-19

## Scope

V1.17.2-Aは新規X API Widget本体の実装段階です。Project方針どおり、この段階では全Version履歴を含むFull Regressionは実行せず、新規機能と影響範囲へ絞ったFocused／Compatibility testを実施しました。Full Current RegressionとRelease GateはV1.17.2-Bで実施します。

実X API通信は実Bearer Token／Developer Consoleの利用状態が必要なため、この環境では行っていません。HTTP request shape、Authentication boundary、Response normalize、Cache、error handlingはfake transportで検証しています。

## V1.17.2-A focused tests

`bash tests/run-v1172a.sh`

- X API／Cache／Validation／SSRF boundary runtime: **33 PASS / 0 FAIL**
- dashboard_widget persistence CRUD／owner scope: **13 PASS / 0 FAIL**
- static/browser/server contract: **30 PASS / 0 FAIL**
- PHP syntax for all changed server files: **PASS**
- JavaScript syntax (`x-widget.js`, `calendar.js`): **PASS**

主な確認内容:

- username validation 1〜15文字、leading `@` normalize。
- display count 3／5／10。
- 3件表示時もX API `max_results=5`。
- `post.fields=created_at`。
- `exclude=replies,retweets`。
- App-only Bearer TokenはServer fetch callbackにのみ渡し、normalized responseへ含めない。
- X API hostを`api.x.com`へ固定し、DNS pinningされたpublic IPだけをtransportへ渡す。
- 非X host拒否。
- 5分Cache、Transient failure時のstale fallback。
- Authorization／access failureではstale Postsを返さずfail closed。
- 非公開AccountのApp-only accessを明示Error。
- X CRUDは既存`dashboard_widget`を使用し、owner/type scopeを維持。
- Browserは`api_v1.php`だけを呼び、`window.location.reload()`を使用しない。
- Post本文はjQuery `.text()`でrender。
- X Cardの追加／差し替えは対象Cardだけで、既存Grid全体をre-appendしない。

## Relevant compatibility tests

既存Safe Fetch／SSRFとDashboard coreへの影響を重点確認しました。

- `test_sb09_fetch.php`: **42 / 42 PASS**
- `test_sb14_ssrf_matrix.php`: **40 / 40 PASS**
- `test_m1f_http_conditional.php`: **21 / 21 PASS**
- `test_m1g_http_retry.php`: **37 / 37 PASS**
- `test_v11d_dashboard_widget.php`: **39 / 39 PASS**
- `test_v17g_widget_grid_runtime.js`: **PASS**
- `test_v115_information_widgets.php`: **32 / 32 PASS**
- `test_current_information_widget_contract.py`: **32 / 32 PASS**
- `test_current_version_contract.py`: **6 / 6 PASS**
- `test_current_asset_contract.py`: **67 / 67 PASS**
- `test_v1171a_session_release.php`: **PASS**
- `test_v1171a_api_session_policy.py`: **7 / 7 PASS**
- `test_v1171e_hls_sri.py`: **6 / 6 PASS**

## Not executed in A

- X Developer Consoleの実Bearer Tokenを用いたlive request。
- Browser実機でのX API smoke。
- Full `tests/run-current.sh`。
- V1.17.2 final package／tag／release gate。

これらはV1.17.2-Bで、ユーザー環境の実API smoke結果を反映した後に実施します。
