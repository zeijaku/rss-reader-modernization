# SB-08〜SB-10 Test Report

Build: `Secure Baseline SB-10 / R1`

## Build environment

- PHP CLI: 8.4.16
- PDO core: available
- `pdo_mysql`: unavailable in this sandbox
- cURL extension: unavailable in this sandbox
- SimpleXML extension: unavailable in this sandbox
- mbstring extension: unavailable in this sandbox

Consequences:

- real PHP -> MySQL integration cannot be executed here
- real cURL/TLS network transfer cannot be executed here
- real SimpleXML RSS/Atom parse cannot be executed here

Those integration points remain mandatory deployment-side checks.
The security policy itself is exercised with injected test resolver/transport only after the same target-validation pipeline.

## Full regression suite

Command:

```bash
./tests/run.sh
```

The suite includes all earlier SB-00〜07 regression tests plus SB-08〜10 tests.
Final source-tree run: **30 PHP files linted successfully, 413 explicit PASS checks, 0 FAIL.**

## SB-08 validation

`tests/test_sb08_validation.php`: **80 checks passed**.

Coverage includes:

- strict resource IDs
- exact location/tab parsing
- all content/theme/navbar/icon allowlists
- Legacy icon normalization
- UTF-8 / control characters / lengths
- Feed / Stock / Navbar URL schemes and limits
- userinfo / fragment / host / whitespace policy
- bracketed IPv6 syntax
- render-time fallback of unsafe Legacy configuration
- HTML escape helper

## SB-09 safe fetch

`tests/test_sb09_fetch.php`: **42 checks passed**.

Coverage includes:

- IPv4 loopback/private/link-local/reserved rejection
- IPv6 loopback/ULA/link-local rejection
- public IPv4/IPv6 acceptance
- 80/443 baseline port enforcement
- localhost and alternate numeric loopback handling
- unresolved DNS rejection
- mixed public/private DNS fail-closed
- unsafe schemes/userinfo rejection
- relative/root/scheme-relative redirect resolution
- non-http redirect rejection
- every-hop revalidation
- private redirect blocked before second transport call
- redirect limit
- pinned validated DNS address / original hostname metadata passed to transport
- fixed application User-Agent
- timeout/body-limit propagation
- non-2xx / empty body / oversized body / transport error handling

Static checks additionally verify cURL automatic redirect is disabled, TLS peer/hostname verification is enabled, and hostname DNS pinning uses `CURLOPT_RESOLVE`.

## SB-10 XSS / output

`tests/test_sb10_feed_payload.php`: **15 dynamic checks passed**.

Payload tests verify:

- script/svg/img/a/iframe markup not preserved as executable Feed markup
- javascript/data item URLs suppressed
- unsafe channel URL falls back safely
- valid HTTPS article URL retained
- quote/tag/ampersand escaping
- Feed text length bounding
- JSON HEX output defense

`tests/test_sb10_output_static.py`: **35 checks passed**.

Static/frontend checks cover:

- centralized HTML escaping
- safe UI normalization
- no raw DB values in key href/value/class contexts
- safe Feed DOM construction via `.text()` / `.attr()`
- `_blank` link rel hardening
- no Stock article server-side refetch
- `LIBXML_NONET`
- no suppressed SimpleXML parse errors
- version marker `Secure Baseline SB-10 / R1`

## SB-05〜07 regression under new validation

The two-user authorization/API tests were extended to pass through SB-08/09 logic and additionally verify:

- invalid Feed scheme rejected before DB write
- invalid content style/location rejected
- unsafe settings rejected
- overlong tab rejected
- unsafe Stock URL rejected
- overlong Stock title rejected
- Stock creation causes no outbound HTTP request
- existing owner/IDOR/CSRF contract remains intact

## Packaging acceptance

Before release:

1. runtime Session/throttle/error-log artifacts are removed
2. package manifest is regenerated
3. ZIP is created
4. ZIP integrity is checked
5. ZIP is extracted into a fresh directory
6. the full test suite is rerun from that extracted tree
7. SHA-256 is generated for the final ZIP

The final SHA and exact assertion count are recorded after this acceptance step.
