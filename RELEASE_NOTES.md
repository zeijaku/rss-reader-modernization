# RSS Reader Modernization 1.39.2

V1.39.2 is a Maintenance / Correction release focused on making a clean Fresh Install reproducible from the Runtime ZIP, while simplifying repository maintenance and preserving existing application behavior.

## Main changes

### Fresh Install: one SQL file

- A new empty MySQL / MariaDB database now uses `database/schema.sql` once as the Current Fresh Install schema.
- Historical `database/migrations/` files remain available only for upgrading existing databases; Fresh Install does not apply them after `schema.sql`.
- The consolidated schema contains the Current 27 tables and the later Calendar, Notification, Mail, Remember Me / 2FA, Remote Files, Account Security, Feed Health and RSS Rules structures.
- Fix the `calendar_event_reminder` fragment in the consolidated Calendar table definition. The issue was found during the final real-database import test.
- After the correction, the complete `schema.sql` was manually imported as one file into a real MySQL / MariaDB environment successfully.

## Current database verifier

Add the Runtime-safe read-only command:

```bash
php tools/db_current.php verify
```

It verifies:

- configured MySQL connection
- configured table prefix
- all 27 Current tables
- selected high-signal Current columns
- selected important Current indexes

A successful check ends with:

```text
CURRENT SCHEMA: PASS
```

`tools/db_sb13.php` remains the Legacy / SB-13 audit and migration helper. It is no longer presented as the complete Current Fresh Install schema verifier.

## Installation and deployment documentation

- Simplify README and the Installation / Update / Migration documentation so Fresh Install and existing-database upgrades are clearly separated.
- Document the short Fresh Install path: extract Runtime ZIP, create private config, create empty database, run `schema.sql` once, verify runtime / Current schema, then register the first user.
- Treat Mail, Remote Files, X Timeline and TOTP 2FA as optional post-install verification when those features are used.
- Separate Production Runtime ZIP checks from Repository / Complete Source checks; Runtime deployments do not require `tests/` or Node.js.
- Align `public/.htaccess` ErrorDocument paths with the recommended `DocumentRoot=<project>/public` deployment.
- Keep the complete documentation tree in the Runtime ZIP and include the README-linked `CONTRIBUTING.md`.

## Repository / CI maintenance

- Update pinned GitHub Actions dependencies and use Node.js 24 in CI / Release workflows.
- Keep `tests/run-ci.sh` as the Current gate while clarifying Current, Historical and focused-investigation test roles.
- Keep durable behavior/security regressions in Current CI even when filenames retain an older version prefix.
- Keep historical release/finalization evidence outside normal Current CI.
- Preserve dedicated Fresh Install schema, Current DB verifier, Runtime documentation and package contracts.
- Clean obsolete merged / temporary branches while intentionally retaining the V1.26 rollback safety branch.

## Database upgrade

No new migration is required for an existing V1.39.1 database.

Existing installations must **not** run `database/schema.sql` over the current database. Continue to follow `docs/update.md` and apply only release-specific migrations when a future release requires them.

## Configuration

No new required application configuration is introduced.

The recommended deployment still keeps:

- `config/local.php` outside `public/`
- private runtime data under `var/`
- `public/` as the Web Server DocumentRoot
- the configured `DB_TABLE_PREFIX` equal to the prefix used when creating database tables

## Verification completed

- PR #100 Fresh Install finalization CI passed on PHP 8.1 and PHP 8.4.
- Current Fresh Install static contract confirms exactly 27 required tables and the integrated post-migration identifiers.
- Current DB verifier contract confirms read-only table / column / index verification.
- Runtime documentation / package contracts confirm the recommended public DocumentRoot and Runtime-linked files.
- The real MySQL / MariaDB one-file `schema.sql` import was manually repeated after the Calendar reminder correction and completed successfully.
- Release workflow will independently repeat Current regression, package build / verification, secret scan, clean-room checks, SHA-256 verification and Artifact Attestation before publication.

## Verification limits

- The GitHub-hosted Current schema MariaDB smoke test skips when MariaDB server tools are unavailable on the runner; the final one-file database import was therefore additionally verified manually on a real database environment.
- Formal Runtime / Complete ZIP, checksum, clean-room and Artifact Attestation verification must still pass in the Release workflow before `v1.39.2` is published.
- Production deployment remains a separate step and is not performed automatically by the Release workflow.

## Release assets

The Release workflow publishes:

- `rss-reader-modernization-1.39.2.zip`
- `rss-reader-modernization-1.39.2.zip.sha256`
- `rss-reader-modernization-1.39.2-complete.zip`
- `rss-reader-modernization-1.39.2-complete.zip.sha256`

Both ZIP files receive GitHub Artifact Attestations. Consumers with GitHub CLI can verify the downloaded ZIPs with `gh attestation verify ... --repo zeijaku/rss-reader-modernization`.
