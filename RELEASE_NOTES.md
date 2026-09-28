# RSS Reader Modernization 1.39.1

V1.39.1 is a Correction / Security Bug Fix release for the existing 24-hour trusted-browser behavior after TOTP 2FA. It does not add a new authentication feature or change the configured trust duration.

## Main change

### Remember Me / 2FA trusted-browser correction

- Fix the trust-marker update used when an existing untrusted Remember Me token restores a browser and the user then completes TOTP 2FA.
- Replace the repeated `:verified_at` named placeholder in the token update SQL with a distinct `:expires_after` placeholder for the expiry predicate.
- Keep `PDO::ATTR_EMULATE_PREPARES => false` and the existing native MySQL prepare policy unchanged.
- Preserve Remember-token validator rotation and update only the exact token selector that completed the second factor.

## Why this patch is needed

Password-origin 2FA login could issue a new Remember Token that was trusted immediately, so that path continued to work. An older untrusted Remember Token used a different path: restore the token, require 2FA, then mark that existing token as trusted.

The trust-update statement reused one named PDO placeholder twice. With native MySQL prepares enabled, that update could fail after the authenticated PHP session had already been established. The browser therefore worked for the current session, but the Remember Token could remain untrusted and request 2FA again after the normal session expired.

## Database upgrade

No database migration is required for V1.39.1.

The existing `remember_token_second_factor_verified_at` column introduced in V1.35 remains unchanged.

## Configuration

No configuration change is required.

The existing values remain unchanged:

- Remember Me lifetime: 30 days
- normal session idle timeout: 2 hours
- normal session absolute timeout: 12 hours
- trusted-browser 2FA window: 24 hours (`AUTH_REMEMBER_2FA_TRUST_SECONDS=86400`)

## Security and compatibility

- Existing password authentication, TOTP verification, Recovery Code, Step-up Authentication, Session Registry, CSRF, owner scope, Remember-token validator rotation, and Authentication Security Audit Log boundaries remain unchanged.
- The patch does not weaken or extend the 24-hour trust window.
- No UI/API contract, database schema, credential format, public endpoint, or Runtime external dependency is changed.
- Explicit logout still revokes the current Remember Token as before.

## Verification completed

- The focused Remember/2FA runtime test now rejects duplicate named placeholders to model the production native-prepare constraint.
- Regression coverage verifies: untrusted Remember Token restoration -> pending 2FA -> successful authentication -> trust timestamp update -> next Remember restoration without another 2FA challenge inside the trust window.
- A current contract test prevents the previous duplicate-placeholder SQL shape from returning.
- Pull Request #89 passed the repository CI on PHP 8.1 and PHP 8.4.
- After merging PR #89, the updated `main` commit also passed the repository CI on PHP 8.1 and PHP 8.4.

## Verification limits

- Formal package, checksum, clean-room and GitHub Artifact Attestation verification must still pass in the Release workflow before `v1.39.1` is published.
- Production deployment remains a separate step and is not performed automatically by the Release workflow.
- Existing browsers whose Remember Token still has no second-factor verification timestamp are not retroactively trusted. After deployment, such a browser must complete 2FA once; the corrected path then records the new 24-hour trust timestamp.

## Release assets

The Release workflow publishes:

- `rss-reader-modernization-1.39.1.zip`
- `rss-reader-modernization-1.39.1.zip.sha256`
- `rss-reader-modernization-1.39.1-complete.zip`
- `rss-reader-modernization-1.39.1-complete.zip.sha256`

Both ZIP files receive GitHub Artifact Attestations. Consumers with GitHub CLI can verify the downloaded ZIPs with `gh attestation verify ... --repo zeijaku/rss-reader-modernization`.
