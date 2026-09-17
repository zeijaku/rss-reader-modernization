# RSS Reader Modernization 1.35.0

V1.35.0 adds Gmail OAuth2 support, improves Mail Widget responsiveness and newest-message selection, introduces the visual-only Cursor Field effect, and adds an exact 24-hour trusted-browser period after successful 2FA.

## Main changes

- Add Gmail OAuth 2.0 Authorization Code + PKCE connection with encrypted refresh-token storage and short-lived XOAUTH2 credentials for IMAP and SMTP.
- Reuse valid Google access tokens within the session, remove duplicate folder-switch connections, make UID handling explicit, and allow sufficient client wait time for mail operations.
- Sort Gmail IMAP UID search results numerically before applying the 5/10-message display limit, ensuring the newest available messages are selected consistently.
- Allow the OAuth callback route in `public/.htaccess` without exposing secrets or weakening existing access controls.
- Add the Cursor Field visual effect without scoring, persistence, network access, or additional dependencies.
- Add a 24-hour 2FA trusted-browser token. Existing tokens are not trusted automatically and become eligible only after a successful 2FA challenge.
- Add `tests/run-ci.sh` as the shared regression entry point for local checks and GitHub Actions.

## Database upgrade

Apply these additive migrations in order:

1. `database/migrations/028_mail_google_oauth.sql`
2. `database/migrations/029_account_2fa_trust.sql`

The migrations do not remove existing data. Password-based mail accounts, stored credentials, Remember-token expiry, and existing user data remain compatible.

## Gmail OAuth configuration

Configure a Google OAuth web application and provide the private Client ID, Client Secret, exact callback URL, allowed Gmail address, and the existing mail encryption key in the private server configuration. Do not place credentials in public files or commit them to the repository.

## Security and compatibility

- Existing authentication, owner scope, CSRF, session, step-up verification, IMAP SSRF/DNS-pinning/TLS, and size-limit boundaries remain in place.
- OAuth codes, access tokens, refresh tokens, client secrets, and XOAUTH2 payloads are not written to application logs.
- Remember login remains 30 days; normal session idle and absolute limits remain 2 hours and 12 hours.
- This release does not delete or rewrite existing user content.

## Production verification completed

- Gmail OAuth connection and IMAP connection test
- Current and newly received message listing
- Message body display and folder switching
- New-message composition, reply, and Sent-folder storage
- Received attachment download and outgoing attachment delivery
- Gmail IMAP search diagnostics: the application sends the requested `SUBJECT` search and renders the UID set returned by Gmail; it does not emulate substring matching locally

## Verification limits

- Final PHP 8.1 and PHP 8.4 CI and the release workflow must pass for the formal tag and assets to be published.
- Gmail OAuth was verified with the configured allowed Gmail account; other Google Workspace policies may require separate administrator approval.
- The 24-hour trust boundary is covered by automated time-bound tests; a manual 24-hour wait was not performed.
- Cursor Field rendering remains browser-dependent and is visual only.

## Release assets

The release workflow publishes:

- `rss-reader-modernization-1.35.0.zip`
- `rss-reader-modernization-1.35.0.zip.sha256`
- `rss-reader-modernization-complete-1.35.0.zip`
- `rss-reader-modernization-complete-1.35.0.zip.sha256`
