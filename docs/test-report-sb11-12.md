# SB-11〜SB-12 Test Report

Build: `Secure Baseline SB-12 / R1`

## Build environment

- PHP CLI: 8.4.16
- PDO core: available
- `pdo_mysql`: unavailable in this sandbox
- cURL: unavailable in this sandbox
- SimpleXML: unavailable in this sandbox
- mbstring: unavailable in this sandbox

そのためreal PHP→MySQL、real cURL/TLS、real SimpleXML/mbstring Feed parseはsandboxでは実行できない。
SB-11でFeed parserを変更したため、RSS 2.0 / Atomの実Feed確認はdeployment-side mandatory checkとする。

## Full regression suite

```bash
./tests/run.sh
```

Final source-tree run:

- **32 PHP files linted successfully**
- **482 explicit PASS checks**
- **0 FAIL**

## SB-11/SB-12 dedicated static checks

`tests/test_sb11_12_static.py`: **46 checks passed**.

主なcoverage:

- exact 0/1/2/3 tab mapping
- Navbar tab label mapping
- RSS create location
- no Legacy Text-success fallback
- RSS2 / Atom / RSS1 root logic
- default namespace / RSS1 namespace handling
- UTF-8 XML declaration normalization
- zero-item and <5 item policy
- partial Feed/Stock row closure
- Stock DB order preservation
- tabs.update isolation
- tabs/settings one-AJAX-submit path
- current selected/checked settings
- Content edit style retention
- generic icon handler isolation
- duplicate modal id correction
- Navbar link enabled
- no Stock second fetch
- missing UA handling
- obsolete runtime setting checks
- E_ALL / debug error policy
- PHP 8.1+ gate
- no global mbstring mutation

## PHP 8 dynamic runtime

`tests/test_sb12_runtime.php` converts PHP warnings/notices/deprecations into exceptions and validates null/malformed boundaries, Feed parser failure behavior, UI attribute helpers, missing request metadata, and `update_setting()` reflection.

`tests/test_sb12_signatures.php` token-scans all runtime PHP declarations under `app/`, `public/`, `tools/` and rejects optional-before-required signatures.

`tests/test_sb12_public_warnings.py` starts PHP's built-in server with `APP_DEBUG=true` and performs anonymous/login/logout-invalid-path smoke traffic, asserting no PHP Warning/Notice/Deprecated/Fatal/TypeError is emitted.

## Earlier security regression

The full suite reruns SB-00〜10 tests including:

- session/auth/password/throttling
- API/authorization/CSRF two-user tests
- strict validation
- SSRF/DNS/redirect/TLS policy tests
- XSS Feed payload and frontend output checks
- public HTTP smoke
- secret pattern scan

## Packaging acceptance

Release packaging must:

1. remove runtime Session/throttle/log artifacts
2. regenerate package manifest
3. create ZIP
4. integrity-test ZIP
5. extract ZIP to a fresh directory
6. rerun full test suite from extracted tree
7. compute final SHA-256

Final package acceptance was completed with:

- ZIP integrity: pass
- packaged secret/runtime filename scan: pass
- fresh ZIP extraction full suite: **32 PHP lint OK / 482 PASS / 0 FAIL**

The final archive SHA-256 is distributed in the adjacent `.zip.sha256` file so the archive does not need to contain a self-referential checksum.
