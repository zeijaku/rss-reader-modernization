# RSS Reader Modernization 1.29.0

Release tag: `v1.29.0`
Release date: 2026-09-01

## Overview

Version 1.29.0 adds an authenticated Remote File Manager while preserving the existing RSS Reader and File Library security boundaries. FTP, explicit FTPS, SFTP and HTTPS WebDAV connections can be registered per user and used for directory navigation, upload/download, mkdir, rename/move, delete, refresh, File Library transfer and bounded preview operations.

Remote credentials are never stored in plaintext. They are encrypted server-side with Sodium XChaCha20-Poly1305 AEAD using owner/connection-bound authenticated data, while the encryption key remains private deployment configuration outside the database and repository.

## Main changes

- Added `/remote-files` with connection list/register/edit/delete and read-only Connection Test.
- Added FTP, explicit FTPS, SFTP and HTTPS WebDAV providers behind a shared Remote File service boundary.
- Added remote directory listing/navigation, file size/update metadata, upload/download, mkdir, rename/move, delete and refresh.
- Added Remote -> File Library and File Library -> Remote transfers without exposing private stored filenames or filesystem paths to the browser.
- Reused the existing bounded Image/PDF/TXT/CSV preview and content-validation boundaries for eligible remote files. ZIP remains non-previewable and is never extracted.
- Added responsive/touch-oriented Remote Files UI and shared Drawer navigation entry.
- Added production environment diagnostics through `tools/remote_file_env_check.php`.
- Grouped V1.29 backend implementation under `app/remote_file/` for maintainability.

## Security / compatibility

- Remote connections are owner scoped and all mutating JSON/multipart operations require authenticated session + CSRF validation.
- Hostname and port values are validated before transport. DNS answers are checked on each remote operation and validated addresses are pinned to transport where supported.
- Public IP targets are the default. Private/LAN targets require both administrator CIDR configuration and per-connection opt-in. Loopback, link-local and other blocked classes remain denied.
- WebDAV redirects are manually handled, same-origin, DNS/IP revalidated and confined to the configured Base Path; automatic redirect following remains disabled.
- Relative paths are normalized by segments and confined under the configured Base Path. Traversal, NUL/control characters and backslash path forms fail closed.
- Entries identified as symbolic links or unknown types are refused for protected operations. Because FTP/SFTP listing metadata is not uniform across servers, a dedicated server-side root/chroot remains recommended as the final boundary.
- SFTP requires a verified known_hosts file and host-key validation.
- FTPS and WebDAV keep TLS peer and hostname verification enabled. PHP `ftp_ssl_connect()` is not used.
- Plain FTP credentials and file data are unencrypted on the wire; the UI warns about this protocol risk.
- Transfers and previews use bounded streaming/private temporary storage rather than unbounded whole-file memory loading.
- Remote credentials, private keys and encrypted credential envelopes are not returned to JavaScript or logged by normal application paths.

## Database migration

Version 1.29.0 requires one new existing-database migration:

`database/migrations/021_v1_29_remote_connection.sql`

Back up the database first. Set the migration `@table_prefix` to the same value as `DB_TABLE_PREFIX`, then apply Migration 021 once. It adds the owner-scoped `remote_connection` table only; existing tables/columns are not removed or rewritten.

Fresh installations use `database/schema.sql`, which already includes the V1.29 Remote connection table. Do not re-run `schema.sql` against an existing database.

## Required private configuration

Generate a dedicated 32-byte key and keep it outside Git/package contents:

`php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`

Configure at least:

- `APP_REMOTE_CREDENTIAL_KEY_ID=primary`
- `APP_REMOTE_CREDENTIAL_KEY_B64=<generated Base64 value whose decoded length is exactly 32 bytes>`
- `APP_REMOTE_ALLOWED_PORTS=21,22,443` adjusted to the minimum ports actually needed
- `APP_REMOTE_TEMP_DIR=<private writable directory outside public/>`
- `APP_REMOTE_SSH_KNOWN_HOSTS_FILE=<verified known_hosts path>` when SFTP is used

For private/LAN targets, additionally configure `APP_REMOTE_PRIVATE_NETWORK_ENABLED=true` and the narrowest practical `APP_REMOTE_PRIVATE_NETWORK_CIDRS`, then enable private-network use on only the required connection.

Run `php tools/remote_file_env_check.php` after deployment. It validates the credential-key shape without printing the secret and reports the relevant cURL/protocol/extension capabilities.

## Upgrade summary

1. Back up application code, `config/local.php`, database, File Library storage and other private runtime data.
2. Apply `database/migrations/021_v1_29_remote_connection.sql` once after matching `@table_prefix` to `DB_TABLE_PREFIX`.
3. Generate/configure the Remote credential key and private temporary directory. Do not overwrite an existing production key after credentials have been stored.
4. Configure only the required Remote ports/private CIDRs and verified SFTP known_hosts data.
5. Deploy Version 1.29.0 without replacing `config/local.php`, private keys, known_hosts, File Library uploads or other runtime-private data.
6. Run `php tools/remote_file_env_check.php`.
7. Reload the browser and confirm the footer/login reports `RSS Reader Modernization 1.29.0`.
8. Open `/remote-files`, create a non-critical test connection and run Connection Test.
9. Verify directory listing/navigation, upload, preview/download, mkdir, rename/move, Remote -> File Library, File Library -> Remote and delete in a test Base Path.
10. Verify invalid credentials fail safely; for SFTP verify host-key mismatch rejection, and for FTPS/WebDAV verify untrusted TLS certificate rejection.
11. Check Browser Console and PHP/Web server logs for new errors and confirm no credential material is logged.

## Release assets

- `rss-reader-modernization-1.29.0.zip`
- `rss-reader-modernization-1.29.0.zip.sha256`
- `rss-reader-modernization-1.29.0-complete.zip`
- `rss-reader-modernization-1.29.0-complete.zip.sha256`

## Verification limits

V1.29 B-I used focused security, provider, operation, File Library integration, UI and package checks. The production checkpoint also confirmed real FTP connection registration/authentication after the Remote credential key was configured correctly. V1.29-J runs the durable current regression/current-feature suites, PHP/JavaScript syntax, release/workflow/version hygiene, secret scanning, deterministic Runtime/Complete package verification and clean-room extraction. GitHub Actions provides the release matrix with PHP 8.1 and PHP 8.4 plus curl, mbstring, pdo_mysql, pdo_sqlite and simplexml.

Protocol behavior can still vary with the target server and deployment cURL build. The target environment remains responsible for validating the actual FTP/FTPS/SFTP/WebDAV servers it uses, SFTP known_hosts provenance, FTPS/WebDAV certificate trust, private-network CIDRs, filesystem permissions, PHP/Web server upload/time limits, Smartphone rendering and the final post-deployment smoke check. Remote text editing, SCP, SMB/NFS, S3/cloud drives, shell commands, chmod/chown, archive browsing and background synchronization are outside Version 1.29.0.
