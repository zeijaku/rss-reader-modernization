# V1.18.0 pre-Git R2 test report

## Focused fixes
- `python tests/test_v1180_prerelease_fixes.py`: PASS 16 / FAIL 0
- `php tests/test_v17f_persistent_login.php`: PASS 21 / FAIL 0
- `php tests/test_sb03_session.php`: PASS
- PHP syntax: app/session.php, app/persistent_login.php, app/api.php, public/api_v1.php PASS
- `node --check public/js/dashboard.js`: PASS

## Current regression
The Current Regression suite exceeds the execution limit when run as one command in this environment. It was executed in the same split method used for the V1.18-G gate:
- first segment through Article Actions static checks: PASS, no FAIL before timeout
- remaining segment from Article Actions browser checks through Information Widgets: PASS, no FAIL
- Article Actions browser: PASS 34 / FAIL 0
- Information Widget contract: PASS 32 / FAIL 0

## Compatibility / V1.18
- `bash tests/run-v118.sh`: PASS, including B/C/D/E/F/G contracts and the new pre-release fix contract
- `bash tests/run-v117.sh`: PASS
- `bash tests/run-v1171.sh`: PASS
- `bash tests/run-v1172.sh`: PASS

## Package verification
- Runtime package builder/verifier: PASS
- Complete source package builder/verifier: PASS
- No DB schema, migration, SQL, or new config requirement.

## Browser limitation
Automated CSS layout rendering is not treated as evidence in this environment because headless Chromium is unstable here. The Smartphone Calendar width fix is therefore also listed as an explicit production-device check.
