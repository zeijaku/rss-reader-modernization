# RSS Reader Modernization 1.36.0

V1.36.0 adds a persistent in-app Dashboard Notification Center and Calendar reminders, then finishes the Calendar editing experience with duration-preserving date/time moves, a more compact responsive event dialog, and a sticky Dashboard navbar.

## Main changes

### Dashboard Notification Center

- Add a navbar bell with unread badge and an authenticated Notification Center.
- Persist notifications per owner and support read, mark-all-read, and hide actions.
- Keep safe target URLs so a Calendar reminder can reopen the related Calendar event or occurrence.
- Isolate reminder synchronization failures from the rest of the Dashboard.

### Calendar reminders

- Add reminder choices: none, event time, 10 minutes, 30 minutes, 1 hour, or 1 day before.
- Treat 09:00 Asia/Tokyo as the event-time reference for all-day reminders.
- Support ordinary events and recurring series. Each occurrence inherits the series reminder setting.
- Reschedule a pending occurrence reminder when that occurrence is moved or its effective time changes.
- Remove only the pending reminder for a cancelled occurrence and recreate it when the occurrence is restored.
- Preserve already-due notification history instead of rewriting or deleting past notifications.
- Use stable per-occurrence source keys and bounded rolling synchronization to avoid duplicate reminder materialization.
- Copying one occurrence continues to create an independent event and preserves the inherited reminder value.

### Calendar and Dashboard usability

- Moving the start date or time preserves the existing event duration, including multi-day and cross-midnight events.
- If the end date/time is edited directly, that new duration becomes the baseline for the next start move.
- Add browser-side minimum constraints for end date/time while retaining the existing server-side validation.
- Use a wider, internally scrollable Calendar event dialog on larger screens and a one-column layout on Smartphones.
- Keep URL and Memo in an expandable Details section without removing either field; existing content opens the section automatically.
- Compact recurrence and occurrence-scope controls while retaining series / occurrence editing behavior.
- Keep the Dashboard navbar visible during page scrolling with sticky positioning; modal, offcanvas, and Notification Center layers remain above it.

## Database upgrade

Existing V1.35.4 databases must apply these additive migrations in order:

1. `database/migrations/030_v1_36_notification_center.sql`
2. `database/migrations/031_v1_36_calendar_reminder.sql`

Migration 030 creates the per-owner `notification` table. Migration 031 adds `calendar_event_reminder` to the existing Calendar event table with the default value `none`.

Fresh installations use `database/schema.sql`, where both changes are already integrated; do not run 030/031 again solely for a fresh installation.

If the V1.36 development checkpoints already applied 030 and 031 in production, do not reapply them just because the version is being promoted to 1.36.0.

## Security and compatibility

- Existing authentication, session, owner scope, CSRF, input validation, Calendar occurrence revision checks, output escaping, and notification ownership boundaries remain in place.
- Existing Calendar data, occurrence exceptions, Mail accounts, RSS data, File Library data, Remote Files settings, and account-security data are preserved.
- No new required configuration, credential format, external dependency, background scheduler, web push, or email notification channel is introduced.
- V1.36 notifications are in-app only. Reminder materialization occurs through the application while it is in use; there is no promise of background delivery while the application is closed.

## Verification completed

- V1.36-A Notification Center foundation was production-checked before later Calendar integration.
- V1.36-B ordinary Calendar reminders were production-checked.
- V1.36-D duration-preserving date/time editing, compact responsive Calendar dialogs, and sticky navbar were production-checked.
- Dedicated occurrence-reminder tests cover rolling materialization, owner isolation, moved occurrences, cancellation, restore, series reminder changes, duplicate prevention, due-history preservation, and effective target URLs.
- V1.36-D runtime tests cover multi-day movement, 90-minute timed events, user-edited duration baselines, cross-midnight movement, and new-event end-date initialization.
- Current CI runs the complete regression gate on PHP 8.1 and PHP 8.4.

## Verification limits

- Final PHP 8.1 and PHP 8.4 CI and the Release workflow must pass before the immutable tag and release assets are considered complete.
- One MariaDB-server-tools migration test may be skipped on GitHub-hosted runners when those server tools are unavailable; the migration contracts remain covered by the other current tests.
- In-app reminders do not run as a background service when the application is closed.
- The Release workflow does not automatically deploy the formal package to the production environment.

## Release assets

The Release workflow publishes:

- `rss-reader-modernization-1.36.0.zip`
- `rss-reader-modernization-1.36.0.zip.sha256`
- `rss-reader-modernization-1.36.0-complete.zip`
- `rss-reader-modernization-1.36.0-complete.zip.sha256`
