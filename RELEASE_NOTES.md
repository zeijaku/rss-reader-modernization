# RSS Reader Modernization 1.34.2 - Display and Interaction Improvements

V1.34.2 is a patch release improving existing Dashboard and Calendar interactions while preserving existing features and data.

## Changes
- **A — Smartphone Modal:** Give Bootstrap modal content, body and footer an opaque active-theme background on Smartphone widths.
- **B — Calendar Copy:** Copy an existing event into the new-event dialog for review before saving. Copying one recurring occurrence creates an independent event; copying a series retains its recurrence settings.
- **C — Calendar Drag & Drop:** Move ordinary events and individual recurring occurrences between dates on PC. Preserve multi-day duration and event fields, refresh only the Calendar card, and retain 409 conflict resynchronization and bindings after redraw.
- **D — Card Mouse Wheel:** Allow native page scrolling over Dashboard cards while retaining existing internally scrollable areas.
- **E — Release Readiness:** Synchronize the formal version and asset revision to `1.34.2` and retain A–E contracts alongside the current core and feature regression suites.
- **Modal Close:** Use Bootstrap 5.3 `btn-close` with `data-bs-theme="dark"` for dark modal headers, including Dashboard, Stock, Settings and dynamically created Widget dialogs. Preserve the application-owned white close-icon styling.

## Security / compatibility
- Authentication, Session, owner scope, CSRF, input validation and Calendar occurrence revision checks remain intact.
- Existing Calendar APIs and data models are reused; this patch introduces no DB migration, API change, mandatory configuration, secret or dependency.
- Existing Mail received/Sent attachment display and download restored in V1.34.1 remain enabled, with their existing security and size boundaries.
- Runtime and Complete Source packages remain separate and use the existing deterministic builders, SHA-256 verification, secret scan and clean-room gates.

## Verification limits
- The user confirmed A–E and the Dashboard modal close correction in the dev.9 package on the rental server before finalization.
- Remaining legacy close-button declarations in Stock, Settings and dynamic Widget dialogs were found during finalization and corrected with the same Bootstrap 5.3 markup; this additional scope has not been verified on the user's production server.
- PHP 8.1 and PHP 8.4 must both pass the current CI regression gates before merge. The standard release workflow reruns both runtimes and package verification before publishing.
- These files prepare the formal release source; they do not record a completed merge, tag or GitHub Release publication. Each remains pending explicit user authorization.
- Updating `.github/release-request.txt` on main triggers the existing release workflow, including immutable tag and GitHub Release publication. Approval to merge alone must not be treated as approval to publish.
- Production deployment is outside this preparation task.
