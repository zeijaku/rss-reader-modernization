# RSS Reader Modernization 1.35.1

V1.35.1 is a focused usability release for the Calendar month view and Remote Text Editor.

## Main changes

### Calendar

- Show dates, events, cancelled occurrences, holidays, and Tasks in the visible previous/next-month cells while retaining a dimmed treatment.
- Request the complete visible 28/35/42-day grid through the existing bounded Calendar range API while keeping the toolbar label anchored to the selected month.
- Focus the add-event title after the modal opens only on hover + fine-pointer input environments. Touch-first devices do not automatically open the software keyboard.

### Remote Text Editor

- Add synchronized visual line numbers beside the existing textarea.
- Keep the gutter aligned while scrolling and update numbering as text changes or remote content is reloaded.
- Keep line numbers outside the textarea and Base64 save transport so they never enter the saved file.

## Database and configuration

- No database migration is required when updating from V1.35.0.
- No required configuration, credential, dependency, or endpoint change is introduced.
- Existing Gmail OAuth2 and password-based Mail Account settings remain unchanged.

## Security and compatibility

- Existing authentication, owner scope, CSRF, output escaping, Calendar range limits, and date bounds remain unchanged.
- Remote Editor conflict detection, size/type limits, UTF-8 validation, line endings, UTF-8 BOM handling, and Base64 transport remain unchanged.
- Adjacent-month cells outside the supported 2000-01-01 through 2100-12-31 range remain disabled.

## Production verification completed

- Previous/next-month Calendar dates and entries display with dimmed styling.
- Calendar add-event title focus works on PC-style pointer input without automatically opening the Smartphone keyboard.
- Remote Editor line numbers update and scroll correctly without changing saved file content.

## Verification limits

- Final PHP 8.1 and PHP 8.4 CI and the release workflow must pass before the immutable tag and assets are published.
- Browser rendering can vary slightly by operating system, browser, theme, font, and input device.
- External Calendar, Remote File, mail, and network services retain their existing environment-specific behavior.

## Release assets

The release workflow publishes:

- `rss-reader-modernization-1.35.1.zip`
- `rss-reader-modernization-1.35.1.zip.sha256`
- `rss-reader-modernization-1.35.1-complete.zip`
- `rss-reader-modernization-1.35.1-complete.zip.sha256`
