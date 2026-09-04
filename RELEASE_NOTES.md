# RSS Reader Modernization 1.31.0

Release tag: `v1.31.0`
Release date: 2026-09-04

## Overview

Version 1.31.0 extends the authenticated owner-scoped Remote File Manager with best-effort Unix permission display and bounded preset chmod support. The implementation keeps permission handling as an optional provider capability instead of widening the common Remote File Provider contract.

SFTP reports permission-change support. FTP and explicit FTPS use server-dependent `SITE CHMOD`. HTTPS WebDAV does not expose portable Unix chmod and is therefore reported as unsupported. Permission display remains best-effort for Remote protocols and shows `—` when reliable metadata is unavailable.

## Main changes

- Added permission metadata to Remote listings when it can be obtained safely: symbolic mode such as `rw-r--r--` and three-digit numeric mode such as `644`.
- Added a dedicated Permission column, capability status, and preset chmod UI to Remote Files without replacing the existing listing UI.
- Added File presets `600`, `640`, `644` and Directory presets `700`, `750`, `755`. Free-form chmod and special-bit changes are not exposed.
- Added `remote.permission.capabilities` and `remote.file.chmod` API actions using the existing authenticated POST/CSRF/owner-scoped Remote API boundary.
- Added the optional `RemotePermissionProvider` interface and a server-side permission service. WebDAV remains outside this capability boundary.
- Known symbolic links are not chmod targets. Server-side path/type validation is authoritative rather than browser-submitted type information.

## FTP / FTPS permission behavior

- MLSD remains the authoritative FTP/FTPS directory listing for filename, entry type, size and timestamp data.
- Well-formed `UNIX.mode` permission metadata is used when the server provides it.
- When MLSD succeeds but does not contain permission metadata, a best-effort Unix-style LIST request may supplement permission fields only. Supplemental LIST never adds/removes authoritative MLSD entries or replaces their unrelated metadata.
- If supplemental LIST is unavailable or cannot be matched conservatively, the Remote listing still succeeds and Permission remains `—`.
- FTP/FTPS chmod uses `SITE CHMOD`. A successful 2xx response is accepted; 500/502/504 are classified as unsupported and 550 remains a target/user-specific denial rather than disabling permission changes for the whole connection.

## SFTP permission behavior

- SFTP exposes permission-change support through libcurl quote commands with strict three-digit octal validation.
- SFTP quote paths used by chmod, mkdir, rename and delete now escape spaces, backslashes and quote characters so the intended Remote path remains one command argument.
- Permission display is still best-effort and depends on directory metadata returned by the target endpoint.

## Security / compatibility

- Existing authentication, owner scope, CSRF, Base Path confinement, traversal/control-character rejection and provider transport verification remain in force.
- Chmod accepts only `^[0-7]{3}$`; special bits are not accepted through the change API.
- Existing special-bit symbolic display can be preserved while numeric preset reuse is intentionally omitted for those entries.
- Known symbolic links are rejected before chmod. Permission changes do not bypass the existing server-side safe-path checks.
- Remote credentials, private keys, `known_hosts`, credential encryption keys and runtime/private data remain server-side and are excluded from release packages.
- Plain FTP remains unencrypted on the wire. Explicit FTPS, SFTP and HTTPS WebDAV retain the transport verification requirements introduced by V1.29.

## Database / configuration

Version 1.31.0 adds **no database migration**, schema change, or new required secret/configuration.

Existing V1.29/V1.30 Remote configuration remains in force. In particular, do not replace an existing `APP_REMOTE_CREDENTIAL_KEY_B64` after Remote credentials have been stored.

## Upgrade summary

1. Back up the application, `config/local.php`, database, File Library storage and other private runtime data.
2. Deploy Version 1.31.0 without replacing `config/local.php`, credential keys, private keys, `known_hosts`, uploads, logs, cache, sessions, DB dumps or runtime temp contents.
3. Reload the browser and confirm `RSS Reader Modernization 1.31.0` is visible.
4. Open Remote Files and verify Connection Test/listing/download for each protocol actually configured in production.
5. On a disposable regular file, confirm Permission display when available and change `644 -> 640 -> 644` through the preset UI.
6. On a disposable directory, confirm `755 -> 750 -> 755` when the target server supports chmod.
7. For FTP/FTPS, confirm a server that does not expose permission metadata can still list files successfully with `—` rather than failing the page.
8. Remove disposable test files/directories after verification.

## Release assets

- `rss-reader-modernization-1.31.0.zip`
- `rss-reader-modernization-1.31.0.zip.sha256`
- `rss-reader-modernization-1.31.0-complete.zip`
- `rss-reader-modernization-1.31.0-complete.zip.sha256`

## Verification limits

V1.31 A-G used focused architecture, backend/provider, API, browser UI, permission parsing, FTP/FTPS response classification, supplemental LIST enrichment, SFTP quote-path, validation and package/security checks. Production FTPS verification confirmed that the target server accepts actual permission changes and that permission acquisition can be improved without replacing MLSD as the authoritative listing.

The final H flow promotes durable V1.31 permission contracts into the current feature suite and runs `tests/run-current.sh` plus `tests/run-current-features.sh` in GitHub Actions on PHP 8.1 and PHP 8.4. The standard Release workflow repeats release-ready validation, both PHP regressions, high-signal source secret scanning, deterministic Runtime/Complete package verification, clean-room extraction, main-SHA revalidation, immutable tag protection and GitHub Release publication.

A final live SFTP production endpoint verification was not performed for this release, so SFTP production interoperability is not claimed beyond the focused automated tests and libcurl-compatible command construction. Actual FTP/FTPS/SFTP/WebDAV behavior can still vary with the target server, cURL build and filesystem policy.
