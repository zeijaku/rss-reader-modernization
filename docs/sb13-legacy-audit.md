# SB-13 Legacy DB Audit Summary

Source: frozen `rss.sql` SHA-256 `25675068ed172725d1ec3ce144947f388a371f312b9bffc7fa2fb687dfd13c1f`

This document contains counts and structural observations only. Production credential values and row payloads are intentionally omitted.

## Frozen dump counts

- `ig_user_info`: 22 rows
- `ig_user_conf`: 22 rows
- `ig_content`: 41 rows
- `ig_content_stock`: 63 rows

The current frozen dump contains **63 stock rows**. Earlier draft notes that stated 11 stock rows were superseded by this SB-13 re-audit of the frozen SQL source.

## User / settings integrity

- all 22 users have `user_flag=0`
- `ig_user_conf`: exactly 1 row per user in the frozen dump
- duplicate `ig_user_conf.user_id` groups: 0
- orphan `ig_user_conf` rows: 0
- users missing `ig_user_conf`: 0
- duplicate login identity groups: 1 group / 3 user rows

Duplicate identities are preserved. SB-13 does **not** merge or delete users and does not add a UNIQUE constraint to `ig_user_info.user_email`.

## Content integrity

`ig_content`:

- active (`content_flag=0`): 36
- logical deleted (`content_flag=1`): 5
- location 0: 26
- location 1: 2
- location 2: 11
- location 3: 2
- owner=0 orphan rows: 9
- negative owner IDs: 0

The owner=0 rows are preserved and remain the reason no foreign key is added in SB-13.

## Stock integrity

`ig_content_stock`:

- rows: 63
- active (`stock_flag=0`): 63
- owner=1: 63
- orphan owner rows: 0
- negative owner IDs: 0

Some old titles are already mojibake in the frozen dump. Charset migration preserves existing stored characters; it does not attempt heuristic text repair.

## Schema observations before SB-13

- four tables are InnoDB
- table charset is Legacy `utf8`
- only primary-key indexes exist
- there are no foreign keys
- `ig_user_info.user_id` is UNSIGNED, while relationship columns were signed

## SB-13 decisions

1. Convert all four tables to `utf8mb4_unicode_ci`.
2. Align relationship columns (`user_id`, `content_owner`, `stock_owner`) to UNSIGNED after proving there are no negative values.
3. Add indexes matching current query patterns.
4. Add a UNIQUE index to `ig_user_conf.user_id` only after preflight proves no duplicates.
5. Do not add foreign keys.
6. Do not delete/merge duplicate identities, owner=0 content, logical-deleted content, or unknown Legacy data.
7. Publish only schema-only SQL and fake fixtures; never publish the production dump.
