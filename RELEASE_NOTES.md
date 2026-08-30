# RSS Reader Modernization 1.26.0

Release tag: `v1.26.0`
Release date: 2026-08-30

## Overview

Version 1.26.0 adds the Information Board Widget to the Dashboard. The widget is inspired by an electronic NEWS board: the NEWS label and current article title remain fixed while only the current RSS summary scrolls from right to left. After the summary fully leaves the left edge, the board advances to the next article and repeats.

The widget uses only RSS data already obtained through the existing hardened feed path. It does not scrape article pages or introduce a secondary article fetch. A specific Feed is stored by owner-scoped `content_id`, not as an arbitrary raw URL.

## Main changes

- Added Information Board to the Dashboard Information category.
- Added All RSS / specific Feed selection.
- Added item limits of 5 / 10 / 20.
- Added slow / normal / fast horizontal summary speed.
- Added summary ON / OFF while preserving compatible stored summary settings.
- Expanded displayed RSS summary content up to the existing 4096-character RSS safety ceiling where fuller sanitized RSS content is available.
- Kept NEWS and current article title fixed while only the summary moves right-to-left.
- Article changes occur only after the current summary fully exits the left edge.
- Added visible pause / resume, desktop hover/focus pause, touch/wheel interaction pause, page-hidden pause, and `prefers-reduced-motion` handling.
- Added previous / next article controls with first/last wrap-around and temporary automatic-movement pause after manual navigation.
- Added source site, article date, current/total position, NEXT article preview, and summary progress in the lower board area.
- Matched the converted Information Board header to the existing 44px Dashboard feed-card header while retaining 44px Smartphone touch targets.
- Finalized application and public asset revision markers at `1.26.0`.

## Security / compatibility

- Existing authentication, authorization, CSRF, request-size and owner-scope boundaries are unchanged.
- Specific Feed configuration stores `content_id`; arbitrary article or Feed URLs are not added to the Information Board configuration boundary.
- Feed retrieval continues through the existing hardened RSS fetch/parser path and SSRF protections.
- Remote RSS text remains inside the existing sanitization/text-rendering boundary.
- The ticker adds no new `fetch`, `XMLHttpRequest`, jQuery Ajax, localStorage, or sessionStorage path.
- The earlier experimental separate `info-board-navigation.js`, global Ajax startup gate, and RSS startup scheduling workaround are not included.
- No new required secret or external service is introduced.

## Database migration

No database migration is required for Version 1.26.0. Information Board configuration reuses the existing owner-scoped widget/search storage contract and registered Feed `content_id`.

## Upgrade summary

1. Back up the application code, `config/local.php`, database, and required runtime data.
2. Deploy the Version 1.26.0 application files without overwriting private config/runtime data.
3. Reload the browser and confirm the footer reports `RSS Reader Modernization 1.26.0`.
4. Add an Information Board Widget and verify All RSS / specific Feed selection, item count, speed, summary setting, and height setting.
5. Confirm NEWS and the current article title remain fixed while only the summary moves horizontally.
6. Confirm previous / next, source/date/count, NEXT preview, and progress display work without affecting other Feed Widgets.
7. Confirm reduced-motion and Smartphone operation remain usable.
8. Check Browser Console and PHP/Web server logs for new errors.

## Release assets

- `rss-reader-modernization-1.26.0.zip`
- `rss-reader-modernization-1.26.0.zip.sha256`
- `rss-reader-modernization-1.26.0-complete.zip`
- `rss-reader-modernization-1.26.0-complete.zip.sha256`

## Verification limits

The formal release gate runs the full current regression suite and current feature suite on PHP 8.1 and PHP 8.4, version/dependency/workflow hygiene checks, high-signal secret scanning, package verification, SHA-256 verification, and clean-room extraction checks. V1.26-specific contracts cover the owner-scoped backend, Information Board UI, fixed-title horizontal-summary ticker, navigation, lower-board metadata, NEXT/progress display, reduced-motion behavior, 44px header/touch targets, cache propagation, and the absence of additional article-fetch/network paths.

The target environment remains responsible for deployment-specific PHP/Web server configuration, real browser rendering, external RSS availability, and final post-deployment smoke verification. The V1.26 Information Board candidate, including the corrected header height, expanded summary, navigation, lower-board metadata, NEXT preview, and progress bar, was physically verified before formalization.
