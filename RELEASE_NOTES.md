# RSS Reader Modernization 1.32.0

Release tag: `v1.32.0`
Release date: 2026-09-07

## Overview

Version 1.32 focuses on Account Security. It adds TOTP 2FA, one-time Recovery Codes, Step-up Authentication for sensitive operations, owner-scoped Session Management, and a bounded Authentication Security Activity log while preserving the existing Secure Baseline authentication, CSRF, throttling, Remember Me and filesystem-backed PHP session model.

V1.32-G Session Management and V1.32-H Authentication Security Audit Log completed production smoke verification. V1.32-I integrated the final regression, fresh-install schema/documentation and release gates, and the 1.32.0-RC1 production verification completed without reported problems before formal release.

## Main changes

- TOTP 2FA enrollment and login verification using a dedicated encrypted TOTP secret envelope.
- Recovery Codes generated as one-time values and stored only through one-way password hashes.
- Short-lived Step-up Authentication for sensitive Account Security actions.
- Session Management showing the current/other logical sessions with individual and all-other revoke operations.
- Authentication Security Activity for login success/failure, 2FA success/failure, Recovery Code use, Step-up, 2FA changes, Password/Email changes, Session revoke and Logout.
- Account Security UI shared by Dashboard/Settings with owner-scoped Security Activity and Session views.

## Security / privacy

- Password, TOTP code, Recovery Code, TOTP secret, encryption key and raw/full PHP Session IDs are not written to the Authentication Audit Log.
- Raw email is not used for failure correlation; the existing keyed login identity is used where needed.
- Raw IP address is not stored. `REMOTE_ADDR` is reduced to a keyed digest; forwarded proxy headers are not implicitly trusted.
- Full User-Agent is not stored. A bounded Browser/platform label is used for Session/Security Activity display.
- Recovery Codes are one-time and hash-stored; accepted TOTP time steps are tracked to reduce replay/reuse.
- Sensitive changes keep CSRF protection and require recent Step-up Authentication where applicable.
- 2FA/Login rate-limit and existing login-throttle boundaries remain in force.

## Database / configuration

Existing V1.31 installations use these additive migrations, in numeric order when not already applied:

1. `022_v1_32_auth_2fa.sql` - adds `auth_totp` and `auth_recovery_code`.
2. `023_v1_32_auth_session.sql` - adds `auth_session`.
3. `024_v1_32_auth_audit_log.sql` - adds `auth_audit_log`.

For a `rss_` prefix, the resulting tables are `rss_auth_totp`, `rss_auth_recovery_code`, `rss_auth_session`, and `rss_auth_audit_log`. The migration files default to `ig_`; change `SET @table_prefix` to the actual `DB_TABLE_PREFIX` before execution. Do not re-run a migration solely because 1.32.0 is being deployed if the corresponding table already exists.

Fresh installs now include all four V1.32 Account Security tables directly in `database/schema.sql`.

`APP_TOTP_SECRET_KEY_B64` is a dedicated 32-byte Base64 encryption key used for TOTP secret envelopes. If 2FA has already been enrolled, **do not replace this key** or existing encrypted TOTP secrets will no longer be decryptable. `APP_TOTP_SECRET_KEY_ID`, `APP_TOTP_ISSUER`, 2FA throttling settings and `AUTH_STEP_UP_TIMEOUT` are documented in the example configuration.

## Upgrade summary for an existing V1.31/V1.32 checkpoint environment

1. Back up the application, `config/local.php`, database and private runtime data.
2. Confirm whether `auth_totp`, `auth_recovery_code`, `auth_session` and `auth_audit_log` already exist under the configured prefix. Apply only the missing migrations 022/023/024, in numeric order.
3. Keep the existing `APP_TOTP_SECRET_KEY_B64`, `APP_HASH_KEY`, Remote credential key, private keys, `known_hosts`, uploads, logs and other private runtime data unchanged.
4. Deploy the 1.32.0 application update.
5. Reload the browser and confirm `RSS Reader Modernization 1.32.0` is visible.
6. Verify Login, TOTP 2FA, Recovery Code handling as applicable, Step-up Authentication, Session Management and Security Activity in the production environment.

## Verification limits

Local V1.32-I regression was executed with the available PHP 8.4 runtime and the current PHP/Python/Node test suites. The local environment does not provide every production/CI PHP extension and does not provide PHP 8.1; extension-dependent tests may explicitly skip where their contract permits. Formal publication is performed only by the generic GitHub Release workflow after PHP 8.1 and PHP 8.4 regression, release workflow hygiene, secret scan, deterministic package verification, clean-room validation and main-SHA checks pass.

Production-specific behavior remains the responsibility of the real hosting environment, database, browser and configured Remote endpoints. In particular, existing V1.31 Remote Files protocol limitations remain unchanged: SFTP production endpoint behavior is not newly claimed by V1.32, FTP remains unencrypted, and FTPS/WebDAV trust plus SFTP `known_hosts` provenance remain deployment responsibilities.
