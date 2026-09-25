# RSS Reader Modernization 1.35.4

V1.35.4 is a diagnostics release that makes Mail and RSS failures more specific and actionable while keeping provider messages, credentials, and tokens out of the browser response.

## Main changes

### Mail error diagnostics

- Replace the broad credential re-entry response with fixed reason codes and safe Japanese guidance.
- Distinguish missing or invalid encryption keys, key mismatch, damaged encrypted data, OAuth reconnection, expired or mismatched OAuth state, denied or incomplete Google authorization, Google timeout/TLS/response failures, invalid OAuth server configuration, and Mail storage failure.
- Preserve separate IMAP, SMTP, input validation, disabled-account, folder, received-attachment, and sent-attachment failures.
- Distinguish a successful send followed by a Sent-folder save failure, including the case where an uncertain save must not be retried automatically.
- Add a safe 12-character reference to unexpected Mail and OAuth callback failures without exposing raw provider details.

### RSS error diagnostics

- Map existing RSS transport and parser outcomes into six public categories: URL/security blocking, DNS/connection failure, timeout/temporary failure, HTTP rejection or rate limiting, invalid Feed format, and local RSS Reader server failure.
- Use only the six allow-listed messages in RSS Cards and retain a generic fallback for unknown API or provider text.
- Show the same classification and safe Japanese guidance in Feed Health for persisted failures.
- Keep the existing fetch boundary, DNS pinning, TLS verification, response limits, retry and Retry-After behavior, cache, stale-on-error behavior, persistence, and parser behavior unchanged.

## Database and configuration

- No database migration is required when updating from V1.35.3.
- No required configuration, credential format, external dependency, or endpoint change is introduced.

## Security and compatibility

- Browser responses use fixed allow-listed messages rather than raw provider or cURL messages.
- Credentials, passwords, OAuth tokens, authorization codes, and encryption material are not added to error responses or diagnostic references.
- Existing Password and Gmail OAuth2 Mail accounts remain compatible.
- Existing RSS URLs, Feed Health records, retry state, and cache files remain compatible.

## Verification completed

- Static contracts cover Mail reason mapping, OAuth callback failures, unexpected-reference formatting, the six RSS categories, allow-listed Card messages, and Feed Health translation.
- JavaScript runtime checks cover the RSS Card display mapping and fallback behavior.
- Current Mail, Feed Health, version, asset-revision, workflow-hygiene, and dependency-hygiene checks are included in the release gate.

## Verification limits

- Final PHP 8.1 and PHP 8.4 CI and the release workflow must pass before the immutable tag and assets are considered complete.
- Provider-specific failures can only be reproduced when the corresponding external failure occurs; unknown failures continue to use a safe generic message and reference.
- The release does not automatically deploy to the production environment.

## Release assets

The release workflow publishes:

- `rss-reader-modernization-1.35.4.zip`
- `rss-reader-modernization-1.35.4.zip.sha256`
- `rss-reader-modernization-1.35.4-complete.zip`
- `rss-reader-modernization-1.35.4-complete.zip.sha256`
