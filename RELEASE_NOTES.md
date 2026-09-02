# RSS Reader Modernization 1.30.0

Release tag: `v1.30.0`
Release date: 2026-09-03

## Overview

Version 1.30.0 adds a bounded authenticated Remote Text Editor on top of the V1.29 Remote File Manager. It is designed for small UTF-8 text/source maintenance rather than IDE replacement and reuses the existing owner-scoped Remote Service/provider security boundary for FTP, explicit FTPS, SFTP and HTTPS WebDAV.

The editor supports `txt`, `md`, `csv`, `json`, `xml`, `html`, `htm`, `css`, `js`, `php`, `ini`, `conf`, `yml`, and `yaml`. Binary files, mixed/CR-only EOL content, invalid UTF-8, and unsupported extensions fail closed.

## Main changes

- Added `/remote-editor` with bounded read/edit/save, metadata, dirty state, Reload, Save, Ctrl/Cmd+S and return to the originating Remote connection/directory.
- Default editor ceiling is 512 KiB (`APP_REMOTE_EDITOR_MAX_BYTES`), bounded on actual download and reconstructed save bytes.
- Added raw-byte SHA-256 optimistic conflict detection. The SHA is checked before staging and again before the final provider move.
- On HTTP 409 conflict, local unsaved text remains in the textarea, Save is disabled, and the latest Remote version must be reloaded before saving again. No force-overwrite bypass is exposed.
- Preserved LF/CRLF and UTF-8 BOM where supported. The browser textarea uses LF internally; server-side save reconstruction keeps the Remote source style when the Remote file has not changed.
- Added random same-directory `.iguguru-editor-*.tmp` staging, bounded post-save read-back SHA verification, collision retry, and best-effort staged cleanup.
- Added zero-byte text save support at the provider layer without weakening the generic Remote File Manager upload contract.
- Changed editor save transport from raw source JSON to canonical Base64-in-JSON to avoid common hosting WAF/ModSecurity false positives when saving PHP/HTML/JS source. Base64 is not encryption; HTTPS remains the transport confidentiality boundary.
- Improved Remote Files actions and file-type icon differentiation using glyph shape plus semantic color, while retaining filename/title/aria-label cues.
- Improved narrow/mobile editor input behavior while retaining 44px coarse-pointer controls and disabling spellcheck/autocomplete/autocapitalize for source editing.

## Security / compatibility

- Authentication, owner scope, CSRF, same-origin resource policy, no-store responses, Base Path confinement, DNS/IP validation, credential encryption and provider transport verification from V1.29 remain in force.
- Editor source is assigned through textarea/text values, not `innerHTML`, and is not persisted to Local Storage/Session Storage or logged to the Browser console by the editor runtime.
- The editor backend remains protocol-neutral and receives decrypted credentials only through the existing server-side Remote Service/provider construction.
- Staged replacement is deliberately not described as universally atomic across FTP RNFR/RNTO, SFTP rename, and WebDAV MOVE. V1.30 does not implement Remote file locking, so a narrow race can remain after the final SHA check and before provider move.
- If a target server rejects rename-overwrite semantics, V1.30 fails rather than deleting the original file as a fallback.
- Plain FTP remains unencrypted on the wire; explicit FTPS, SFTP and HTTPS WebDAV retain their V1.29 transport-validation requirements.

## Database / configuration

Version 1.30.0 adds **no database migration** and does not change the V1.29 `remote_connection` schema.

Existing V1.29 private configuration remains required for Remote Files, including the credential key and any protocol-specific settings already in use. Do not replace an existing `APP_REMOTE_CREDENTIAL_KEY_B64` after credentials have been stored.

V1.30 adds/uses the editor byte-limit setting documented in the example configuration. The private Remote temporary workspace must remain outside `public/` and writable by PHP. The repository now keeps only `var/remote-tmp/.gitkeep`; runtime temp contents remain ignored and must not be committed.

## Upgrade summary

1. Back up the application, `config/local.php`, database, File Library storage and other private runtime data.
2. If upgrading from V1.29.0, **do not re-run Migration 021**; V1.30 has no new migration.
3. Deploy Version 1.30.0 without replacing `config/local.php`, credential keys, private keys, `known_hosts`, uploads, logs, cache, sessions, DB dumps or runtime temp contents.
4. Ensure the configured/private Remote temp directory is outside `public/` and writable by the PHP process.
5. Reload the browser and confirm `RSS Reader Modernization 1.30.0` is visible.
6. Open Remote Files and verify Connection Test/listing/download for the protocols actually configured in production.
7. Open a disposable UTF-8 TXT/PHP file in Remote Text Editor, save a harmless change, return to the same directory, and verify the Remote bytes by download/readback.
8. Verify one stale-editor conflict stops overwrite and retains local text until Remote latest is reloaded.
9. Remove disposable test files and confirm no `.iguguru-editor-*.tmp` remains where dotfiles are visible.

## Release assets

- `rss-reader-modernization-1.30.0.zip`
- `rss-reader-modernization-1.30.0.zip.sha256`
- `rss-reader-modernization-1.30.0-complete.zip`
- `rss-reader-modernization-1.30.0-complete.zip.sha256`

## Verification limits

V1.30 A-G used focused architecture, bounded-read, save/conflict, real HTTP API, browser runtime, EOL/BOM, WAF-safe transport, navigation, mobile/UI, package-hygiene and production smoke verification. Production testing confirmed TXT and PHP source saves after the WAF-safe transport change and confirmed the F/G checkpoints used for finalization.

The final H flow promotes durable V1.30 contracts into the current feature suite and runs `tests/run-current.sh` plus `tests/run-current-features.sh` in GitHub Actions on PHP 8.1 and PHP 8.4. The standard Release workflow repeats release-ready validation, both PHP regressions, high-signal source secret scanning, deterministic Runtime/Complete package verification, clean-room extraction, main-SHA revalidation, immutable tag protection and GitHub Release publication.

Actual FTP/FTPS/SFTP/WebDAV behavior can still vary with the target server, cURL build, filesystem and server-side rename semantics. The deployment remains responsible for its real protocol endpoints, SFTP known_hosts provenance, FTPS/WebDAV certificate trust, private-network CIDRs, PHP/Web server limits, filesystem ownership/permissions and final Smartphone/browser smoke checks. chmod/chown, Remote locking, universal atomic replacement, Shift_JIS/EUC-JP auto-conversion, Monaco/CodeMirror/IDE features, shell execution, SCP, SMB/NFS, S3/cloud drives and background synchronization are outside Version 1.30.0.
