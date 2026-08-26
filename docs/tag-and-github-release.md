# Tag and GitHub Release Procedure

## Current formal target

- Version: `1.22.0`
- Tag: `v1.22.0`
- Release branch: `release/v1.22.0-final`

## Safety rules

- Never move or overwrite an existing formal release tag.
- Never force-update `v1.22.0` if it already exists.
- The final tag must point to the exact commit that passed the Version 1.22 release gate.
- Production `config/local.php`, runtime data, secrets, and legacy private archives must not be added to release assets.
- Existing databases must back up first and apply only unapplied migrations 014, 015, 016 in numeric order.

## Gate

The Version 1.22 release workflow performs the final release contract, PHP 8.1 / 8.4 regression, historical compatibility gates, V1.22-B/C/D gates, high-signal source secret scan, deterministic Runtime / Complete Source package verification, and clean-room package checks.

Only after the gate succeeds may `v1.22.0` and the GitHub Release be created.

## Formal assets

- `rss-reader-modernization-1.22.0.zip`
- `rss-reader-modernization-1.22.0.zip.sha256`
- `rss-reader-modernization-1.22.0-complete.zip`
- `rss-reader-modernization-1.22.0-complete.zip.sha256`

After publication, `main` should be fast-forwarded to the same exact commit as `v1.22.0`.
