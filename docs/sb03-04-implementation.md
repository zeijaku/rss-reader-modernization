# SB-03 / SB-04 Implementation Notes

## Basis

Implemented on top of the verified SB-00〜02 R6 checkpoint.
Existing-user credential compatibility was explicitly waived for this implementation slice.

## SB-03 — Session Foundation

Implemented:

- one Session bootstrap (`app/session.php`) used by Web UI, API and logout
- private `var/session/` storage retained from R5/R6 foundation
- strict-mode and cookie-only PHP Session policy
- browser-session cookie; Legacy 90-day cookie removed
- HttpOnly, SameSite=Lax, Secure on HTTPS
- URL Session IDs disabled
- successful authentication rotates Session ID with deletion of old Session
- idle and absolute authenticated timeouts
- expiry rotates the Session ID and removes authentication state
- CSRF token generated inside Session
- Session reduced to:
  - user_id
  - authenticated_at
  - last_activity
  - csrf_token
- UI settings are loaded from `ig_user_conf` on each authenticated page request instead of being Session cache
- logout changed to POST + CSRF + cookie expiry + session_destroy + 303

## SB-04 — Authentication

Compatibility decision:

- no automatic Legacy credential migration
- non-`password_hash` stored credentials fail closed
- existing rows are not deleted or rewritten automatically

Implemented new account format:

- login email is trimmed/lowercased
- DB login identity is `HMAC-SHA256(normalized email, APP_HASH_KEY)`
- raw login email is not stored by this layer
- passwords use `password_hash(PASSWORD_DEFAULT)`
- login uses `password_verify()`
- successful login performs `password_needs_rehash()` and updates the hash if needed

Identity lookup:

- password removed from SQL lookup criteria
- only active (`user_flag=0`) candidates are returned
- two candidates are sufficient to detect ambiguity; duplicate active identity fails closed
- registration checks identity existence independently of password

Registration:

- enable/disable switch
- valid email required
- default minimum password length 12
- maximum default 72 bytes to avoid bcrypt truncation ambiguity while `PASSWORD_DEFAULT` resolves to bcrypt
- SB-02 transaction still creates user + user_conf atomically

Rate limiting:

- private file-backed state using `flock()`
- keyed filenames contain no raw identity/IP
- pair (identity+IP) and IP buckets
- temporary blocks, no permanent account lockout

## Deliberately deferred

- login/register CSRF: SB-07 (logout CSRF was required by SB-03 and is implemented)
- API authentication guard/dispatcher: SB-05
- owner enforcement: SB-06
- broad validation: SB-08
- outbound fetch safety: SB-09
- XSS hardening: SB-10
