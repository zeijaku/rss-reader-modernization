# V1.33-I Final Test Report

実行日: 2026-09-09

対象: `RSS Reader Modernization 1.33.0`

## Summary

| Gate | PASS | FAIL | SKIP | Result |
|---|---:|---:|---:|---|
| Release-ready contract | 16 assertions | 0 | 0 | PASS |
| V1.33-I formal release contract | 20 assertions | 0 | 0 | PASS |
| V1.33 Calendar Python static/contract | 241 assertions | 0 | 0 | PASS |
| V1.33 Calendar JavaScript behavior/DOM | 130 assertions | 0 | 0 | PASS |
| Asset loader behavior | 22 assertions | 0 | 0 | PASS |
| Generic version/workflow/release-flow | 93 assertions | 0 | 0 | PASS |
| Current asset/cache/drawer/information/remote/version static regression | 282 assertions | 0 | 0 | PASS |
| Current Account Security / Session / Audit static regression | 5 suites | 0 | 0 | PASS |
| Calendar architecture regression | 69 assertions | 0 | 0 | PASS |
| Public JavaScript syntax | 54 files | 0 | 0 | PASS |
| Python release-tool syntax | 6 files | 0 | 0 | PASS |
| Current/V1.33 shell syntax | 3 files | 0 | 0 | PASS |
| High-signal source secret scan | 1 gate | 0 | 0 | PASS |
| Runtime package build / independent manifest verification | 2,188 checks | 0 | 0 | PASS |
| Complete Source build / independent manifest verification | 1 package gate | 0 | 0 | PASS |
| V1.33 HTTP integration | 0 | 0 | 2 tests | SKIP: PHP CLI unavailable |
| PHP 8.1 / PHP 8.4 current suites | 0 | 0 | 2 matrix jobs | SKIP: local PHP unavailable |
| Migration 025 live MariaDB | 0 | 0 | 1 test | SKIP: MariaDB tools unavailable |
| Production Browser | accepted RC2 result | 0 | local rerun | PASS (user environment) / local SKIP |

実行可能だったGateにFAILはありません。

## Finalization coverage

- Formal `APP_VERSION`, visible label and immutable asset revision are all `1.33.0`.
- Production source contains no V1.33 dev/RC cache key.
- Accepted JavaScript dependency order, four-stylesheet batches and one 600ms static-asset retry remain unchanged.
- The loader never retries Calendar/API create, update or delete requests.
- README, CHANGELOG and RELEASE_NOTES agree on `1.33.0` / `v1.33.0`.
- `.github/release-request.txt` is a formal semantic-version input; the release workflow independently validates the exact requested release before publishing.
- Complete Source collection excludes `dist/`, `deliverables/`, private configuration, generated runtime data, database dumps and nested archives.
- Runtime and Complete Source ZIPs have one safe top-level directory, no duplicate/traversal path and full internal SHA-256 manifests.

## Calendar / compatibility coverage

- Existing red/blue/green values plus yellow/purple contracts.
- Common range limits, stable occurrence identity, deduplication and stale-response rejection.
- Additive Migration 025, prefix validation, idempotency contract and fresh-schema parity.
- Occurrence-only update/cancel/restore, optimistic revision and 409 conflict handling.
- Connected `single` / `start` / `middle` / `end` display across week/month boundaries and deterministic overlap lanes.
- Day/week/month range calculation, 24-hour timeline, timed-event placement, compact card and responsive toolbar.
- Owner scope, CSRF, defensive JSON encoding, text-only Calendar rendering and non-destructive SQL constraints.
- Existing Account Security, Step-up, 2FA, Recovery Code, Session Registry and Authentication Audit static boundaries.

## SKIP details / GitHub gate

This build environment has no PHP CLI, PHP 8.1/8.4 runtime, MariaDB/MySQL tools or interactive browser. These are not reported as PASS. The generic GitHub Release workflow must run both current suites on PHP 8.1 and PHP 8.4, the live-compatible Migration test, secret scan, deterministic package verification and clean-room checks from the exact `main` commit before publishing `v1.33.0`.

The accepted RC2 production verification covers the production Browser behavior. Formal deployment must still confirm that the visible version and static asset cache keys changed from RC2 to `1.33.0` without changing Calendar behavior.
