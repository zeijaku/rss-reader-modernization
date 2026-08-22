# V1.19-C R1 Test Report

## Focused V1.19-C checks

- `tests/test_v119c_security_hardening.py`
  - PASS 38 / FAIL 0
  - API byte limit/default/order
  - Registration throttle source/privacy integration
  - CSP directives
  - Public PHP endpoint inventory/whitelist
  - first-party `<object>/<embed>/<applet>` absence
  - optional config examples

- `tests/test_v119c_registration_throttle.py`
  - PASS
  - Real PHP built-in HTTP flow
  - 2-attempt test budget followed by blocked third attempt
  - Generic registration response preserved
  - Throttle state contains counters/timestamps only
  - No raw Email/IP/Honeypot value persisted

- `tests/test_v119c_api_request_limit.py`
  - PASS
  - Real authenticated HTTP request over 64 KiB test limit => HTTP 413 / `request_too_large`
  - No-store and X-CSRF-Token response behavior preserved
  - Invalid CSRF still returns 403 before size rejection
  - Anonymous request still returns 401 before size rejection

- PHP syntax
  - PASS: `app/common/common_conf.php`
  - PASS: `app/login_throttle.php`
  - PASS: `public/api_v1.php`
  - PASS: `public/index.php`
  - PASS: `public/stock.php`

## Apache 2.4 integration observation

Temporary Apache 2.4 instance with `mod_rewrite` + `mod_headers`:

- `.htaccess` syntax: `Syntax OK`
- whitelisted `connection_probe.php`: HTTP 204
- temporary unlisted `public/v119c_unlisted_probe.php`: HTTP 403
- blocked PHP body marker was not executed/reflected
- Response CSP contained `object-src 'none'`

The temporary Apache process was terminated after the response checks and the probe file was removed.

## Existing compatibility/security checks rerun

- V1.19-B modular architecture focused checks: PASS 40 / FAIL 0
- V1.2-A authentication HTTP checks: PASS
- Current cache/security checks: PASS 16 / FAIL 0
- SB-04 authentication checks: PASS
- SB-05..07 API/authorization checks: PASS 44
- V1.17.2 focused/compatibility suite: PASS
- V1.18 Connection Monitor focused suite: PASS

## Not run in V1.19-C

The full current regression/release package gate was intentionally **not** rerun in this phase. Per V1.19 plan, the complete regression is reserved for V1.19-E Compatibility/RC unless a focused test reveals a cross-cutting regression.

## SRI follow-up verification

- Browser-side SHA-384 calculation for `https://cdn.jsdelivr.net/npm/hls.js@1.6.16/dist/hls.min.js` matched the corrected `HLS_LIBRARY_INTEGRITY` value.
- `APP_ASSET_REVISION` and Camera Streaming dynamic loader were advanced to `1.18.0-r4` to avoid reusing the previously cached incorrect script.
- PHP / JavaScript syntax and ZIP path/CRC checks were re-run for the hotfix.
