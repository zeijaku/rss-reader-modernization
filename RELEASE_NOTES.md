# RSS Reader Modernization 1.25.0

Release tag: `v1.25.0`
Release date: 2026-08-28

## Overview

Version 1.25.0 expands the existing Calendar Widget without replacing its established Event / Task model. Calendar events now support all-day or timed schedules, an optional end time, a related HTTP/HTTPS URL, and daily / weekly / monthly / yearly recurrence with an optional repeat-until date. Existing Task due dates continue to be displayed directly from the Task table rather than copied into Calendar events.

RSS and Stock article actions can pre-fill the existing Calendar registration modal with the article title and URL. This does not auto-save the event, does not fetch the article URL on the server, and does not change Stock processed / important / archived / Stock解除 state.

The Calendar UI also adds a clearer Today flow, a server-bounded 14-day upcoming list, compact three-item initial display with a more/close control, Smartphone presentation adjustments, modal focus handling, and month-switch layout stabilization.

## Main changes

- Calendar events can be all-day or timed.
- Timed events require a start time; end time is optional. Same-day end-before-start is rejected while a multi-day event may wrap to an earlier clock time on the ending date.
- Calendar events can store one optional related URL, limited to HTTP/HTTPS and 2048 characters.
- Repetition supports `none`, `daily`, `weekly`, `monthly`, and `yearly`, with an optional repeat-until date.
- Recurrence editing/deletion is series-level in V1.25.0. Per-occurrence exceptions are intentionally not implemented.
- Monthly recurrence skips months that do not contain the anchor day; yearly February 29 recurrence skips non-leap years and resumes on leap years.
- RSS / Stock article actions add `Calendarへ追加`, reusing the existing Calendar registration modal and pre-filling title + URL without auto-submit.
- Calendar event creation does not alter or remove the source Stock row.
- Today is visually emphasized and the Today button returns to the current month/day.
- Upcoming events cover today through the next 14 days, are server-bounded to eight results, and show the first three initially with `もっと見る` / `閉じる` controls.
- Calendar modal focus is released before Bootstrap applies its hidden state and restored after the modal is fully hidden, avoiding focused descendants being left inside an `aria-hidden` modal.
- Month navigation keeps the current Calendar grid height during asynchronous redraw to reduce visible layout shift.
- V1.25 Calendar contracts are promoted into the current CI/release feature suite.

## Database migration

Existing Version 1.24.0 installations must back up the database and apply these migrations in order:

1. `database/migrations/018_v1_25_calendar_event_time_url.sql`
2. `database/migrations/019_v1_25_calendar_recurrence.sql`

Set `@table_prefix` in each migration to the same value as `DB_TABLE_PREFIX` before execution.

Migration 018 adds these columns to `calendar_event`:

- `calendar_event_all_day` — `TINYINT UNSIGNED NOT NULL DEFAULT 1`
- `calendar_event_start_time` — nullable `TIME`
- `calendar_event_end_time` — nullable `TIME`
- `calendar_event_url` — nullable `VARCHAR(2048)`

Existing events therefore remain all-day by default and do not gain synthetic time/URL values.

Migration 019 adds:

- `calendar_event_repeat_type` — fixed recurrence type storage with default `none`
- `calendar_event_repeat_until` — nullable repeat end date

The migrations check existing schema state before adding their columns and do not delete or rewrite existing Calendar events.

For a fresh installation, `database/schema.sql` already integrates the Calendar columns from Migrations 013, 018, and 019 together with the V1.24 Stock state schema from Migration 017. Do not re-run integrated migrations after importing the fresh schema. Follow `docs/installation.md` for the remaining post-base migrations.

## Security / privacy

- Calendar mutation endpoints remain authenticated POST operations with CSRF validation and request-size limits.
- Recurrence actions use a fixed server-side action allowlist; client input is not used as a raw action name, SQL identifier, or file path.
- Calendar event reads/updates remain owner-scoped and limited to active rows.
- The stored Calendar URL is validation-only. V1.25.0 does not perform server-side outbound fetches to event or article URLs, so the feature does not introduce a new SSRF path.
- Active recurring series are resource-bounded to 50 per owner for recurrence expansion, and month expansion is bounded to 2000 occurrences.
- Upcoming event lookup uses a server-derived date window, is fixed to 14 days, returns at most eight events, and bounds its non-recurring source query.
- RSS / Stock to Calendar pre-fill is client-side and is revalidated by the existing Calendar server boundary on save.
- No new required secret, external Calendar credential, OAuth integration, reminder service, or background scheduler is introduced.

## Upgrade summary

1. Back up the application code, `config/local.php`, database, and required runtime data.
2. Apply Migration 018 with the deployment table prefix.
3. Apply Migration 019 with the same table prefix.
4. Deploy the Version 1.25.0 application files without overwriting private config/runtime data.
5. Reload the browser and confirm the footer reports `RSS Reader Modernization 1.25.0`.
6. Verify all-day and timed Calendar event creation/editing, optional URL, event colors, and recurrence.
7. Verify RSS and Stock article `Calendarへ追加` pre-fill title + URL without changing Stock state.
8. Verify Today, upcoming events, `もっと見る` / `閉じる`, month navigation, and Smartphone layout.
9. Open/close Calendar modals by backdrop, close button, X, and Escape and confirm no new focus/`aria-hidden` warning appears.
10. Confirm existing Task due-date display, Stock actions, RSS article actions, and recurrence endpoint access continue to work.
11. Check Browser Console and PHP/Web server logs for new errors.

## Release assets

- `rss-reader-modernization-1.25.0.zip`
- `rss-reader-modernization-1.25.0.zip.sha256`
- `rss-reader-modernization-1.25.0-complete.zip`
- `rss-reader-modernization-1.25.0-complete.zip.sha256`

## Verification limits

The formal release gate runs the full regression suite, compatibility suite, current feature suite including V1.25 Calendar contracts, security suite, version/dependency hygiene, high-signal secret scanning, deterministic package rebuild comparison, package verification, and clean-room extraction. Focused V1.25 tests additionally cover time/URL validation, recurrence calculations and resource bounds, RSS/Stock pre-fill contracts, modal focus behavior, upcoming-event bounds, and R3 compact/layout-stabilization behavior.

The target environment remains responsible for real MySQL migration execution, deployment-specific PHP/Web server configuration, actual Browser/Bootstrap focus lifecycle, and production rendering. V1.25 development overlays were verified in the target environment through F R3, including the compact upcoming display and month-switch layout improvement; the formal package should still receive the normal post-deployment smoke check.
