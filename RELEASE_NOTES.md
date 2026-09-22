# RSS Reader Modernization 1.35.2

V1.35.2 is a focused Calendar interaction release that removes unnecessary full Dashboard reloads after event changes.

## Main changes

### Calendar event operations

- Refresh every visible Calendar card after an ordinary event is created, updated, or deleted.
- Apply the same partial-refresh path to recurring-series creation, update, and deletion.
- Refresh the upcoming-event projection together with the visible Calendar cards.
- Close the completed event modal and show the existing success notice without disturbing unrelated Dashboard state or scroll position.

### Intentional boundary

- Calendar Widget creation, setting changes, and deletion continue to reload the full page because they change Dashboard structure.
- Occurrence-only operations and desktop date Drag & Drop retain their existing partial-refresh behavior.

## Database and configuration

- No database migration is required when updating from V1.35.1.
- No required configuration, credential, dependency, or endpoint change is introduced.

## Security and compatibility

- Existing authentication, owner scope, CSRF protection, Calendar validation, recurrence handling, and optimistic occurrence conflict checks remain unchanged.
- Existing event API actions and request payloads remain unchanged; only successful client-side completion behavior is updated.

## Production verification completed

- Ordinary event creation updates Calendar content without a full Dashboard reload.
- Ordinary event changes update Calendar content without a full Dashboard reload.
- Ordinary event deletion updates Calendar content without a full Dashboard reload.

## Verification limits

- Final PHP 8.1 and PHP 8.4 CI and the release workflow must pass before the immutable tag and assets are published.
- Browser rendering and perceived redraw timing can vary slightly by operating system, browser, theme, and device performance.
- External Calendar and network services retain their existing environment-specific behavior.

## Release assets

The release workflow publishes:

- `rss-reader-modernization-1.35.2.zip`
- `rss-reader-modernization-1.35.2.zip.sha256`
- `rss-reader-modernization-1.35.2-complete.zip`
- `rss-reader-modernization-1.35.2-complete.zip.sha256`
