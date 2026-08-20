# Version 1.18.0 Release Gate Test Report

Date: 2026-08-20

## Result

Version 1.18.0 Release Gateは自動検証範囲でPASS。

## Current regression

`tests/run-current.sh`はこの実行環境の1 command実行時間制限を超えるため、同じRunnerのCommand列を前半／後半に分けて全項目を実行した。

- 前半: PHP syntax / Core & Secure Baseline / RSS engine / Frontend runtime / Dashboard & Widget core / Feed & Search / Article Actions static — FAIL 0
- `test_v12d_article_actions_browser.py` — PASS 34 / FAIL 0
- 後半: Game / Clock / Mobile / Assets / Login / Grid / Calendar / Stock / Settings Browser / Information Widgets — FAIL 0
- `test_current_information_widget_contract.py` — PASS 32 / FAIL 0

実行時間制限による分割であり、Test failureによる中断ではない。

## Compatibility / focused suites

- `tests/run-v117.sh` — PASS
- `tests/run-v1171.sh` — PASS
- `tests/run-v1172.sh` — PASS; V1.17.2 release-marker testはlater release compatibility contractへ更新
- V1.18-B health probe — PASS 28 / FAIL 0
- V1.18-C history/statistics — PASS 33 / FAIL 0
- V1.18-D state/outage/quality — PASS 32 / FAIL 0
- V1.18-E UI/polling — PASS 40 / FAIL 0
- V1.18-F scope lock — PASS 36 / FAIL 0
- V1.18-G release contract — PASS 17 / FAIL 0

## Syntax / security

- Repository PHP syntax check — PASS in Current Regression
- `node --check public/js/connection-monitor.js` — PASS
- `node --check public/js/calendar.js` — PASS
- `node --check public/js/camera-video-streaming.js` — PASS
- high-signal source secret scan — PASS
- `test_current_version_contract.py` — PASS

## Release packaging

- Runtime deterministic builder — PASS
- Runtime package verifier — PASS
- Complete source deterministic builder — PASS
- Complete source package verifier — PASS
- Runtime / Complete packages exclude `config/local.php`, runtime DB, log, session, cache, other ZIPs and Python cache according to builder/verifier contracts.

## Release-time fix discovered by regression

Version markerを1.18.0へ更新した後、V1.17 compatibility testsによりCamera / X等のdynamic asset loaderとCamera streaming fallback stylesheetに1.17.2 cache keyが残っていることを検出。

- `public/js/calendar.js` dynamic Camera / X asset URLを`?v=1.18.0`へ更新
- `public/js/camera-video-streaming.js` fallback stylesheet URLを`?v=1.18.0`へ更新

修正後、V1.17 / V1.17.1 / V1.17.2 compatibility suitesはPASS。

## Manual production confirmation still required

Automationでは実利用Network／Browser／Themeを完全再現しないため、`CHECKLIST_FOR_USER_V1_18_G.md`のBrowser確認を本番またはStagingで実施する。DB Migration／SQL実行は不要。
