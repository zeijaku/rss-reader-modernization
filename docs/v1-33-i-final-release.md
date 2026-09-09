# V1.33-I Final Release

## 状態

- Application source: `RSS Reader Modernization 1.33.0`
- Intended immutable tag: `v1.33.0`
- RC2 production verification: accepted
- Feature scope: frozen; no behavior change from the accepted RC2
- Schedule copy and Calendar Drag & Drop: deferred beyond V1.33

The version promotion prepares release-ready source and deterministic packages. The tag and GitHub Release are published only after the exact `main` commit passes the generic Release workflow.

## 変更の要約

- Promoted `APP_VERSION`, visible label and immutable asset revision from RC2 to `1.33.0`.
- Kept the accepted dependency-ordered JavaScript queue, ordered stylesheet batches and one static-asset retry unchanged.
- Updated stable-release documentation and final release contracts.
- Excluded local `deliverables/` checkpoints from Complete Source package collection.
- Added no Calendar feature, API behavior, database structure, required configuration or secret after RC2.

## DB / Config / Security

- DB change for V1.33: additive `database/migrations/025_v1_33_calendar_event_exception.sql` only.
- Existing environment: apply Migration 025 once only when the prefixed `calendar_event_exception` table is absent.
- Fresh installation: use `database/schema.sql`; do not additionally apply Migration 025.
- Config change: none. Preserve `config/local.php`, existing secret keys and private runtime data.
- Security: Authentication, Authorization/Owner Scope, CSRF, XSS escaping, PDO parameterization, input validation, Session, Step-up Authentication, 2FA, Recovery Code, Session Registry and Authentication Security Audit Log remain unchanged.

## 本番確認手順

1. Record the currently deployed version and confirm the accepted RC2 production result has no unresolved FAIL.
2. Back up the application, `config/local.php`, database and private runtime data; confirm the V1.32/RC2 rollback source is available.
3. Put the final Runtime ZIP and its `.zip.sha256` sidecar in the same staging directory and verify SHA-256.
4. Extract the ZIP outside the live directory and confirm it has one top-level directory containing `app/`, `public/`, `database/` and the release documents.
5. Confirm the actual `DB_TABLE_PREFIX` and check whether the corresponding `calendar_event_exception` table already exists.
6. If the table is absent, adjust `SET @table_prefix` and apply `025_v1_33_calendar_event_exception.sql` once. If it exists, do not re-run the migration. Never run `database/schema.sql` on an existing database.
7. Confirm existing `calendar_event` row counts have not changed and the new exception table is available.
8. Overlay the Runtime package by relative path. Preserve `config/local.php`, the production database, generated `var/` data, uploaded files and all secret/private-key files.
9. Reload the browser and confirm the visible version is `RSS Reader Modernization 1.33.0`.
10. In DevTools Network, confirm Calendar/Dashboard CSS and JavaScript return HTTP 200 with the correct MIME type and the formal cache key `1.33.0` (`calendar-views.css` keeps the scoped `1.33.0-r1` key).
11. Reload ten times with cache disabled, then ten times with normal cache. Confirm no intermittent 503/aborted asset burst, missing widget initialization or Console error.
12. If a static asset transiently fails, confirm only one request with `asset_retry=1` occurs. Confirm create/update/delete API requests are not automatically repeated.
13. Verify existing Calendar titles, notes, dates, times, URLs, recurrence rules and existing red/blue/green colors are unchanged.
14. Verify red/blue/green/yellow/purple in light and dark themes, including create and edit retention.
15. Verify connected multi-day display for a single day, two days, a week boundary, a month boundary and overlapping multi-day events.
16. Verify day/week/month switching, previous/next/today navigation, compact toolbar and 14:00–15:00 placement in the corresponding hour lane.
17. Verify 320, 375, 768, 992 and 1280px layouts, with no Calendar toolbar, grid or modal overflow.
18. Verify one recurring occurrence can be edited, deleted and restored without changing adjacent occurrences or the series rule; verify series-wide edit/delete remains available.
19. Verify Calendar HTML-like title/note content remains escaped, cross-owner access is denied and missing/invalid CSRF is rejected.
20. Smoke-test Login, Remember Me, 2FA, Recovery Code, Step-up Authentication, Session Management, Security Activity, RSS, Stock, Task, Settings and other major widgets.
21. On PHP 8.1 and PHP 8.4, run `tests/run-current.sh` and `tests/run-current-features.sh`; record PASS/FAIL/SKIP without treating an unavailable runtime as PASS.
22. Confirm the final Runtime and Complete Source package verifiers, secret scan, Migration test and clean-room tests pass for the exact release commit.
23. Merge the release-ready source through a Pull Request only after its GitHub Actions checks pass.
24. From that exact `main` commit, run `.github/workflows/release.yml` with version `1.33.0`.
25. Confirm the immutable `v1.33.0` tag, GitHub Release and four formal assets point to the same commit and hashes; check production Error log and database row counts once more.

## Formal assets

- `rss-reader-modernization-1.33.0.zip`
- `rss-reader-modernization-1.33.0.zip.sha256`
- `rss-reader-modernization-1.33.0-complete.zip`
- `rss-reader-modernization-1.33.0-complete.zip.sha256`

## 既知の制限

- “This and following” occurrence edit/delete is not included in V1.33.
- Schedule copy and Calendar Drag & Drop are deferred.
- Drag implementation must later include touch and keyboard alternatives.
