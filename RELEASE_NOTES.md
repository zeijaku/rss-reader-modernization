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
