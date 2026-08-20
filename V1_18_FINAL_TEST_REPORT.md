# V1.18.0 final pre-Git test report

## Final revision / prerelease fixes
- `python tests/test_current_version_contract.py`: PASS 6 / FAIL 0
- `python tests/test_v1180_prerelease_fixes.py`: PASS 34 / FAIL 0
- `python tests/test_v118g_release_contract.py`: PASS 17 / FAIL 0
- `bash tests/run-v118.sh`: PASS
- `bash tests/run-v117.sh`: PASS
- `bash tests/run-v1171.sh`: PASS
- `bash tests/run-v1172.sh`: PASS
- `php -l app/version.php`: PASS
- `node --check public/js/calendar.js`: PASS
- `node --check public/js/camera-video-streaming.js`: PASS

## Current Regression
`tests/run-current.sh`は実行環境の1-command時間上限でArticle Actions Browser通過後にtimeoutしました。timeoutまでFAILはありません。残りのGame / Clock / Mobile interactionsからInformation Widgetsまでを同じ順序で継続実行し、全項目PASS / FAIL 0を確認しています。

主な後半結果:
- Article Actions Browser: PASS 34 / FAIL 0
- V1.18 pre-release fixes: PASS 34 / FAIL 0
- Information Widget contract: PASS 32 / FAIL 0
- Current Regression resumed segment: PASS

## Release status
- APP_VERSION: `1.18.0`
- APP_VERSION_LABEL: `RSS Reader Modernization 1.18.0`
- APP_ASSET_REVISION: `1.18.0-r2`
- Intended Git tag: `v1.18.0`
- DB migration / SQL: none
- New required config / secret: none
