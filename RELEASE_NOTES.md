# RSS Reader Modernization 1.28.0

Release tag: `v1.28.0`
Release date: 2026-08-31

## Overview

Version 1.28.0 extends the authenticated File Library introduced in V1.27. The existing private storage and metadata-only database contract remain unchanged while File Detail, protected PDF viewing, bounded TXT/CSV previews, and Smartphone-oriented action/Modal polish are added.

The release keeps the existing authentication, session, CSRF, owner scope, private-path resolution, serve-time content validation, XSS boundaries, and public-endpoint protections. ZIP remains download-only and is never opened, extracted, or executed by the application.

## Main changes

- Added File Detail with original filename, MIME type, extension, formatted size, upload time, numeric file id, and image dimensions when available.
- File Detail never returns the stored random filename, filesystem path, or owner id.
- Added protected PDF preview through the existing authenticated file content endpoint for validated PDF files only.
- PDF display relies on the browser-native PDF viewer. No PDF.js, CDN dependency, server-side PDF parser, or secondary remote fetch is introduced.
- Added UTF-8 TXT Preview bounded to 64 KiB and 300 lines. UTF-8 BOM is accepted; invalid/non-UTF-8 content fails safely and full download remains available.
- Added UTF-8 CSV Preview bounded to 512 KiB, 50 data rows, 30 columns, and 64 KiB per logical record using bounded `fgetcsv` parsing.
- TXT/CSV content is inserted as text rather than HTML.
- Added responsive File Library action polish, including a touch-friendly 2x2 layout when four actions are present on narrow cards.
- Improved long filename/metadata wrapping and PDF/TXT/CSV/File Detail Modal behavior on Smartphone widths.
- Removed development phase badges from File Library and RSS Management while retaining the central application version label for deployment verification.
- Finalized application and active public asset revision markers at `1.28.0`.

## Security / compatibility

- Preview/detail operations start from the authenticated session user and an owner-scoped positive numeric file id.
- The server resolves private storage paths internally; request data cannot select a stored random filename, owner id, or filesystem path.
- Files are revalidated before preview/content responses are returned.
- File responses retain `Cache-Control: no-store`, `X-Content-Type-Options: nosniff`, `Cross-Origin-Resource-Policy: same-origin`, and restrictive sandbox CSP behavior.
- PDF preview is limited to validated PDF content and does not enable server parsing.
- TXT and CSV previews are UTF-8-only and bounded before rendering; invalid encoding fails closed.
- CSV parsing is bounded by total bytes, rows, columns, and per-record bytes to avoid unbounded preview work.
- Dynamic TXT/CSV/File Detail values are rendered through text-safe DOM paths rather than user-controlled HTML.
- ZIP remains storage/download only; there is no `ZipArchive`, extraction, archive browser, or execution path in V1.28.
- Existing V1.27 upload MIME/content checks, random physical names, dangerous-extension rejection, private storage, owner scope, and soft-delete behavior remain in place.
- No new required external service, secret, environment variable, or permission change is introduced.

## Database migration

No database migration is required for Version 1.28.0.

Existing Version 1.27.0 installations already using `database/migrations/020_v1_27_user_files.sql` keep the same metadata-only `user_file` table contract. Do not reapply Migration 020 solely for V1.28.

For a fresh installation, `database/schema.sql` already contains the File Library table introduced in V1.27.

## Upgrade summary

1. Back up application code, `config/local.php`, database, and `var/uploads/` before deployment.
2. No V1.28 SQL/Migration is required when upgrading from Version 1.27.0.
3. Deploy Version 1.28.0 without overwriting private configuration or runtime upload data.
4. Reload the browser and confirm the footer/login reports `RSS Reader Modernization 1.28.0`.
5. Open File Library and verify Image Viewer, File Detail, PDF Viewer, TXT Preview, CSV Preview, Download, Delete, upload, and drag-and-drop selection.
6. On Smartphone width, verify four-action cards remain touch-friendly and PDF/TXT/CSV/File Detail Modals remain usable.
7. Verify a different authenticated user cannot access another user's file id.
8. Verify invalid/non-UTF-8 TXT/CSV preview fails safely while the normal download path remains available.
9. Verify ZIP has download/delete actions only and no archive extraction/browser action appears.
10. Check Browser Console and PHP/Web server logs for new errors.

## Release assets

- `rss-reader-modernization-1.28.0.zip`
- `rss-reader-modernization-1.28.0.zip.sha256`
- `rss-reader-modernization-1.28.0-complete.zip`
- `rss-reader-modernization-1.28.0-complete.zip.sha256`

## Verification limits

The V1.28-G integration checkpoint completed the durable current regression/current-feature gates on GitHub Actions for PHP 8.1 and PHP 8.4, together with focused File Library preview/integration contracts, syntax checks, version/workflow hygiene, owner/private-storage/security checks, and checkpoint package verification. V1.28-H formalizes the version and release documentation, then the generic GitHub Release workflow again runs `tests/run-current.sh` and `tests/run-current-features.sh` on PHP 8.1 and PHP 8.4, release-readiness/version/workflow hygiene checks, high-signal secret scanning, deterministic Runtime and Complete Source package verification, SHA-256 checks, clean-room extraction, immutable-tag protection, and main-SHA rechecks before publication.

The target environment remains responsible for real browser-native PDF rendering differences, actual Smartphone rendering, deployment-specific PHP/Web server limits and permissions, filesystem ownership, and the final post-deployment smoke check.
