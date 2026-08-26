# V1.22-A Production Verification — OPML Import / Export

Baseline: RSS Reader Modernization 1.21.0.

## Scope

V1.22-A adds an RSS management page, OPML import/export, and per-feed metadata used to preserve OPML title/site/category information. It does not change RSS fetch transport, Stock, Memo, Task, Calendar, RSS Highlight, or article actions.

## Database

Apply `database/migrations/014_v1_22_opml_feed_metadata.sql` after changing `@table_prefix` to the same value as `DB_TABLE_PREFIX` in Production. The migration creates one new `feed_metadata` table. Existing `content` rows and existing Dashboard Widgets are not modified by the migration.

## Deployment

1. Back up the Production application files and database.
2. Apply Migration 014 with the Production table prefix.
3. Copy the overlay files while preserving `config/local.php` and runtime data.
4. Perform one hard reload so the V1.22-A asset revision is used.
5. Open Drawer -> FEED -> RSS管理.

## Verification focus

- RSS一覧 shows only feeds owned by the signed-in account.
- Export downloads an OPML file containing only that account's active RSS feeds.
- Import reports Added / Duplicate / Failure counts.
- Re-importing the same OPML does not create duplicate RSS widgets.
- Nested OPML categories are shown as a safe flattened category path in RSS一覧 and are rebuilt as nested outlines on export.
- Imported RSS appears on Tab 1 as a normal existing RSS Widget with default style/size.
- Invalid XML, DOCTYPE/ENTITY, invalid/non-http(s) Feed URLs, and oversized files are rejected.
- Existing Dashboard RSS fetch, Stock, Memo, Task, Calendar, RSS Highlight, and Article Actions continue to behave normally.

## V1.22-A R1 fix

The first verification overlay omitted `feed_metadata` from the central database table allowlist. If `rss_feed_metadata` existed but RSS一覧 returned `Internal server error`, replace the overlay with this revised package. No additional database migration is required when Migration 014 has already created the table.

## Rollback

Restore the V1.21.0 application files. The new metadata table may remain unused, or it can be dropped after a database backup if a full database rollback is required. Imported RSS rows/widgets are normal application data and should be removed through the application or restored from the pre-test database backup if the entire test is to be reverted.
