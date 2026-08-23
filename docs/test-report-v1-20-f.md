# V1.20-F RC1 Test Report

Date: 2026-08-23
Target: `RSS Reader Modernization 1.20.0-RC1`
Baseline: formal `v1.19.0` Complete Source / commit `c0b39e6061f4a522ebc95dfe319c6e8af7b64486`

## Result

**Automated RC gate: PASS**

V1.20-F integrates V1.20-B/C/D/E on the formal V1.19.0 source and validates the integrated Release Candidate before production verification.

## Baseline integrity

- GitHub V1.19.0 Release Gate run: success
- Formal V1.19.0 Production ZIP sidecar: verified
- Formal V1.19.0 Complete Source ZIP sidecar: verified
- V1.20 RC source was assembled from the verified formal Complete Source, not from an intermediate checkpoint ZIP.

## Full regression

`bash tests/run-current.sh`

- Exit status: `0`
- Completion marker: `PASS: current regression suite completed`
- Numbered `RESULT` blocks: 17
- Numbered assertions represented by those blocks: PASS 391 / FAIL 0 / SKIP 0
- Additional unnumbered syntax / smoke / completion checks in the runner also passed.

During RC integration, two runtime compatibility issues were found and corrected before the final full regression:

1. Wire Defense drawer bootstrap now feature-detects `document.querySelector` before use, preserving compatibility with the older Fake DOM test environment.
2. Lazy-loaded Calendar / Camera-Video assets now use the RC asset revision instead of the previous hard-coded `1.19.0` revision.

The full regression was rerun from the beginning after these corrections and completed successfully.

## Compatibility gates

The following compatibility runners were rerun against the final RC source and all returned exit status `0`:

- `tests/run-v117.sh`
- `tests/run-v1171.sh`
- `tests/run-v1172.sh`
- `tests/run-v118.sh`
- `tests/run-v119.sh`
- `tests/run-v119c.sh`
- `tests/run-v119d.sh`
- `tests/run-v120f.sh`

Across the numbered `RESULT` blocks emitted by this compatibility run: PASS 684 / FAIL 0 / SKIP 0.

Historical `tests/run-v119e.sh` and `tests/run-v119f.sh` are intentionally retained unchanged. They are exact V1.19.0-RC1 / V1.19.0 publication-version gates and therefore are not a V1.20 compatibility test. Current CI uses the V1.19 architecture/security/cleanup compatibility gates plus `run-v120f.sh`.

## V1.20-F integration gate

`tests/run-v120f.sh` completed successfully.

- Static / security / integration contract: PASS 69 / FAIL 0 / SKIP 0
- RSS Typing + Wire Defense actual JS helper runtime: PASS 29 / FAIL 0 / SKIP 0
- All RSS Recent PHP validation/config/date behavior: PASS 24 / FAIL 0 / SKIP 0
- Changed PHP syntax checks: PASS
- Changed JavaScript syntax checks: PASS

## Whole-source gates

- PHP syntax: PASS, 164 files
- JavaScript syntax: PASS, 45 files
- Python compile: PASS
- GitHub Actions YAML parse: PASS, 7 workflows
- High-signal secret scan: PASS, 0 matches
- V1.20 DB migration / SQL additions: none
- Merge conflict markers: none
- Apache configuration syntax: `Syntax OK`
  - Test environment emitted only the normal global `ServerName` warning; syntax returned status 0.

## Package gate

Release tooling targets:

- RC application version: `1.20.0-rc1`
- Visible label: `RSS Reader Modernization 1.20.0-RC1`
- Asset revision: `1.20.0-rc1`
- Intended stable release: `1.20.0`
- Intended tag: `v1.20.0`
- RC publishable: `no`

Preflight runtime and Complete Source packages were built and verified before this report was finalized. The final artifacts are rebuilt from the finalized source and passed the same verifiers before handoff.

Package verification covers SHA-256 sidecars, ZIP CRC, duplicate entries, unsafe/absolute/parent-traversal paths, required files, private/runtime exclusions, manifests, exact RC version metadata, and Complete Source manifest integrity.

## Security / data boundary

No DB schema change, migration, SQL execution, or new required configuration/secret is introduced by V1.20-F. Existing authentication, CSRF, SSRF/feed fetch, XSS/sanitization, validation, request-size, public PHP execution, session, and API routing boundaries remain under the current/compatibility regression suites.

## Manual production verification still required

Automated gates cannot replace the production-browser checks in `docs/v1-20-f-production-checklist.md`. In particular, verify actual external RSS behavior, Japanese IME typing feel, Wire Defense canvas/touch behavior, responsive layout, external integrations, browser console, and hosting error logs.
