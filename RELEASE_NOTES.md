# RSS Reader Modernization 1.41.0-dev.1

## Development preview

正式Releaseではありません。

This is a playable development checkpoint based on formal V1.40.1. It is not a formal release. Balance, visuals and mobile play will be refined after user playtesting.

- Calendar: optional **期日を強調する**, independent of notification reminders. Two days before the final date: border; from the previous day through the deadline: gentle border pulse for 5.4 seconds when displayed, then a static border. Existing event colors remain readable; reduced-motion disables animation. All-day deadlines end at the end of the final day; timed events use the end time, or the start time if no end time exists. Calculations use Asia/Tokyo. No completed/overdue state or additional notification delivery is introduced.
- Calendar: day/week/month period label moves into the card header, keeping navigation, view controls and add on one row on PC and narrow cards. Long period labels retain a full hover title.
- Game: Tower Defense adds six fixed-path fantasy stages, four tower types, five enemy types, three tower levels and eight waves per stage. Build on non-road cells, prepare without a time limit and start each wave explicitly. Selling during preparation returns 75% of invested cost. Tap a cell, choose a tower and confirm placement. Pause, 2× speed and existing expanded view are available.
- TD: preparation checkpoints and each stage's best 1–3 star result are saved under the existing user/widget scoped browser key. Closing mid-wave restores the preparation before that wave. No cross-device synchronization or permanent stat upgrades. Switching stages replaces the current preparation; best stars remain.
- TD: original CC0 Kenney Tiny Dungeon / Tiny Town tiles are bundled; no external asset requests or new dependencies.

## Upgrade from V1.40.1

Back up application and database, then apply `database/migrations/032_v1_41_calendar_deadline.sql` with `@table_prefix` matching `DB_TABLE_PREFIX` before using this code. It adds default-off `calendar_event_deadline_highlight` and nullable `calendar_event_exception_deadline_highlight` columns. The migration is additive and idempotent. Fresh installs use only the updated `database/schema.sql`. No new required configuration, background jobs or external integration.

Keep local configuration and private runtime data. Application Version / Asset Revision: `1.41.0-dev.1`. TD progress stays in the browser; clearing site storage removes it. A browser which refuses persistent storage falls back to session storage, then memory, and shows the limitation.

## Verification

Local CI-equivalent checks pass, including the new deadline/TD contracts. Focused browser fixtures pass on PC and 360px widths: Calendar 22 checks, TD 42 checks, existing Game settings/state 218 checks. The dedicated MariaDB gate passes five migration/fresh-schema checks. Default local CI retains unavailable-tool skips; those are not counted as passes. Browser fixtures use isolated APIs; physical phone use and gameplay balance await user playtesting.

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
