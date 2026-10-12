# V1.47-B / P0 security hardening — test deployment notes

This is the **first focused development step** of V1.47-B, based on the V1.46.0 code. It is not a new formal release.

## Changed runtime files
- `app/api_rate_limit.php` (new): per-authenticated-user filesystem counter with a shared network-action bucket.
- `public/api_v1.php`: calls limiter after method, authentication, CSRF, action syntax, and request-byte validation; returns JSON HTTP 429 and Retry-After when exceeded.

No schema migration, .htaccess change, production secret change, or config file update is needed. Writable private `var/security/api-throttle/` is created automatically (0700 directory / 0600 new counter files). The existing root `.htaccess` denies direct access to `var/`.

## Defaults (private config/local.php or environment only)
- `APP_API_RATE_LIMIT_ENABLED=true`
- `APP_API_RATE_WINDOW=60` seconds (allowed range 10–3600)
- `APP_API_RATE_TOTAL_MAX=600` per user/window (allowed 1–10000)
- `APP_API_RATE_NETWORK_MAX=120` per user/window (allowed 1–10000)

Selected outbound-intensive actions include `feed.fetch`, search/feed fetch, X timeline, remote.*, mail.account.*, mail.message.*, mail.oauth.*, and widget.healthprobe.*; other actions are protected by the total bucket. Limits are per authenticated user, not per browser session. This does **not** implement a global/IP flood limiter or concurrency cap; leave those for separate evaluation with production traffic data.

## Safe test deployment order
1. Back up existing `public/api_v1.php`. Confirm actual live application version and upload target.
2. Upload **new** `app/api_rate_limit.php` first.
3. Upload replacement `public/api_v1.php` second.
4. Confirm PHP 8.1+ and write permissions on `var/security/`, and that private paths return 403. Never browse private counter JSON.
5. Log in, reload Dashboard, use RSS/Stock/Calendar. Confirm no unexpected HTTP 429.
6. Check File Library read/upload, Remote Files connection and transfer, Mail read/send, 2FA settings (only features configured for your account). Do not test by generating hundreds of requests on production.
7. Verify ordinary API responses remain no-store and include CSRF synchronization header; monitor server-side logs for rate-limit storage errors.
8. On any malfunction, restore the backed-up `public/api_v1.php`; unused new helper can then be deleted. No DB rollback is needed.

## CI contract
`bash tests/run-ci.sh` runs under PHP 8.1 and 8.4 on the PR, including:
- API limiter unit checks for windows, isolation, classification, private state and invalid identifiers;
- authenticated HTTP checks for CSRF/auth/method precedence, 429 and Retry-After;
- strict current `public/*.php` and `public/.htaccess` parity test with root private-path guard.

## Deferred V1.47-B topics
FTP plaintext mode policy, error-log data minimization, repository-wide secret checks, CSP rollout, additional `.htaccess` defense-in-depth, and limits for standalone public endpoints require separate limited-scope changes and compatibility testing. Do not treat this P0 PR as the completion of all V1.47-B work.
