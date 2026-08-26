# RSS Reader Modernization 1.22.0

Release tag: `v1.22.0`
Release date: 2026-08-26

## Overview

Version 1.22.0 strengthens RSS management without replacing the existing feed engine or dashboard architecture. It adds an authenticated RSS management screen, OPML Import / Export, Feed Health, and owner-scoped RSS Rules. RSS Rules are integrated into normal RSS article rendering for Highlight / Hide / Stock / Task actions.

## Main changes

- RSS Management: list owned feeds and access OPML Import / Export from `/rss-management`.
- OPML Export: exports active feeds owned by the logged-in user only.
- OPML Import: local XML validation only; imported URLs are not fetched during import. Duplicate detection is scoped to the current user.
- Feed metadata: optional title / site URL / category path; a blank title can be supplemented by a later successful normal feed fetch without extra network access.
- Feed Health: Normal / Warning / Error / Unknown-oriented state with last check / success, latest article time, HTTP status, reason, consecutive failure count, redirect state, and effective URL.
- Manual Feed Health recheck uses the stored owned feed URL through the existing safe feed fetch pipeline.
- RSS Rules: owner-scoped rules and ordered conditions, integrated into normal RSS article rendering for Highlight / Hide / Stock / Task.
- Documentation policy: obsolete checkpoint Markdown was reduced before E; historical release contracts still referenced by compatibility tests remain available.

## Database migration

Version 1.22.0 adds three migrations for existing databases. Back up the database first, set each `@table_prefix` to the deployed `DB_TABLE_PREFIX`, then apply in this order:

1. `database/migrations/014_v1_22_opml_feed_metadata.sql`
2. `database/migrations/015_v1_22_feed_health.sql`
3. `database/migrations/016_v1_22_rss_rules.sql`

Do not rerun `database/schema.sql` against an existing database. Environments that already applied a V1.22 checkpoint migration do not apply that same migration again.

## Security / privacy

- Authenticated user ownership remains authoritative; request-supplied `user_id` is not trusted.
- Feed metadata and Feed Health ownership are derived from `content.content_owner`.
- RSS Rule condition ownership is derived from the parent rule owner.
- OPML import performs no outbound HTTP request.
- Feed Health does not add an arbitrary-URL network probe; manual recheck uses the owned stored feed and the existing SSRF-safe fetch path.
- No new required secret or external API key is introduced.

## Upgrade summary

1. Back up application code, `config/local.php`, the database, and required runtime data.
2. Apply unapplied migrations 014, 015, and 016 in numeric order.
3. Verify the Runtime ZIP SHA-256, extract it outside the production directory, then update code without overwriting private config/runtime data.
4. Reload the browser and confirm the footer reports `RSS Reader Modernization 1.22.0`.
5. Verify login, dashboard/feed refresh, RSS Management/OPML, Feed Health, RSS Rules, Stock, Task, Settings, and logout.

## Release assets

- `rss-reader-modernization-1.22.0.zip`
- `rss-reader-modernization-1.22.0.zip.sha256`
- `rss-reader-modernization-1.22.0-complete.zip`
- `rss-reader-modernization-1.22.0-complete.zip.sha256`

## Verification limits

Automated release gates cover regression, compatibility, syntax/security contracts, deterministic package integrity, manifest hashes, clean-room extraction, and high-signal secret scanning. Deployment-specific PHP/Web server/MySQL configuration, real external feed behavior, and production browser rendering still depend on the target environment and should be checked after deployment.
