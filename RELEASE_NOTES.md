# RSS Reader Modernization 1.34.1 - Mail Received Attachment Correction

V1.34.1 is a correction release for V1.34.0. It restores the existing received/Sent attachment display and download implementation that was already used in production but was mistakenly disabled during the V1.34.0 formal release finalization.

## Corrected
- Restored received/Sent attachment metadata listing in the existing Mail message body UI.
- Restored attachment download through the existing `mail.message.attachment.download` route.
- Kept the existing bounded 25 MiB decoded-content download limit and 80 MiB transfer-encoded safety cap.
- Kept filename/content-type sanitization and bounded attachment enumeration.

## Security / compatibility
- Authentication, Session, CSRF, owner scope and Mail Widget ownership checks remain required.
- The requested folder must still match the configured Mail Widget folder.
- IMAP uses the existing public-address-only target validation, validated-IP pinning and TLS certificate validation.
- This patch does not change SMTP send, Reply, Sent save modes or outbound attachment limits introduced in V1.34.0.
- No database migration and no new mandatory configuration or secret are introduced by V1.34.1.

## Intentionally not included
- HTML compose.
- CC / BCC.
- Reply All / Forward.
- OAuth2 authentication implementation.
- Automatic re-attachment of original received files when replying.
- Application database storage of sent message bodies.

## Verification limits
- The restored received attachment implementation corresponds to the existing production capability; production code is not modified as part of this Git correction.
- The formal GitHub Release workflow reruns the current regression and current feature contracts on PHP 8.1 and PHP 8.4 before publishing the immutable tag.
- Provider-specific IMAP policies and provider-side attachment limits remain external.
