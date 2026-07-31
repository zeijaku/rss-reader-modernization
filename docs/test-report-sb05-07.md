# SB-05〜SB-07 Test Report

Build: `Secure Baseline SB-07 / R1`

## Environment

- PHP CLI: 8.4.16
- Node.js: 22.16.0
- PDO core: available
- `pdo_mysql`: not available in build sandbox
- `pdo_sqlite`: not available in build sandbox

Therefore real MySQL/MariaDB integration remains a deployment-side verification item.

## Final automated run

Command:

```bash
./tests/run.sh
```

Result:

- PHP syntax: **25 files passed**
- explicit `PASS:` assertions/checks: **232**
- failures: **0**

The final count includes SB-00〜04 regression tests and the new SB-05〜07 checks.

## SB-05 API checks

Verified:

- POST-only endpoint
- JSON 405 for GET
- explicit action required
- unknown action rejected
- unified `{ok:true,data:{}}` response
- unified `{ok:false,error:{...}}` response
- no API redirect
- unauthenticated API request -> 401
- action-level validation error -> structured 422
- unexpected backend failure -> generic JSON 500
- DB/driver diagnostics not exposed in JSON 500

## SB-06 Authorization / IDOR checks

Two-user fake-DB tests verified:

- content.create uses authenticated user as owner even if forged owner/user fields are supplied
- User B cannot update User A Feed
- User B cannot delete User A Feed
- unauthorized update does not alter row
- unauthorized delete does not alter row
- User B cannot `feed.fetch` User A content ID
- unauthorized feed.fetch performs no outbound request
- owner can update/delete own Feed
- deleted Feed cannot be fetched
- settings update ignores forged request `user_id`
- tab update ignores forged request owner
- Stock owner is authenticated Session user
- malformed resource ID is rejected before mutation

Feed fetch contract verified:

- client raw `steal_content` value is not consumed
- DB-owned Feed URL is fetched instead
- browser window-load code sends `content_id`, not Feed URL

## SB-07 CSRF checks

Verified:

- cryptographic Session CSRF helper regression
- Login form contains CSRF
- Registration form contains CSRF
- Login without CSRF -> 403 before authentication
- Registration wrong CSRF -> 403 before DB registration
- authenticated API missing CSRF -> 403 JSON
- authenticated API wrong CSRF -> 403 JSON
- valid CSRF reaches dispatcher
- Logout remains POST + CSRF

## Browser/frontend checks

Verified:

- Login/Register page renders
- Bootstrap asset is served
- visible `Secure Baseline SB-07 / R1` marker
- hardened Session cookie remains in place
- dashboard API calls share one CSRF-aware `apiRequest()` helper
- Legacy request owner fields removed from browser API payloads
- Legacy fixed `setting_token` removed
- dashboard inline JavaScript parses successfully with Node.js

## Security/static regression

Verified:

- no known sensitive/runtime file types under `public/`
- private PHP remains outside DocumentRoot
- parameterized SQL/PDO policy retained
- owner predicate exists on protected content queries
- no obvious secret/private-key pattern in `public/` and `app/`

## Not testable in this sandbox

Must be checked on the deployment test environment:

- real `pdo_mysql` connection
- real MySQL content/settings/stock writes through the new API
- real two-account IDOR test against MySQL
- real RSS fetch after API contract change
- hosting-specific Apache/PHP-FPM behavior

SB-08〜10 security tests are intentionally not run yet because strict validation, SSRF/TLS hardening and XSS-safe output are their dedicated scopes.
