# RSS Reader Modernization 1.27.0

Release tag: `v1.27.0`
Release date: 2026-08-30

## Overview

Version 1.27.0 extends article URL tracking-parameter cleanup and adds an authenticated, owner-scoped File Library. Uploaded files are stored outside `public/` in private storage, while the database keeps metadata only. The File Library provides responsive browsing, secure upload/download/delete, drag-and-drop file selection, and an in-page Image Viewer for validated image types.

The release keeps the existing authentication, session, CSRF, SSRF, XSS, PDO and public-endpoint protections. File upload does not trust the browser-provided MIME type, does not expose the physical stored name or path, and does not extract or execute ZIP files.

## Main changes

- Expanded article URL tracking-parameter removal for common `utm_*`, click-id and campaign parameters while leaving registered Feed URLs unchanged.
- Normalized remaining Dashboard header and touch-target inconsistencies without redesigning the existing grid or drag-and-drop model.
- Added authenticated secure upload with private `var/uploads/` storage.
- Default per-file limit is 10 MiB. Supported extensions are JPEG/JPG, PNG, GIF, WebP, PDF, TXT, CSV and ZIP.
- Added strict server-side Fileinfo MIME validation plus image structure or non-image signature/content validation.
- Physical files receive a 256-bit random stored name; the original filename is metadata/display information only.
- Added `/file-library`, owner-scoped listing, newest-first ordering and fixed 24-item pagination.
- Added responsive File Library cards: two columns on small screens, three on medium screens and four on extra-large screens.
- Added authenticated thumbnails, download and soft-delete plus best-effort physical deletion.
- Added upload progress/spinner feedback and drag-and-drop file selection without automatic upload.
- Added a Bootstrap Image Viewer for validated images. The Viewer builds only the fixed protected `file_content.php?id=...&mode=view` URL from a positive numeric file id.
- Added File Library to the shared Drawer.
- Finalized application/public asset revision markers at `1.27.0`.

## Security / compatibility

- Upload and File Library operations require an authenticated session; state-changing requests remain CSRF protected.
- File ownership always comes from the authenticated session user, not request-supplied user ids.
- Browser MIME metadata is ignored. `finfo(FILEINFO_MIME_TYPE)` is the MIME authority.
- Image files are additionally checked with `getimagesize`, bounded dimensions and pixel count.
- PDF, TXT/CSV and ZIP receive explicit content/signature checks. ZIP is never opened with `ZipArchive`, extracted, or executed.
- Dangerous executable/script extensions and dangerous double extensions remain blocked.
- Uploaded files are stored outside `public/`; storage resolution is confined to the configured private directory.
- Stored physical names use `random_bytes(32)` and are revalidated before filesystem access.
- Content serving resolves files by authenticated owner + numeric id, revalidates size/MIME/content at serve time, and never accepts a filesystem path from the request.
- Only validated images may be served inline. PDF/TXT/CSV/ZIP remain attachment-only.
- File responses retain `X-Content-Type-Options: nosniff`, same-origin resource policy, restrictive CSP and no-store behavior.
- The public PHP endpoint allowlist remains deny-by-default and explicitly contains only the required File Library/upload/content endpoints.
- Existing RSS, Stock, Calendar, Information Board and Dashboard data contracts remain unchanged except for the new V1.27 `user_file` metadata table.

## Database migration

Existing Version 1.26.0 installations must back up the database and apply:

1. `database/migrations/020_v1_27_user_files.sql`

Set `@table_prefix` in the migration to the same value as `DB_TABLE_PREFIX` before execution.

Migration 020 adds the metadata-only `<prefix>user_file` table with owner, original filename, random stored filename, server-detected MIME, canonical extension, size, created time and active/deleted flag. Uploaded binary data is not stored in the database.

For a fresh installation, `database/schema.sql` already integrates the same V1.27 `user_file` table contract. Follow `docs/installation.md` for the repository's normal fresh-install migration procedure.

## Deployment configuration

The application default is 10 MiB per file. Production PHP/Web server limits must allow that request size. A typical deployment uses:

- `upload_max_filesize = 10M` or larger
- `post_max_size = 12M` or larger
- `APP_FILE_UPLOAD_MAX_BYTES=10485760` if the optional application limit is explicitly configured
- `APP_FILE_UPLOAD_MAX_REQUEST_BYTES=12582912` if the optional request limit is explicitly configured

Keep the configured upload directory outside `public/` and writable only as required by the PHP/Web server account.

## Upgrade summary

1. Back up application code, `config/local.php`, database and required runtime data.
2. Apply Migration 020 using the deployment's actual table prefix.
3. Confirm private upload storage exists outside `public/` and is writable by the application.
4. Confirm PHP/Web server upload/body limits allow a 10 MiB file plus multipart overhead.
5. Deploy Version 1.27.0 without overwriting private configuration/runtime data.
6. Reload the browser and confirm the footer reports `RSS Reader Modernization 1.27.0`.
7. Open File Library and verify image/PDF/TXT/CSV/ZIP upload, spinner, drag-and-drop selection, download and delete.
8. Verify an uploaded image opens in the in-page Image Viewer and non-image files do not receive inline-view behavior.
9. Where a second account is available, verify another owner's file id cannot be read or deleted.
10. Verify normal RSS article links remove the supported tracking parameters while registered Feed URLs are unchanged.
11. Check Browser Console and PHP/Web server logs for new errors.

## Release assets

- `rss-reader-modernization-1.27.0.zip`
- `rss-reader-modernization-1.27.0.zip.sha256`
- `rss-reader-modernization-1.27.0-complete.zip`
- `rss-reader-modernization-1.27.0-complete.zip.sha256`

## Verification limits

The V1.27 integration gate completed the current regression/current-feature suites, focused V1.27 contracts, PHP/JavaScript syntax checks, secret/high-signal execution scans, schema/migration consistency checks and deterministic checkpoint package verification. The formal GitHub Release gate again runs `tests/run-current.sh` and `tests/run-current-features.sh` on PHP 8.1 and PHP 8.4, version/dependency/workflow hygiene checks, high-signal secret scanning, Runtime and Complete Source package verification, SHA-256 checks, clean-room extraction, immutable-tag protection and main-SHA rechecks before publication.

The target environment remains responsible for real production MySQL/MariaDB migration execution, deployment-specific PHP/Web server body limits and permissions, real browser rendering/Bootstrap behavior, filesystem ownership, and the final post-deployment smoke check.
