# Version 1.22.0 Final Release

## Baseline / target

- Baseline: Version 1.21.0 plus V1.22-A/B/C/D checkpoints and documentation cleanup
- Formal target: Version 1.22.0
- Tag: `v1.22.0`
- Release branch: `release/v1.22.0-final`

## Included scope

- RSS Management / OPML Import / Export
- Feed metadata title supplementation on successful normal fetch
- Feed Health and safe manual recheck
- RSS Rules foundation and integrated Highlight / Hide / Stock / Task actions
- V1.22 integration/security compatibility and documentation cleanup

## Database

Existing databases require only unapplied migrations 014, 015, and 016, in numeric order after backup. `schema.sql` must not be rerun against an existing database.

## Final gate

The release branch finalizes Version / Asset Revision / release documentation and package tooling, then runs V1.22-E contract checks, PHP 8.1 and PHP 8.4 regression/compatibility gates, secret scanning, deterministic package verification, and clean-room checks. Tag publication is refused if `v1.22.0` already points elsewhere.
