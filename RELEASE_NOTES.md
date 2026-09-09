# RSS Reader Modernization 1.33.1

Intended release tag: `v1.33.1`
Release date: 2026-09-09

## Overview

Version 1.33.1 is a Remote Files patch release. It adds multiple file Upload to the existing Remote Files dialog without changing the single-file server API or Calendar behavior from 1.33.0.

## Main changes

- The Remote Files Upload picker accepts multiple files and sends one request per file in selection order.
- Every request uses the existing `remote_file_upload_api.php` contract, current CSRF token, same-origin credentials, owner scope and server-side size/content validation.
- A single failed file does not abort later files. The dialog reports successful and failed counts plus failed names, while disabling overlapping submits during processing.
- Existing one-file selection remains supported; overwrite behavior is applied independently to each request.

## Calendar baseline

- Existing `red`, `blue` and `green` event colors remain unchanged; `yellow` and `purple` are added without a color-only migration.
- Single-day, multi-day and recurring occurrences use common inclusive date-range and occurrence-identity helpers.
- A user can edit, delete and restore one occurrence of a recurring series while retaining series-wide edit/delete operations.
- Month view renders multi-day events as connected `single` / `start` / `middle` / `end` segments, including week and month boundaries.
- Day and week views place timed events against their hour lanes; all-day and multi-day events remain in a separate area.
- Day/week/month switching is integrated into the compact responsive Calendar toolbar for PC and smartphone layouts.

## Asset loading stabilization

- Dynamically loaded JavaScript now starts in its existing dependency order instead of releasing the entire chain at once.
- Dynamically loaded stylesheets start in small batches while retaining their original cascade order.
- A failed JavaScript or stylesheet request is retried once after 600ms with a retry-only cache marker.
- API mutations and Calendar data contracts are unchanged and are never automatically retried by this loader.
- No database, configuration, UI or Calendar feature change was introduced by this patch.

## Deferred Calendar improvements

The following requested features are intentionally not included in V1.33 and are recorded for a later version:

- Schedule copy, with an explicit choice between copying one occurrence and copying a recurring series.
- Schedule Drag & Drop for date movement in month view and time/date movement in day/week views.

Both features must reuse the V1.33 range/occurrence identity, require confirmation of recurring-event scope, preserve optimistic revision checks, and provide a non-drag mobile/keyboard fallback. Details are in `docs/v1-33-future-calendar.md`.

## Database / configuration

Existing V1.32 installations apply the additive Migration `database/migrations/025_v1_33_calendar_event_exception.sql` once after a backup. Set `@table_prefix` to the actual `DB_TABLE_PREFIX` before execution. The migration creates only the owner-scoped `calendar_event_exception` table; it does not drop, truncate or rewrite existing Calendar records.

Fresh installations already include the exception table in `database/schema.sql` and must not run Migration 025 again.

No new mandatory configuration or secret is added. Keep `config/local.php`, `APP_HASH_KEY`, `APP_TOTP_SECRET_KEY_B64`, Remote credential keys and private runtime data unchanged.

## Security / compatibility

- Authentication, Authorization/Owner Scope, CSRF, XSS escaping, PDO parameterization, input validation and Session boundaries remain in force.
- Step-up Authentication, 2FA, Recovery Codes, Session Registry and Authentication Security Audit Log are not weakened or bypassed.
- Calendar title, note and URL output continues through the existing escaping and URL-validation policy.
- Exception lookup and mutation are owner-scoped to both the authenticated owner and the underlying event.
- Existing three-color values, recurrence rows and non-recurring events remain readable without data conversion.

## Upgrade summary from V1.33.0

1. Back up the application, `config/local.php`, database and private runtime data.
2. Extract the Runtime ZIP outside the live directory and verify its SHA-256.
3. Overlay the packaged `app/` and `public/` paths while preserving private configuration and runtime data. No SQL is required for this patch.
4. Reload the browser and confirm `RSS Reader Modernization 1.33.1` is visible.
5. Complete the ordered production checks in `docs/v1-33-1-release.md`.

## Verification limits

The source and packages are verified by the tests available in each build environment and by deterministic package integrity checks. The production RC result was accepted. PHP 8.1, PHP 8.4, live MariaDB/MySQL migration, all browser/theme/responsive combinations and hosting-specific behavior remain separately recorded when the relevant runtime is unavailable locally.

The immutable `v1.33.1` tag and GitHub Release may be published only from the exact `main` commit that passes the GitHub Actions Current, Feature, Security, Migration, Package and Clean-room gates.
