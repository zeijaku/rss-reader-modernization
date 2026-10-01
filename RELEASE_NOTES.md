# RSS Reader Modernization 1.41.0-dev.7

## Development preview

正式Releaseではありません。

This is a playable development checkpoint based on formal V1.40.1. It is not a formal release. Balance, visuals and mobile play will be refined after user playtesting.

- Calendar: optional **期日を強調する**, independent of notification reminders. Two days before the final date: border; from the previous day through the deadline: gentle border pulse for 5.4 seconds when displayed, then a static border. Existing event colors remain readable; reduced-motion disables animation. All-day deadlines end at the end of the final day; timed events use the end time, or the start time if no end time exists. Calculations use Asia/Tokyo. No completed/overdue state or additional notification delivery is introduced.
- Calendar: day/week/month period label moves into the card header, keeping navigation, view controls and add on one row on PC and narrow cards. Long period labels retain a full hover title.
- Game: Tower Defense adds six fixed-path fantasy stages, four tower types, five enemy types, three tower levels and eight waves per stage. Build on non-road cells, prepare without a time limit and start each wave explicitly. Selling during preparation returns 75% of invested cost. Tap a cell, choose a tower and confirm placement. Pause, 2× speed and existing expanded view are available.
- TD: preparation checkpoints and each stage's best 1–3 star result are saved under the existing user/widget scoped browser key. Closing mid-wave restores the preparation before that wave. No cross-device synchronization or permanent stat upgrades. Switching stages replaces the current preparation; best stars remain.
- TD: neutral grid, flat line icons and geometric enemies follow Wire Defense / Icon Quest. Original bundled CC0 tiles remain in older installations but are no longer requested by TD. No external asset requests or new dependencies.

## Changes from dev.6

- Add a target-card refresh button and visible-page polling every three minutes, plus throttled checks when returning to the page. Reuse the authenticated range API without database changes.
- Compare stable range content before rendering. Unchanged responses preserve the board DOM; changed responses replace only that card’s board while retaining the selected period/view. Quiet refresh failures keep the current display.
- Avoid overlapping requests and refresh during modal editing or event/widget dragging; discard obsolete responses after navigation/removal and retry responses deferred by editing when the modal closes.
- From dev.6, overwrite the supplied `app` / `public` files. No SQL or additional file deletion is required. TD is unchanged.

## Changes from dev.5

- Move Calendar deadline emphasis to the top/right/bottom edges, preserving the left event-color stripe for all five colors and Themes. Pulse only those three edges; retain timing, reduced motion, focus outlines and existing layout.
- From dev.5, overwrite the supplied `app` / `public` files. No SQL or additional file deletion is required. TD code and balance are unchanged.

## Changes from dev.4

- Add fixed rock cells on stages 4–6 (6 / 6 / 12 cells); keep the route and the first three maps unchanged. New towers cannot be placed on rocks. Legacy saved towers on those cells remain selectable, upgradeable and sellable; selling does not permit rebuilding there. Restart to play with the full new placement restrictions.
- Fast enemies retain 85% speed under ice, compared with 55% for other enemies. Tower attack intervals remain unchanged.
- Add 0.22-second colored impact rings/crosses and a brief enemy hit highlight. Reduced motion uses static impact marks without expanding rings or enemy flashes. Hit effects do not enter saved preparations.
- From dev.4, overwrite the supplied `app` / `public` files. No SQL or additional file deletion is required. Existing stars remain.

## Changes from dev.3

- Increase HP of every TD enemy, including bosses, by 15% across all stages and waves. Preserve speed, spawn timing, armor, rewards and tower economy.
- Existing preparation saves and best stars remain usable. The new balance applies when a wave begins; closing mid-wave still resumes its saved preparation. Previous stars are retained without re-scoring.
- From dev.3, overwrite the supplied `app` / `public` files. No additional SQL or file deletion is required.

## Changes from dev.2

- Replace TD pixel terrain and sprites with a Theme-aware neutral grid, four tower line icons and five distinct enemy shapes. Fantasy unit types, maps, game rules and browser checkpoints are retained.
- From dev.2, overwrite the supplied `app` / `public` files. No additional SQL is required. Calendar code is unchanged.

## Changes from dev.1

