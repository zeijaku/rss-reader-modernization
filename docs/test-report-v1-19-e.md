# V1.19-E Test Report

## Result

V1.19.0-RC1 Release Candidate gate: **PASS**

V1.19-Eでは、V1.19-B/C/Dを統合した候補に対してCurrent Regression、V1.17系、V1.18互換、V1.19 focused gate、Security scan、Runtime/Complete package build・verifyを実施した。

## Test environment

- PHP: 8.4.23 CLI
- Python: 3.13.5
- Node.js: v22.16.0
- Apache: 2.4.x (`apache2ctl -t` = `Syntax OK`)

## Executed gates

| Gate | Result | Notes |
| --- | --- | --- |
| Current regression | PASS | `tests/run-current.sh` completed with exit 0 |
| V1.17 focused | PASS | Camera / Video and related compatibility checks |
| V1.17.1 focused | PASS | Release Gate checks completed; reported groups include 52/0, 48/0, 47/0, 6/0, 48/0 |
| V1.17.2 compatibility | PASS | 34/0 release-gate contract |
| V1.18 compatibility | PASS | Connection Monitor / prerelease compatibility groups all PASS |
| V1.19-B architecture | PASS | 40/0 |
| V1.19-C hardening | PASS | 38/0 plus Registration Throttle and API 413 HTTP checks |
| V1.19-D cleanup/docs | PASS | 62/0 plus Account Settings runtime/render checks |
| V1.19-E RC contract | PASS | 35/0 |
| Current asset contract | PASS | 68/0 |
| Cache/security header contract | PASS | 16/0 |
| High-signal secret scan | PASS | app/public/config/tools checked |
| Apache configuration syntax | PASS | `Syntax OK` |
| Public PHP endpoint inventory | PASS | exactly 7 expected endpoints |
| RC runtime package build/verify | PASS | CRC, manifest, path safety, private-file exclusions, metadata |
| RC complete package build/verify | PASS | source manifest and metadata verified |

## Full regression note

`tests/run-current.sh` is long enough to exceed one interactive tool execution window in this environment. During the first pass the suite was completed in logical segments. After compatibility-test maintenance was finished, the complete runner was executed again as one process with output redirected to a log; it finished with exit code `0` and `PASS: current regression suite completed`.

This is an execution-environment limitation only; it is not a test skip.

## Compatibility-test maintenance during V1.19-E

Several historical static tests assumed that all API handlers remained physically inside `app/api.php`, or that the current visible version must remain exactly V1.18.x. V1.19-B intentionally moved handlers into `app/api/*.php`, and V1.19-E intentionally changes the candidate version to `1.19.0-rc1`.

The affected tests were updated so that they continue to enforce the original behavior/security contract against the logical API source (facade + modules) and later compatible version lines. Runtime/API behavior assertions were not removed or weakened.

Representative maintenance:

- SB05-07 / SB10 / SB11-12 / SB14 static API-source checks
- Current Information Widget API-source contract
- V1.2-D semantic-version acceptance for `-rcN`
- V1.17.1 / V1.17.2 / V1.18 release compatibility version checks
- hls.js SRI expectations updated to the browser-computed SHA-384 already confirmed during V1.19-C

No application defect was identified by these static-test failures.

## Security / package notes

- DB migration / SQL: none
- New required config / secret: none
- HSTS rollout: deferred
- Strict `script-src` / `style-src` CSP rollout: deferred
- Trusted-proxy HTTPS handling: deferred
- V1.19.0-RC1 is not a final/taggable release
- GitHub write operations were not performed

## Manual verification still required

Automated tests cannot fully reproduce the production web-server, browser cache, actual external feeds/APIs, camera streams, network conditions, or smartphone interaction. Use `docs/v1-19-e-production-checklist.md` before moving to V1.19-F.
