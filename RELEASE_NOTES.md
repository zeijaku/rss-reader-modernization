# RSS Reader Modernization 1.24.0

Release tag: `v1.24.0`
Release date: 2026-08-27

## Overview

Version 1.24.0 strengthens Memo and Stock workflows without changing the existing Stock解除 contract. Memo now stays within the selected Dashboard Widget height and shows a live 4000-character counter. Stock gains independent processed / important / archived states, individual controls, server-backed filters, bulk state updates, and responsive controls for Smartphone use.

The Stock state model is intentionally separate from `stock_flag`: `stock_flag = 1` remains Stock解除, while Archive is stored in `stock_archived` and can be filtered or restored without removing the Stock row.

## Main changes

- Memo height fix: long Memo content scrolls inside the Widget instead of growing the Dashboard Grid row.
- Memo character count: Dashboard and register/edit UI show the current length against the existing 4000-character server limit.
- Stock state foundation: `stock_processed`, `stock_important`, and `stock_archived` are stored independently.
- Individual Stock controls: 未処理 / 処理済み, 通常 / 重要, Archive / Archive済み.
- Default Stock list excludes archived rows while an Archive filter allows archived-only or all-state views.
- State filters coexist with existing text search, Stock Tags, sorting, and pagination.
- Current-page selection and bulk state actions support processed/unprocessed, important/normal, and archive/unarchive.
- Bulk Stock解除 is intentionally not added; Stock解除 remains the existing individual action.
- Smartphone layout keeps state/filter/bulk controls usable without requiring a horizontal workflow.
- V1.24 feature contracts are included in the current CI/release feature suite.

## Database migration

Existing Version 1.23.0 installations must back up the database and apply:

`database/migrations/017_v1_24_stock_state.sql`

Set `@table_prefix` in the migration to the same value as `DB_TABLE_PREFIX` before execution.

Migration 017 adds the following columns to `content_stock`, each with default `0`:

- `stock_processed`
- `stock_important`
- `stock_archived`

It also adds `idx_stock_owner_flag_archived_id (stock_owner, stock_flag, stock_archived, stock_id)`. The migration is written to avoid re-adding columns/indexes that already exist. It does not convert Archive into Stock解除 and does not delete existing Stock data.

For a fresh installation, these V1.24 Stock state columns/index are already integrated into `database/schema.sql`; do not additionally run Migration 017 after importing the fresh schema. Follow `docs/installation.md` for the remaining post-base migrations.

## Security / privacy

- Stock state read/update/bulk actions stay behind the authenticated POST API boundary and existing CSRF validation.
- Every state operation is owner-scoped and limited to active Stock rows (`stock_flag = 0`).
- State names map to a fixed server allowlist; request values are not used as raw SQL column names.
- Bulk IDs must be positive integers, are deduplicated, and are capped at 100 submitted IDs.
- Bulk updates pre-check ownership/availability and execute transactionally instead of partially updating a mixed-owner request.
- Stock list filters are converted to fixed SQL fragments; invalid Archive input falls back to the normal non-archived list.
- No new required secret or external API credential is introduced.

## Upgrade summary

1. Back up the application code, `config/local.php`, database, and required runtime data.
2. Apply `database/migrations/017_v1_24_stock_state.sql` with the deployment's table prefix.
3. Deploy the Version 1.24.0 application files without overwriting private config/runtime data.
4. Reload the browser and confirm the footer reports `RSS Reader Modernization 1.24.0`.
5. Verify Memo long-text scrolling and the 4000-character counters.
6. Verify Stock individual processed/important/archive controls, state persistence after reload, filters, search/tag/sort/pagination coexistence, and bulk state changes.
7. Confirm Archive does not change `stock_flag` and that existing Stock解除, Tags, Task action, and 3-dot article actions continue to work.
8. Check Browser Console and PHP/Web server logs for new errors.

## Release assets

- `rss-reader-modernization-1.24.0.zip`
- `rss-reader-modernization-1.24.0.zip.sha256`
- `rss-reader-modernization-1.24.0-complete.zip`
- `rss-reader-modernization-1.24.0-complete.zip.sha256`

## Verification limits

Automated release gates cover the current full regression suite, current feature contracts including V1.24, syntax/security contracts, release wiring, deterministic package integrity, clean-room extraction, and high-signal secret scanning. Focused V1.24 tests exercise owner scoping, invalid/mixed Stock IDs, state allowlists, filtering, bulk workflow, and Memo presentation contracts. Real production MySQL execution, deployment-specific PHP/Web server configuration, external feed behavior, and browser rendering remain environment-dependent and should be checked in the target environment after deployment.
