# RSS Reader Modernization 1.21.0

Version 1.21.0 is the Drawer / Navigation organization and readability release based on the formal Version 1.20.1 baseline.

## Highlights

- Drawer categories are now organized as DISPLAY, FEED, PRODUCTIVITY, INFORMATION, MEDIA, GAME, SETTINGS, USER LINKS, and ACCOUNT where applicable.
- Existing Mail and Camera / Video functions remain intact while being presented in PRODUCTIVITY and MEDIA.
- Drawer visual hierarchy is clearer without category-by-category rainbow coloring.
- Current state remains distinct, Logout retains Danger styling, and keyboard focus remains visible.
- Smartphone Drawer scrolling, safe-area handling, 44px touch targets, Modal fit, and long-label behavior are refined.
- RSS / Information Widget Catalog accordion chevrons are moved slightly inward on Smartphone for easier operation.

## Compatibility

- Bootstrap 5 Offcanvas remains the Drawer implementation.
- Existing jQuery support remains in place.
- No database migration is required for Version 1.21.0.
- No `config/local.php` change is required.
- Existing authentication, CSRF, SSRF, XSS, PDO, Session, and secret-handling protections are not intentionally changed by this release.

## Deferred from Version 1.21

- File Upload / File Library / Image Viewer
- Imgur Random / Gallery Widget
- Whole-dashboard Grid alignment for Height 2 Widgets

## Release assets

- Runtime ZIP: `rss-reader-modernization-1.21.0.zip`
- Runtime SHA-256: `rss-reader-modernization-1.21.0.zip.sha256`
- Complete Source ZIP: `rss-reader-modernization-1.21.0-complete.zip`
- Complete Source SHA-256: `rss-reader-modernization-1.21.0-complete.zip.sha256`

## Production update

Back up the current application, extract the Runtime ZIP, preserve the Production `config/local.php` and runtime data, overwrite the application files, and perform one hard reload. See `APPLY_NOTE_V1_21_0.md` and `docs/v1-21-0-production-checklist.md` for the verification points.
