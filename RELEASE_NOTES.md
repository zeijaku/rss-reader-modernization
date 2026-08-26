# RSS Reader Modernization 1.22.0

Release date: 2026-08-26
Release tag: `v1.22.0`

## Summary

Version 1.22.0 is the RSS management enhancement release. It integrates OPML Import / Export, Feed Health, and RSS Rules on top of the existing authenticated, CSRF-protected, SSRF-safe RSS pipeline.

## Highlights

- OPML Import / Export for the signed-in user's active feeds.
- Feed metadata for title, site URL, and imported category path.
- Feed Health status, last success, latest article date, HTTP status, consecutive failures, error reason, and manual recheck.
- RSS Rules with ownership-safe management and article display / action integration.
- V1.22-D integration keeps existing Stock, Task, Calendar, Dashboard, Feed fetch, and Article Actions contracts.
- Repository documentation cleanup removes obsolete checkpoint notes while keeping referenced release evidence and Git history.

## Security boundaries

- User ownership is derived from the authenticated Session; request-supplied user IDs are not trusted.
- Feed checks and manual rechecks reuse stored owned Feed URLs and the existing SSRF-safe Feed fetch path. Arbitrary probe URLs are not accepted.
- OPML import keeps XML size/count/depth limits, rejects DTD/ENTITY input, and performs no outbound HTTP during parsing.
- Existing CSRF, input validation, output escaping, Session handling, and PDO boundaries remain in effect.
- Release packages continue to exclude `config/local.php`, runtime DB/cache/log/session data, legacy source archives, and high-signal secret patterns.

## Database migrations

Existing installations must back up the database and apply only migrations not already applied, in numeric order:

- `014_v1_22_opml_feed_metadata.sql`
- `015_v1_22_feed_health.sql`
- `016_v1_22_rss_rules.sql`

Do not re-run migrations already applied in the target environment. New installations should follow `docs/installation.md` and the current schema/migration guidance.

## Packages

- Runtime: `rss-reader-modernization-1.22.0.zip`
- Complete source/test package: `rss-reader-modernization-1.22.0-complete.zip`
- Each ZIP is accompanied by a `.sha256` sidecar.

## Verification

The V1.22.0 final gate runs the current regression suite, V1.22 A/B/C/D focused and integration checks, release contract checks, PHP/JavaScript syntax checks, deterministic package build, package manifest verification, and secret-pattern checks on PHP 8.1 and PHP 8.4 in GitHub Actions.

## Verification limits

Automated verification cannot replace the final deployment check against the production web server, production database state, browser cache behavior, or external RSS endpoints. The final tag should be created only after the release-candidate package has been verified in the target environment.