- Center the calendar period within the entire card header on desktop and narrow cards.
- Align TD with existing Game UI: a centered 540px board, separate Wave/HP/gold values and 44px controls. Game rules and saved preparation remain compatible.
- From dev.1, overwrite the supplied `app` / `public` files. No additional SQL is required.

## Upgrade from V1.40.1

Back up application and database, then apply `database/migrations/032_v1_41_calendar_deadline.sql` with `@table_prefix` matching `DB_TABLE_PREFIX` before using this code. It adds default-off `calendar_event_deadline_highlight` and nullable `calendar_event_exception_deadline_highlight` columns. The migration is additive and idempotent. Fresh installs use only the updated `database/schema.sql`. No new required configuration, background jobs or external integration.

Keep local configuration and private runtime data. Application Version / Asset Revision: `1.41.0-dev.7`. TD progress stays in the browser; clearing site storage removes it. A browser which refuses persistent storage falls back to session storage, then memory, and shows the limitation.

## Verification

Local CI-equivalent checks pass, including the new deadline/TD contracts. Focused browser fixtures pass on PC and 360px widths: Calendar 42 checks, TD 66 checks, Calendar refresh 34 checks, existing Game settings/state 218 checks. The dedicated MariaDB gate passes five migration/fresh-schema checks. Default local CI retains unavailable-tool skips; those are not counted as passes. Browser fixtures use isolated APIs; physical phone use and gameplay balance await user playtesting.

## Playtest focus

Try the calendar's opt-in checkbox with all-day, timed, multi-day and recurring events, including an occurrence-only edit, copying and moving dates. Try all six TD stages on PC; on a phone use expanded view and landscape if useful. Report unclear controls, repetitive strategies, stages that feel too easy/hard, and whether typical play approaches 5–10 minutes. Actual duration depends on preparation and speed; the target needs user playtesting.

Previous formal release notes follow for historical context.

---

# RSS Reader Modernization 1.40.1

V1.40.1 finalizes the production-confirmed dev1–dev5 UI improvements. Finalization changes the Version and documentation; the tested dev5 UI execution code is retained.

## Main changes

- Organize the Drawer Widget catalog and navigation, retaining all entries and existing modal targets. Keep category arrows inside the Drawer.
- Show Account Settings as Basic, Security and Authentication log tabs, opening Basic first. Preserve email/password/security forms and their contracts.
- Split Settings into Display, Dashboard tabs, User links and RSS Highlight; preserve legacy anchors, URL history and existing save handlers. Display/User links still share their original form, with the combined save scope disclosed.
- Align Widget header titles/actions/focus, retaining 44px headers, drag handles and existing controls.
- Improve Navbar bell, Memo and Notification Center Theme contrast, the notification close X and inset Navbar keyboard focus.
- Make mobile Page Top a 48px arrow-only target with safe-area margins and a 300px scroll threshold. Retain desktop presentation/100px threshold, main-content focus and reduced-motion behavior.

## Upgrade and compatibility

No database migration, new required configuration, framework or runtime dependency. Authentication, owner scope, CSRF, API, Game engines and stored data are retained.

From a fully applied dev5 installation, the Runtime finalization diff is only `app/version.php`. From stable 1.40.0, use the cumulative app/public update or verified Runtime package. Back up first and preserve `config/local.php`, private runtime data and the existing database; never execute Fresh Install `database/schema.sql` over an existing database. See [installation and checks](docs/v1.40.1-release.md).

Application Version and Asset Revision are both `1.40.1`. Game assets/dictionaries remain locally hosted with existing license notices and browser-local progress semantics.

## Verification limits

Production confirmation was supplied by the user for dev1–dev5, including the Navbar bell correction. Focused and adjacent isolated-browser gates passed 1,876 checks, covering actual PHP/HTML/local assets, four Themes, narrow/desktop widths, keyboard/focus, notification feedback, Memo editing, reduced motion, Settings saves, Drawer and Game state. APIs in browser fixtures are isolated; physical Safari/safe-area, delivery and production persistence rely on the user's production checks.

Finalization validates Version/docs, current regression, PHP 8.1/8.4 CI and the standard Release Workflow: Runtime/Complete package manifests, SHA-256, secret scan, clean-room and provenance attestations before immutable publication. Five pre-existing optional local checks skip when Python browser tools or MariaDB server tools are unavailable; skips are not counted as passes. No new production deployment or external Mail/Remote Files connection is performed by finalization.
