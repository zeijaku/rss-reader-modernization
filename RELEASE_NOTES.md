# Development checkpoint: 1.41.1-dev.1

Calendar deadline repeat-pulse correction. Urgent opt-in events pulse on display and then once per active minute, while the existing red three-edge cue remains between pulses. Hidden-tab time is excluded from the cadence, card replacement keeps the current cycle, and reduced-motion uses the static cue only. This adds no one-minute API request and does not change deadline calculation, recurrence/exception behavior, the three-minute Calendar refresh, DB schema or Tower Defense.

See [scope, installation and checks](docs/v1.41.1-dev1-calendar-deadline-pulse.md). This is not the formal V1.41.1 release; stable V1.41.0 release notes follow below.

# RSS Reader Modernization 1.41.0

V1.41.0 finalizes the user-confirmed dev.1–dev.7 Calendar and Tower Defense features. Finalization changes Version/docs and removes unused bitmap assets; tested dev.7 runtime behavior is retained.

## Calendar

- Optional **期日を強調する**, independent of notification reminders. Two calendar days before the final date: border emphasis. From the previous day through the deadline: a gentle 5.4-second pulse when displayed, then a static border. Only the top/right/bottom edges are emphasized, preserving the event-color stripe. Reduced motion disables animation.
- All-day deadlines end at the end of the final day; timed deadlines use end time, or start time without an end time. Calculations use Asia/Tokyo. Recurrence/occurrence edits, copies and date moves retain the setting. No completed/overdue state or additional email delivery.
- Center the period in the card header and keep navigation/view/add/refresh controls in one row on PC and narrow cards.
- Add target-card manual refresh and three-minute polling only while the page is visible, plus throttled return-to-page checks. Compare existing API range data and replace only changed boards; retain period/view and current content on quiet-refresh failure. Avoid overlapping requests, editing/dragging replacement and obsolete navigation results.

## Tower Defense

- Six fixed-path fantasy stages, four towers, five enemy types, three tower levels and eight waves per stage. Tap non-road/non-rock cells and confirm placement. Prepare without a time limit; explicitly start waves. Preparation-only sale returns 75% of invested cost. Existing expanded view, pause and 2× speed are available.
- Flat line icons/geometric enemies follow Wire Defense / Icon Quest, with Theme-aware colors and reduced-motion hit feedback. No external graphics, new runtime dependencies or sound.
- Enemy HP includes the confirmed 15% increase. Stages 4–6 include 6 / 6 / 12 fixed rocks. Fast enemies retain 85% speed under ice; others retain 55%. Attack cadence is unchanged. Difficulty adjustment is paused at the user-confirmed checkpoint.
- User/widget-scoped browser saves retain preparation checkpoints and best 1–3 stars. Closing mid-wave resumes the preceding preparation. No cross-device TD synchronization or permanent stat upgrades. Legacy towers on new rocks remain usable until sold; Restart applies full placement restrictions. Clearing browser storage removes progress; session/memory fallback is disclosed in the UI.

## Upgrade and compatibility

From V1.40.1, back up code/config/database and apply `database/migrations/032_v1_41_calendar_deadline.sql` with `@table_prefix` matching `DB_TABLE_PREFIX` before updating runtime files. The additive, idempotent migration introduces default-off event deadline flags and nullable occurrence overrides. Fresh installs use the updated `database/schema.sql` only; never execute it over an existing database.

From a fully applied dev.7 installation, only `app/version.php` changes at runtime. No additional SQL. Remove unused `public/assets/td/` and `licenses/kenney-td/` if still present; these are absent from formal packages. Preserve local configuration/private runtime data. Application Version / Asset Revision: `1.41.0`. See [installation and verification](docs/v1.41.0-release.md).

## Verification limits

The user confirmed dev.7 production behavior. Local CI-equivalent checks and focused isolated browser fixtures passed: Calendar deadline/header 42, Calendar refresh 34, TD 66, existing Game settings/state 218. Dedicated MariaDB migration/fresh-schema checks passed five checks during development. Unavailable optional local tools remain explicit skips, not passes. APIs in browser fixtures are isolated; physical phone behavior and gameplay balance rely on user playtesting.

Publication uses the existing PHP 8.1 / PHP 8.4 CI and Release Workflow for Runtime/Complete packages, SHA-256, secret scan, clean-room and provenance attestations before immutable Tag/Release publication. Production deployment remains a separate user action.

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
