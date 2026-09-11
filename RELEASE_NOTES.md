# RSS Reader Modernization 1.34.0 - Mail Send / Reply / Sent / Attachments

V1.34 extends the existing read-only IMAP Mail Widget into a bounded plain-text send/reply workflow while preserving the existing receive path and owner-scoped security boundaries.

## Added
- SMTP settings per Mail Account with SSL/TLS 465 or STARTTLS 587.
- IMAP credential reuse or separate encrypted SMTP credentials.
- From Address / optional From Name.
- SMTP connection/authentication check without sending mail.
- Plain-text Compose with To / Subject / Body.
- Plain-text Reply using Reply-To when valid, otherwise From, plus validated In-Reply-To / References threading headers.
- Sent handling modes: Auto, Server, RSS Reader.
- Auto Sent mode checks immediately, after 1 second and after a further 2 seconds before performing IMAP APPEND when no server-saved copy is found.
- Send progress indicator while SMTP/Sent handling is active.
- Outbound attachments: up to 5 files, 10 MiB per file, 20 MiB total, subject to lower PHP/Web-server limits.
- Bundled PHPMailer 7.1.1 subset required by the SMTP implementation, including OAuthTokenProvider interface preparation; OAuth2 authentication itself is not implemented.

## Database
Existing installations apply, in order when not already applied:
1. `026_v1_34_mail_smtp.sql`
2. `027_v1_34_mail_sent_save_mode.sql`

Both migrations are additive and idempotent by column-existence checks. Migration 026 leaves existing Mail Accounts SMTP-disabled. Migration 027 defaults existing accounts to Sent mode `auto`.

## Security / compatibility
- Existing Authentication, Session, CSRF, owner scope, input validation and IMAP SSRF/public-IP validation remain in force.
- SMTP uses the same public-address-only target validation and validated-IP pinning model as Mail IMAP.
- SMTP TLS peer/hostname verification remains enabled.
- Raw passwords, generated MIME and internal Message-ID values are not exposed through the Mail JSON API.
- SMTP success is never automatically retried because Sent verification/storage failed.
- Outbound attachments reject malformed uploads, dangerous executable/script extensions and clearly dangerous MIME types.

## Intentionally not included
- HTML compose.
- CC / BCC.
- Reply All / Forward.
- OAuth2 authentication implementation.
- Automatic re-attachment of files from the original received message on Reply.
- Received/Sent attachment display and download.
- Application database storage of sent message bodies.

## Verification limits
- Production checkpoint verification confirmed SMTP send, Reply, Sent handling and outbound attachments on the deployed hosting environment before formal release.
- The V1.34-G focused integration gate completed 605 PASS / 0 FAIL, plus PHP/JavaScript syntax, secret-scan, deterministic-package, and clean-room verification.
- The formal GitHub Release workflow reruns the repository current regression and current feature contracts on PHP 8.1 and PHP 8.4 before publishing the immutable tag.
- Provider-specific SMTP/IMAP policies remain external. Auto Sent can duplicate if a provider creates its own Sent copy only after the bounded approximately 3-second verification window; Server mode is available for such providers. Attachment delivery also remains subject to Hosting/PHP/provider size and content policies.
