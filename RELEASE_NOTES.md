# RSS Reader Modernization 1.23.0

Release tag: `v1.23.0`
Release date: 2026-08-27

## Overview

Version 1.23.0 is a repository, test, GitHub Actions, and release-maintenance release. It does not add or change application features, database schema, migrations, public API behavior, or UI behavior.

The main goal is to make future releases less error-prone: current-following tests no longer freeze old asset revisions, historical Version-specific workflows no longer participate in current Actions, and final packaging/release uses one generic workflow with an explicit release version.

## Main changes

- Documentation cleanup: transient checkpoint handoff Markdown was removed from the current tree; Git history and release tags remain the historical source.
- Version/test dependency cleanup: current tests follow the active application version while historical final-release tests remain preserved as historical contracts.
- Workflow cleanup: current Actions are `ci.yml` and generic `release.yml`; Version-specific workflow files are not kept active.
- Standard release flow: final release is manually dispatched from release-ready `main` with explicit `X.Y.Z` input.
- Immutable release protection: an existing tag on a different commit causes failure; force tag/ref updates are not used.
- Existing GitHub Release protection: reruns leave an existing Release and its assets unchanged.
- Package tooling: Runtime and Complete Source builders/verifiers receive an explicit `--release` value and independently cross-check source/package metadata.

## Database migration

No database schema or migration changes are introduced in Version 1.23.0. Existing Version 1.22.0 installations do not apply any additional SQL for this maintenance release.

## Security / privacy

- No new required secret or external API credential is introduced.
- `config/local.php`, runtime data, database/archive files, and high-signal secrets remain excluded from release packages.
- Existing application authentication, authorization, CSRF, SSRF, XSS, validation, PDO, and session boundaries are unchanged by this maintenance release.

## Upgrade summary

1. Back up application code, `config/local.php`, the database, and required runtime data.
2. Verify the Runtime ZIP SHA-256.
3. Update application code without overwriting private config/runtime data. No new database migration is required for Version 1.23.0.
4. Reload the browser and confirm the footer reports `RSS Reader Modernization 1.23.0`.
5. Verify login, dashboard/feed refresh, RSS Management, Stock, Task, Settings, and logout in the target environment.

## Release assets

- `rss-reader-modernization-1.23.0.zip`
- `rss-reader-modernization-1.23.0.zip.sha256`
- `rss-reader-modernization-1.23.0-complete.zip`
- `rss-reader-modernization-1.23.0-complete.zip.sha256`

## Verification limits

Automated release gates cover current regression, retained compatibility gates, syntax/security contracts, deterministic package integrity, manifest hashes, clean-room extraction, and high-signal secret scanning. Deployment-specific PHP/Web server/MySQL configuration, real external feed behavior, and production browser rendering still depend on the target environment and should be checked after deployment.
